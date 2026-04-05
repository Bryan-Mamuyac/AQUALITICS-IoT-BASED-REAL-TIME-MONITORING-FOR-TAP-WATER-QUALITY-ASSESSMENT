</main>

<style>
    /* Footer Styles */
    .footer {
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(12px);
        border-top: 1px solid rgba(14, 165, 233, 0.2);
        margin-top: 3rem;
    }

    .footer-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2.5rem 2rem 1rem;
    }

    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .footer-section h3 {
        color: #0ea5e9;
        font-size: 1.25rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-weight: 700;
    }

    .footer-section h4 {
        color: rgba(255, 255, 255, 0.9);
        font-size: 1.1rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .footer-section p {
        color: rgba(255, 255, 255, 0.7);
        line-height: 1.6;
        margin-bottom: 1rem;
        font-size: 0.95rem;
    }

    .footer-section ul {
        list-style: none;
    }

    .footer-section ul li {
        margin-bottom: 0.5rem;
    }

    .footer-section ul li a {
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0;
        font-weight: 500;
    }

    .footer-section ul li a:hover {
        color: #0ea5e9;
        transform: translateX(2px);
    }

    .status-indicator {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        margin-bottom: 0.6rem;
        padding: 0.25rem 0;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #ef4444;
        flex-shrink: 0;
    }

    .status-dot.online {
        background: #22c55e;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
        }
        70% {
            box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
        }
    }

    .status-indicator span {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .last-update {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        font-weight: 500;
        margin-top: 0.5rem;
    }

    .footer-bottom {
        border-top: 1px solid rgba(14, 165, 233, 0.2);
        padding-top: 1.5rem;
        text-align: center;
    }

    .footer-bottom p {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
        font-weight: 500;
    }

    /* Mobile Footer */
    @media (max-width: 768px) {
        .footer-container {
            padding: 2rem 1rem 1rem;
        }

        .footer-content {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .footer-section {
            text-align: center;
        }

        .footer-section ul li a {
            justify-content: center;
        }

        .status-indicator {
            justify-content: center;
        }
    }
</style>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-tint"></i> Aqualitics</h3>
                <p>Advanced water quality monitoring system for aquaculture operations, providing real-time insights and comprehensive analytics for sustainable aquatic management.</p>
            </div>
            
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Analytics</a></li>
                    <?php else: ?>
                        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                        <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Support</h4>
                <ul>
                    <li><a href="#" onclick="showContact()"><i class="fas fa-envelope"></i> Contact Us</a></li>
                    <li><a href="#" onclick="showHelp()"><i class="fas fa-question-circle"></i> Help Center</a></li>
                    <li><a href="#" onclick="showAbout()"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="#" onclick="showDocumentation()"><i class="fas fa-book"></i> Documentation</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>System Status</h4>
                <div class="status-indicator">
                    <span class="status-dot online"></span>
                    <span>System Online</span>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Aqualitics. All rights reserved. | Version 2.0.1</p>
        </div>
    </div>
</footer>

<!-- JavaScript Files -->
<?php if (isset($additional_js)): ?>
    <?php foreach ($additional_js as $js): ?>
        <script src="js/<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Common JavaScript -->
<script>
    // Update timestamp every minute
    setInterval(function() {
        const now = new Date();
        const timestamp = now.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        const updateElements = document.querySelectorAll('#lastUpdate, #footerLastUpdate');
        updateElements.forEach(element => {
            if (element) {
                element.textContent = timestamp;
            }
        });
    }, 60000);

    // Dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = document.querySelectorAll('.dropdown');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (toggle && menu) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.classList.remove('show');
                        }
                    });
                    
                    // Toggle current dropdown
                    menu.classList.toggle('show');
                });
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
    });

    // Alert auto-dismiss
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        });
    }, 5000);

    // Modal functions
    function showContact() {
        alert('ðŸ“§ Contact Information:\n\nEmail: support@aqualitics.com\nPhone: +63-917-123-4567\nAddress: San Fernando, La Union, Philippines\n\nBusiness Hours: 8:00 AM - 6:00 PM (PHT)');
    }

    function showHelp() {
        alert('ðŸ†˜ Help Center:\n\nFor technical support and troubleshooting:\nâ€¢ Check our documentation\nâ€¢ Contact technical support\nâ€¢ Submit a support ticket\n\nEmergency Support: Available 24/7');
    }

    function showAbout() {
        alert('â„¹ï¸ About Aqualitics:\n\nVersion: 2.0.1\nAdvanced water quality monitoring for aquaculture\n\nFeatures:\nâ€¢ Real-time water quality monitoring\nâ€¢ Advanced analytics and reporting\nâ€¢ Data export and management\nâ€¢ Mobile-responsive design\n\nBuilt with modern web technologies for reliability and performance.');
    }

    function showDocumentation() {
        alert('ðŸ“š Documentation:\n\nAccess comprehensive guides for:\nâ€¢ System setup and configuration\nâ€¢ Sensor calibration procedures\nâ€¢ Data interpretation guidelines\nâ€¢ Troubleshooting common issues\nâ€¢ API integration examples\n\nVisit our knowledge base for detailed information.');
    }

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add loading states for buttons
    function addLoadingState(button) {
        if (button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            button.disabled = true;
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        }
    }

    // Add click handlers for buttons with loading states
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-import') || e.target.classList.contains('btn-export')) {
            addLoadingState(e.target);
        }
    });
</script>
</body>
</html>
