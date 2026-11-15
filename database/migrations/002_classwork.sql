-- Apply 001_subject_features.sql first. This adds only classwork and explicit ownership.
-- School administrators must assign real professor user IDs to their subjects.
-- No existing users, subjects, messages, or enrollments are altered.
CREATE TABLE IF NOT EXISTS subject_professors (
    subject_id INT NOT NULL,
    professor_id INT NOT NULL,
    PRIMARY KEY (subject_id, professor_id),
    KEY classwork_professor (professor_id),
    CONSTRAINT kns_classwork_owner_subject FOREIGN KEY (subject_id) REFERENCES subjects (id),
    CONSTRAINT kns_classwork_owner_user FOREIGN KEY (professor_id) REFERENCES user (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS subject_classwork (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id INT NOT NULL,
    professor_id INT NOT NULL,
    section VARCHAR(80) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(40) NOT NULL,
    category ENUM('activities', 'quizzes', 'exams', 'projects') NOT NULL,
    title VARCHAR(180) NOT NULL,
    instructions TEXT NOT NULL,
    questions_json LONGTEXT NOT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY classwork_context (subject_id, section, academic_year, semester, status),
    KEY classwork_author (professor_id),
    CONSTRAINT kns_classwork_subject FOREIGN KEY (subject_id) REFERENCES subjects (id),
    CONSTRAINT kns_classwork_professor FOREIGN KEY (professor_id) REFERENCES user (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS subject_classwork_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    classwork_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    answers_json LONGTEXT NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY classwork_enrollment_submission (classwork_id, enrollment_id),
    KEY classwork_submission_enrollment (enrollment_id),
    CONSTRAINT kns_classwork_submission_work FOREIGN KEY (classwork_id) REFERENCES subject_classwork (id),
    CONSTRAINT kns_classwork_submission_enrollment FOREIGN KEY (enrollment_id) REFERENCES subject_enrollments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
