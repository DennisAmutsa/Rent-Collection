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
    <title><?php echo APP_NAME; ?> - Modern Rent Collection System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .landing-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            display: flex;
            flex-direction: column;
        }
        
        /* Navigation Bar */
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
        
        .hero-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 80px 20px;
        }
        
        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 40px;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .cta-btn {
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .cta-btn-primary {
            background: #e74c3c;
            color: white;
        }
        
        .cta-btn-primary:hover {
            background: #c0392b;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .cta-btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .cta-btn-secondary:hover {
            background: white;
            color: #2c3e50;
        }
        
        .features-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 80px 20px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: white;
            padding: 40px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #ecf0f1;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .feature-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .feature-card p {
            color: #7f8c8d;
            line-height: 1.6;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 40px 20px;
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
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-content p {
                font-size: 1.1rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .cta-btn {
                width: 200px;
            }
        }
    </style>
</head>
<body class="landing-page">
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo"><?php echo APP_NAME; ?></a>
            <div class="nav-buttons">
                <a href="index.php" class="nav-btn nav-btn-secondary">Home</a>
                <a href="about.php" class="nav-btn nav-btn-secondary">About</a>
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
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Create Account</a></li>
            </ul>
        </div>
    </div>
    
    <div class="hero-section">
        <div class="hero-content">
            <h1><?php echo APP_NAME; ?></h1>
            <p>Streamline your rental property management with our modern, user-friendly platform</p>
            <div class="cta-buttons">
                <a href="login.php" class="cta-btn cta-btn-primary">Get Started</a>
                <a href="register.php" class="cta-btn cta-btn-secondary">Create Account</a>
            </div>
        </div>
    </div>
    
    <div class="features-section" id="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🏠</div>
                <h3>Property Management</h3>
                <p>Easily manage multiple properties, track tenants, and monitor rent collection all in one place.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Payment Tracking</h3>
                <p>Track rent payments, monitor overdue accounts, and generate payment reports effortlessly.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📧</div>
                <h3>Smart Notifications</h3>
                <p>Send automated notifications to tenants via email or system alerts for better communication.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Multi-Role Access</h3>
                <p>Separate dashboards for landlords, tenants, and administrators with role-based permissions.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Analytics & Reports</h3>
                <p>Comprehensive reporting and analytics to help you make informed business decisions.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure & Reliable</h3>
                <p>Built with security in mind, your data is protected with modern encryption and best practices.</p>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p>Professional rental property management solution.</p>
    </div>
    
    <script>
        // Mobile navigation functions
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
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.feature-card');
            
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.style.animation = 'fadeInUp 0.6s ease forwards';
            });
        });
        
        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>