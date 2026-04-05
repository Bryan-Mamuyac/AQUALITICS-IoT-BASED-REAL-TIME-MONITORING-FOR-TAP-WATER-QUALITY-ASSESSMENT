<?php
session_start();
require_once 'config/database.php';
require_once 'config/email_config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user info
try {
    $stmt = $pdo->prepare("SELECT username, email, is_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
    
    // If already verified, redirect to dashboard
    if ($user['is_verified']) {
        header('Location: admin_dashboard.php');
        exit();
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$message = '';
$error = '';

// Handle email verification
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Find user with this verification token
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND id = ?");
        $stmt->execute([$token, $_SESSION['user_id']]);
        $user_id = $stmt->fetchColumn();
        
        if ($user_id) {
            // Update user as verified
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $_SESSION['success'] = "Email verified successfully! Welcome to AquaMonitor.";
            header('Location: admin_dashboard.php');
            exit();
        } else {
            $error = "Invalid or expired verification token.";
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle resend verification email
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_verification'])) {
    try {
        // Generate new verification token
        $verification_token = bin2hex(random_bytes(32));
        
        // Update user with new token
        $stmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
        $stmt->execute([$verification_token, $_SESSION['user_id']]);
        
        // Send verification email
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=" . $verification_token;
        
        $subject = "Verify Your AquaMonitor Account";
        $email_body = "
        <html>
        <head>
            <title>Verify Your Account</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>
                        <i class='fas fa-fish'></i> AquaMonitor
                    </h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px;'>Verify Your Account</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <h2 style='color: #333; margin-top: 0;'>Hello " . htmlspecialchars($user['username']) . "!</h2>
                    
                    <p>Thank you for registering with AquaMonitor. To complete your account setup and start monitoring your aquaculture systems, please verify your email address.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $verification_link . "' style='background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                            Verify Email Address
                        </a>
                    </div>
                    
                    <p style='color: #666; font-size: 14px;'>
                        If the button doesn't work, copy and paste this link into your browser:<br>
                        <a href='" . $verification_link . "'>" . $verification_link . "</a>
                    </p>
                    
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                    
                    <p style='color: #666; font-size: 12px; text-align: center;'>
                        This verification link will expire in 24 hours. If you didn't create an account with AquaMonitor, please ignore this email.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: AquaMonitor <noreply@aquamonitor.com>" . "\r\n";
        
        if (mail($user['email'], $subject, $email_body, $headers)) {
            $message = "Verification email has been resent to " . htmlspecialchars($user['email']);
        } else {
            $error = "Failed to send verification email. Please try again later.";
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

$page_title = 'Email Verification';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AquaMonitor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card verify-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-fish"></i>
                    <span>AquaMonitor</span>
                </div>
                <h2>Email Verification Required</h2>
                <p>Please verify your email address to continue</p>
            </div>

            <div class="auth-content">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="verification-info">
                    <div class="verification-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    
                    <h3>Check Your Email</h3>
                    <p>We've sent a verification link to:</p>
                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div class="verification-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-text">Check your email inbox (and spam folder)</div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-text">Click the verification link in the email</div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-text">You'll be automatically redirected to your dashboard</div>
                        </div>
                    </div>
                </div>

                <form method="POST" class="resend-form">
                    <p class="resend-text">Didn't receive the email?</p>
                    <button type="submit" name="resend_verification" class="btn btn-secondary btn-full">
                        <i class="fas fa-paper-plane"></i>
                        Resend Verification Email
                    </button>
                </form>

                <div class="auth-links">
                    <a href="logout.php" class="link">
                        <i class="fas fa-sign-out-alt"></i>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
        .verify-card {
            max-width: 500px;
            margin: 2rem auto;
        }

        .verification-info {
            text-align: center;
            margin: 2rem 0;
        }

        .verification-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            font-size: 2rem;
        }

        .verification-info h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .user-email {
            background: #f0f4ff;
            color: #667eea;
            padding: 0.75rem 1rem;
            border-radius: 5px;
            font-weight: 500;
            margin: 1rem 0;
            word-break: break-word;
        }

        .verification-steps {
            margin: 2rem 0;
            text-align: left;
        }

        .step {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .step-text {
            color: #4b5563;
        }

        .resend-form {
            margin: 2rem 0;
            text-align: center;
        }

        .resend-text {
            color: #6b7280;
            margin-bottom: 1rem;
        }

        .btn-secondary {
            background: #6b7280;
            border-color: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
            border-color: #4b5563;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        @media (max-width: 768px) {
            .auth-container {
                padding: 1rem;
            }
            
            .verify-card {
                margin: 1rem auto;
            }
            
            .verification-steps {
                text-align: center;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem 1rem;
            }
            
            .step-number {
                margin: 0 0 0.5rem 0;
            }
        }
    </style>

    <script>
        // Auto-refresh page every 30 seconds to check if verification was completed in another tab
        setInterval(function() {
            fetch('verify.php?check=1')
                .then(response => response.text())
                .then(data => {
                    if (data.includes('admin_dashboard.php')) {
                        window.location.href = 'admin_dashboard.php';
                    }
                })
                .catch(error => console.log('Check failed:', error));
        }, 30000);

        // Show loading state when resending email
        document.querySelector('.resend-form').addEventListener('submit', function() {
            const button = this.querySelector('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;
            
            // Re-enable button after 5 seconds
            setTimeout(function() {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 5000);
        });
    </script>
</body>
</html>