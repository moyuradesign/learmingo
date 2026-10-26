CREATE TABLE lm_answer_selections (
 attempt_id BIGINT UNSIGNED NOT NULL,
 question_id BIGINT UNSIGNED NOT NULL,
 selection_key VARCHAR(4) NOT NULL,
 PRIMARY KEY (attempt_id,question_id),
 CONSTRAINT answer_selection_legacy FOREIGN KEY (attempt_id,question_id)
 REFERENCES lm_quiz_answers(attempt_id,question_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
