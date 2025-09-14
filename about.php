<?php
require_once 'config.php';

// Redirect to appropriate dashboard if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    switch ($user['role']) {
        case 'admin':
            redirect('admin_dashboard.php');
            break;
        case 'landlord':
            redirect('landlord_dashboard.php');
            break;
        case 'tenant':
            redirect('tenant_dashboard.php');
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background: #f8f9fa;
            color: #333;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 8px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .logo {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
            text-decoration: none;
        }
        
        .nav-buttons {
            display: flex;
            gap: 15px;
        }
        
        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #2c3e50;
            padding: 0.3rem;
        }
        
        .mobile-nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .mobile-nav-content {
            position: fixed;
            top: 10px;
            left: -100%;
            width: 250px;
            height: auto;
            max-height: calc(95vh - 20px);
            background: white;
            padding: 0.8rem;
            transition: left 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            border-radius: 0 0 15px 0;
            overflow-y: auto;
        }
        
        .mobile-nav.open .mobile-nav-content {
            left: 0;
        }
        
        .mobile-nav.open {
            display: block;
        }
        
        .mobile-nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
            padding-bottom: 0.2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .mobile-nav-header h3 {
            font-size: 0.8rem;
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
            line-height: 1.2;
        }
        
        .mobile-nav-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #2c3e50;
        }
        
        .mobile-nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .mobile-nav-menu li {
            margin-bottom: 0.2rem;
        }
        
        .mobile-nav-menu a {
            display: block;
            padding: 0.5rem 0.7rem;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .mobile-nav-menu a:hover,
        .mobile-nav-menu a.active {
            background: #3498db;
            color: white;
        }
        
        .nav-btn {
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .nav-btn-primary {
            background: #3498db;
            color: white;
        }
        
        .nav-btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .nav-btn-secondary {
            background: transparent;
            color: #2c3e50;
            border: 2px solid #2c3e50;
        }
        
        .nav-btn-secondary:hover {
            background: #2c3e50;
            color: white;
        }
        
        .nav-btn.active {
            background: #2c3e50;
            color: white;
        }
        
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .page-header h1 {
            font-size: 3rem;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .page-header p {
            font-size: 1.2rem;
            color: #7f8c8d;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .content-section {
            background: white;
            padding: 60px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .content-section h2 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .content-section p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #555;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 15px;
            border-left: 4px solid #3498db;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .features-list {
            display: grid;
            gap: 20px;
        }
        
        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #3498db;
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .feature-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .feature-text {
            flex: 1;
        }
        
        .feature-text strong {
            color: #2c3e50;
            font-size: 1.1rem;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 40px 20px;
            margin-top: 60px;
        }
        
        .footer p {
            margin: 5px 0;
            opacity: 0.8;
        }
        
        @media (max-width: 768px) {
            .hamburger {
                display: block;
            }
            
            .nav-buttons {
                display: none;
            }
            
            .page-container {
                padding: 40px 15px;
            }
            
            .page-header h1 {
                font-size: 2.5rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .content-section {
                padding: 30px 20px;
            }
            
            .content-section h2 {
                font-size: 1.8rem;
            }
            
            .content-section p {
                font-size: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stat-item {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .feature-item {
                flex-direction: column;
                text-align: center;
                gap: 10px;
                padding: 15px;
            }
            
            .feature-icon {
                font-size: 1.5rem;
            }
            
            .feature-text strong {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .content-section {
                padding: 20px 15px;
            }
            
            .content-section h2 {
                font-size: 1.5rem;
            }
            
            .nav-btn {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            
            .stat-item {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
            
            .feature-item {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo"><?php echo APP_NAME; ?></a>
            <div class="nav-buttons">
                <a href="index.php" class="nav-btn nav-btn-secondary">Home</a>
                <a href="about.php" class="nav-btn nav-btn-secondary active">About</a>
                <a href="contact.php" class="nav-btn nav-btn-secondary">Contact</a>
                <a href="login.php" class="nav-btn nav-btn-primary">Login</a>
                <a href="register.php" class="nav-btn nav-btn-secondary">Create Account</a>
            </div>
            <button class="hamburger" onclick="toggleMobileNav()">☰</button>
        </div>
    </nav>
    
    <!-- Mobile Navigation -->
    <div class="mobile-nav" id="mobileNav">
        <div class="mobile-nav-content">
            <div class="mobile-nav-header">
                <h3><?php echo APP_NAME; ?></h3>
                <button class="mobile-nav-close" onclick="closeMobileNav()">×</button>
            </div>
            <ul class="mobile-nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php" class="active">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Create Account</a></li>
            </ul>
        </div>
    </div>
    
    <div class="page-container">
        <div class="page-header">
            <h1>About Our Platform</h1>
            <p>Revolutionizing rental property management in Kenya with modern technology and user-friendly solutions.</p>
        </div>
        
        <div class="content-section">
            <h2>Our Mission</h2>
            <p>We're revolutionizing the way rental properties are managed in Kenya. Our comprehensive platform brings together landlords, tenants, and administrators in one seamless ecosystem.</p>
            <p>Built with modern technology and user experience in mind, we provide everything you need to manage your rental properties efficiently, from tenant onboarding to payment tracking and maintenance requests.</p>
            <p>Whether you're a property owner managing multiple units or a tenant looking for a smooth rental experience, our platform is designed to make your life easier.</p>
        </div>
        
        <div class="content-section">
            <h2>Why Choose Us?</h2>
            <p><strong>🏠 Comprehensive Property Management:</strong> Manage multiple properties, track tenants, and monitor rent collection all in one place.</p>
            <p><strong>💰 Advanced Payment Tracking:</strong> Track rent payments, monitor overdue accounts, and generate detailed payment reports effortlessly.</p>
            <p><strong>📧 Smart Notifications:</strong> Send automated notifications to tenants via email or system alerts for better communication.</p>
            <p><strong>👥 Multi-Role Access:</strong> Separate dashboards for landlords, tenants, and administrators with role-based permissions.</p>
            <p><strong>📊 Analytics & Reports:</strong> Comprehensive reporting and analytics to help you make informed business decisions.</p>
            <p><strong>🔒 Secure & Reliable:</strong> Built with security in mind, your data is protected with modern encryption and best practices.</p>
        </div>
        
        <div class="content-section">
            <h2>Our Impact</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Properties Managed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">1000+</div>
                    <div class="stat-label">Happy Tenants</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">99%</div>
                    <div class="stat-label">Uptime Guarantee</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Customer Support</div>
                </div>
            </div>
        </div>
        
        <div class="content-section">
            <h2>Our Vision</h2>
            <p>To become the leading rental property management platform in Kenya, empowering property owners and tenants with innovative technology solutions that simplify the rental process and enhance the overall experience.</p>
            <p>We believe in creating a transparent, efficient, and user-friendly environment where property management becomes effortless and enjoyable for everyone involved.</p>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p>Professional rental property management solution for Kenya.</p>
    </div>
    
    <script>
        function toggleMobileNav() {
            const mobileNav = document.getElementById('mobileNav');
            mobileNav.classList.toggle('open');
        }
        
        function closeMobileNav() {
            const mobileNav = document.getElementById('mobileNav');
            mobileNav.classList.remove('open');
        }
        
        // Close mobile nav when clicking on a link
        document.querySelectorAll('.mobile-nav-menu a').forEach(link => {
            link.addEventListener('click', () => {
                closeMobileNav();
            });
        });
        
        // Close mobile nav when clicking outside
        document.getElementById('mobileNav').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMobileNav();
            }
        });
    </script>
</body>
</html>
