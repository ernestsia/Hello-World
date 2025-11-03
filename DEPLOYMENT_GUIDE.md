# SchoolManagement System - Deployment Guide

## 📋 Pre-Deployment Checklist

### ✅ Required Items
- [ ] Web hosting account with PHP support (PHP 7.4 or higher)
- [ ] MySQL database (5.7 or higher)
- [ ] Domain name (optional but recommended)
- [ ] FTP/SFTP access credentials
- [ ] SSL certificate (recommended for security)

---

## 🚀 Deployment Steps

### Step 1: Prepare Your Files

#### 1.1 Create a Deployment Package
```bash
# Create a zip file of your application
# Exclude unnecessary files:
- node_modules/
- .git/
- .env (if exists)
- any local config files
```

#### 1.2 Files to Include
```
SchoolManagement/
├── api/
├── assets/
├── attendance/
├── classes/
├── config/
├── database/
├── grades/
├── includes/
├── parents/
├── students/
├── subjects/
├── teachers/
├── uploads/
├── index.php
├── login.php
├── logout.php
└── README.md
```

---

### Step 2: Set Up Database

#### 2.1 Create Database on Server
1. Log into your hosting control panel (cPanel/Plesk)
2. Go to **MySQL Databases**
3. Create a new database:
   - Database name: `school_management`
   - Username: `school_user`
   - Password: (strong password)
4. Grant all privileges to the user

#### 2.2 Import Database Schema
1. Go to **phpMyAdmin**
2. Select your database
3. Click **Import** tab
4. Upload and import these files in order:
   ```
   database/school_management.sql
   database/liberian_grades.sql
   database/insert_elementary_subjects.sql (optional)
   database/insert_junior_high_subjects.sql (optional)
   ```

---

### Step 3: Upload Files

#### 3.1 Using FTP/SFTP (FileZilla)
1. Connect to your server:
   - Host: `ftp.yourdomain.com`
   - Username: Your FTP username
   - Password: Your FTP password
   - Port: 21 (FTP) or 22 (SFTP)

2. Navigate to public directory:
   - Usually: `/public_html/` or `/www/` or `/htdocs/`

3. Upload all files:
   - Upload the entire `SchoolManagement` folder
   - Or upload contents directly to root

#### 3.2 Using cPanel File Manager
1. Log into cPanel
2. Go to **File Manager**
3. Navigate to `public_html`
4. Click **Upload**
5. Upload your zip file
6. Right-click and **Extract**

---

### Step 4: Configure Application

#### 4.1 Update Database Configuration
Edit `config/database.php`:

```php
<?php
class Database {
    private $host = "localhost";           // Usually localhost
    private $db_name = "your_db_name";     // Your database name
    private $username = "your_db_user";    // Your database username
    private $password = "your_db_password"; // Your database password
    private $conn;
    
    // ... rest of the code
}
```

#### 4.2 Update Application Configuration
Edit `config/config.php`:

```php
<?php
// Application Settings
define('APP_NAME', 'Your School Name');
define('APP_URL', 'https://yourdomain.com'); // Your actual domain
define('APP_VERSION', '1.0.0');

// Database Configuration (loaded from database.php)
// ...
```

#### 4.3 Set Correct Permissions
```bash
# Set folder permissions
chmod 755 uploads/
chmod 755 uploads/students/
chmod 755 uploads/teachers/
chmod 755 uploads/parents/

# Make uploads writable
chmod 777 uploads/
chmod 777 uploads/students/
chmod 777 uploads/teachers/
chmod 777 uploads/parents/
```

---

### Step 5: Security Configuration

#### 5.1 Secure Sensitive Files
Create `.htaccess` in root directory:

```apache
# Prevent directory listing
Options -Indexes

# Protect config files
<FilesMatch "^(config|database)\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protect database files
<FilesMatch "\.(sql|db)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Enable HTTPS redirect
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

#### 5.2 Secure Database Folder
Create `.htaccess` in `database/` folder:

```apache
Order deny,allow
Deny from all
```

#### 5.3 Update PHP Settings (if needed)
Create `php.ini` or `.user.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

---

### Step 6: SSL Certificate Setup

#### 6.1 Free SSL (Let's Encrypt)
1. Log into cPanel
2. Go to **SSL/TLS Status**
3. Click **Run AutoSSL**
4. Wait for certificate installation

#### 6.2 Force HTTPS
Add to `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

### Step 7: Create Default Admin Account

#### 7.1 Access Database
1. Go to phpMyAdmin
2. Select your database
3. Run this SQL:

```sql
-- Create admin user
INSERT INTO users (username, password, email, role, status) 
VALUES ('admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe6/6.MvJ6VkW3VfXzCqKHqXjLPfNlXKu', 
        'admin@yourschool.com', 'admin', 'active');
```

**Default Login:**
- Username: `admin`
- Password: `admin123`

**⚠️ IMPORTANT: Change this password immediately after first login!**

---

### Step 8: Test Your Deployment

#### 8.1 Access Your Application
Visit: `https://yourdomain.com`

#### 8.2 Test Checklist
- [ ] Homepage loads correctly
- [ ] Login page accessible
- [ ] Can log in with admin credentials
- [ ] Dashboard displays properly
- [ ] All menu items work
- [ ] Can add/edit/delete records
- [ ] File uploads work
- [ ] Reports generate correctly
- [ ] Grade sheets display properly
- [ ] Attendance marking works
- [ ] No PHP errors displayed

#### 8.3 Check Error Logs
- cPanel: **Errors** section
- Check: `/error_log` file
- PHP errors: Check server logs

---

## 🔧 Post-Deployment Configuration

