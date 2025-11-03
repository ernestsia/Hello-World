# Liberian Grade Sheet System - Setup Instructions

## Overview
The Liberian Grade Sheet system has been implemented following the official Liberian grading format with:
- 6 assessment periods (1st, 2nd, 3rd, 4th, 5th, 6th)
- Two semesters with automatic average calculations
- Attendance tracking (Days Absent, Days Late)
- Remarks sections for each unit
- Official Liberian grading scale

## Database Setup

### Step 1: Run the SQL Script
Execute the following SQL file in your phpMyAdmin or MySQL client:
```
database/liberian_grades.sql
```

This will create three tables:
1. `liberian_grades` - Stores student grades for each subject and period
2. `student_attendance_summary` - Tracks attendance and remarks
3. `class_subjects` - Links subjects to classes

### Step 2: Assign Subjects to Classes
Before using the grade sheet, you need to assign subjects to classes:

1. Go to **Classes** → Select a class → **View Details**
2. Or create a new page to manage class-subject assignments

You can manually insert records into `class_subjects` table:
```sql
INSERT INTO class_subjects (class_id, subject_id, teacher_id) 
VALUES (1, 1, 1);
```

## Features

### 1. View Liberian Grade Sheet
- Navigate to **Grades** → **Liberian Grade Sheet**
- Select Class, Student, and Academic Year
- View complete grade sheet with all periods
- Print-friendly format

### 2. Edit Grades
- Teachers and Admins can edit grades
- Click "Edit Grades" button on the grade sheet
- Enter scores for all 6 periods
- Update attendance (Days Absent, Days Late)
- Automatic average calculations

### 3. Grading Scale
- 94 – 100: Excellent
- 88 – 93: Very Good
- 77 – 87: Good
- 70 – 76: Improvement Needed
- 69 – Below: Failure

## Grade Sheet Format

The system follows the official Liberian format with:

| SUBJECT | 1st | 2nd | 3rd | Ave. | 4th | 5th | 6th | Ave. | Final Ave. |
|---------|-----|-----|-----|------|-----|-----|-----|------|------------|
| Math    | 85  | 90  | 88  | 87.7 | 92  | 89  | 91  | 90.7 | 89.2       |

## Access Permissions

- **Students**: Can view their own grade sheets
- **Teachers**: Can view and edit grades for their classes
- **Admins**: Full access to all grade sheets

## Navigation

Access the Liberian Grade Sheet from:
1. Main menu: **Grades** → **Liberian Grade Sheet**
2. Direct URL: `/grades/liberian-grade-sheet.php`

## Troubleshooting

### No subjects showing?
- Ensure subjects are assigned to the class in `class_subjects` table
- Check that subjects exist in the `subjects` table

### Cannot edit grades?
- Verify you're logged in as Teacher or Admin
- Check database permissions

### Grades not saving?
- Verify all three tables were created successfully
- Check for foreign key constraints
- Ensure student_id and subject_id are valid

## Future Enhancements

Potential additions:
- Bulk grade entry for entire class
- Export to PDF/Excel
- Semester exam scores
- Conduct and participation ratings
- Parent access to view grades
- Grade history and trends

## Support

For issues or questions, check:
1. Database tables are created
2. Subjects are assigned to classes
3. User has appropriate role permissions
