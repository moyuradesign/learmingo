CREATE TABLE lm_course_revision_drafts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id BIGINT UNSIGNED NOT NULL,
 base_version_id BIGINT UNSIGNED NOT NULL,
 owner_id BIGINT UNSIGNED NOT NULL,
 state ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
 snapshot_json LONGTEXT NOT NULL,
 snapshot_sha256 CHAR(64) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at TIMESTAMP NULL DEFAULT NULL,
 KEY revision_course_state (course_id,state),
 CONSTRAINT revision_draft_course FOREIGN KEY (course_id) REFERENCES lm_courses(id) ON DELETE RESTRICT,
 CONSTRAINT revision_draft_base FOREIGN KEY (base_version_id) REFERENCES lm_course_versions(id) ON DELETE RESTRICT,
 CONSTRAINT revision_draft_owner FOREIGN KEY (owner_id) REFERENCES lm_users(id) ON DELETE RESTRICT,
 CONSTRAINT revision_draft_reviewer FOREIGN KEY (reviewed_by) REFERENCES lm_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
