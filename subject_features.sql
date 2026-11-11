-- Additive tables for subject-private messaging in althea_database.
-- Populate subject_enrollments only from verified school enrollment records.
-- No assignments, enrollments, or existing messages are fabricated or migrated.
CREATE TABLE IF NOT EXISTS subject_enrollments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    section VARCHAR(80) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(40) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY enrollment_context (student_id, subject_id, section, academic_year, semester),
    KEY enrollment_subject (subject_id),
    CONSTRAINT kns_enrollment_student FOREIGN KEY (student_id) REFERENCES user (id),
    CONSTRAINT kns_enrollment_subject FOREIGN KEY (subject_id) REFERENCES subjects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS subject_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    sender_id INT NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    subject_module VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY conversation_messages (enrollment_id, id),
    KEY subject_message_sender (sender_id),
    CONSTRAINT kns_message_enrollment FOREIGN KEY (enrollment_id) REFERENCES subject_enrollments (id),
    CONSTRAINT kns_message_sender FOREIGN KEY (sender_id) REFERENCES user (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS subject_message_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    storage_name CHAR(68) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    original_name VARCHAR(180) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    attachment_kind ENUM('photo', 'file') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY attachment_storage_name (storage_name),
    KEY message_attachments (message_id),
    CONSTRAINT kns_attachment_message FOREIGN KEY (message_id) REFERENCES subject_messages (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;