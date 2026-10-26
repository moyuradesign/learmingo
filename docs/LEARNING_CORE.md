# Learmingo learning core milestone

## Available workflows

- Instructors and administrators can create **draft** courses and author sections and lessons **only for courses they own**. All mutations enforce both CSRF and ownership on the server.
- A course must contain at least one lesson to be submitted for review.
- A pending course is frozen until an administrator approves it (published) or rejects it (returns to draft).
- Published curriculum is immutable in this milestone. This protects progress denominators and historical enrollments. Editing a published course requires a future versioned-curriculum feature.
- Only administrators can review submitted courses. Self-publication through course creation is disabled.
- Enrolled students may mark existing lessons as completed. Progress derives from recorded completions and total lessons inside a database transaction, and duplicate completion requests are idempotent. A student cannot mark a lesson in a course they are not enrolled in.
- Existing paid enrollment restrictions remain in force. Do **not** enable payments or premium access without an independently verified checkout and entitlements.

## Design boundaries

`app/Domain/CoursePolicy.php` isolates non-database validation and workflow rules. `app/Services/LearningCore.php` owns transactional database operations, validates ownership independently of controllers, and locks relevant rows during status changes and progress calculation. `routes/learning.php` is an HTTP adapter and never assumes that a submitted identifier belongs to the current user.

No schema migration is required: this milestone uses existing `lm_course_sections`, `lm_lessons`, `lm_lesson_progress`, `lm_enrollments`, and `lm_courses` tables. Fresh installs use the existing schema. Production requires controlled migrations if the actual live schema differs.

## Release gating

Run `php tests/learning_policy.php` and the standard source checks. In isolated staging, verify: cross-instructor IDOR rejection; pending/published edit denial; empty-course submission rejection; administrator review; student-specific lesson completion; repeated completion; and status/progress persistence. Repeat HTTP CSRF, login and rate-limit tests. Do not infer production acceptance from source CI.

## Deliberately deferred

Content revisions for live courses, section/lesson deletion, assessment engine, quizzes, certificates, media pipeline, verified payments, instructor applications, messaging, analytics, background jobs, and audit event persistence. These should be built as separate milestones with database migrations, permissions and focused tests.

## Milestone 2: operational infrastructure and draft content lifecycle

- Explicit versioned SQL migrations with checksum ledger (`lm_schema_migrations`). Run `php scripts/migrate.php --check` to view pending changes; application boot NEVER applies schema changes automatically. After independent SQL backup and staging verification, `php scripts/migrate.php --apply TARGET_DB I_HAVE_A_VERIFIED_DATABASE_BACKUP` requires an explicit named target and operator acknowledgement. The migration process uses a MySQL advisory lock, refuses checksum drift, and is forward-only. MySQL DDL autocommits: SQL schema rollback is NOT automatic; restore a verified backup when required.
- `20261026_000001_create_audit_events.sql` adds `lm_audit_events` with actor ID, action, subject and bounded nonsensitive context. The service writes audit events in the same InnoDB transaction as the associated business change; failed audit writes cause the business change to roll back.
- Instructor draft courses support section renaming, moving sections/lessons up or down, and permanent deletion of sections or lessons with explicit DELETE confirmation. Only the course owner may edit a draft; pending/published curricula are locked. Destructive actions are also blocked for drafts with *any historical enrollment*.
- Admin-only paginated activity view at `/admin/audit`, without exposing private context JSON to the browser. No audit log deletion endpoint.
- All database-backed CI scenarios use disposable `learning_ci`; never run them against production or the persistent aaPanel QA database.

## Deployment safety gate

A running deployment MUST apply the audit migration before enabling curriculum mutation routes. If `lm_audit_events` is absent, audited actions fail closed. Existing production `.env`, sessions, uploads and data must be preserved. Do not run schema modifications before a database backup and a clean read-only migration status check.

## Milestone 3: instructor onboarding, assessments and versioned learning

- `lm_instructor_applications`: student applications require a substantive teaching statement; an administrator may approve (atomically changing the database role to instructor) or reject. Resubmission is only available after rejection. Decisions are audited. A newly promoted instructor must sign in / let the session revalidate before using teaching routes.
- `lm_quizzes` and `lm_quiz_questions`: instructors create draft multiple-choice quizzes for courses they own. They may publish only when the course is published and questions exist. A published quiz is immutable (options and answer keys are never sent to student views).
- `lm_quiz_attempts` and `lm_quiz_answers`: server-side grading, student enrollment requirements, locked attempt counters, exact question coverage, no client-supplied scores, and a maximum attempt limit. Completed attempts cannot be edited.
- `lm_certificates`: a certificate is issued only to an enrolled learner with completed lessons and a passed published assessment. Credentials are random 256-bit values and verification is noindex/no-store. Records use learner/course names captured on issuance; a revoked credential will not verify.
- `lm_course_versions` and `lm_version_lessons`: immutable snapshots of approved curricula. The explicit administrator baseline tool snapshots a previously published course and pins existing enrollments to version 1, migrating their existing completed-lesson records.
- `lm_enrollment_versions` and `lm_version_lesson_progress`: newly enrolled students pin to the latest published snapshot, existing learners remain pinned to their version. The classroom uses snapshot-specific lessons and progress. Legacy completion requests on pinned enrollments are blocked.
- `lm_course_revision_drafts`: an instructor may propose a detached revision for a baseline-versioned published course, edit its lesson body/title/video, then submit. Administrator approval creates a new immutable version. The live legacy curriculum remains locked. Only new enrollments receive the new version; no mass migration of existing learners.

### Guarded rollout

This is a **forward-only migration series**, not an automatically deployed upgrade. Never apply schema changes to the production database before taking and verifying a database backup, reviewing schema drift, and testing the exact release on an isolated staging copy. The old aaPanel QA database has not been updated. Current frontend/browser acceptance, email notifications, cryptographic PDF signing, payment processing, freeform exams, rich question banks, section changes within detached revisions, and comprehensive attempt analytics are not claimed complete.
