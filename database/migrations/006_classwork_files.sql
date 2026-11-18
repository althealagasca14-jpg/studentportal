-- 006: preserve all existing classwork and online answer records.
ALTER TABLE subject_classwork
 ADD COLUMN submission_mode ENUM('online','files','online_and_files') NOT NULL DEFAULT 'online',
 ADD COLUMN file_points DECIMAL(8,2) NOT NULL DEFAULT 100.00;
CREATE TABLE classwork_attachments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 classwork_id BIGINT UNSIGNED NOT NULL,
 storage_name CHAR(68) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 original_name VARCHAR(180) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 byte_size INT UNSIGNED NOT NULL,
 attachment_kind ENUM('photo','file') NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY classwork_storage(storage_name),
 KEY classwork_files(classwork_id),
 CONSTRAINT classwork_file_work FOREIGN KEY(classwork_id) REFERENCES subject_classwork(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE classwork_submission_files (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 submission_id BIGINT UNSIGNED NOT NULL,
 storage_name CHAR(68) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 original_name VARCHAR(180) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 byte_size INT UNSIGNED NOT NULL,
 attachment_kind ENUM('photo','file') NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY submission_storage(storage_name),
 KEY submission_files(submission_id),
 CONSTRAINT submission_file_response FOREIGN KEY(submission_id) REFERENCES subject_classwork_submissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
