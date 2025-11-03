-- Migration: Add subject_id to attendance table for subject-specific attendance
-- Date: October 13, 2025
-- Description: Allows teachers to mark attendance per subject they teach

-- Step 1: Add subject_id column
ALTER TABLE attendance 
ADD COLUMN subject_id INT NULL AFTER class_id;

-- Step 2: Add foreign key constraint
ALTER TABLE attendance 
ADD CONSTRAINT fk_attendance_subject 
FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE;

-- Step 3: Drop old unique constraint
ALTER TABLE attendance 
DROP INDEX unique_attendance;

-- Step 4: Add new unique constraint (student_id, attendance_date, subject_id)
ALTER TABLE attendance 
ADD UNIQUE KEY unique_attendance (student_id, attendance_date, subject_id);

-- Note: Existing attendance records will have NULL subject_id
-- Admin can mark attendance without subject (for general attendance)
-- Teachers must select a subject when marking attendance
