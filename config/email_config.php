<?php
// config/email_config.php
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $code) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kidongkun69@gmail.com';
        $mail->Password   = 'qiae ijyr sfby vjku';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Enable debugging in development
        if (defined('DEBUG_EMAIL') && DEBUG_EMAIL) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        // Recipients
        $mail->setFrom('noreply@aqualitics.com', 'Aqualitics System');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - Aqualitics';
        $mail->Body    = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Email Verification</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                    .code-box { background: white; border: 2px solid #0ea5e9; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
                    .code { font-size: 32px; font-weight: bold; color: #0ea5e9; letter-spacing: 5px; font-family: 'Courier New', monospace; }
                    .footer { color: #666; font-size: 12px; text-align: center; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin: 0; font-size: 28px;'>🌊 Aqualitics</h1>
                        <p style='margin: 10px 0 0 0; font-size: 16px;'>Email Verification</p>
                    </div>
                    
                    <div class='content'>
                        <h2 style='color: #333; margin-top: 0;'>Welcome to Aqualitics!</h2>
                        <p>Thank you for registering with us. To complete your account setup, please use the verification code below:</p>
                        
                        <div class='code-box'>
                            <p style='margin: 0; color: #666; font-size: 14px;'>Your verification code is:</p>
                            <div class='code'>$code</div>
                        </div>
                        
                        <p><strong>Instructions:</strong></p>
                        <ol>
                            <li>Go back to the registration page</li>
                            <li>Enter this 6-digit code in the verification field</li>
                            <li>Click 'Verify Email' to complete your registration</li>
                        </ol>
                        
                        <p style='color: #666; font-size: 14px;'>
                            <strong>Note:</strong> This verification code will expire in 15 minutes for security reasons.
                        </p>
                        
                        <div class='footer'>
                            <p>If you didn't create an account with Aqualitics, please ignore this email.</p>
                            <p>© " . date('Y') . " Aqualitics. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "
Welcome to Aqualitics!

Your email verification code is: $code

Please enter this code on the registration page to verify your email address.

This code will expire in 15 minutes.

If you didn't create an account with Aqualitics, please ignore this email.
        ";

        $result = $mail->send();
        error_log("Email sent successfully to: $email with code: $code");
        return $result;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

function sendPasswordResetEmail($email, $code, $firstName = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kidongkun69@gmail.com';
        $mail->Password   = 'qiae ijyr sfby vjku';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Enable debugging in development
        if (defined('DEBUG_EMAIL') && DEBUG_EMAIL) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        // Recipients
        $mail->setFrom('noreply@aqualitics.com', 'Aqualitics System');
        $mail->addAddress($email);

        $greeting = $firstName ? "Hello $firstName," : "Hello,";

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - Aqualitics';
        $mail->Body    = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Password Reset</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                    .code-box { background: white; border: 2px solid #0ea5e9; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
                    .code { font-size: 32px; font-weight: bold; color: #0ea5e9; letter-spacing: 5px; font-family: 'Courier New', monospace; }
                    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                    .footer { color: #666; font-size: 12px; text-align: center; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin: 0; font-size: 28px;'>🔑 Aqualitics</h1>
                        <p style='margin: 10px 0 0 0; font-size: 16px;'>Password Reset Request</p>
                    </div>
                    
                    <div class='content'>
                        <h2 style='color: #333; margin-top: 0;'>$greeting</h2>
                        <p>We received a request to reset your password. Use the code below to reset your password:</p>
                        
                        <div class='code-box'>
                            <p style='margin: 0; color: #666; font-size: 14px;'>Your password reset code is:</p>
                            <div class='code'>$code</div>
                        </div>
                        
                        <p><strong>Instructions:</strong></p>
                        <ol>
                            <li>Go back to the Aqualitics login page</li>
                            <li>Click on 'Forgot Password'</li>
                            <li>Enter this 6-digit code</li>
                            <li>Create your new password</li>
                        </ol>
                        
                        <div class='warning'>
                            <strong>⚠️ Security Notice:</strong>
                            <ul style='margin: 5px 0; padding-left: 20px;'>
                                <li>This code will expire in 15 minutes</li>
                                <li>Never share this code with anyone</li>
                                <li>If you didn't request this, please ignore this email</li>
                            </ul>
                        </div>
                        
                        <div class='footer'>
                            <p>If you didn't request a password reset, please ignore this email or contact support if you're concerned.</p>
                            <p>© " . date('Y') . " Aqualitics. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "
$greeting

We received a request to reset your Aqualitics password.

Your password reset code is: $code

Instructions:
1. Go back to the Aqualitics login page
2. Click on 'Forgot Password'
3. Enter this 6-digit code
4. Create your new password

SECURITY NOTICE:
- This code will expire in 15 minutes
- Never share this code with anyone
- If you didn't request this, please ignore this email

If you didn't request a password reset, please ignore this email.

© " . date('Y') . " Aqualitics. All rights reserved.
        ";

        $result = $mail->send();
        error_log("Password reset email sent successfully to: $email with code: $code");
        return $result;
        
    } catch (Exception $e) {
        error_log("Password reset email sending failed: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

// Test function to verify email configuration
function testEmailConfiguration() {
    try {
        $testEmail = 'test@example.com';
        $testCode = '123456';
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kidongkun69@gmail.com';
        $mail->Password = 'qiae ijyr sfby vjku';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Just test the connection without sending
        $mail->smtpConnect();
        $mail->smtpClose();
        
        return true;
    } catch (Exception $e) {
        error_log("Email configuration test failed: " . $e->getMessage());
        return false;
    }
}
?>