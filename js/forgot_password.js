// Forgot Password functionality
let resetEmail = null;

// Show forgot password modal
function showForgotPasswordModal() {
    const modal = document.getElementById('forgotPasswordModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        
        // Reset forms
        document.getElementById('forgotPasswordForm').style.display = 'block';
        document.getElementById('resetPasswordForm').style.display = 'none';
        document.getElementById('resetEmail').value = '';
        document.getElementById('modalTitle').textContent = 'Reset Password';
        document.getElementById('modalSubtitle').textContent = 'Enter your email to receive a reset code';
        
        // Focus on email input
        setTimeout(() => {
            document.getElementById('resetEmail').focus();
        }, 300);
    }
}

// Hide forgot password modal
function hideForgotPasswordModal() {
    const modal = document.getElementById('forgotPasswordModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            // Reset all forms
            document.getElementById('forgotPasswordForm').reset();
            document.getElementById('resetPasswordForm').reset();
            document.getElementById('forgotPasswordForm').style.display = 'block';
            document.getElementById('resetPasswordForm').style.display = 'none';
            resetEmail = null;
        }, 300);
    }
}

// Initialize forgot password form
document.addEventListener('DOMContentLoaded', function() {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', handleForgotPassword);
    }
    
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', handleResetPassword);
    }
    
    // Auto-format reset code input
    const resetCodeInput = document.getElementById('resetCode');
    if (resetCodeInput) {
        resetCodeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    }
    
    // Close modal when clicking outside
    const modal = document.getElementById('forgotPasswordModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideForgotPasswordModal();
            }
        });
    }
});

// Handle forgot password - send reset code
async function handleForgotPassword(e) {
    e.preventDefault();
    
    const email = document.getElementById('resetEmail').value.trim();
    
    if (!email) {
        showMessage('Please enter your email address', 'error');
        return;
    }
    
    try {
        showMessage('Sending reset code...', 'info');
        
        const response = await fetch('api/forgot_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
        });
        
        const data = await response.json();
        
        if (data.success) {
            resetEmail = email;
            showMessage('Reset code sent! Check your email.', 'success');
            
            // Switch to reset password form
            document.getElementById('forgotPasswordForm').style.display = 'none';
            document.getElementById('resetPasswordForm').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Enter Reset Code';
            document.getElementById('modalSubtitle').textContent = 'Check your email for the 6-digit code';
            document.getElementById('displayResetEmail').textContent = email;
            
            // Focus on code input
            setTimeout(() => {
                document.getElementById('resetCode').focus();
            }, 300);
        } else {
            showMessage(data.message || 'Failed to send reset code', 'error');
        }
    } catch (error) {
        showMessage('Network error. Please try again.', 'error');
        console.error('Forgot password error:', error);
    }
}

// Handle reset password - verify code and reset
async function handleResetPassword(e) {
    e.preventDefault();
    
    const code = document.getElementById('resetCode').value.trim();
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmNewPassword').value;
    
    // Validation
    if (!code || code.length !== 6) {
        showMessage('Please enter a valid 6-digit code', 'error');
        return;
    }
    
    if (newPassword.length < 8) {
        showMessage('Password must be at least 8 characters long', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showMessage('Passwords do not match', 'error');
        return;
    }
    
    if (!resetEmail) {
        showMessage('Session expired. Please start over.', 'error');
        hideForgotPasswordModal();
        return;
    }
    
    try {
        showMessage('Resetting password...', 'info');
        
        const response = await fetch('api/reset_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: resetEmail,
                code: code,
                newPassword: newPassword
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Password reset successful! Please login.', 'success');
            setTimeout(() => {
                hideForgotPasswordModal();
                resetEmail = null;
            }, 2000);
        } else {
            showMessage(data.message || 'Failed to reset password', 'error');
        }
    } catch (error) {
        showMessage('Network error. Please try again.', 'error');
        console.error('Reset password error:', error);
    }
}

// Resend reset code
async function resendResetCode() {
    if (!resetEmail) {
        showMessage('Session expired. Please start over.', 'error');
        hideForgotPasswordModal();
        return;
    }
    
    try {
        showMessage('Resending reset code...', 'info');
        
        const resendBtn = event.target;
        resendBtn.disabled = true;
        resendBtn.textContent = 'Sending...';
        
        const response = await fetch('api/forgot_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: resetEmail })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Reset code resent! Check your email.', 'success');
        } else {
            showMessage(data.message || 'Failed to resend code', 'error');
        }
        
        // Re-enable button after 30 seconds
        setTimeout(() => {
            resendBtn.disabled = false;
            resendBtn.textContent = "Didn't receive the code? Resend";
        }, 30000);
        
    } catch (error) {
        showMessage('Failed to resend code.', 'error');
        console.error('Resend error:', error);
        
        const resendBtn = event.target;
        resendBtn.disabled = false;
        resendBtn.textContent = "Didn't receive the code? Resend";
    }
}