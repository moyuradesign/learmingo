# Readiness task planning and defect tracking

This increment extends the existing `/admin/readiness` workspace. It does not modify live courses, assessments, instructor accounts or certificates.

## Work planning

Each record now supports a work owner, low/normal/high/urgent priority and an optional due date. The owner is an administrator-entered assignment label, separate from the reviewer; it does not grant permissions or send notifications. Dates are stored as calendar dates. Overdue and next-seven-day filters use the viewer's local calendar; approved records are excluded from those due filters.

A record may have up to 50 checklist tasks. Task definitions and ordering are part of the reviewed design. Completion can be updated during review; every defined task must be complete before verification. Empty checklists remain valid for existing records or work that does not need subtasks.

Work ownership, priority, date and checklist definitions are locked once review begins. Return the record to planned to change them. Checklist completion becomes immutable at verification until the record is reopened. These values are included in content snapshots and comparisons.

## Structured defects

Each record may track up to 30 defects with a stable reference, title, low/medium/high/critical severity, open/in-progress/resolved status, assignee, resolution notes and evidence URL. A resolved defect requires both resolution notes and an HTTP/HTTPS evidence URL without embedded credentials.

Existing reviewed defects cannot be deleted or have their title/severity changed without returning to planned. During review, administrators can add newly discovered defects, assign work and record resolutions. Verified defect records are locked until reopened. All tracked defects must be resolved before verification, regardless of severity. Existing additional defect notes remain available and must still be `none` at verification.

The same checklist and defect rules apply when counting approved records toward release readiness. Editing a dependency continues to invalidate affected release acceptance through the existing workflow.

## Board and register

- Switch between the paginated list and a five-column board grouped by workflow state.
- Select a single workflow area or all seven areas together.
- Filter by work owner, priority, overdue work, work due within seven days, open defects or unassigned work, alongside existing filters.
- Sort by due date or priority, as well as existing title/status/update sorting.
- The summary shows matching records, overdue work, open defects and unassigned work.
- Board columns show 12 records initially with a Show more control; all state changes use the existing guided review editor.
- Download the complete task register from `/admin/readiness/export-csv`. This authenticated, uncached export includes ownership, dates, task progress and open defect summaries. It exports all records, independent of screen filters. CSV cells with formula-like prefixes are escaped as text; UTF-8 BOM is included for spreadsheet compatibility.

Checklist tasks are included in saved templates with completion reset. Copying an item clears ownership, dates and defects and resets checklist completion. Restoring a snapshot resets tasks to pending and reopens the snapshot's defects without historical resolution evidence. The existing history and approval trail remain available.

## Validation status

Source review, JavaScript syntax and whitespace checks were performed. QA, PHP execution, browser/device validation, staging installation and production deployment remain deferred under the user's existing instruction. The increment uses `[skip ci]` to avoid automatically starting those checks. Do not treat this implementation as acceptance evidence.

No database migration is required; missing planning fields in existing records default to unassigned, normal priority, no due date, no tasks and no structured defects. The existing private storage, CSRF, authorization, optimistic locking and atomic-write behavior continue to apply. See `READINESS-WORKSPACE.md` for storage and release workflow constraints.
