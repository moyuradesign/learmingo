# Milestone 3 acceptance workspace

The admin workspace is `/admin/readiness`. This branch preserves Learmingo's existing custom PHP application, autoloader, services, routes, authentication and view structure. The repository currently does not use Laravel.

## Implemented scope

| Area | Available functionality |
| --- | --- |
| Assessment design | Outcomes, assessment type, pass threshold, attempts, time limit and accessibility notes |
| Question banks | Outcome coverage, question types/answer-key policy, count, difficulty and randomization rules |
| Instructor review | Application reference, qualification and teaching summaries, conflict declaration and recommendation |
| Grading and moderation | Rubric, automatic/manual/hybrid grading method, moderator, findings, appeals and observed score |
| Certificates | Template reference, language, eligibility, verification/revocation rules, staging issuance count and acceptance outcome |
| Course revisions | Previous version, outcomes, lessons, assessment mapping, eligibility and migration impact snapshots |
| Release readiness | Six-area approval coverage and installation, permissions, backup and browser/device acceptance assertions |
| Common records | Course, assessment/reference, version, status, environment, grading/acceptance rule, reviewer, evidence URLs, defects, rollback notes and publication decision |
| Navigation | Search, course/version/reviewer/status/environment filters, sort, pagination, reload and JSON export |
| Templates | Built-in blank form per view; create, select, update and delete saved templates; configuration-only reuse |
| History | Actor, timestamp, transition, decision, revision, full content snapshots, comparison with current content and restore as a planned revision |
| Course comparison | Two saved revisions of the same course, including structured fields; all fields or changes only |

The workspace tracks acceptance work. It does not execute assessments, grade learner submissions, approve actual instructor accounts, issue actual certificates, or publish actual courses. Existing LMS workflows remain separate. Course and assessment references are entered by administrators; this workspace does not synchronize live LMS records. Qualification and application records should use references and summaries, not personal applicant documents.

## Workflow rules

1. Every new or copied record starts **planned**. Required identity fields are title, course and version. Templates carry design configuration only; scores, evidence, reviewer assignment, approval status and release checks are not inherited.
2. **In progress** permits design and evidence preparation.
3. **In review** requires an assigned reviewer, an acceptance/grading rule and the view's required structured fields. Design and ownership are locked. Review result fields, observed scores, evidence, defects and rollback notes remain editable.
4. **Verified** requires local or staging evidence URLs, rollback notes and defect notes exactly `none`. Design, review results, observed score and verification evidence are then locked. URLs are validated syntactically; their contents are not automatically fetched or verified.
5. **Approved** requires the acceptance-complete publication decision. A record that needs changes must return to planned. Returning to planned resets its decision to hold.

The editor offers the current state, the next state and return to planned. Server checks enforce the same sequence. Reviewer names are assignments recorded by an administrator, not authenticated second-person sign-off. The history actor is always the authenticated administrator's ID. QA has not been inferred from these metadata fields.

A release can be verified or approved only when all six preceding areas contain at least one approved record with an acceptance-complete decision for the exact same **course + version + environment**. All records in those groups must be approved. It must also have all four release acceptance assertions marked complete, its own evidence and rollback notes, and no open defects. The verified release captures the dependency IDs and revisions. Adding or changing a dependency returns affected verified/approved releases to planned and hold with an attributed history event, including changes that move the dependency to another scope.

Production publishing is always disabled in this workspace, even after approval. There is no deployment or course/certificate activation endpoint. Production-tagged records can be planned or reviewed, but cannot be verified. Acceptance completion remains a recorded decision, not permission for this tool to modify production.

## Snapshots and templates

Each new save preserves an immutable content snapshot within the record history. Existing records remain readable; legacy approvals missing newly required structured fields do not count toward release acceptance and must return to planned for completion and re-review; earlier revisions without captured content explicitly show that the snapshot is unavailable. On the first edit after this upgrade, the prior current content is preserved before the new snapshot is saved.

Restore uses the server-held snapshot and the current optimistic revision. It creates a new planned revision, preserves history, resets the decision to hold, clears score/evidence/defects/rollback fields and resets release acceptance assertions. It never restores historical approval or publishes a course. A copied record starts a separate history. Comparison of course versions uses separate course-revision records, while history comparisons show the saved content of an individual record against its current content.

Saved templates contain the view, structured design fields, grading rule and additional design notes. Template updates and deletion require the saved template revision. Template changes do not alter existing readiness records.

## Storage and access

- Routes are restricted to authenticated administrators; POST actions require the existing CSRF token.
- Records live in `storage/readiness/items.json`; reusable templates live in `storage/readiness/templates.json`. Both are outside `public/` and excluded from Git.
- Storage uses a separate file lock, restricted file permissions, checked writes and atomic file replacement. Unreadable or malformed storage returns an availability error instead of resetting records.
- Optimistic revision checks reject stale edits and restores. Reload records before retrying a conflict.
- Server-generated history is append-only through the application; server administrators with filesystem access can still alter the files. This is not a tamper-proof audit ledger.
- This storage adapter supports a single application server or shared filesystem with reliable `flock`. Multi-server deployments require a suitable database storage adapter before acceptance.
- Exports include current records, snapshots, history and templates. The two files are read separately, so export is an administrative report, not a cross-file transactional backup.
- Use a persistent writable storage directory and include it in backups. Never store credentials, learner answers or private applicant documents in notes or evidence URL parameters.

## Development review and outstanding acceptance

The completion pass used source review and JavaScript syntax / whitespace checks. No new QA, browser/device checks, staging installation, backup rehearsal or production deployment was performed, as requested. The existing workflow fixture was updated for the new required assessment fields; it was not executed during this pass. The completion commit skips automatic CI so that QA remains deferred.

Before acceptance, run the repository checks and exercise access/CSRF, all transitions and structured validations, saved template lifecycle, concurrent edits, evidence links, snapshot restoration, dependency invalidation, revision comparison, responsive/theme behavior, persistent storage permissions and backup/restore. Existing passing results from the initial draft do not certify the completion changes.

Rollback removes the new routes, navigation entry, services and assets by reverting the feature commits. Preserve both readiness JSON files and their backups for audit retention. This branch adds no database migrations and performs no production operations.
