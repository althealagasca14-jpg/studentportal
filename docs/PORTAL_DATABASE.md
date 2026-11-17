# Database-backed portal management

This phase connects the existing portal pages to MariaDB. It does not install a
framework or dependency, recreate the database, or import the former admin mock
people, subjects, conversations, or report values.

## Schema and backups

Apply the existing 001 and 002 migrations before 003. On MariaDB 10.4 or newer:

    php database/migrate.php --apply

The runner creates a full SQL backup in the private database-backups directory
beside the existing private attachment directory before changing the real
database. A failed backup stops the migration. DDL is not transactional; migration
003 is repeatable so an interrupted application can be resumed.

New structures cover departments, courses, professor permissions, subject
sections, manual grades, notification preferences/history, admin–professor
messages and private attachments, account tokens, encrypted mail queue, rate
limits, and contact/admission requests. User/subject profile fields, enrollment
course, and classwork deadlines are additive.

Existing columns and credentials are preserved by migration. Sections are
backfilled only from existing enrollment contexts and explicit professor
ownership; ambiguous ownership stays unassigned. Existing names, email addresses,
faculty IDs, departments, courses, and subject codes are never guessed.

The legacy messages and inquiries tables are retained. They are not imported into
admin–professor conversations because they do not establish the required account
roles and ownership.

## First setup

1. Sign in with an existing admin account.
2. In Settings, save real admin profile details and create the school's
   departments and courses.
3. Register professors with their real faculty ID and institutional email.
   Activation-email mode creates a pending account. Alternatively, uncheck that
   option and supply an initial password of 12–72 bytes.
4. Configure each professor's class viewing, classwork, grading, and
   admin-messaging permissions. Existing professors without a permission row
   retain class viewing/classwork/messaging access; grading requires an explicit
   grant.
5. Create subjects/sections with the verified professor, academic year, semester,
   and capacity. Existing subjects can be completed using Manage.
6. Confirm student enrollments and, when known, the verified course in Subjects &
   sections. Capacity is checked in a transaction.

Student registration creates a student login with a password hash. It does not
create a verified enrollment or grant access to someone else's class records.
Existing usernames continue to work; login also accepts registered email,
faculty ID, and student number.

Successful login upgrades a legacy password to a hash and clears its plaintext
column. Password reset/change invalidates sessions that had the old password
version. Suspended accounts are rejected by authenticated handlers.

## Connected screens

- Admin Overview and directories load actual database records. Profile details
  missing from old records are shown as unspecified or left blank.
- Professor registration/edit, department/course creation, subject/section
  management, verified enrollment, admin profile/preferences, and request review
  persist through authenticated, CSRF-protected handlers.
- Admin–professor chat is separate from student subject chat. Both participants
  can send, read, and download privately stored attachments. The professor
  workspace has an Admin messages tab. Only the intended admin/professor can
  download those files. The latest 100 messages are displayed per conversation.
- Existing student–professor messages, uploads, classwork imports, and answer
  submissions keep their existing routes.
- Classwork supports an optional deadline. Professors with grading permission
  can save a score and feedback for their assigned classwork. Grades are manual;
  no automatic grading or timed-exam feature is introduced.
- Student dashboard totals and completion use published classwork in active,
  verified enrollments. The weekly chart counts recorded deadlines; work without
  a deadline is not assigned a fabricated date.
- Notification preferences and in-app notification history are stored in the
  database. Account creation and verified enrollment produce notifications.
- Public Contact/Admission requests are stored for review in Admin Settings.

## Reports

Academic-year/semester/department filters select actual subject/section coverage.
Message activity has optional explicit From/To dates. With blank dates, it is
labeled as activity across all dates; the application does not guess the school's
semester start/end dates. Date ranges are limited to two years.

The reply rate is the share of incoming professor messages followed by a later
admin message in the same conversation. No incoming messages produces an
unavailable rate rather than a fabricated percentage.

The chart counts recorded subject/section creation dates. Unknown legacy subject
creation dates are excluded. Report export streams actual aggregate CSV data;
it does not store a generated file on the server.

## Activation and recovery email

Account activation links expire after 48 hours; password reset links after one
hour. Tokens are random, stored hashed, single-use, and never returned by public
recovery requests. Public recovery uses a generic response to avoid revealing
whether an account exists.

Email bodies are encrypted with a key stored outside the public web directory,
beside private attachment storage. Keep that key private and include it in secure
backups if queued mail must survive a restore.

This checkout's school SMTP transport has not been configured. Queueing an email
does not mean it has been sent. To deliver queued email:

- Configure PHP's SMTP/mail transport for the school's authorized mail service.
- Set KNS_MAIL_FROM to the school's sender email address.
- Set KNS_PORTAL_URL to the correct trusted portal base URL (default:
  http://localhost/studentportal).
- Run explicitly or schedule through the school's approved process:

      php database/send_mail.php --send

The worker requires KNS_MAIL_FROM, processes at most 20 items per run, and records
transport failures without marking them sent. Delivery is not started or scheduled automatically. No external emails are sent during tests or migration.

For a newly invited professor, the admin is shown the confidential activation
link so it can be shared using the school's approved process while mail delivery
is being configured. Public password recovery depends on the registered email
and configured delivery; it never exposes reset links.

## Verification

    php tests/portal_management_test.php
    php tests/subject_features_test.php

Checks run against isolated kns_portal_test_* / kns_subject_test_* databases and
verify the real portal tables are unchanged. They cover account creation,
activation/reset replay prevention, role/CSRF checks, private attachments,
capacity, section isolation, deadlines, grades, report export, and persistence.
Empty test schemas may remain for inspection; no real portal tables are dropped.
The --keep option on the portal test retains isolated fixtures for browser
verification; never use it to seed the real database.

Browser checks also cover actual form saves/reloads, professor chat attachments,
responsive layouts at 320/390/768/1024/1440 pixels, keyboard focus, and Escape.
