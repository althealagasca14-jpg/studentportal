Current database-backed behavior (accounts, deadlines, grades, notifications, and reports) is documented in [Portal database setup](PORTAL_DATABASE.md). Historical setup notes below may predate migration 003.

# Classwork and private question imports

This checkout had no professor posting, question builder, quiz records, or student
answering flow. These are now small, subject-scoped PHP pages. No framework or
package dependency was added. Existing private messaging and its uploads are
separate and unchanged.

## Setup

1. Back up the database using the school's normal process.
2. Apply database/migrations/001_subject_features.sql if not already applied, then apply database/migrations/002_classwork.sql
   to the same database used by app/helpers/subject_support.php (normally althea_database).
   The migration only creates two classwork tables and an explicit ownership
   mapping table. It does not alter existing tables or populate accounts/enrollments.
3. An authorized school administrator must provision genuine user records with
   usertype = 'professor' using the existing account process, and insert verified
   subject_id/professor_id pairs into subject_professors. Do not infer these
   assignments from the subjects.instructor display name. Removing the mapping
   revokes posting access and availability of that professor's classwork.
4. Populate subject_enrollments through the school's verified enrollment process.
   Professors select an existing active section/year/semester context. Students
   need an active matching enrollment to see and submit published work.
5. Sign in using a professor account. Login routes to professorhome.php,
   which lists only verified classes in explicitly assigned subjects. Create
   classwork and draft links open the existing professor_classwork.php builder.

PHP needs mysqli, mbstring and fileinfo; DOCX additionally uses the existing zip
and DOM extensions. No source file is copied to the public tree or private
attachment storage: PHP's temporary upload is read and cleaned up by PHP when
the import request ends. The existing 5 MiB application limit applies, with a
1 MiB extracted-text/question-data limit, 200 questions and 26 choices per question.
Server upload_max_filesize and post_max_size may impose lower limits.

## Supported sources

- DOCX: body paragraphs, text in tables, and simple direct decimal/letter lists.
  The existing Office validation rejects encrypted/macro/malformed packages,
  unsafe archive paths, excessive entries, and excessive expanded size.
- TXT: UTF-8, optionally with BOM, or BOM-marked UTF-16.
- CSV: the same text encodings; comma, semicolon, or tab delimiters, one question
  per row. Unique headers: question (also question_text or prompt), A through Z
  (also choice_a / option_a), answer (also correct_answer), points, type.
  Only question is required. Standard quoted multiline fields are supported.
  Multiple-choice answer keys must identify a choice label or exactly one choice
  text. Numeric choice keys are not inferred as zero/one-based indices.
- RTF: plain body text from standard Windows-1252 RTF with common Unicode escapes.
  Unsupported code pages, surrogate escapes and binary objects fail explicitly;
  export those sources to UTF-8 TXT or DOCX.

PDF, legacy DOC, scans and image-only questions are not supported. The project has
no PDF text parser and this feature adds no PDF dependency or OCR.

Text question boundaries include 1., 1), and Question 1:. Choice labels include
A), A., and corresponding letters. Answer: and Correct answer: lines are explicit
keys; a separate Answer key section can contain 1. B entries for unique question
numbers. Missing, conflicting, or unmatched keys remain unset. Unnumbered lines
ending in ? are treated as possible prompts and marked for review. Other
continuation lines are preserved with a boundary warning.

Questions default to one point; text Points:/Marks: lines and CSV points can supply explicit values. Professors
can edit all question data, add/remove choices or questions, and change types
before saving a draft or publishing. Question-level warnings remain on saved
drafts until the professor marks them reviewed. Imported warnings and the
extracted-text preview are review aids, not student resources. The review checkbox is reset by
edits; the server also requires review confirmation on publish.

## Limitations and privacy

Complex layouts, inherited/custom Word list styles, multicolumn reading order,
equations, images, text boxes, headers, footnotes, tracked changes, embedded
objects and nonstandard export schemas need manual comparison with the original.
DOCX/RTF imports display notices about excluded visual/non-body content. Unknown
CSV columns and unassigned lines are called out and remain in the extracted-text
preview. Ambiguous content is marked, not presented as a verified complete quiz.
No answer is inferred from subject knowledge or choice order.

Published question definitions cannot be edited, preventing existing answers
from referring to different questions or choices. Make a new classwork for a
revision. Each enrollment can submit once. Server-side transactions recheck
active enrollment, subject/term matching, publication, and professor ownership.
A unique constraint prevents duplicate submissions. Failed bounded responses
are retained in the session for retry. Students receive only question prompts,
choices, points, and their own responses; private source files and answer keys
are never embedded or offered as downloads.

The professor can view the latest 100 submissions for their own classwork.
Answers are stored for manual review; this feature does not implement automated
grading, gradebook integration, timed exams, resource downloads, messaging,
account administration, or roster management.

No tests were added or run, as requested. Syntax and diff checks are separate
from behavioral verification.
