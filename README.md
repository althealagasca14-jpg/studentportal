# Student Portal

A plain PHP and MySQL school portal with student subject pages, private subject
messages and attachments, and professor classwork with file-based question import.

## Project structure

```text
studentportal/
|-- app/
|   |-- classwork/              # Question parser, validation, and classwork helpers
|   |-- helpers/                # Shared PHP helpers and student UI icons
|   `-- views/partials/         # Included student classwork markup
|-- assets/
|   |-- css/                    # Stylesheets
|   |-- js/                     # Browser scripts
|   `-- images/                 # School branding and campus image
|-- database/migrations/        # Ordered, additive SQL migrations
|-- database/seeds/             # Optional local demo data
|-- docs/                       # Feature setup and limitations
|-- tests/                      # Existing integration checks
|-- index.html                  # Public landing page
|-- login.php                   # Login page
|-- login_check.php             # Authentication handler
|-- logout.php                  # Session logout
|-- adminhome.php               # Admin dashboard
|-- studenthome.php             # Student dashboard
|-- subjectview.php             # Subject classroom and private conversation
|-- subject_attachment.php      # Authenticated attachment downloads
|-- professorhome.php           # Professor classwork, messages, and class roster
|-- professor_classwork.php     # Professor drafts, imports, and publishing
|-- professor_attachment.php    # Authorized professor attachment viewing
|-- classwork_import.php        # Authenticated question-import endpoint
`-- classwork_submit.php        # Student answer-submission endpoint
```

Page and endpoint filenames stay at the project root so existing portal URLs,
form actions, redirects, and dashboard dialogs continue to work. PHP includes
use filesystem paths; browser assets use paths relative to these root pages.

## Local setup

1. Serve this folder through the existing XAMPP Apache installation, for example
   at http://localhost/studentportal/.
2. Use the existing althea_database database and its user and subjects tables.
   This repository's additive migrations do not recreate the original database.
3. For a new installation of these features, apply migrations in order:
   - database/migrations/001_subject_features.sql
   - database/migrations/002_classwork.sql
   - database/migrations/003_portal_management.sql (run php database/migrate.php --apply for a private backup first)
   Moving or renaming these files does not require rerunning migrations already
   applied to an existing installation.
4. Use PHP 8.1 or newer with mysqli, mbstring, fileinfo, zip, and DOM enabled.
5. Provision actual professor accounts, explicit subject assignments, and
   verified student enrollments through the school's authorized process.

Private message attachments remain outside the web directory, by default at
C:\xampp\studentportal-private\subject-attachments. The folder reorganization
does not move existing uploads. Import documents remain temporary and private.

The test runner remains in tests/. Only its helper and migration references
were updated for the new layout; its assertions and behavior are unchanged.

## Feature guides

- [Connected admin, accounts, reports, settings, and database setup](docs/PORTAL_DATABASE.md)

- [Classwork and question imports](docs/CLASSWORK_IMPORT.md)
- [Professor workspace](docs/PROFESSOR_WORKSPACE.md)
- [Sample professor account](docs/DEMO_PROFESSOR.md)
- [Subject messaging and private attachments](docs/SUBJECT_FEATURES.md)

Keep reusable backend code in app/, public assets in assets/, schema changes
in database/migrations/, and detailed feature documentation in docs/. New PHP
includes should resolve paths with __DIR__ rather than the current directory.
