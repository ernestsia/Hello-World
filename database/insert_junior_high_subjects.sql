-- Insert Junior High Division Subjects (Grades 7-9)
-- Run this in phpMyAdmin or through the installer

-- Insert Junior High Subjects
INSERT INTO subjects (subject_name, subject_code, description) VALUES
('Holy Bible', 'BIBLE-JH', 'Bible Studies for Junior High Division'),
('English', 'ENG-JH', 'English Language and Literature for Junior High'),
('Vocabulary', 'VOCAB-JH', 'Vocabulary Development'),
('French', 'FRE-JH', 'French Language'),
('Literature', 'LIT-JH', 'Literature Studies'),
('Mathematics', 'MATH-JH', 'Mathematics for Junior High'),
('Geography', 'GEO-JH', 'Geography and World Studies'),
('History', 'HIST-JH', 'History Studies'),
('Civics', 'CIV-JH', 'Civics and Government'),
('General Science', 'GSCI-JH', 'General Science for Junior High'),
('Computer Science', 'CS-JH', 'Computer Science and Technology'),
('Writing', 'WRIT-JH', 'Writing and Composition'),
('Physical Education', 'PE-JH', 'Physical Education and Sports')
ON DUPLICATE KEY UPDATE 
    subject_name = VALUES(subject_name),
    description = VALUES(description);
