# 🚀 Quick Deployment Checklist

## Before Deployment

### 1. Hosting Requirements
- [ ] PHP 7.4 or higher
- [ ] MySQL 5.7 or higher
- [ ] At least 500MB disk space
- [ ] SSL certificate available
- [ ] FTP/SFTP access

### 2. Prepare Files
- [ ] Create backup of local application
- [ ] Remove development files (.git, node_modules)
- [ ] Zip application files
- [ ] Test zip file integrity

### 3. Database Preparation
- [ ] Export local database
- [ ] Review SQL files for errors
- [ ] Prepare database credentials

---

## During Deployment

### 4. Server Setup
- [ ] Create database on server
- [ ] Create database user
- [ ] Grant all privileges
- [ ] Note down credentials

### 5. Upload Files
- [ ] Connect via FTP/SFTP
- [ ] Upload all files to public_html
- [ ] Verify all files uploaded
- [ ] Check file structure intact

### 6. Import Database
- [ ] Access phpMyAdmin
- [ ] Select database
- [ ] Import school_management.sql
- [ ] Import liberian_grades.sql
- [ ] Verify tables created

### 7. Configure Application
- [ ] Update config/database.php with server credentials
- [ ] Update config/config.php with domain URL
- [ ] Set APP_NAME to your school name
- [ ] Save changes

### 8. Set Permissions
- [ ] uploads/ folder: 777
- [ ] uploads/students/: 777
- [ ] uploads/teachers/: 777
- [ ] uploads/parents/: 777

### 9. Security Setup
- [ ] Install SSL certificate
- [ ] Force HTTPS redirect
- [ ] Protect config files
- [ ] Secure database folder

---

## After Deployment

### 10. Testing
- [ ] Visit your domain
- [ ] Login page loads
- [ ] Login with admin/admin123
- [ ] Dashboard displays correctly
- [ ] Test navigation menu
- [ ] Test adding a student
- [ ] Test adding a teacher
- [ ] Test marking attendance
- [ ] Test entering grades
- [ ] Test generating reports
- [ ] Test file uploads
- [ ] Check on mobile device

### 11. Security
- [ ] Change admin password immediately
- [ ] Create new admin accounts
- [ ] Delete default admin (optional)
- [ ] Review user permissions
- [ ] Enable error logging
- [ ] Disable error display

### 12. Configuration
- [ ] Add school logo
- [ ] Configure email settings
- [ ] Set up backup schedule
- [ ] Configure cron jobs (if needed)
- [ ] Set timezone

### 13. Data Setup
- [ ] Add school information
- [ ] Create academic years
- [ ] Add classes and sections
- [ ] Add subjects
- [ ] Install elementary subjects (if needed)
- [ ] Install junior high subjects (if needed)
- [ ] Add teachers
- [ ] Add students
- [ ] Assign teachers to classes
- [ ] Assign subjects to classes

### 14. User Training
- [ ] Train administrators
- [ ] Train teachers
- [ ] Provide user manuals
- [ ] Schedule support sessions

### 15. Monitoring
- [ ] Set up monitoring alerts
- [ ] Check error logs daily
- [ ] Monitor disk space
- [ ] Monitor database size
- [ ] Review access logs

---

## Quick Reference

### Default Login
- **Username:** admin
- **Password:** admin123
- **⚠️ CHANGE IMMEDIATELY AFTER FIRST LOGIN**

### Important Files
- Database config: `config/database.php`
- App config: `config/config.php`
- Security: `.htaccess`

### Important Folders
- Uploads: `uploads/` (needs 777 permissions)
- Database: `database/` (protect with .htaccess)
- Config: `config/` (protect with .htaccess)

### Support Resources
- Deployment Guide: `DEPLOYMENT_GUIDE.md`
- README: `README.md`
- Error logs: Check cPanel or `/error_log`

---

## Emergency Contacts

**Hosting Provider:** _______________  
**Support Email:** _______________  
**Support Phone:** _______________  

**Database Info:**
- Host: _______________
- Database: _______________
- Username: _______________
- Password: _______________ (keep secure!)

**FTP Info:**
- Host: _______________
- Username: _______________
- Password: _______________ (keep secure!)

---

## Deployment Status

**Date:** _______________  
**Deployed By:** _______________  
**Domain:** _______________  
**Status:** ⬜ In Progress  ⬜ Complete  ⬜ Issues

**Notes:**
_______________________________________________
_______________________________________________
_______________________________________________

---

## ✅ Final Verification

Before going live, verify:
- [ ] All checklist items completed
- [ ] No errors on any page
- [ ] All features tested
- [ ] Backups configured
- [ ] SSL working
- [ ] Mobile responsive
- [ ] Admin password changed
- [ ] Documentation updated

**🎉 Ready to Go Live!**
