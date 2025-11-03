-- Insert Elementary Division Subjects (Grades 1-6)
-- Run this in phpMyAdmin or through the installer

-- Insert Elementary Subjects
INSERT INTO subjects (subject_name, subject_code, description) VALUES
('Bible', 'BIBLE', 'Bible Studies for Elementary Division'),
('Reading', 'READ', 'Reading and Comprehension'),
('Spelling/Dictation', 'SPELL', 'Spelling and Dictation Exercises'),
('Phonics', 'PHON', 'Phonics and Pronunciation'),
('English', 'ENG-ELEM', 'English Language for Elementary'),
('Arithmetic', 'ARITH', 'Basic Arithmetic and Mathematics'),
('Science', 'SCI-ELEM', 'General Science for Elementary'),
('Hygiene', 'HYG', 'Health and Hygiene'),
('Writing', 'WRIT-ELEM', 'Handwriting and Composition'),
('Coloring', 'COLOR', 'Art and Coloring'),
('Social Studies', 'SS-ELEM', 'Social Studies for Elementary'),
('Physical Education', 'PE-ELEM', 'Physical Education and Sports')
ON DUPLICATE KEY UPDATE 
    subject_name = VALUES(subject_name),
    description = VALUES(description);
