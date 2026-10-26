CREATE TABLE lm_question_formats (
 question_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 format ENUM('single','true_false','multi_select') NOT NULL DEFAULT 'single',
 answer_key VARCHAR(4) NOT NULL,
 explanation VARCHAR(1000) NOT NULL DEFAULT '',
 CONSTRAINT question_format_question FOREIGN KEY (question_id) REFERENCES lm_quiz_questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
