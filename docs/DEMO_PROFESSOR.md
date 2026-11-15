# Sample professor account

This optional local demo creates two clearly named accounts, one fictional subject,
one professor ownership assignment, one demo enrollment, and one quiz draft.
Existing school accounts, subjects, messages, and classwork are left unchanged.

## Open the professor page

1. Start Apache and MySQL in XAMPP.
2. Sign out of any current portal session.
3. Open http://localhost/studentportal/login.php and choose Professor.
4. Enter account ID professor.demo and password DemoProf!2026.
5. The professor dashboard opens at http://localhost/studentportal/professorhome.php.
6. Select DEMO - Introduction to Computing / DEMO-A in the class selector.
7. In Classwork, open [DEMO] Computer Fundamentals Quiz to edit the sample draft.
   Student messages and Students use this same selected class.

The sample contains two multiple-choice questions and one short-answer question,
worth five points total. Review and edit the fields before confirming review and
choosing Publish. The demo is initially a draft; the setup script never publishes.

## Optional student preview

After publishing, sign out and log in as the demo student:

- Student account ID: student.demo
- Password: DemoStudent!2026

Open DEMO - Introduction to Computing to answer and submit the quiz inside the portal.
This student belongs only to the fictional DEMO-A / 2026-2027 / 1st Semester context.

## Recreate on another local setup

From the project root, after applying the migrations, run:

```powershell
php database/seeds/professor_demo.php
```

The CLI-only script runs its inserts in one transaction. Re-running reuses the
same matching demo records and preserves edited or published quiz content.
Changed account credentials, conflicting subject names, and deactivated demo
enrollments stop the setup instead of overwriting those records.

These credentials and records are for the local demo, not real school accounts.
