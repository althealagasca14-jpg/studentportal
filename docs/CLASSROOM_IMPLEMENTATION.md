# Connected Student and Professor classroom features

The existing local XAMPP database is `althea_database` at `127.0.0.1:3306`, verified against `C:/xampp/mysql/data`. No separate test database, remote database, production connection, seed import, or old migration runner was used.

## Database changes and backup

A fresh full SQL backup outside the web folder and repository was verified before implementation. It contained all 23 original tables, data inserts, a completion marker, and matching per-table row counts. The original backup remains unchanged.

Only these new additive migrations were applied, in order:

- `004_class_codes.sql`: nullable unique class codes on existing sections. Existing sections retain a NULL code until their professor creates one.
- `005_class_communication.sql`: independent class updates and official group-message tables.
- `006_classwork_files.sql`: classwork submission format and file-only points; private classwork and submission attachment metadata.
- `007_question_signals.sql`: activity/topic question signals without message contents.

The dedicated `database/apply_classroom.php` runner checks the local server, full backup, 24-hour backup age, table and row coverage, additive SQL, and unexpected existing structures. It records migration checksums in `classroom_migrations`. DDL is not transactional: a partial failure stops for review, with no destructive recovery or automatic rerun.

Migrations 001–003 are unchanged and were never run. Do not use the historical `database/migrate.php --apply` command for this feature work.

For a future reviewed pending application, use an actual fresh full SQL backup outside the public web folder and repository:

```powershell
C:\xampp\php\php.exe database/apply_classroom.php --backup="C:\private-backups\full-backup.sql"
C:\xampp\php\php.exe database/apply_classroom.php --backup="C:\private-backups\full-backup.sql" --apply
```

The first command is a read-only preflight. Fully recorded unchanged migrations are skipped, including on an accidental repeat invocation.

## Account behavior

Student signup, password verification, recovery, existing online answers, question imports and grading remain available. Professor registration is a separate action/page. It assigns `professor` and `active` on the server, ignores submitted role/status values, stores only a password hash, and grants the new professor classwork/grading capabilities without manual approval. Faculty IDs accept 2–50 ASCII letters/numbers/dots/underscores/hyphens and must start with a letter or number. Database uniqueness is case-insensitive. Username and faculty ID use the same identifier; email is also unique. Registration also rejects collisions with legacy login identifiers in other account columns, so existing Student identifiers remain unambiguous.

Admin retirement was the final step after the feature checks passed. Admin login is no longer offered or accepted. `adminhome.php` and `admin_action.php` return HTTP 410 before database access. Records and shared helpers remain. Existing professor/admin message history and protected attachments remain as a read-only archive; new messages to retired Admin accounts are rejected. Existing account restrictions are respected.

## Connected screens

- `professor_register.php`: professor signup.
- `classes.php`: actual classes, professor subject/section creation, and student join-by-code.
- `classroom.php?class=ID`: actual subject updates and official group chat.
- Existing `subjectview.php`: online answers and private messages, with links back to class updates/group chat.
- Existing `professor_classwork.php`: imports, drafts, publishing, attachments, file/online submission format, professor submissions and grading.

Group chat uses normal submit/refresh; it does not use a background push service. The prototype remains isolated and unchanged; fictional prototype data is never copied into connected pages.

## Files and privacy

The existing upload policy is reused: up to three attachments, 5 MiB each; validated JPG/PNG/GIF/WebP photos and PDF/TXT/DOCX/XLSX/PPTX files. Question imports remain separate from attachments. Client previews use local object URLs and do not upload until form submission. Browser validation supplements server MIME/content checks.

New uploaded files are stored in a private `classwork-attachments` directory beside the existing private subject attachment directory, outside the web root. Downloads recheck the current account, class ownership or active enrollment. Submission files are visible only to the owning student and professor. Existing submissions are not overwritten or deleted.

## Repeated-question alerts

Students can optionally associate a private or group question with a published activity and a fixed topic: Instructions, Deadline, Submitting work, or Understanding the topic. Three distinct active enrolled students asking about the same activity/topic within 48 hours produce a professor-only dashboard alert. Repeat questions from one student do not inflate the count. The aggregate contains no private message body, sender name, or conversation transcript. Free-form private text is not analyzed or shared.

## Validation performed

- All changed PHP files passed syntax checks.
- The dedicated `tests/classroom_features_test.php --local-rollback-test` completed 65 transactional checks on the existing local database.
- Fixtures were rolled back and all table row counts matched their pre-test values. Auto-increment sequences can have gaps after transactional verification.
- Admin endpoints passed HTTP 410 checks before database access.
- The three changed JavaScript files compiled successfully. Static classes, classroom and professor-registration layouts passed checks at 1440, 768, 390 and 320 pixels. Local file-preview checks passed without network requests.
- Existing test runners were not run.
- No commit, push or deployment was performed.

The transactional checks cover registration roles/hashes/uniqueness, subject ownership, class-code joins, enrollment revocation/capacity, updates, online/file validation, answer-key privacy, submission uniqueness, file authorization, upload-path rejection and alert privacy/deduplication. Real browser multipart transfer, email delivery, and the manual journeys below still require review.

## Manual review checklist

1. Student: sign in, sign up, and use account recovery. Check existing classwork and private messages.
2. Professor: register with a new faculty ID, then log in. Try a duplicate ID (including different case). Confirm immediate access.
3. Create a subject and two sections. Share each code and join with Student accounts. Check wrong codes, full sections, and cross-class access.
4. Publish an update. Send and refresh the official group chat from two accounts. Verify private conversations stay separate.
5. Import questions and publish online classwork. Separately publish file-only and combined-format activities, with an attached document/photo.
6. Student: select photos/files and inspect previews; submit once. Professor: open the submitted files and save a grade. Check another student cannot open the download URL.
7. Three Students: tag questions to the same activity/topic. Confirm the professor sees an alert, with no private text exposed to classmates.
8. Confirm Admin login is absent and both retired Admin endpoints return 410. Existing database records, archives, and protected historical attachments remain.

## Feature files

- `app/helpers/registration_support.php`
- `professor_register.php`
- `portal_action.php`
- `login.php`
- `app/helpers/classroom_support.php`
- `classroom_action.php`
- `classes.php`
- `database/migrations/004_class_codes.sql`
- `studenthome.php`
- `app/views/partials/professor_shell_start.php`
- `database/migrations/005_class_communication.sql`
- `classroom.php`
- `assets/css/classroom.css`
- `database/migrations/006_classwork_files.sql`
- `app/classwork/classwork_files.php`
- `classwork_attachment.php`
- `app/classwork/classwork_support.php`
- `professor_classwork.php`
- `classwork_submit.php`
- `app/views/partials/classwork_student_panel.php`
- `assets/js/classwork-files.js`
- `assets/js/classwork-builder.js`
- `subjectview.php`
- `assets/css/classwork.css`
- `database/migrations/007_question_signals.sql`
- `app/helpers/subject_support.php`
- `professorhome.php`
- `database/apply_classroom.php`
- `tests/classroom_features_test.php`
- `assets/css/subject-page.css`
- `faculty_messages.php`
- `assets/css/login.css`
- `login_check.php`
- `adminhome.php`
- `admin_action.php`
- `assets/js/login.js`

This guide and the README update are also part of this implementation.
