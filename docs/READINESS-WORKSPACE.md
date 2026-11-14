# Milestone 3 acceptance workspace

Route: `/admin/readiness`. Accessible only to authenticated administrators; every mutation uses the existing CSRF gate. The current repository is custom PHP, not Laravel. This addition preserves its autoloader, routes, services, views, authentication and public asset conventions.

Seven views cover assessment design, question banks, instructor applications, grading/moderation, certificate templates/issuance, course revisions and release readiness. These are acceptance tracking records, not an implementation or execution of those underlying LMS workflows. Records start empty; no production readiness is assumed. Reusable templates populate structured checklist text for each view. All required metadata is editable, records can be searched and filtered by status, environment and course, and course revision snapshots can be compared side by side.

States: planned → in progress → in review → verified → approved. Review requires a named reviewer and a grading/acceptance rule. Verification requires local or staging evidence URLs, rollback notes and defect notes exactly `none`. Approval requires an acceptance-complete decision. Any reviewed content change requires returning to planned. This is administrator-attested acceptance, not independent reviewer identity verification; history records the authenticated actor separately from the assigned reviewer. Production records cannot reach verification. Even approved records cannot publish courses, issue certificates or deploy software; there is no activation switch or execution endpoint in this workspace.

Storage is `storage/readiness/items.json`, outside the document root, with a separate flock lock, atomic replacement, restrictive file permissions and optimistic revision checks. Retain this directory in persistent server storage and backups. It is appropriate for one server/shared filesystem with reliable flock support; multi-node installations need a database repository before acceptance. Do not store learner answers, credentials or personal applicant data here. Course/version values and evidence are administrator-entered references, not live synchronized LMS data. Evidence URL validity is checked, but content is not fetched or automatically verified. Record history preserves actor, time, transition, revision and publication decision; course revision content is compared between separate saved records.

## Acceptance before installation

- Run `php tests/readiness.php`, PHP lint and existing project checks.
- Install this branch on isolated staging with an existing admin; do not deploy to production.
- Confirm guest/student/instructor requests cannot read or mutate `/admin/readiness`, and bad CSRF is rejected.
- Create each template, refresh to verify persistence, test search/filter combinations, and compare two revisions of the same course.
- Verify ordered transitions, rejected missing evidence/open defects, stale-tab conflicts and return-to-planned editing.
- Verify keyboard/dialog behavior, mobile layout and both appearance modes on a browser/device.
- Confirm backup/restore of the readiness directory and that no LMS course/certificate state changes.

Rollback: revert the source commit to remove routes, navigation and assets. Preserve the readiness directory for the audit record. No database migrations or production operations are included.
