// Profile Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeProfilePage();
});

function initializeProfilePage() {
    // Initialize password toggle functionality
    initPasswordToggle();
    
    // Initialize password strength checker
    initPasswordStrength();
    
    // Initialize password match checker
    initPasswordMatch();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize auto-save functionality (optional)
    initAutoSave();
    
    // Initialize keyboard shortcuts
    initKeyboardShortcuts();
}

// Password Toggle Functionality
function initPasswordToggle() {
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.setAttribute('aria-label', 'Hide password');
            } else {
                targetInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.setAttribute('aria-label', 'Show password');
            }
        });
    });
}

// Password Strength Checker
function initPasswordStrength() {
    const newPasswordInput = document.getElementById('new_password');
    const strengthIndicator = document.getElementById('passwordStrength');
    
    if (newPasswordInput && strengthIndicator) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            updatePasswordStrengthUI(strengthIndicator, strength, password.length);
        });
    }
}

function calculatePasswordStrength(password) {
    let score = 0;
    
    if (password.length === 0) return 0;
    
    // Length check
    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;
    
    // Character variety checks
    if (/[a-z]/.test(password)) score += 1;
    if (/[A-Z]/.test(password)) score += 1;
    if (/[0-9]/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;
    
    // Bonus for very long passwords
    if (password.length >= 16) score += 1;
    
    return Math.min(score, 6);
}

function updatePasswordStrengthUI(indicator, strength, length) {
    indicator.className = 'password-strength';
    
    if (length === 0) {
        indicator.style.display = 'none';
        return;
    }
    
    indicator.style.display = 'block';
    
    if (strength <= 2) {
        indicator.classList.add('weak');
        indicator.setAttribute('data-strength', 'Weak password');
    } else if (strength <= 4) {
        indicator.classList.add('medium');
        indicator.setAttribute('data-strength', 'Medium password');
    } else {
        indicator.classList.add('strong');
        indicator.setAttribute('data-strength', 'Strong password');
    }
}

// Password Match Checker
function initPasswordMatch() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const matchIndicator = document.getElementById('passwordMatch');
    
    if (newPasswordInput && confirmPasswordInput && matchIndicator) {
        function checkPasswordMatch() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length === 0) {
                matchIndicator.textContent = '';
                matchIndicator.className = 'password-match';
                return;
            }
            
            if (newPassword === confirmPassword) {
                matchIndicator.textContent = '✓ Passwords match';
                matchIndicator.className = 'password-match match';
            } else {
                matchIndicator.textContent = '✗ Passwords do not match';
                matchIndicator.className = 'password-match no-match';
            }
        }
        
        newPasswordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
}

// Form Validation
function initFormValidation() {
    const form = document.getElementById('profileForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                showValidationErrors();
            } else {
                showLoadingState();
            }
        });
        
        // Real-time validation for individual fields
        const inputs = form.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    }
}

function validateForm() {
    const form = document.getElementById('profileForm');
    const firstName = document.getElementById('first_name');
    const lastName = document.getElementById('last_name');
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    let isValid = true;
    
    // Clear previous errors
    clearValidationErrors();
    
    // Validate required fields
    if (!firstName.value.trim()) {
        showFieldError(firstName, 'First name is required');
        isValid = false;
    }
    
    if (!lastName.value.trim()) {
        showFieldError(lastName, 'Last name is required');
        isValid = false;
    }
    
    // Validate password fields if any password field is filled
    const hasPasswordInput = currentPassword.value || newPassword.value || confirmPassword.value;
    
    if (hasPasswordInput) {
        if (!currentPassword.value) {
            showFieldError(currentPassword, 'Current password is required');
            isValid = false;
        }
        
        if (!newPassword.value) {
            showFieldError(newPassword, 'New password is required');
            isValid = false;
        } else if (newPassword.value.length < 6) {
            showFieldError(newPassword, 'Password must be at least 6 characters');
            isValid = false;
        }
        
        if (newPassword.value !== confirmPassword.value) {
            showFieldError(confirmPassword, 'Passwords do not match');
            isValid = false;
        }
    }
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.getAttribute('name');
    
    clearFieldError(field);
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, `${fieldName.replace('_', ' ')} is required`);
        return false;
    }
    
    if (fieldName === 'new_password' && value && value.length < 6) {
        showFieldError(field, 'Password must be at least 6 characters');
        return false;
    }
    
    return true;
}

function showFieldError(field, message) {
    field.classList.add('error');
    
    let errorElement = field.parentNode.querySelector('.field-error');
    if (!errorElement) {
        errorElement = document.createElement('span');
        errorElement.className = 'field-error';
        field.parentNode.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
}

function clearFieldError(field) {
    field.classList.remove('error');
    const errorElement = field.parentNode.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
}

function clearValidationErrors() {
    const form = document.getElementById('profileForm');
    const errorElements = form.querySelectorAll('.field-error');
    const errorFields = form.querySelectorAll('.error');
    
    errorElements.forEach(el => el.remove());
    errorFields.forEach(field => field.classList.remove('error'));
}

function showValidationErrors() {
    const firstError = document.querySelector('.error');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
    }
}

