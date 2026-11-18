-- 007: store only structured question signals, never private message contents.
CREATE TABLE class_question_signals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 classwork_id BIGINT UNSIGNED NOT NULL,
 enrollment_id BIGINT UNSIGNED NOT NULL,
 topic ENUM('instructions','deadline','submission','concept') NOT NULL,
 asked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY question_student_topic(classwork_id,enrollment_id,topic),
 KEY question_recent(classwork_id,topic,asked_at),
 CONSTRAINT question_work FOREIGN KEY(classwork_id) REFERENCES subject_classwork(id),
 CONSTRAINT question_enrollment FOREIGN KEY(enrollment_id) REFERENCES subject_enrollments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
