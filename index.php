<?php
session_start();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config/database.php';
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password, role, is_verified FROM users WHERE email = ? AND is_verified = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                $redirect = ($user['role'] == 'admin') ? 'admin_dashboard.php' : 'dashboard.php';
                header("Location: $redirect");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } catch (Exception $e) {
            $error = "Login failed. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = ($_SESSION['role'] == 'admin') ? 'admin_dashboard.php' : 'dashboard.php';
    header("Location: $redirect");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aqualitics - Login</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <!-- Floating background elements -->
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>
    <div class="floating-shape shape-3"></div>
    
    <div class="login-container">
        <!-- Left side - Login Form -->
        <div class="login-form">
            <div class="logo">
                <h1>Aqualitics</h1>
                <p>IoT Water Quality Monitoring System</p>
            </div>
            
            <form id="loginForm" method="POST" action="">
                <?php if (isset($error)): ?>
                    <div class="error-message" style="background: rgba(239, 68, 68, 0.15); color: #f87171; padding: 12px; border-radius: 12px; margin-bottom: 15px; text-align: center; border: 1px solid rgba(239, 68, 68, 0.3);">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <input type="email" id="email" name="email" placeholder="Email Address" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" placeholder="Password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="login-btn">Login</button>
                
                <div class="form-links">
                    <a href="#" onclick="showForgotPasswordModal(); return false;">Forgot Password?</a>
                    <span style="color: rgba(255, 255, 255, 0.3); margin: 0 8px;">•</span>
                    <a href="register.php">Don't have an account? Sign up</a>
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

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="hideForgotPasswordModal()">×</button>
            
            <div class="modal-header">
                <div class="email-icon">🔑</div>
                <h3 id="modalTitle">Reset Password</h3>
                <p id="modalSubtitle">Enter your email to receive a reset code</p>
            </div>
            
            <!-- Step 1: Enter Email -->
            <form id="forgotPasswordForm" style="display: block;">
                <div class="form-group">
                    <input type="email" id="resetEmail" name="resetEmail" placeholder="Email Address" required>
                </div>
                
                <button type="submit" class="verify-btn">Send Reset Code</button>
            </form>
            
            <!-- Step 2: Enter Code and New Password -->
            <form id="resetPasswordForm" style="display: none;">
                <div class="verification-help">
                    <strong>Check your email:</strong>
                    <div class="email-display" id="displayResetEmail"></div>
                    <small>Enter the 6-digit code and your new password</small>
                </div>
                
                <div class="form-group">
                    <input type="text" id="resetCode" name="resetCode" placeholder="6-Digit Code" maxlength="6" required>
                </div>
                
                <div class="form-group">
                    <div class="password-input-container">
                        <input type="password" id="newPassword" name="newPassword" placeholder="New Password (min. 8 characters)" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="password-input-container">
                        <input type="password" id="confirmNewPassword" name="confirmNewPassword" placeholder="Confirm New Password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmNewPassword', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="verify-btn">Reset Password</button>
                
                <button type="button" class="resend-link" onclick="resendResetCode()">Didn't receive the code? Resend</button>
            </form>
        </div>
    </div>
    
    <script src="js/main.js"></script>
    <script src="js/forgot_password.js"></script>
    <script>
        // Password toggle function
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

        // Three.js 3D Rectangular IoT Box
        let scene, camera, renderer, iotBox, textMesh, descriptionDiv;

        function initIoTBox() {
            const canvas = document.getElementById('iotCanvas');
            const container = canvas.parentElement;
            
            // Scene setup
            scene = new THREE.Scene();
            
            // Camera setup
            camera = new THREE.PerspectiveCamera(
                45,
                container.offsetWidth / container.offsetHeight,
                0.1,
                1000
            );
            camera.position.set(10, 5, 10);
            camera.lookAt(0, 0, 0);
            
            // Renderer setup
            renderer = new THREE.WebGLRenderer({ 
                canvas: canvas,
                alpha: true,
                antialias: true 
            });
            renderer.setSize(container.offsetWidth, container.offsetHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            renderer.shadowMap.enabled = true;
            renderer.shadowMap.type = THREE.PCFSoftShadowMap;
            
            // Create rectangular IoT device with gray material
            const boxWidth = 7;
            const boxHeight = 2.5;
            const boxDepth = 4.5;
            const boxGeometry = new THREE.BoxGeometry(boxWidth, boxHeight, boxDepth);
            
            // Create gray semi-transparent material for the box
            const boxMaterial = new THREE.MeshStandardMaterial({
                color: 0x505050,
                transparent: true,
                opacity: 0.3,
                metalness: 0.5,
                roughness: 0.5
            });
            
            // Create clean white wireframe
            const edges = new THREE.EdgesGeometry(boxGeometry);
            const lineMaterial = new THREE.LineBasicMaterial({ 
                color: 0xffffff,
                linewidth: 2
            });
            
            // Group for rotation
            iotBox = new THREE.Group();
            
            // Add solid gray box
            const solidBox = new THREE.Mesh(boxGeometry, boxMaterial);
            solidBox.castShadow = true;
            iotBox.add(solidBox);
            
            // Main wireframe on top
            const wireframe = new THREE.LineSegments(edges, lineMaterial);
            iotBox.add(wireframe);
            
            // Create "Aqualitics" 3D text mesh that rotates with box
            createText3D();
            
            iotBox.position.y = 2;
            scene.add(iotBox);
            
            // Shadow plane with enhanced opacity
            const shadowGeo = new THREE.PlaneGeometry(15, 15);
            const shadowMat = new THREE.ShadowMaterial({ opacity: 0.7 });
            const shadowPlane = new THREE.Mesh(shadowGeo, shadowMat);
            shadowPlane.rotation.x = -Math.PI / 2;
            shadowPlane.position.y = 0;
            shadowPlane.receiveShadow = true;
            scene.add(shadowPlane);
            
            // Enhanced circular gradient shadow
            const shadowCircle = createShadowCircle();
            scene.add(shadowCircle);
            
            // Lighting
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.8);
            scene.add(ambientLight);
            
            const mainLight = new THREE.DirectionalLight(0xffffff, 1.2);
            mainLight.position.set(10, 20, 10);
            mainLight.castShadow = true;
            mainLight.shadow.mapSize.width = 4096;
            mainLight.shadow.mapSize.height = 4096;
            mainLight.shadow.camera.near = 0.5;
            mainLight.shadow.camera.far = 50;
            mainLight.shadow.camera.left = -15;
            mainLight.shadow.camera.right = 15;
            mainLight.shadow.camera.top = 15;
            mainLight.shadow.camera.bottom = -15;
            mainLight.shadow.bias = -0.0001;
            scene.add(mainLight);
            
            // Blue accent lights
            const blueLight1 = new THREE.PointLight(0x0ea5e9, 0.6, 25);
            blueLight1.position.set(6, 4, 6);
            scene.add(blueLight1);
            
            const blueLight2 = new THREE.PointLight(0x06b6d4, 0.6, 25);
            blueLight2.position.set(-6, 4, -6);
            scene.add(blueLight2);
            
            // Fill light
            const fillLight = new THREE.DirectionalLight(0xffffff, 0.5);
            fillLight.position.set(-10, 10, -10);
            scene.add(fillLight);
            
            // Create description text with typing effect
            createDescription();
            startTypingEffect();
            
            // Start animation
            animate();
            
            // Handle resize
            window.addEventListener('resize', onWindowResize);
        }
        
        function createText3D() {
            // Create canvas for text
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 1024;
            canvas.height = 256;
            
            // Draw text with enhanced modern styling
            ctx.shadowColor = 'rgba(14, 165, 233, 1)';
            ctx.shadowBlur = 40;
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 110px Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('AQUALITICS', 512, 128);
            
            const texture = new THREE.CanvasTexture(canvas);
            
            // Create plane geometry instead of sprite for proper 3D rotation
            const geometry = new THREE.PlaneGeometry(4.2, 1.05);
            const material = new THREE.MeshBasicMaterial({
                map: texture,
                transparent: true,
                side: THREE.DoubleSide
            });
            
            textMesh = new THREE.Mesh(geometry, material);
            // Position on front face
            textMesh.position.set(0, 0, 2.26);
            iotBox.add(textMesh);
        }
        
        function createDescription() {
            const container = document.querySelector('.iot-showcase');
            
            descriptionDiv = document.createElement('div');
            descriptionDiv.style.cssText = `
                position: absolute;
                bottom: 100px;
                left: 50%;
                transform: translateX(-50%);
                max-width: 900px;
                width: 90%;
                padding: 0 40px;
                color: rgba(255, 255, 255, 0.95);
                font-size: 18px;
                line-height: 1.65;
                text-align: center;
                z-index: 10;
                letter-spacing: 0.4px;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            `;
            
            descriptionDiv.innerHTML = `
                <p id="typingText" style="margin: 0; font-weight: 400;">
                    <span style="color: #0ea5e9; font-weight: 800; font-size: 20px; letter-spacing: 1px; text-transform: uppercase;"></span><span style="color: rgba(255, 255, 255, 0.94); font-weight: 400;"></span>
                </p>
            `;
            
            container.appendChild(descriptionDiv);
        }
        
        function startTypingEffect() {
            const brandName = "AQUALITICS";
            const restOfText = " is an IoT-based device that continuously monitors tap water quality in real-time. Using pH, TDS, EC, turbidity, and temperature sensors, it ensures your water remains safe and clean.";
            
            const brandSpan = document.querySelector('#typingText span:first-child');
            const textSpan = document.querySelector('#typingText span:last-child');
            
            let brandIndex = 0;
            let textIndex = 0;
            
            // Type brand name first
            function typeBrand() {
                if (brandIndex < brandName.length) {
                    brandSpan.textContent += brandName.charAt(brandIndex);
                    brandIndex++;
                    setTimeout(typeBrand, 70);
                } else {
                    setTimeout(typeRest, 200);
                }
            }
            
            // Then type rest of text
            function typeRest() {
                if (textIndex < restOfText.length) {
                    textSpan.textContent += restOfText.charAt(textIndex);
                    textIndex++;
                    setTimeout(typeRest, 20);
                }
            }
            
            // Start typing
            setTimeout(typeBrand, 500);
        }
        
        function createShadowCircle() {
            const canvas = document.createElement('canvas');
            canvas.width = 512;
            canvas.height = 512;
            const ctx = canvas.getContext('2d');
            
            // Enhanced radial gradient with more depth
            const gradient = ctx.createRadialGradient(256, 256, 0, 256, 256, 256);
            gradient.addColorStop(0, 'rgba(0, 0, 0, 0.9)');
            gradient.addColorStop(0.3, 'rgba(0, 0, 0, 0.7)');
            gradient.addColorStop(0.6, 'rgba(0, 0, 0, 0.4)');
            gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, 512, 512);
            
            const texture = new THREE.CanvasTexture(canvas);
            const geometry = new THREE.PlaneGeometry(12, 12);
            const material = new THREE.MeshBasicMaterial({
                map: texture,
                transparent: true,
                opacity: 0.95
            });
            
            const mesh = new THREE.Mesh(geometry, material);
            mesh.rotation.x = -Math.PI / 2;
            mesh.position.y = 0.01;
            
            return mesh;
        }
        
        function animate() {
            requestAnimationFrame(animate);
            
            const time = Date.now() * 0.001;
            
            // Smooth rotation
            iotBox.rotation.y += 0.007;
            
            // Floating effect
            iotBox.position.y = 2 + Math.sin(time * 0.7) * 0.25;
            
            // Subtle tilt
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
        
        // Initialize on load
        window.addEventListener('load', function() {
            if (document.getElementById('iotCanvas')) {
                initIoTBox();
            }
        });
    </script>
</body>
</html>