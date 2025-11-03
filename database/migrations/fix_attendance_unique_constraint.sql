-- Fix Attendance Unique Constraint for Subject-Specific Attendance
-- Date: October 13, 2025
-- Issue: Multiple teachers marking attendance for different subjects overwrites previous records
-- Solution: Ensure unique constraint is on (student_id, attendance_date, subject_id)

-- Step 1: Check and drop ALL existing unique constraints on attendance table
-- This ensures we start clean
ALTER TABLE attendance DROP INDEX IF EXISTS unique_attendance;

-- Step 2: Add the correct unique constraint
-- This allows multiple attendance records per student per day (one per subject)
ALTER TABLE attendance 
ADD UNIQUE KEY unique_attendance (student_id, attendance_date, subject_id);

-- Verification Query (run this after to verify):
-- SHOW INDEXES FROM attendance WHERE Key_name = 'unique_attendance';
-- Expected: Columns should be (student_id, attendance_date, subject_id)

-- Test Query (verify multiple subjects work):
-- SELECT student_id, attendance_date, subject_id, COUNT(*) as count
-- FROM attendance 
-- GROUP BY student_id, attendance_date
-- HAVING count > 1;
-- Expected: Should show students with multiple subject attendance on same date
