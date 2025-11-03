# SchoolManagement System

A comprehensive web-based SchoolManagement System built with PHP and MySQL without using any PHP framework. This system provides complete functionality for managing students, teachers, classes, attendance, grades, and announcements.

## Features

### 👥 User Management
- **Multi-role Authentication System**
  - Admin
  - Teacher
  - Student
- Secure login/logout functionality
- Password change capability
- User profile management
- Session management with timeout

### 👨‍🎓 Student Management
- Add, edit, view, and delete students
- Student profile with photo upload
- Roll number assignment
- Class assignment
- Parent/Guardian information
- Admission date tracking
- Search and filter functionality
- Pagination support

### 👨‍🏫 Teacher Management
- Add, edit, view, and delete teachers
- Teacher profile with photo upload
- Qualification and experience tracking
- Salary information
- Joining date tracking
- Search functionality

### 🏫 Class Management
- Create and manage classes
- Assign class teachers
- Room number and capacity management
- View students in each class
- Subject assignment to classes

### 📚 Subject Management
- Add, edit, and delete subjects
- Subject code and description
- Assign subjects to classes
- Track subject-teacher assignments

### 📅 Attendance Management
- Daily attendance marking
- Mark students as Present, Absent, Late, or Excused
- Date-wise attendance tracking
- Class-wise attendance view
- Attendance history

### 📊 Grades/Marks Management
- Create exams (Midterm, Final, Quiz, Assignment)
- Enter marks for students
- Calculate pass/fail status
- View grade statistics
- Pass percentage calculation
- Subject-wise exam management

### 📢 Announcements
- Create and manage announcements
- Target specific audiences (All, Students, Teachers, Parents)
- Set expiry dates for announcements
- View active announcements on dashboard

### 📈 Dashboard
- Statistics overview
  - Total students
  - Total teachers
  - Total classes
  - Today's attendance
- Quick action buttons
- Recent announcements
- Role-based dashboard views

## Technology Stack

- **Backend:** PHP 7.4+ (Pure PHP, No Framework)
- **Database:** MySQL 5.7+
- **Frontend:** HTML5, CSS3, JavaScript
- **CSS Framework:** Bootstrap 5.3
- **Icons:** Font Awesome 6.4
- **JavaScript Library:** jQuery 3.7

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/LAMP (for local development)
- Modern web browser (Chrome, Firefox, Safari, Edge)

## Installation Instructions

### Step 1: Setup Environment
1. Install XAMPP/WAMP/LAMP on your system
2. Start Apache and MySQL services

### Step 2: Database Setup
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database or use the SQL file:
   ```sql
   CREATE DATABASE school_management;
   ```
3. Import the database:
   - Navigate to `database/school_management.sql`
   - Import the SQL file into your database
   - Or run the SQL file directly in phpMyAdmin

### Step 3: Configure Application
1. Copy the project to your web server directory:
   - XAMPP: `C:\xampp\htdocs\SchoolManagement`
   - WAMP: `C:\wamp\www\SchoolManagement`
   - LAMP: `/var/www/html/SchoolManagement`

2. Update database configuration in `config/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'school_management');
   ```

3. Update the application URL in `config/config.php`:
   ```php
   define('APP_URL', 'http://localhost/School%20Management');
   ```

### Step 4: Set Permissions
Create the uploads directory and set proper permissions:
```bash
mkdir uploads
mkdir uploads/students
mkdir uploads/teachers
chmod 755 uploads
chmod 755 uploads/students
chmod 755 uploads/teachers
```

### Step 5: Access the Application
1. Open your web browser
2. Navigate to: `http://localhost/School%20Management`
3. Login with default credentials:
   - **Username:** admin
   - **Password:** admin123

## Default Login Credentials

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | admin123 |

**⚠️ Important:** Change the default password immediately after first login!

## Directory Structure

