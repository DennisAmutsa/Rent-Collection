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
    <title>Contact Us - <?php echo APP_NAME; ?></title>
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
        
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 60px;
        }
        
        .contact-info {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .contact-info h2 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .contact-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .contact-icon {
            font-size: 2rem;
            margin-right: 20px;
            color: #3498db;
        }
        
        .contact-details h3 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .contact-details p {
            color: #7f8c8d;
            margin: 0;
        }
        
        .contact-form {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .contact-form h2 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            background: white;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .submit-btn {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .submit-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
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
            
            .page-header h1 {
                font-size: 2.5rem;
            }
            
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .contact-info,
            .contact-form {
                padding: 30px 20px;
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
                <a href="about.php" class="nav-btn nav-btn-secondary">About</a>
                <a href="contact.php" class="nav-btn nav-btn-secondary active">Contact</a>
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
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php" class="active">Contact</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Create Account</a></li>
            </ul>
        </div>
    </div>
    
    <div class="page-container">
        <div class="page-header">
            <h1>Contact Us</h1>
            <p>Have questions about our platform? Need support? We're here to help you succeed with your rental property management.</p>
        </div>
        
        <div class="contact-grid">
            <div class="contact-info">
                <h2>Get In Touch</h2>
                
                <div class="contact-item">
                    <div class="contact-icon">📍</div>
                    <div class="contact-details">
                        <h3>Office Address</h3>
                        <p>Westlands Business Centre<br>Nairobi, Kenya</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">📞</div>
                    <div class="contact-details">
                        <h3>Phone Number</h3>
                        <p>+254 700 000 000</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">✉️</div>
                    <div class="contact-details">
                        <h3>Email Address</h3>
                        <p>info@rentcollection.co.ke</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">🕒</div>
                    <div class="contact-details">
                        <h3>Business Hours</h3>
                        <p>Monday - Friday: 8:00 AM - 6:00 PM<br>Saturday: 9:00 AM - 2:00 PM</p>
                    </div>
                </div>
            </div>
            
            <div class="contact-form">
                <h2>Send us a Message</h2>
                <form>
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" required placeholder="Tell us how we can help you..."></textarea>
                    </div>
                    
                    <button type="submit" class="submit-btn">Send Message</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p>Professional rental property management solution for Kenya.</p>
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
        
        // Contact form handling
        document.addEventListener('DOMContentLoaded', function() {
            const contactForm = document.querySelector('.contact-form form');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form data
                    const formData = new FormData(this);
                    const name = formData.get('name');
                    const email = formData.get('email');
                    const subject = formData.get('subject');
                    const message = formData.get('message');
                    
                    // Simple validation
                    if (!name || !email || !subject || !message) {
                        alert('Please fill in all required fields.');
                        return;
                    }
                    
                    // Simulate form submission
                    const submitBtn = this.querySelector('.submit-btn');
                    const originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Sending...';
                    submitBtn.disabled = true;
                    
                    setTimeout(() => {
                        alert('Thank you for your message! We\'ll get back to you soon.');
                        this.reset();
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }, 2000);
                });
            }
        });
    </script>
</body>
</html>
