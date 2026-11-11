# Subject messages and subject work

## Verified source inventory

The local `althea_database` originally contained only `user`, `subjects`,
`messages`, and `inquiries`. The repository has no professor login/posting
handler, enrollment relationship, section/academic-year/semester records,
assignment/assessment definitions, or submission-status records.

The dashboard lists all subjects; it is not evidence of enrollment. The user
confirmed that no separate enrollment or professor-work source exists.

The existing `messages` table is read by the admin inbox. Existing rows in
that table and `inquiries` are untouched. New subject messages use their own
tables so they do not become admin/student request threads. No ambiguous
historical rows are automatically reassigned.

## Additive database migration

Run `subject_features.sql` against `althea_database` once. It creates:

- `subject_enrollments`: verified student ID, subject ID, section, academic
  year, semester, and active flag. The user and subject IDs reference existing
  records. The complete enrollment context is unique.
- `subject_messages`: enrollment/conversation ID, actual sender ID, instructor
  name from the subject record, topic, message, and timestamp.
- `subject_message_attachments`: message ID, generated storage name, safe
  display name, validated MIME type, byte count, and photo/file kind.

All tables use InnoDB and foreign keys. There are no destructive alterations,
data replacements, fabricated enrollment rows, or example assignments.
Only authorized school processes should populate real enrollment records.
Revocation uses `is_active = 0`. Each enrollment ID represents one immutable
student/subject/section/year/semester context; use a new row for a new context.

With no verified active enrollment, the subject page shows the access notice
and disables its composer. A mismatched enrollment ID is denied. If a student
has several enrollments for one subject, the existing subject page lets them
select the specific section and term.

## Attachment validation and storage

The existing composer accepts text, attachments, or both. There may be up to
three attachments total, each between one byte and 5 MiB (5,242,880 bytes).
Photos: JPG/JPEG, PNG, GIF, WebP. Files: PDF, UTF-8 TXT, DOCX, XLSX, PPTX.
These limits and the allowlist are enforced in JavaScript and PHP.

PHP ignores browser-supplied MIME claims. It checks real uploaded-file status,
actual byte size, extension, `finfo` content type, and image headers. Office
files must be valid non-encrypted OOXML packages with the expected document
part and content-type declaration. Macro packages, excessive expanded archive
size/entry counts, and traversal entries are rejected.

By default files are stored in:
`C:\xampp\studentportal-private\subject-attachments`.
Set `KNS_SUBJECT_STORAGE_DIR` for another absolute private directory.
A path inside the project or document root is rejected before creation.
The web-server account needs read/write access to that private directory.

Stored names contain 32 random bytes encoded as hex plus `.bin`; original
names are metadata only. Message and attachment metadata are committed in
one transaction. The enrollment is locked/rechecked before writing, and
stored files are removed if the transaction fails.

`subject_attachment.php?id=...` verifies the current session's student role,
looks up that actual student in the database, and joins attachment -> message
-> active enrollment -> subject. The enrollment must belong to that student.
Other students receive the same 404 as an unknown attachment. There are no
public file URLs or filesystem paths in the page.

Validated photos may be served inline; all files and explicit photo downloads
use Content-Disposition attachment. Responses use no-store, nosniff, and a
restrictive content security policy. Generated filenames and resolved paths
are rechecked before streaming.

Subject-message POSTs use session CSRF tokens, prepared statements, explicit
enrollment ownership checks, and POST/Redirect/GET after success. Upload errors
preserve entered text and show a safe message; file selections must be made
again. Internal failures are logged without exposing database or storage paths.

## Missing professor-work integration

The activity panel replaces the old hard-coded module/assignment content.
Its All, Activities, Quizzes, Exams, Projects controls show available-item
counts and an explicit "not connected yet" state. Zero here is the number
of available items in this view, not a claim that a professor assigned no work.

Real integration still requires authoritative instructor-posted task records
with subject, section, academic year and semester; category, title,
instructions/description, and optional due date; and a professor ownership/
publication relationship. Submission-status rendering additionally requires
a student-to-task submission/status source. No grade/score table is treated
as an assignment source, and no new task records or posting workflow are
invented by this change.

The repository also lacks a professor-side messaging inbox/reply handler.
Messages are saved to the private subject conversation; the page does not
claim that an email, notification, or professor delivery receipt was sent.

## Scope and verification

Only `subjectview.php` changes an existing page. `subject_support.php`,
`subject_attachment.php`, `subject-page.css`, and `subject-page.js` implement
the scoped features. The login, dashboards, shared styles, navigation,
existing subject buttons, and Git configuration remain untouched.

The local PHP configuration permits 40 MiB uploads/posts, which accommodates
three 5 MiB attachments. Deployment elsewhere needs an upload_max_filesize
of at least 5 MiB and post_max_size above 15 MiB plus multipart/form overhead.
Connection defaults match the existing portal. `KNS_SUBJECT_DB_HOST`,
`KNS_SUBJECT_DB_USER`, `KNS_SUBJECT_DB_PASSWORD`, and `KNS_SUBJECT_DB_NAME`
can isolate integration tests; ordinary requests use `althea_database`.

## Automated checks

Run `php tests/subject_features_test.php` from this project after applying the
migration. It starts a loopback-only temporary PHP server and uses a new,
isolated `kns_subject_test_*` database. It never inserts fixture records into
`althea_database`, never drops tables, and checks production row counts.
Fixture rows, uploaded files, sessions, and the temporary server are cleaned
up. The empty test schema is retained to honor the additive-only database
requirement. Set `KNS_SUBJECT_TEST_DATABASE` to a previously created empty test
schema to reuse it; the runner validates its name, table set, and empty state.

This implementation passed 46 integration checks, including text-only and
attachment-only messages, exactly 5 MiB, maximum attachment count, forged MIME
and extension combinations, invalid Office packages, CSRF failures, cross-
student and cross-subject denial, section/year/semester isolation, revoked
enrollment, protected download headers, storage failure rollback, and public
storage rejection. Browser checks also covered preview thumbnails, filenames
and sizes, remove-before-send, client limits, filters, keyboard focus, and
conversation downloads at the existing desktop/tablet widths.

The local verification schema is `kns_subject_test_8fb20e3db97d`; all of its
fixture rows are removed after verification. The real portal's three new
tables remain empty until verified school enrollments are supplied.
