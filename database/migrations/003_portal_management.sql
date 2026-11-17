-- MariaDB 10.4+; apply after 001 and 002. Additive, repeatable migration.
-- Existing accounts, passwords, subjects and messages are not replaced.
CREATE TABLE IF NOT EXISTS departments (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS courses (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, department_id INT NULL,
 code VARCHAR(30) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL,
 FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE user
 ADD COLUMN IF NOT EXISTS first_name VARCHAR(60) NULL,
 ADD COLUMN IF NOT EXISTS last_name VARCHAR(60) NULL,
 ADD COLUMN IF NOT EXISTS faculty_id VARCHAR(50) NULL,
 ADD COLUMN IF NOT EXISTS student_number VARCHAR(10) NULL,
 ADD COLUMN IF NOT EXISTS email VARCHAR(191) NULL,
 ADD COLUMN IF NOT EXISTS contact_number VARCHAR(30) NULL,
 ADD COLUMN IF NOT EXISTS department_id INT NULL REFERENCES departments(id),
 ADD COLUMN IF NOT EXISTS course_id INT NULL REFERENCES courses(id),
 ADD COLUMN IF NOT EXISTS account_status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
 ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL,
 ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS last_activity_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS created_at DATETIME NULL,
 ADD UNIQUE INDEX IF NOT EXISTS portal_username (username),
 ADD UNIQUE INDEX IF NOT EXISTS portal_faculty (faculty_id),
 ADD UNIQUE INDEX IF NOT EXISTS portal_student (student_number),
 ADD UNIQUE INDEX IF NOT EXISTS portal_email (email);
ALTER TABLE subjects
 ADD COLUMN IF NOT EXISTS subject_code VARCHAR(30) NULL,
 ADD COLUMN IF NOT EXISTS department_id INT NULL REFERENCES departments(id),
 ADD COLUMN IF NOT EXISTS created_at DATETIME NULL,
 ADD UNIQUE INDEX IF NOT EXISTS portal_subject_code (subject_code);
CREATE TABLE IF NOT EXISTS professor_permissions (
 professor_id INT NOT NULL PRIMARY KEY,
 view_classes TINYINT(1) NOT NULL DEFAULT 1,
 manage_classwork TINYINT(1) NOT NULL DEFAULT 1,
 record_grades TINYINT(1) NOT NULL DEFAULT 0,
 admin_messaging TINYINT(1) NOT NULL DEFAULT 1,
 FOREIGN KEY (professor_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS subject_sections (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 subject_id INT NOT NULL, professor_id INT NULL,
 section VARCHAR(80) NOT NULL, academic_year VARCHAR(20) NOT NULL,
 semester VARCHAR(40) NOT NULL, capacity SMALLINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY section_context (subject_id,section,academic_year,semester),
 FOREIGN KEY (subject_id) REFERENCES subjects(id),
 FOREIGN KEY (professor_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Derive only verified contexts already present; do not manufacture enrollments.
INSERT INTO subject_sections (subject_id,professor_id,section,academic_year,semester,created_at)
 SELECT e.subject_id, IF(COUNT(DISTINCT p.professor_id)=1,MIN(p.professor_id),NULL),
 e.section,e.academic_year,e.semester,MIN(e.created_at)
 FROM subject_enrollments e LEFT JOIN subject_professors p ON p.subject_id=e.subject_id
 GROUP BY e.subject_id,e.section,e.academic_year,e.semester
 ON DUPLICATE KEY UPDATE subject_id=VALUES(subject_id);
ALTER TABLE subject_enrollments ADD COLUMN IF NOT EXISTS course_id INT NULL REFERENCES courses(id);
ALTER TABLE user ADD FOREIGN KEY IF NOT EXISTS portal_user_department (department_id) REFERENCES departments(id),
 ADD FOREIGN KEY IF NOT EXISTS portal_user_course (course_id) REFERENCES courses(id);
ALTER TABLE subjects ADD FOREIGN KEY IF NOT EXISTS portal_subject_department (department_id) REFERENCES departments(id);
ALTER TABLE subject_enrollments ADD FOREIGN KEY IF NOT EXISTS portal_enrollment_course (course_id) REFERENCES courses(id);
ALTER TABLE subject_classwork ADD COLUMN IF NOT EXISTS due_at DATETIME NULL;
CREATE TABLE IF NOT EXISTS classwork_grades (
 submission_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 graded_by INT NOT NULL, score DECIMAL(10,2) NOT NULL,
 max_score DECIMAL(10,2) NOT NULL, feedback TEXT NOT NULL,
 graded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CHECK (score>=0 AND score<=max_score AND max_score>0),
 FOREIGN KEY (submission_id) REFERENCES subject_classwork_submissions(id),
 FOREIGN KEY (graded_by) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notification_preferences (
 user_id INT NOT NULL PRIMARY KEY,
 messages TINYINT(1) NOT NULL DEFAULT 1,
 invitations TINYINT(1) NOT NULL DEFAULT 1,
 assignments TINYINT(1) NOT NULL DEFAULT 1,
 FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
 category ENUM('messages','invitations','assignments','security') NOT NULL,
 title VARCHAR(160) NOT NULL, body VARCHAR(500) NOT NULL,
 read_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY user_notifications(user_id,read_at,id), FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS admin_professor_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 admin_id INT NOT NULL, professor_id INT NOT NULL, sender_id INT NOT NULL,
 message TEXT NOT NULL, read_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK (sender_id=admin_id OR sender_id=professor_id),
 KEY faculty_thread(admin_id,professor_id,id),
 KEY faculty_unread(admin_id,professor_id,read_at),
 FOREIGN KEY (admin_id) REFERENCES user(id),
 FOREIGN KEY (professor_id) REFERENCES user(id),
 FOREIGN KEY (sender_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS admin_message_attachments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 message_id BIGINT UNSIGNED NOT NULL,
 storage_name CHAR(68) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 original_name VARCHAR(180) NOT NULL, mime_type VARCHAR(100) NOT NULL,
 byte_size INT UNSIGNED NOT NULL, attachment_kind ENUM('photo','file') NOT NULL,
 FOREIGN KEY (message_id) REFERENCES admin_professor_messages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS account_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL, purpose ENUM('activation','reset') NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL, used_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY token_user(user_id,purpose,used_at), FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mail_outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NULL, recipient VARCHAR(191) NOT NULL, subject VARCHAR(180) NOT NULL,
 encrypted_body MEDIUMTEXT NOT NULL, attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 sent_at DATETIME NULL, last_error VARCHAR(250) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS auth_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 identifier_hash CHAR(64) NOT NULL, ip_hash CHAR(64) NOT NULL,
 action VARCHAR(20) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY auth_identifier(identifier_hash,action,created_at), KEY auth_ip(ip_hash,action,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS portal_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 category ENUM('contact','admission') NOT NULL, full_name VARCHAR(120) NOT NULL,
 email VARCHAR(191) NOT NULL, message TEXT NOT NULL,
 status ENUM('new','reviewed') NOT NULL DEFAULT 'new',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
