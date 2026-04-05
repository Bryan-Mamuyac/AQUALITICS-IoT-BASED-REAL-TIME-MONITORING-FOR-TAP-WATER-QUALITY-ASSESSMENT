<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'config/database.php'; // Database connection

$page_title = 'Profile';
$additional_css = ['admin.css', 'profile.css'];
$additional_js = ['profile.js'];

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, is_verified, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_user) {
        // Redirect to login if user not found
        header('Location: login.php');
        exit();
    }
    
    // Combine first_name and last_name for username display
    $current_user['username'] = $current_user['first_name'] . ' ' . $current_user['last_name'];
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching user data: " . $e->getMessage();
    $current_user = [
        'first_name' => '',
        'last_name' => '',
        'username' => 'Unknown User',
        'email' => '',
        'is_verified' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// IMPROVED FORM PROCESSING - Handle ANY POST request to this page
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Process update if we have the required fields
    if (isset($_POST['first_name']) && isset($_POST['last_name'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        // Basic validation
        if (empty($first_name)) {
            $errors[] = "First name is required";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required";
        }
        
        // Password validation only if any password field is filled
        $password_change_requested = !empty($current_password) || !empty($new_password) || !empty($confirm_password);
        
        if ($password_change_requested) {
            if (empty($current_password)) {
                $errors[] = "Current password is required to change password";
            } else {
                // Verify current password
                try {
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $stored_password = $stmt->fetchColumn();
                    
                    if (!password_verify($current_password, $stored_password)) {
                        $errors[] = "Current password is incorrect";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Error verifying password: " . $e->getMessage();
                }
            }
            
            if (empty($new_password)) {
                $errors[] = "New password is required";
            } elseif (strlen($new_password) < 6) {
                $errors[] = "New password must be at least 6 characters";
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match";
            }
        }
        
        // If no errors, update the database
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                if ($password_change_requested && !empty($new_password)) {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET first_name = ?, last_name = ?, password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([$first_name, $last_name, $hashed_password, $_SESSION['user_id']]);
                } else {
                    // Update without password change
                    $sql = "UPDATE users SET first_name = ?, last_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([$first_name, $last_name, $_SESSION['user_id']]);
                }
                
                if ($result && $stmt->rowCount() >= 0) {
                    $pdo->commit();
                    $_SESSION['success'] = "Profile updated successfully!";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, is_verified, created_at FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $current_user['username'] = $current_user['first_name'] . ' ' . $current_user['last_name'];
                } else {
                    $pdo->rollBack();
                    $_SESSION['error'] = "Failed to update profile. Please try again.";
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
    } else {
        $_SESSION['error'] = "Required fields are missing.";
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-container">

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="profile-container">
        <!-- Profile Information Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <div class="avatar-circle">
                        <span><?php echo strtoupper(substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1)); ?></span>
                    </div>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($current_user['username']); ?></h2>
                    <p class="profile-email">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($current_user['email']); ?>
                    </p>
                    <div class="profile-status">
                        <span class="status-badge <?php echo $current_user['is_verified'] ? 'verified' : 'unverified'; ?>">
                            <i class="fas <?php echo $current_user['is_verified'] ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                            <?php echo $current_user['is_verified'] ? 'Verified Account' : 'Unverified Account'; ?>
                        </span>
                    </div>
                    <p class="profile-joined">
                        <i class="fas fa-calendar-alt"></i>
                        Member since <?php echo date('F j, Y', strtotime($current_user['created_at'])); ?>
                    </p>
                </div>
            </div>

            <!-- Profile Update Form -->
            <div class="profile-form-container">
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="profile-form" id="profileForm">
                    <!-- Hidden field to ensure form processing -->
                    <input type="hidden" name="form_submitted" value="1">
                    
                    <div class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-edit"></i> Personal Information</h3>
                            <p class="section-description">Update your basic profile information</p>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">
                                    <i class="fas fa-user"></i>
                                    First Name
                                </label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($current_user['first_name']); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="last_name">
                                    <i class="fas fa-user"></i>
                                    Last Name
                                </label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($current_user['last_name']); ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email Address
                                <span class="locked-indicator">
                                    <i class="fas fa-lock"></i>
                                    Locked
                                </span>
                            </label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($current_user['email']); ?>" 
                                   readonly class="locked-field">
                            <small class="form-note">Email cannot be changed. Contact support if needed.</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">
                            <h3><i class="fas fa-key"></i> Change Password</h3>
                            <p class="section-description">Leave blank to keep your current password</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="current_password">
                                <i class="fas fa-unlock-alt"></i>
                                Current Password
                            </label>
                            <div class="password-input">
                                <input type="password" id="current_password" name="current_password">
                                <button type="button" class="toggle-password" data-target="current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">
                                    <i class="fas fa-lock"></i>
                                    New Password
                                </label>
                                <div class="password-input">
                                    <input type="password" id="new_password" name="new_password" 
                                           minlength="6" placeholder="Minimum 6 characters">
                                    <button type="button" class="toggle-password" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">
                                    <i class="fas fa-lock"></i>
                                    Confirm New Password
                                </label>
                                <div class="password-input">
                                    <input type="password" id="confirm_password" name="confirm_password">
                                    <button type="button" class="toggle-password" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-match" id="passwordMatch"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_profile" value="1" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Account Statistics Sidebar -->
        <div class="profile-sidebar">
            <div class="stats-card">
                <div class="stats-header">
                    <h3><i class="fas fa-chart-bar"></i> Account Statistics</h3>
                </div>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon days-active">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-number">
                                <?php 
                                $days_active = max(1, floor((time() - strtotime($current_user['created_at'])) / (60 * 60 * 24)));
                                echo number_format($days_active); 
                                ?>
                            </span>
                            <span class="stat-label">Days Active</span>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon readings">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-number">
                                <?php
                                // Get total readings count for this user
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sensor_readings WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    echo number_format($stmt->fetchColumn());
                                } catch (PDOException $e) {
                                    echo "0";
                                }
                                ?>
                            </span>
                            <span class="stat-label">Total Readings</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="quick-actions-card">
                <div class="quick-actions-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <a href="dashboard.php" class="quick-action">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="#" class="quick-action" onclick="downloadProfile()">
                        <i class="fas fa-download"></i>
                        Export to PDF Data
                    </a>
                    <a href="#" class="quick-action" onclick="showSupportModal()">
                        <i class="fas fa-life-ring"></i>
                        Get Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add basic styling for alerts if not in your CSS files -->
<style>
.alert {
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.alert i {
    font-size: 1.1em;
}
</style>

<?php include 'includes/footer.php'; ?>