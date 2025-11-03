-- Liberian Grade Sheet Tables

-- Create liberian_grades table
CREATE TABLE IF NOT EXISTS liberian_grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    first_period DECIMAL(5,2) DEFAULT NULL,
    second_period DECIMAL(5,2) DEFAULT NULL,
    third_period DECIMAL(5,2) DEFAULT NULL,
    first_sem_exam DECIMAL(5,2) DEFAULT NULL,
    first_sem_average DECIMAL(5,2) DEFAULT NULL,
    fourth_period DECIMAL(5,2) DEFAULT NULL,
    fifth_period DECIMAL(5,2) DEFAULT NULL,
    sixth_period DECIMAL(5,2) DEFAULT NULL,
    second_sem_exam DECIMAL(5,2) DEFAULT NULL,
    second_sem_average DECIMAL(5,2) DEFAULT NULL,
    final_average DECIMAL(5,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject_year (student_id, subject_id, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create student_attendance_summary table
CREATE TABLE IF NOT EXISTS student_attendance_summary (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    days_absent INT DEFAULT 0,
    days_late INT DEFAULT 0,
    first_unit_remark TEXT,
    second_unit_remark TEXT,
    third_unit_remark TEXT,
    fourth_unit_remark TEXT,
    fifth_unit_remark TEXT,
    sixth_unit_remark TEXT,
    attitude_remark TEXT,
    conduct_remark TEXT,
    participation_remark TEXT,
    general_appearance_remark TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_year (student_id, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create class_subjects table if not exists
CREATE TABLE IF NOT EXISTS class_subjects (
    class_subject_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL,
    UNIQUE KEY unique_class_subject (class_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
