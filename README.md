# Rent Collection System

A comprehensive web-based rent collection system built with PHP, HTML, CSS, JavaScript, and MySQL. The system provides separate dashboards for tenants, landlords, and administrators with role-based access control.

## Features

### 🔐 Authentication System
- Role-based login (Tenant, Landlord, Admin)
- Secure password hashing
- Session management
- Automatic redirection to appropriate dashboard

### 👥 User Roles

#### **Landlord Dashboard**
- Overview of all properties and tenants
- Rent collection statistics
- Pending and overdue payment tracking
- Property management
- Recent activity monitoring

#### **Admin Dashboard**
- System-wide statistics
- User management
- Notification system
- Send notifications to all tenants or specific users
- System activity monitoring

#### **Tenant Dashboard**
- View assigned property details
- Make rent payments
- View payment history
- Receive notifications
- Track payment status

### 📧 Notification System
- Send system notifications
- Email notifications (configurable)
- Bulk notifications to all tenants
- Notification templates
- Read/unread status tracking

### 💰 Payment Management
- Record rent payments
- Payment history tracking
- Overdue payment monitoring
- Multiple payment methods
- Monthly rent tracking

## Installation

### Prerequisites
- XAMPP (MySQL, PHP)
- Web browser

### Setup Instructions

1. **Start XAMPP MySQL**
   - Start only MySQL service in XAMPP

2. **Database Setup**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the `database.sql` file to create the database and tables
   - The database will be created with sample data

3. **Run the Project**
   - Open terminal/command prompt
   - Navigate to project directory: `cd "C:\Users\HP\OneDrive\Documents\PHP FILES\Rent collection"`
   - Start PHP server: `C:\xampp\php\php.exe -S localhost:8000`

4. **Access the System**
   - Open your browser and go to: `http://localhost:8000/`

## Demo Credentials

### Admin
- **Username:** admin
- **Password:** admin123

### Landlord
- **Username:** landlord1
- **Password:** admin123

### Tenant
- **Username:** tenant1
- **Password:** admin123

## File Structure

```
rent-collection-system/
├── index.php                 # Main entry point
├── login.php                 # Login page
├── logout.php                # Logout handler
├── config.php                # Application configuration
├── database.php              # Database connection class
├── style.css                 # Main stylesheet
├── script.js                 # JavaScript functionality
├── database.sql              # Database schema and sample data
├── email_notification.php    # Email notification system
├── send_notification.php     # Admin notification interface
├── landlord_dashboard.php    # Landlord dashboard
├── admin_dashboard.php       # Admin dashboard
├── tenant_dashboard.php      # Tenant dashboard
└── unauthorized.php          # Access denied page
```

## Database Schema

### Users Table
- Single table for all user types (tenant, landlord, admin)
- Role-based access control
- User authentication and profile information

### Key Tables
- `users` - All system users with roles
- `properties` - Property information
- `tenant_properties` - Tenant-property relationships
- `rent_payments` - Payment records
- `notifications` - System notifications

## Features Overview

### 🏠 Property Management
- Add and manage properties
- Assign tenants to properties
- Track property details and rent amounts

### 💳 Payment Processing
- Record rent payments
- Track payment status (pending, paid, overdue)
- Payment history and reporting
- Multiple payment methods support

### 📱 Responsive Design
- Mobile-friendly interface
- Modern gradient design
- Interactive dashboard elements
- Real-time updates

### 🔒 Security Features
- Password hashing
- SQL injection prevention
- XSS protection
- Session management
- Role-based access control

## Customization

### Email Configuration
Update SMTP settings in `config.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### Styling
- Modify `style.css` for custom styling
- Update color schemes and layouts
- Add your branding elements

### Functionality
- Extend `script.js` for additional JavaScript features
- Add new pages and functionality
- Customize notification templates

## Support

For support or questions about the system, please refer to the code comments or contact the development team.

## License

This project is open source and available under the MIT License.
