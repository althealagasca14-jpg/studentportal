# Professor workspace

Open professorhome.php after signing in as a professor. The dark green workspace
has a sidebar, a verified class selector, and Classwork, Student messages, and
Students tabs. It uses the existing professor subject mappings and active student
enrollments; there is no new schema or dependency.

A class is a subject, section, academic year, and semester. The selector contains
only contexts in subjects assigned to the verified professor. Counts, classwork
rows, student accounts, and conversations come from the database.

## Classwork

The Classwork tab shows the signed-in professor's drafts and published items for
the selected context. Category filters run locally on this authorized list.
Create classwork opens professor_classwork.php with the selected context.
Drafts open the existing question builder and private document importer.
Publishing still requires review and returns to the selected class dashboard.
Published rows open the existing professor submission view.

The builder uses the professor sidebar and theme while preserving its existing
CSRF validation, question fields, import limits, and draft/publication rules.
Old direct links to draft IDs continue to work. Opening professor_classwork.php
without a subject now takes the professor to the dashboard.

Due dates are not invented: the current schema has no due-date field, so rows
show their creation date. No sample classwork is automatically published.

## Students and private conversations

The Students tab lists verified active student accounts in the selected class
and supports searching those account IDs. The current user schema has no verified
full-name field. Each Message action opens that student's enrollment conversation.

Student messages reads the same subject_messages and subject_message_attachments
records used by the existing student subject page. Professors can send text
replies to these verified conversations. Every send validates CSRF, professor
role, explicit subject ownership, and the student's active class enrollment.
Ownership and enrollment are locked and rechecked in the save transaction.

Professor attachment viewing uses professor_attachment.php. It independently
verifies the current professor and the attachment's active enrollment/subject
ownership, then applies the existing private-storage and file-type checks.
No public file URL or import source download is added. Student photo/file uploads
and the original student download endpoint remain unchanged.

The latest 100 messages are displayed per selected conversation. There are no
unread badges or delivery receipts because those states are not stored.
Professor replies are text only; this adds no separate teacher upload workflow.

## Local demo

Use the accounts and draft described in [Sample professor account](DEMO_PROFESSOR.md).
The seeded draft remains editable and unpublished until the professor reviews it.
No dashboard setup step alters the existing demo or real school records.

No tests were added or run. PHP syntax, file references, and the final diff can be
checked independently without sending messages or publishing classwork.
