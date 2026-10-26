CREATE TABLE lm_quiz_questions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 quiz_id BIGINT UNSIGNED NOT NULL,
 prompt VARCHAR(1000) NOT NULL,
 option_a VARCHAR(500) NOT NULL,
 option_b VARCHAR(500) NOT NULL,
 option_c VARCHAR(500) NOT NULL,
 option_d VARCHAR(500) NOT NULL,
 correct_option ENUM('a','b','c','d') NOT NULL,
 points SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 KEY quiz_question_order (quiz_id,sort_order,id),
 CONSTRAINT question_quiz FOREIGN KEY (quiz_id) REFERENCES lm_quizzes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
