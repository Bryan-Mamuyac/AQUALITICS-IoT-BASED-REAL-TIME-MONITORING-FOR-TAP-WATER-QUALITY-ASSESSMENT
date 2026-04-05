// Global variables
let currentUser = null;
let registrationEmail = null; // Store email globally

// Password toggle function
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    
    // Toggle icon
    button.textContent = type === 'password' ? '👁️' : '🙈';
}

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in
    checkAuthStatus();
    
    // Initialize forms
    initializeForms();
    
    // Setup password toggle functionality for existing password inputs
    setupPasswordToggles();
});

// Setup password toggles for dynamically created or existing inputs
function setupPasswordToggles() {
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    
    passwordInputs.forEach(input => {
        // Check if toggle button already exists
        const container = input.parentElement;
        if (!container.querySelector('.password-toggle')) {
            // Create toggle button if it doesn't exist
            const toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            toggleButton.className = 'password-toggle';
            toggleButton.innerHTML = '👁️';
            toggleButton.onclick = () => togglePassword(input.id, toggleButton);
            
            // Add container class if needed
            if (!container.classList.contains('password-input-container')) {
                container.classList.add('password-input-container');
            }
            
            container.appendChild(toggleButton);
        }
    });
}

// Initialize all forms
function initializeForms() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const verificationForm = document.getElementById('verificationForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
    
    if (verificationForm) {
        verificationForm.addEventListener('submit', handleVerification);
    }
}

// Handle login
async function handleLogin(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const email = formData.get('email');
    const password = formData.get('password');
    
    try {
        showMessage('Logging in...', 'info');
        
        const response = await fetch('api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Login failed. Please try again.', 'error');
        console.error('Login error:', error);
    }
}

// Handle registration
async function handleRegister(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const password = formData.get('password');
    const confirmPassword = formData.get('confirmPassword');
    const email = formData.get('email');
    
    // Validate passwords match
    if (password !== confirmPassword) {
        showMessage('Passwords do not match', 'error');
        return;
    }
    
    const userData = {
        firstName: formData.get('firstName'),
        lastName: formData.get('lastName'),
        gender: formData.get('gender'),
        email: email,
        password: password
    };
    
    try {
        showMessage('Creating account...', 'info');
        
        const response = await fetch('api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(userData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store email globally for verification
            registrationEmail = email;
            showMessage('Registration successful! Please check your email for verification code.', 'success');
            showVerificationModal(email);
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Registration failed. Please try again.', 'error');
        console.error('Registration error:', error);
    }
}

// Handle email verification
async function handleVerification(e) {
    e.preventDefault();
    
    const code = document.getElementById('verificationCode').value.trim();
    
    // Use stored email from registration
    const email = registrationEmail || document.getElementById('email')?.value;
    
    if (!email) {
        showMessage('Email not found. Please try registering again.', 'error');
        return;
    }
    
    if (!code || code.length !== 6) {
        showMessage('Please enter a valid 6-digit verification code', 'error');
        return;
    }
    
    try {
        showMessage('Verifying code...', 'info');
        
        const response = await fetch('api/verify_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                email: email, 
                code: code 
            })
        });
        
        const data = await response.json();
        
        console.log('Verification response:', data); // Debug log
        
        if (data.success) {
            showMessage('Email verified successfully! Please login.', 'success');
            hideVerificationModal();
            // Clear stored email
            registrationEmail = null;
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        } else {
            showMessage(data.message || 'Verification failed', 'error');
        }
    } catch (error) {
        showMessage('Verification failed. Please try again.', 'error');
        console.error('Verification error:', error);
    }
}

// Show verification modal
function showVerificationModal(email) {
    const modal = document.getElementById('verificationModal');
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
        
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.classList.add('show');
        }
        
        // Store email for verification - try multiple methods
        registrationEmail = email;
        
        // Update display email if element exists
        const displayEmail = document.getElementById('displayEmail');
        if (displayEmail) {
            displayEmail.textContent = email;
        }
        
        // Also try to set in hidden input if it exists
        const hiddenEmailInput = document.getElementById('hiddenEmail');
        if (hiddenEmailInput) {
            hiddenEmailInput.value = email;
        }
        
        // Focus on verification code input
        const codeInput = document.getElementById('verificationCode');
        if (codeInput) {
            setTimeout(() => {
                codeInput.focus();
            }, 300);
            codeInput.value = ''; // Clear any previous value
        }
        
        console.log('Verification modal shown for email:', email); // Debug log
    }
}

// Hide verification modal
function hideVerificationModal() {
    const modal = document.getElementById('verificationModal');
    if (modal) {
        modal.classList.remove('show');
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.classList.remove('show');
        }
        
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
        
        // Clear the verification code input
        const codeInput = document.getElementById('verificationCode');
        if (codeInput) {
            codeInput.value = '';
        }
        
        // Reset verify button
        const verifyBtn = document.getElementById('verifyBtn');
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.textContent = 'Verify Email';
        }
    }
}

// Show message
function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        messageDiv.textContent = message;
        messageDiv.className = `message ${type}`;
        messageDiv.style.display = 'block';
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
}

// Check authentication status
async function checkAuthStatus() {
    try {
        const response = await fetch('api/auth_status.php');
        const data = await response.json();
        
        if (data.authenticated) {
            currentUser = data.user;
        }
    } catch (error) {
        console.error('Auth check error:', error);
    }
}

// Utility functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function formatNumber(num) {
    return Number(num).toFixed(2);
}

// Export/Import helper functions
function downloadFile(content, filename, contentType) {
    const blob = new Blob([content], { type: contentType });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

// Resend verification code
async function resendVerificationCode() {
    if (!registrationEmail) {
        showMessage('Email not found. Please try registering again.', 'error');
        return;
    }
    
    try {
        showMessage('Resending verification code...', 'info');
        
        // Disable resend button temporarily
        const resendBtn = document.getElementById('resendBtn');
        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending...';
        }
        
        const response = await fetch('api/resend_verification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: registrationEmail })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Verification code resent! Please check your email.', 'success');
        } else {
            showMessage(data.message || 'Failed to resend code', 'error');
        }
        
        // Re-enable button after 30 seconds
        if (resendBtn) {
            setTimeout(() => {
                resendBtn.disabled = false;
                resendBtn.textContent = "Didn't receive the code? Resend";
            }, 30000);
        }
        
    } catch (error) {
        showMessage('Failed to resend verification code.', 'error');
        console.error('Resend error:', error);
        
        // Re-enable button
        const resendBtn = document.getElementById('resendBtn');
        if (resendBtn) {
            resendBtn.disabled = false;
            resendBtn.textContent = "Didn't receive the code? Resend";
        }
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('verificationModal');
    if (e.target === modal) {
        hideVerificationModal();
    }
});

// Allow only numeric input for verification code and setup auto-submit
document.addEventListener('DOMContentLoaded', function() {
    const verificationCode = document.getElementById('verificationCode');
    if (verificationCode) {
        verificationCode.addEventListener('input', function(e) {
            // Remove any non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });
        
        // Auto-submit when 6 digits are entered
        verificationCode.addEventListener('input', function(e) {
            if (this.value.length === 6) {
                // Small delay to let user see the complete code
                setTimeout(() => {
                    const form = document.getElementById('verificationForm');
                    if (form) {
                        form.dispatchEvent(new Event('submit', { cancelable: true }));
                    }
                }, 500);
            }
        });
    }
});