```
SchoolManagement/
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── script.js
├── config/
│   ├── config.php
│   └── database.php
├── database/
│   └── school_management.sql
├── includes/
│   ├── functions.php
│   ├── header.php
│   ├── footer.php
│   └── session.php
├── students/
│   ├── list.php
│   ├── add.php
│   ├── edit.php
│   ├── view.php
│   └── delete.php
├── teachers/
│   ├── list.php
│   ├── add.php
│   ├── edit.php
│   ├── view.php
│   └── delete.php
├── classes/
│   ├── list.php
│   ├── add.php
│   ├── edit.php
│   ├── view.php
│   └── delete.php
├── subjects/
│   ├── list.php
│   ├── add.php
│   ├── edit.php
│   └── delete.php
├── attendance/
│   └── index.php
├── grades/
│   ├── index.php
│   ├── add-exam.php
│   └── enter-marks.php
├── announcements/
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   └── delete.php
├── uploads/
│   ├── students/
│   └── teachers/
├── index.php
├── login.php
├── logout.php
├── profile.php
├── change-password.php
└── README.md
```

## Database Schema

### Main Tables
- **users** - User authentication and roles
- **students** - Student information
- **teachers** - Teacher information
- **classes** - Class/grade information
- **subjects** - Subject details
- **class_subjects** - Class-subject-teacher mapping
- **attendance** - Daily attendance records
- **exams** - Exam/test information
- **grades** - Student marks/grades
- **timetable** - Class schedules
- **fees** - Fee management
- **announcements** - School announcements

## Features by Role

### Admin
- Full access to all modules
- Add/Edit/Delete students, teachers, classes
- Manage subjects and assignments
- Mark attendance
- Enter and view grades
- Create announcements
- View all reports and statistics

### Teacher
- View students and classes
- Mark attendance (if assigned)
- Enter grades for assigned subjects
- View announcements
- Update own profile

### Student
- View own profile
- View attendance records
- View grades and results
- View announcements
- Change password

## Security Features

- Password hashing using bcrypt
- SQL injection prevention using prepared statements
- XSS protection with input sanitization
- Session management with timeout
- Role-based access control
- CSRF protection ready
- Secure file upload validation

## Usage Guide

### Adding a Student
1. Login as Admin
2. Navigate to Students → Add Student
3. Fill in all required information
4. Upload photo (optional)
5. Assign to a class
6. Click "Save Student"

### Marking Attendance
1. Navigate to Attendance
2. Select date and class
3. Mark each student as Present/Absent/Late/Excused
4. Click "Save Attendance"

### Creating an Exam
1. Navigate to Grades → Create Exam
2. Enter exam details
3. Select class and subject
4. Set total marks and passing marks
5. Save the exam

### Entering Grades
1. Navigate to Grades → Enter Marks
2. Select class and exam
3. Enter marks for each student
4. Add remarks if needed
5. Save grades

## Customization

### Changing Colors
Edit `assets/css/style.css` and modify the CSS variables:
```css
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
}
```

### Adding New Features
1. Create new PHP files in appropriate directories
2. Follow the existing code structure
3. Include necessary header and footer files
4. Use the Database class for database operations
5. Apply security functions from `includes/functions.php`

## Troubleshooting

### Database Connection Error
- Check database credentials in `config/config.php`
- Ensure MySQL service is running
- Verify database exists

### Login Issues
- Clear browser cache and cookies
- Check if default admin user exists in database
- Verify password hash in users table

### File Upload Issues
- Check uploads directory permissions
- Verify MAX_FILE_SIZE in config
- Ensure PHP upload settings are correct

### Session Timeout
- Adjust SESSION_TIMEOUT in `config/config.php`
- Check PHP session settings

## Browser Compatibility

- ✅ Google Chrome (Recommended)
- ✅ Mozilla Firefox
- ✅ Microsoft Edge
- ✅ Safari
- ✅ Opera

## Performance Optimization

- Enable PHP OPcache
- Use MySQL query caching
- Optimize images before upload
- Enable GZIP compression
- Use CDN for external libraries

## Future Enhancements

- [ ] Email notifications
- [ ] SMS integration
- [ ] Report card generation (PDF)
- [ ] Fee payment gateway integration
- [ ] Library management
- [ ] Transport management
- [ ] Hostel management
- [ ] Online exam system
- [ ] Parent portal
- [ ] Mobile app integration

## Support

For issues, questions, or contributions:
- Create an issue in the repository
- Contact the development team
- Check documentation

## License

This project is open-source and available for educational purposes.

## Credits

Developed by: Professional PHP Developer
Version: 1.0.0
Last Updated: October 2025

---

**Note:** This is a complete SchoolManagement system built with pure PHP and MySQL. No frameworks were used, making it easy to understand and customize for learning purposes.
