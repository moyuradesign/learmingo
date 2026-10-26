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
