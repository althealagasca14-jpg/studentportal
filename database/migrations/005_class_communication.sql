-- 005: separate official class communication from private subject_messages.
CREATE TABLE class_updates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 section_id BIGINT UNSIGNED NOT NULL,
 author_id INT NOT NULL,
 title VARCHAR(180) NOT NULL,
 body TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY class_update_order(section_id,id),
 CONSTRAINT class_update_section FOREIGN KEY(section_id) REFERENCES subject_sections(id),
 CONSTRAINT class_update_author FOREIGN KEY(author_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE class_group_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 section_id BIGINT UNSIGNED NOT NULL,
 sender_id INT NOT NULL,
 message TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY class_group_order(section_id,id),
 CONSTRAINT class_group_section FOREIGN KEY(section_id) REFERENCES subject_sections(id),
 CONSTRAINT class_group_sender FOREIGN KEY(sender_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