### 1. Change Default Passwords
```sql
-- Update admin password
UPDATE users 
SET password = '$2y$10$NEW_HASHED_PASSWORD' 
WHERE username = 'admin';
```

### 2. Configure Email Settings
Edit `config/config.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### 3. Set Up Backups

#### Automated Database Backup
Create cron job in cPanel:
```bash
# Daily backup at 2 AM
0 2 * * * mysqldump -u username -ppassword database_name > /backups/db_$(date +\%Y\%m\%d).sql
```

#### File Backup
- Use cPanel backup feature
- Schedule weekly full backups
- Store backups off-site

### 4. Configure Cron Jobs (Optional)

For automated tasks:
```bash
# Send daily attendance reminders at 8 AM
0 8 * * * php /path/to/your/app/cron/attendance-reminder.php

# Generate monthly reports on 1st of each month
0 0 1 * * php /path/to/your/app/cron/monthly-reports.php
```

---

## 🌐 Domain Configuration

### Option 1: Root Domain
Application accessible at: `https://yourdomain.com`

Upload files to: `/public_html/`

### Option 2: Subdomain
Application accessible at: `https://school.yourdomain.com`

1. Create subdomain in cPanel
2. Upload files to subdomain directory
3. Update `APP_URL` in config

### Option 3: Subdirectory
Application accessible at: `https://yourdomain.com/school/`

1. Upload to: `/public_html/school/`
2. Update `APP_URL` to include `/school`

---

## 📱 Mobile Optimization

### Enable Mobile Access
The application is already responsive. Ensure:
- [ ] Viewport meta tag is present
- [ ] Touch-friendly buttons
- [ ] Readable font sizes
- [ ] Fast loading on mobile networks

---

## 🔒 Security Best Practices

### 1. Regular Updates
- [ ] Keep PHP updated
- [ ] Update MySQL regularly
- [ ] Monitor security advisories

### 2. Strong Passwords
- [ ] Enforce strong password policy
- [ ] Change default passwords
- [ ] Use password manager

### 3. Access Control
- [ ] Limit admin access
- [ ] Use role-based permissions
- [ ] Monitor user activity

### 4. Data Protection
- [ ] Regular backups
- [ ] Encrypt sensitive data
- [ ] Secure file uploads

### 5. Monitoring
- [ ] Enable error logging
- [ ] Monitor access logs
- [ ] Set up alerts for suspicious activity

---

## 🐛 Troubleshooting

### Issue: White Screen (WSOD)
**Solution:**
1. Enable error display:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
2. Check error logs
3. Verify database connection

### Issue: Database Connection Failed
**Solution:**
1. Verify credentials in `config/database.php`
2. Check if database exists
3. Ensure user has privileges
4. Test connection from phpMyAdmin

### Issue: File Upload Fails
**Solution:**
1. Check folder permissions (777)
2. Verify PHP upload limits
3. Check disk space
4. Review error logs

### Issue: 404 Errors
**Solution:**
1. Check `.htaccess` file
2. Verify mod_rewrite is enabled
3. Check file paths in config
4. Ensure all files uploaded

### Issue: Session Errors
**Solution:**
1. Check session save path
2. Verify folder permissions
3. Clear browser cache
4. Check PHP session settings

---

## 📊 Performance Optimization

### 1. Enable Caching
```php
// Add to .htaccess
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 2. Enable Compression
```apache
# Add to .htaccess
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>
```

### 3. Optimize Database
```sql
-- Run periodically
OPTIMIZE TABLE users;
OPTIMIZE TABLE students;
OPTIMIZE TABLE grades;
OPTIMIZE TABLE attendance;
```

### 4. CDN for Assets (Optional)
- Use CDN for Bootstrap, jQuery
- Reduces server load
- Faster loading times

---

## 📞 Support & Maintenance

### Regular Maintenance Tasks

**Daily:**
- [ ] Check error logs
- [ ] Monitor system performance
- [ ] Verify backups completed

**Weekly:**
- [ ] Review user activity
- [ ] Check disk space
- [ ] Test critical functions

**Monthly:**
- [ ] Full system backup
- [ ] Security audit
- [ ] Performance review
- [ ] Update documentation

**Quarterly:**
- [ ] Software updates
- [ ] Database optimization
- [ ] User training
- [ ] Feature review

---

## 🎯 Deployment Checklist Summary

### Pre-Launch
- [ ] Database created and imported
- [ ] Files uploaded completely
- [ ] Configuration files updated
- [ ] Permissions set correctly
- [ ] SSL certificate installed
- [ ] Admin account created
- [ ] All features tested

### Launch
- [ ] Application accessible
- [ ] No errors displayed
- [ ] All pages load correctly
- [ ] Login system works
- [ ] File uploads functional
- [ ] Reports generate properly

### Post-Launch
- [ ] Default passwords changed
- [ ] Backups configured
- [ ] Monitoring enabled
- [ ] Documentation updated
- [ ] Users notified
- [ ] Training scheduled

---

## 📧 Contact Information

**For Technical Support:**
- Check error logs first
- Review this deployment guide
- Contact your hosting provider
- Consult PHP/MySQL documentation

**For Application Issues:**
- Review troubleshooting section
- Check system requirements
- Verify configuration settings
- Test in different browsers

---

## ✅ Deployment Complete!

Your SchoolManagement System is now deployed and ready to use!

**Next Steps:**
1. Log in as admin
2. Change default password
3. Add school information
4. Create classes and subjects
5. Add teachers and students
6. Configure system settings
7. Train users
8. Start using the system!

---

**Deployment Date:** _______________  
**Deployed By:** _______________  
**Domain:** _______________  
**Version:** 1.0.0

**🎉 Congratulations on your successful deployment!**
