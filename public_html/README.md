# Premium Book Store - Setup Guide

## Overview
A modern, high-converting single-page book selling website with admin dashboard for Bangladesh market.

## Features
- Single-page responsive design optimized for mobile
- bKash payment integration (prepaid)
- Clean admin dashboard
- Real-time order management
- AJAX-powered order submission

## Tech Stack
- Frontend: HTML + Tailwind CSS + Vanilla JavaScript
- Backend: PHP 7+ + MySQL
- No heavy frameworks required

## Installation Steps

### 1. Database Setup
1. Create a new MySQL database named `urimas_books`
2. Import the `database.sql` file using phpMyAdmin or MySQL command line:
   ```bash
   mysql -u root -p urimas_books < database.sql
   ```

### 2. File Structure
Upload all files to your web server. The structure should be:
```
/public_html/
├── index.php
├── config.php
├── database.sql
├── assets/
│   ├── css/
│   ├── js/
│   │   └── app.js
│   └── images/
│       ├── book1.jpg
│       ├── book2.jpg
│       ├── book3.jpg
│       └── book4.jpg
├── api/
│   ├── get-books.php
│   ├── get-settings.php
│   └── create-order.php
└── admin/
    ├── login.php
    ├── dashboard.php
    ├── settings.php
    └── logout.php
```

### 3. Configuration
Edit `config.php` and update database credentials if needed:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'urimas_books');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

### 4. Add Book Images
Place book cover images in `assets/images/` folder:
- book1.jpg
- book2.jpg
- book3.jpg
- book4.jpg

### 5. Admin Access
Default admin credentials:
- Username: `admin`
- Password: `admin123`

**Important:** Change the default password after first login!

### 6. Permissions
Ensure the following directories are writable (755 permissions):
- `/public_html/assets/images/`

## Usage

### Customer Flow
1. Visit the website
2. Browse books and click "Order Now"
3. Pay via bKash to the displayed number
4. Fill the order form with transaction ID
5. Submit order

### Admin Panel
1. Go to `/admin/login.php`
2. Login with admin credentials
3. View orders, update status, manage settings

## Customization

### Update bKash Number
Login to admin panel → Settings → Update bKash number

### Change Delivery Charges
Admin panel → Settings → Update delivery charges

### Modify Books
Admin panel → Settings → Edit book information

### Styling
All styles use Tailwind CSS. Modify classes in `index.php` and admin files.

## Security Notes
- Uses PDO prepared statements
- Input sanitization and validation
- Session-based admin authentication
- Password hashing with bcrypt

## Deployment
Compatible with:
- Hostinger shared hosting
- Any Apache + PHP + MySQL hosting
- No Node.js required

## Support
For issues or customization requests, check the code comments and PHP error logs.

## License
This project is provided as-is for educational and commercial use.