<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Aqualitics' : 'Aqualitics'; ?></title>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Header/Navigation Styles (Dark Theme) -->
    <link rel="stylesheet" href="css/header.css">
    
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="css/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <?php
    // Robust page detection
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_page_without_ext = pathinfo($current_page, PATHINFO_FILENAME);
    
    function isActivePage($page_names) {
        global $current_page, $current_page_without_ext;
        $page_names = is_array($page_names) ? $page_names : [$page_names];
        
        foreach ($page_names as $page_name) {
            if ($current_page == $page_name . '.php' || 
                $current_page == $page_name || 
                $current_page_without_ext == $page_name) {
                return true;
            }
        }
        return false;
    }
    
    // Get user's first name for greeting
    $user_name = $_SESSION['first_name'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'User';
    ?>
    
    <nav class="navbar">
        <div class="nav-container">
            <!-- Logo -->
            <a href="dashboard.php" class="nav-logo">
                <i class="fas fa-tint"></i>
                <span>Aqualitics</span>
            </a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Mobile Toggle -->
            <div class="mobile-toggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <!-- Navigation Menu (Desktop) -->
            <ul class="nav-menu" id="navMenu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo isActivePage(['dashboard', 'admin_dashboard', 'index']) ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo isActivePage(['profile']) ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <!-- Logout in mobile menu only -->
                <li class="nav-item mobile-only">
                    <a href="logout.php" class="nav-link logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
            
            <!-- User Section (Desktop) -->
            <div class="nav-user" id="navUser">
                <span class="user-greeting">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <main class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

    <script>
        function toggleMobileMenu() {
            const toggle = document.querySelector('.mobile-toggle');
            const menu = document.getElementById('navMenu');
            const user = document.getElementById('navUser');
            
            if (toggle && menu) {
                toggle.classList.toggle('active');
                menu.classList.toggle('show');
                
                // Prevent body scroll when menu is open on mobile
                if (menu.classList.contains('show')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const navbar = document.querySelector('.navbar');
            const menu = document.getElementById('navMenu');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (navbar && !navbar.contains(event.target) && menu && menu.classList.contains('show')) {
                if (toggle) toggle.classList.remove('active');
                menu.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // Close menu when clicking on a nav link (mobile)
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            const menu = document.getElementById('navMenu');
            const toggle = document.querySelector('.mobile-toggle');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768 && menu && menu.classList.contains('show')) {
                        if (toggle) toggle.classList.remove('active');
                        menu.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });
            });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const menu = document.getElementById('navMenu');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth > 768) {
                if (toggle) toggle.classList.remove('active');
                if (menu) menu.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    </script>