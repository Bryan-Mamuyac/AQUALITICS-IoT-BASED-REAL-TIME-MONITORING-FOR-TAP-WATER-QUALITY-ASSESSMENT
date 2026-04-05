<?php
// register.php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: admin_dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aqualitics - Register</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <!-- Floating background elements -->
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>
    <div class="floating-shape shape-3"></div>
    
    <div class="login-container">
        <!-- Left side - Registration Form -->
        <div class="login-form register-form">
            <div class="logo">
                <h1>Aqualitics</h1>
                <p>Create Your Account</p>
            </div>
            
            <form id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="firstName" name="firstName" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <input type="text" id="lastName" name="lastName" placeholder="Last Name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <select id="location" name="location" required>
                        <option value="">Select Location</option>
                        <option value="Metro San Fernando">Metro San Fernando</option>
                        <option value="Sevilla">Sevilla</option>
                        <option value="Catbangen">Catbangen</option>
                        <option value="Lingsat">Lingsat</option>
                        <option value="Poro">Poro</option>
                        <option value="Tanqui">Tanqui</option>
                        <option value="Pagdalagan">Pagdalagan</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <input type="email" id="email" name="email" placeholder="Email Address" required>
                </div>
                
                <div class="form-group">
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" placeholder="Password (min. 6 characters)" required minlength="6">
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="password-input-container">
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="login-btn">Register</button>
                
                <div class="form-links">
                    <a href="index.php">Already have an account? Login</a>
                </div>
            </form>
            
            <div id="message" class="message"></div>
        </div>

        <!-- Right side - 3D IoT Box -->
        <div class="iot-showcase">
            <div class="iot-canvas-container">
                <canvas id="iotCanvas"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Email Verification Modal -->
    <div id="verificationModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="hideVerificationModal()" type="button" aria-label="Close">×</button>
            
            <div class="modal-header">
                <div class="email-icon">📧</div>
                <h3>Verify Your Email</h3>
                <p>We've sent a 6-digit verification code to your email address. Please enter it below to complete your registration.</p>
            </div>
            
            <div class="verification-help">
                <strong>Email:</strong>
                <div class="email-display" id="displayEmail"></div>
                <small>🔍 Check your spam folder if you don't see the email within a few minutes</small>
            </div>
            
            <form id="verificationForm">
                <div class="verification-input-container">
                    <input 
                        type="text" 
                        id="verificationCode" 
                        placeholder="000000" 
                        maxlength="6" 
                        pattern="[0-9]{6}"
                        required
                        autocomplete="off"
                    >
                </div>
                <button type="submit" class="verify-btn" id="verifyBtn">
                    Verify Email Address
                </button>
            </form>
            
            <button class="resend-link" onclick="resendVerificationCode()" id="resendBtn">
                Didn't receive the code? Resend verification email
            </button>
            
            <input type="hidden" id="hiddenEmail" name="email">
        </div>
    </div>
    
    <script>
        let currentUser = null;
        let registrationEmail = null;

        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const svg = button.querySelector('svg');
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            if (type === 'password') {
                svg.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
            } else {
                svg.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                `;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeForms();
            setupVerificationCodeInput();
        });

        function initializeForms() {
            const registerForm = document.getElementById('registerForm');
            const verificationForm = document.getElementById('verificationForm');
            
            if (registerForm) {
                registerForm.addEventListener('submit', handleRegister);
            }
            
            if (verificationForm) {
                verificationForm.addEventListener('submit', handleVerification);
            }
        }

        function setupVerificationCodeInput() {
            const verificationCode = document.getElementById('verificationCode');
            if (verificationCode) {
                verificationCode.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                });
                
                verificationCode.addEventListener('input', function(e) {
                    if (this.value.length === 6) {
                        setTimeout(() => {
                            const form = document.getElementById('verificationForm');
                            if (form) {
                                form.dispatchEvent(new Event('submit', { cancelable: true }));
                            }
                        }, 500);
                    }
                });
            }
        }

        async function handleRegister(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const password = formData.get('password');
            const confirmPassword = formData.get('confirmPassword');
            const email = formData.get('email');
            
            if (password !== confirmPassword) {
                showMessage('Passwords do not match', 'error');
                return;
            }
            
            if (password.length < 6) {
                showMessage('Password must be at least 6 characters long', 'error');
                return;
            }
            
            const userData = {
                firstName: formData.get('firstName'),
                lastName: formData.get('lastName'),
                location: formData.get('location'),
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
                    registrationEmail = email;
                    showMessage('Registration successful! Please check your email for verification code.', 'success');
                    showVerificationModal(email);
                } else {
                    showMessage(data.message || 'Registration failed', 'error');
                }
            } catch (error) {
                showMessage('Registration failed. Please try again.', 'error');
                console.error('Registration error:', error);
            }
        }

        async function handleVerification(e) {
            e.preventDefault();
            
            const code = document.getElementById('verificationCode').value.trim();
            const email = registrationEmail || document.getElementById('hiddenEmail')?.value;
            
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
                
                const verifyBtn = document.getElementById('verifyBtn');
                verifyBtn.disabled = true;
                verifyBtn.textContent = 'Verifying...';
                
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
                
                if (data.success) {
                    showMessage('Email verified successfully! Redirecting to login...', 'success');
                    hideVerificationModal();
                    registrationEmail = null;
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else {
                    showMessage(data.message || 'Verification failed', 'error');
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Verify Email';
                }
            } catch (error) {
                showMessage('Verification failed. Please try again.', 'error');
                console.error('Verification error:', error);
                const verifyBtn = document.getElementById('verifyBtn');
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify Email';
            }
        }

        function showVerificationModal(email) {
            const modal = document.getElementById('verificationModal');
            const displayEmail = document.getElementById('displayEmail');
            
            if (modal) {
                registrationEmail = email;
                document.getElementById('hiddenEmail').value = email;
                
                if (displayEmail) {
                    displayEmail.textContent = email;
                }
                
                modal.style.display = 'block';
                modal.classList.add('show');
                
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.classList.add('show');
                }
                
                const codeInput = document.getElementById('verificationCode');
                if (codeInput) {
                    setTimeout(() => {
                        codeInput.focus();
                    }, 300);
                    codeInput.value = '';
                }
            }
        }

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
                
                const codeInput = document.getElementById('verificationCode');
                if (codeInput) {
                    codeInput.value = '';
                }
                
                const verifyBtn = document.getElementById('verifyBtn');
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify Email';
            }
        }

        function showMessage(message, type) {
            const messageDiv = document.getElementById('message');
            if (messageDiv) {
                messageDiv.textContent = message;
                messageDiv.className = `message ${type}`;
                messageDiv.style.display = 'block';
                
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000);
            }
        }

        async function resendVerificationCode() {
            if (!registrationEmail) {
                showMessage('Email not found. Please try registering again.', 'error');
                return;
            }
            
            try {
                showMessage('Resending verification code...', 'info');
                
                const resendBtn = document.getElementById('resendBtn');
                resendBtn.disabled = true;
                resendBtn.textContent = 'Sending...';
                
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
                
                setTimeout(() => {
                    resendBtn.disabled = false;
                    resendBtn.textContent = "Didn't receive the code? Resend verification email";
                }, 30000);
                
            } catch (error) {
                showMessage('Failed to resend verification code.', 'error');
                console.error('Resend error:', error);
                
                const resendBtn = document.getElementById('resendBtn');
                resendBtn.disabled = false;
                resendBtn.textContent = "Didn't receive the code? Resend verification email";
            }
        }

        document.addEventListener('click', function(e) {
            const modal = document.getElementById('verificationModal');
            if (e.target === modal) {
                hideVerificationModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.target.id === 'verificationCode' && e.key === 'Enter') {
                e.preventDefault();
                if (e.target.value.length === 6) {
                    document.getElementById('verificationForm').dispatchEvent(new Event('submit', { cancelable: true }));
                }
            }
        });

        // Three.js 3D IoT Box (keeping existing 3D visualization code)
        let scene, camera, renderer, iotBox, textMesh, descriptionDiv;

        function initIoTBox() {
            const canvas = document.getElementById('iotCanvas');
            if (!canvas) return;
            
            const container = canvas.parentElement;
            
            scene = new THREE.Scene();
            camera = new THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 0.1, 1000);
            camera.position.set(10, 5, 10);
            camera.lookAt(0, 0, 0);
            
            renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
            renderer.setSize(container.offsetWidth, container.offsetHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            renderer.shadowMap.enabled = true;
            renderer.shadowMap.type = THREE.PCFSoftShadowMap;
            
            const boxGeometry = new THREE.BoxGeometry(7, 2.5, 4.5);
            const boxMaterial = new THREE.MeshStandardMaterial({
                color: 0x505050,
                transparent: true,
                opacity: 0.3,
                metalness: 0.5,
                roughness: 0.5
            });
            
            const edges = new THREE.EdgesGeometry(boxGeometry);
            const lineMaterial = new THREE.LineBasicMaterial({ color: 0xffffff, linewidth: 2 });
            
            iotBox = new THREE.Group();
            
            const solidBox = new THREE.Mesh(boxGeometry, boxMaterial);
            solidBox.castShadow = true;
            iotBox.add(solidBox);
            
            const wireframe = new THREE.LineSegments(edges, lineMaterial);
            iotBox.add(wireframe);
            
            createText3D();
            
            iotBox.position.y = 2;
            scene.add(iotBox);
            
            const shadowGeo = new THREE.PlaneGeometry(15, 15);
            const shadowMat = new THREE.ShadowMaterial({ opacity: 0.7 });
            const shadowPlane = new THREE.Mesh(shadowGeo, shadowMat);
            shadowPlane.rotation.x = -Math.PI / 2;
            shadowPlane.position.y = 0;
            shadowPlane.receiveShadow = true;
            scene.add(shadowPlane);
            
            scene.add(createShadowCircle());
            
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.8);
            scene.add(ambientLight);
            
            const mainLight = new THREE.DirectionalLight(0xffffff, 1.2);
            mainLight.position.set(10, 20, 10);
            mainLight.castShadow = true;
            mainLight.shadow.mapSize.width = 4096;
            mainLight.shadow.mapSize.height = 4096;
            mainLight.shadow.camera.near = 0.5;
            mainLight.shadow.camera.far = 50;
            mainLight.shadow.camera.left = mainLight.shadow.camera.bottom = -15;
            mainLight.shadow.camera.right = mainLight.shadow.camera.top = 15;
            mainLight.shadow.bias = -0.0001;
            scene.add(mainLight);
            
            const blueLight1 = new THREE.PointLight(0x0ea5e9, 0.6, 25);
            blueLight1.position.set(6, 4, 6);
            scene.add(blueLight1);
            
            const blueLight2 = new THREE.PointLight(0x06b6d4, 0.6, 25);
            blueLight2.position.set(-6, 4, -6);
            scene.add(blueLight2);
            
            const fillLight = new THREE.DirectionalLight(0xffffff, 0.5);
            fillLight.position.set(-10, 10, -10);
            scene.add(fillLight);
            
            createDescription();
            startTypingEffect();
            animate();
            
            window.addEventListener('resize', onWindowResize);
        }
        
        function createText3D() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 1024;
            canvas.height = 256;
            
            ctx.shadowColor = 'rgba(14, 165, 233, 1)';
            ctx.shadowBlur = 40;
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 110px Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('AQUALITICS', 512, 128);
            
            const texture = new THREE.CanvasTexture(canvas);
            const geometry = new THREE.PlaneGeometry(4.2, 1.05);
            const material = new THREE.MeshBasicMaterial({ map: texture, transparent: true, side: THREE.DoubleSide });
            
            textMesh = new THREE.Mesh(geometry, material);
            textMesh.position.set(0, 0, 2.26);
            iotBox.add(textMesh);
        }
        
        function createDescription() {
            const container = document.querySelector('.iot-showcase');
            if (!container) return;
            
            descriptionDiv = document.createElement('div');
            descriptionDiv.style.cssText = `
                position: absolute; bottom: 100px; left: 50%; transform: translateX(-50%);
                max-width: 900px; width: 90%; padding: 0 40px;
                color: rgba(255, 255, 255, 0.95); font-size: 18px; line-height: 1.65;
                text-align: center; z-index: 10; letter-spacing: 0.4px;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            `;
            
            descriptionDiv.innerHTML = `
                <p id="typingText" style="margin: 0; font-weight: 400;">
                    <span style="color: #0ea5e9; font-weight: 800; font-size: 20px; letter-spacing: 1px; text-transform: uppercase;"></span>
                    <span style="color: rgba(255, 255, 255, 0.94); font-weight: 400;"></span>
                </p>
            `;
            
            container.appendChild(descriptionDiv);
        }
        
        function startTypingEffect() {
            const brandName = "AQUALITICS";
            const restOfText = " is an IoT-based device that continuously monitors tap water quality in real-time. Using pH, TDS, EC, turbidity, and temperature sensors, it ensures your water remains safe and clean.";
            
            const spans = document.querySelectorAll('#typingText span');
            if (spans.length < 2) return;
            
            const brandSpan = spans[0];
            const textSpan = spans[1];
            
            let brandIndex = 0;
            let textIndex = 0;
            
            function typeBrand() {
                if (brandIndex < brandName.length) {
                    brandSpan.textContent += brandName.charAt(brandIndex);
                    brandIndex++;
                    setTimeout(typeBrand, 70);
                } else {
                    setTimeout(typeRest, 200);
                }
            }
            
            function typeRest() {
                if (textIndex < restOfText.length) {
                    textSpan.textContent += restOfText.charAt(textIndex);
                    textIndex++;
                    setTimeout(typeRest, 20);
                }
            }
            
            setTimeout(typeBrand, 500);
        }
        
        function createShadowCircle() {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 512;
            const ctx = canvas.getContext('2d');
            
            const gradient = ctx.createRadialGradient(256, 256, 0, 256, 256, 256);
            gradient.addColorStop(0, 'rgba(0, 0, 0, 0.9)');
            gradient.addColorStop(0.3, 'rgba(0, 0, 0, 0.7)');
            gradient.addColorStop(0.6, 'rgba(0, 0, 0, 0.4)');
            gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, 512, 512);
            
            const texture = new THREE.CanvasTexture(canvas);
            const geometry = new THREE.PlaneGeometry(12, 12);
            const material = new THREE.MeshBasicMaterial({ map: texture, transparent: true, opacity: 0.95 });
            
            const mesh = new THREE.Mesh(geometry, material);
            mesh.rotation.x = -Math.PI / 2;
            mesh.position.y = 0.01;
            
            return mesh;
        }
        
        function animate() {
            requestAnimationFrame(animate);
            
            const time = Date.now() * 0.001;
            
            iotBox.rotation.y += 0.007;
            iotBox.position.y = 2 + Math.sin(time * 0.7) * 0.25;
            iotBox.rotation.x = Math.sin(time * 0.5) * 0.04;
            iotBox.rotation.z = Math.cos(time * 0.6) * 0.03;
            
            renderer.render(scene, camera);
        }
        
        function onWindowResize() {
            const container = document.querySelector('.iot-canvas-container');
            if (container && camera && renderer) {
                const width = container.offsetWidth;
                const height = container.offsetHeight;
                
                camera.aspect = width / height;
                camera.updateProjectionMatrix();
                renderer.setSize(width, height);
            }
        }
        
        window.addEventListener('load', function() {
            if (document.getElementById('iotCanvas')) {
                initIoTBox();
            }
        });
    </script>
</body>
</html>