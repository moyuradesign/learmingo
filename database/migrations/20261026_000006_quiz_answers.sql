CREATE TABLE lm_quiz_answers (
 attempt_id BIGINT UNSIGNED NOT NULL,
 question_id BIGINT UNSIGNED NOT NULL,
 selected_option ENUM('a','b','c','d') NOT NULL,
 points_awarded SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (attempt_id,question_id),
 CONSTRAINT answer_attempt FOREIGN KEY (attempt_id) REFERENCES lm_quiz_attempts(id) ON DELETE RESTRICT,
 CONSTRAINT answer_question FOREIGN KEY (question_id) REFERENCES lm_quiz_questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
