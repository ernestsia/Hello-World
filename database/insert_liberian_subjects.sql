-- Insert Liberian Subjects
-- Run this in phpMyAdmin or through the installer

INSERT INTO subjects (subject_name, subject_code) VALUES
('HOLY BIBLE / RME', 'RME'),
('LANGUAGE', 'LANG'),
('ENGLISH', 'ENG'),
('VOCABULARY', 'VOCAB'),
('FRENCH', 'FRE'),
('LITERATURE', 'LIT'),
('MATHEMATICS', 'MATH'),
('ALGEBRA', 'ALG'),
('GEOMETRY', 'GEOM'),
('TRIGONOMETRY', 'TRIG'),
('SOCIAL STUDIES', 'SS'),
('GEOGRAPHY', 'GEO'),
('HISTORY', 'HIST'),
('ECONOMICS / CIVICS', 'ECON'),
('GEN. SCIENCE', 'GSCI'),
('BIOLOGY', 'BIO'),
('CHEMISTRY', 'CHEM'),
('PHYSICS', 'PHY'),
('COMPUTER SCIENCE', 'CS'),
('WRITING', 'WRIT'),
('P.E.', 'PE')
ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name);
