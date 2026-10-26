CREATE TABLE lm_certificates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL,
 passing_attempt_id BIGINT UNSIGNED NOT NULL,
 credential CHAR(64) NOT NULL,
 learner_name VARCHAR(150) NOT NULL,
 course_title VARCHAR(200) NOT NULL,
 issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 revoked_at TIMESTAMP NULL DEFAULT NULL,
 UNIQUE KEY certificate_course_student (course_id,student_id),
 UNIQUE KEY certificate_credential (credential),
 CONSTRAINT certificate_course FOREIGN KEY (course_id) REFERENCES lm_courses(id) ON DELETE RESTRICT,
 CONSTRAINT certificate_student FOREIGN KEY (student_id) REFERENCES lm_users(id) ON DELETE RESTRICT,
 CONSTRAINT certificate_attempt FOREIGN KEY (passing_attempt_id) REFERENCES lm_quiz_attempts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