function showLoadingState() {
    const submitButton = document.querySelector('button[name="update_profile"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitButton.classList.add('loading');
    }
}

// Auto-save functionality (saves draft to memory instead of localStorage)
function initAutoSave() {
    const form = document.getElementById('profileForm');
    const inputs = form.querySelectorAll('input:not([type="password"])');
    
    // Save data on input changes
    inputs.forEach(input => {
        input.addEventListener('input', debounce(function() {
            showAutoSaveIndicator();
        }, 1000));
    });
}

function showAutoSaveIndicator() {
    // Create or update auto-save indicator
    let indicator = document.getElementById('autosave-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'autosave-indicator';
        indicator.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.85rem;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        document.body.appendChild(indicator);
    }
    
    indicator.innerHTML = '<i class="fas fa-check"></i> Changes detected';
    indicator.style.opacity = '1';
    
    setTimeout(() => {
        indicator.style.opacity = '0';
    }, 2000);
}

// Keyboard Shortcuts
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const submitButton = document.querySelector('button[name="update_profile"]');
            if (submitButton) {
                submitButton.click();
            }
        }
        
        // Escape to clear password fields
        if (e.key === 'Escape') {
            const passwordFields = document.querySelectorAll('input[type="password"]');
            passwordFields.forEach(field => field.value = '');
            clearValidationErrors();
        }
    });
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Quick Actions Functions - Updated for PDF Export
function downloadProfile() {
    // Show loading notification
    showNotification('Generating PDF report...', 'info');
    
    // Create a temporary form to submit to the PDF export endpoint
    const form = document.createElement('form');
    form.method = 'GET';
    form.action = 'api/export_pdf.php';
    form.target = '_blank'; // Open in new tab
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Show success message after a short delay
    setTimeout(() => {
        showNotification('PDF report generated successfully!', 'success');
    }, 1000);
}

function showSupportModal() {
    // Create modal if it doesn't exist
    let modal = document.getElementById('support-modal');
    if (!modal) {
        modal = createSupportModal();
        document.body.appendChild(modal);
    }
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function createSupportModal() {
    const modal = document.createElement('div');
    modal.id = 'support-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 15px; padding: 2rem; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; color: #1f2937;">
                    <i class="fas fa-life-ring" style="color: #667eea; margin-right: 0.5rem;"></i>
                    Get Support
                </h3>
                <button onclick="closeSupportModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="space-y: 1rem;">
                <p style="color: #6b7280; margin-bottom: 1.5rem;">Need help with your account? Choose an option below:</p>
                
                <div style="display: grid; gap: 1rem;">
                    <a href="mailto:support@aqualitics.com" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f8fafc; border-radius: 10px; text-decoration: none; color: #374151; transition: all 0.3s ease;">
                        <i class="fas fa-envelope" style="color: #667eea; font-size: 1.2rem;"></i>
                        <div>
                            <div style="font-weight: 600;">Email Support</div>
                            <div style="font-size: 0.85rem; color: #6b7280;">Get help via email</div>
                        </div>
                    </a>
                    
                    <a href="#" onclick="openLiveChat()" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f8fafc; border-radius: 10px; text-decoration: none; color: #374151; transition: all 0.3s ease;">
                        <i class="fas fa-comments" style="color: #10b981; font-size: 1.2rem;"></i>
                        <div>
                            <div style="font-weight: 600;">Live Chat</div>
                            <div style="font-size: 0.85rem; color: #6b7280;">Chat with our support team</div>
                        </div>
                    </a>
                    
                    <a href="#" onclick="openKnowledgeBase()" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f8fafc; border-radius: 10px; text-decoration: none; color: #374151; transition: all 0.3s ease;">
                        <i class="fas fa-book" style="color: #f59e0b; font-size: 1.2rem;"></i>
                        <div>
                            <div style="font-weight: 600;">Knowledge Base</div>
                            <div style="font-size: 0.85rem; color: #6b7280;">Browse help articles</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    `;
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeSupportModal();
        }
    });
    
    return modal;
}

function closeSupportModal() {
    const modal = document.getElementById('support-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function openLiveChat() {
    // Placeholder for live chat integration
    showNotification('Live chat will be available soon!', 'info');
    closeSupportModal();
}

function openKnowledgeBase() {
    // Placeholder for knowledge base
    window.open('https://www.ewg.org/tapwater/', '_blank');
    closeSupportModal();
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        color: white;
        font-weight: 500;
        z-index: 10001;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b'
    };
    
    notification.style.background = colors[type] || colors.info;
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i> ${message}`;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 4000);
}

// Add CSS for field errors
const style = document.createElement('style');
style.textContent = `
    .form-group input.error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important;
    }
    
    .field-error {
        color: #ef4444;
        font-size: 0.85rem;
        margin-top: 0.25rem;
        display: block;
        font-weight: 500;
    }
    
    .quick-action:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        color: white !important;
    }
`;
document.head.appendChild(style);