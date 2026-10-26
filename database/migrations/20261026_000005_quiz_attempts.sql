CREATE TABLE lm_quiz_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 quiz_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL,
 attempt_no TINYINT UNSIGNED NOT NULL,
 points_earned INT UNSIGNED NOT NULL,
 points_possible INT UNSIGNED NOT NULL,
 score_percent DECIMAL(5,2) NOT NULL,
 passed TINYINT(1) NOT NULL DEFAULT 0,
 submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY quiz_attempt_number (quiz_id,student_id,attempt_no),
 KEY quiz_attempt_student (student_id,submitted_at),
 CONSTRAINT attempt_quiz FOREIGN KEY (quiz_id) REFERENCES lm_quizzes(id) ON DELETE RESTRICT,
 CONSTRAINT attempt_student FOREIGN KEY (student_id) REFERENCES lm_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
