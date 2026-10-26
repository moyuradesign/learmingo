CREATE TABLE lm_audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  subject_type VARCHAR(40) NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  context_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY audit_created_id (created_at, id),
  KEY audit_actor_created (actor_id, created_at),
  KEY audit_subject (subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
