<?php
session_start();
require_once __DIR__ . '/app/helpers/professor_support.php';
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if (($_SESSION['usertype'] ?? '') !== 'professor') {
    header('Location: login.php');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !isset($_GET['id'])) {
    header('Location: professorhome.php');
    exit;
}
$error = '';
$subject = null;
$subjects = $contexts = $work = $responses = [];
$ready = false;
$workId = 0;
$responseId = 0;
$values = ['title' => '', 'instructions' => '', 'category' => 'quizzes', 'context' => '', 'questions_json' => '[]'];
try {
    $data = kns_subject_db();
    $professor = kns_classwork_professor($data);
    if (!$professor) {
        http_response_code(403);
        exit('Your professor account could not be verified.');
    }
    $ready = kns_classwork_schema_ready($data);
    if ($ready) {
        $query = $data->prepare('SELECT s.id, s.subject_name FROM subjects s JOIN subject_professors p
            ON p.subject_id = s.id WHERE p.professor_id = ? ORDER BY s.subject_name');
        $query->bind_param('i', $professor['id']);
        $query->execute();
        $subjects = $query->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $subjectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (isset($_GET['id'])) {
        $subject = $ready && $subjectId ? kns_classwork_owned_subject($data, (int) $professor['id'], $subjectId) : null;
        if (!$subject) {
            http_response_code(403);
            throw new InvalidArgumentException('This subject is not assigned to your professor account.');
        }
    }
    if ($subject) {
        $query = $data->prepare('SELECT DISTINCT section, academic_year, semester FROM subject_enrollments
            WHERE subject_id = ? AND is_active = 1 ORDER BY academic_year DESC, semester, section');
        $query->bind_param('i', $subjectId);
        $query->execute();
        $contexts = $query->get_result()->fetch_all(MYSQLI_ASSOC);
        if (isset($_GET['class'])) {
            $requestedClass = filter_var($_GET['class'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $query = $data->prepare('SELECT section, academic_year, semester FROM subject_enrollments
                WHERE id = ? AND subject_id = ? AND is_active = 1');
            $query->bind_param('ii', $requestedClass, $subjectId);
            $query->execute();
            $initialClass = $query->get_result()->fetch_assoc();
            if (!$requestedClass || !$initialClass) {
                http_response_code(403);
                throw new InvalidArgumentException('This class is not available in your assigned subject.');
            }
            $values['context'] = json_encode(array_values($initialClass), JSON_UNESCAPED_UNICODE);
        }
        $requestedWork = filter_var($_GET['work'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (isset($_GET['work'])) {
            $query = $data->prepare("SELECT * FROM subject_classwork WHERE id = ? AND subject_id = ? AND professor_id = ? AND status = 'draft'");
            $query->bind_param('iii', $requestedWork, $subjectId, $professor['id']);
            $query->execute();
            $draft = $query->get_result()->fetch_assoc();
            if (!$draft) throw new InvalidArgumentException('This draft is unavailable. Published questions cannot be changed after students can answer them.');
            $workId = (int) $draft['id'];
            foreach (['title', 'instructions', 'category', 'questions_json'] as $field) $values[$field] = $draft[$field];
            $values['context'] = json_encode([$draft['section'], $draft['academic_year'], $draft['semester']], JSON_UNESCAPED_UNICODE);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            foreach (array_keys($values) as $field) {
                if (is_string($_POST[$field] ?? null)) $values[$field] = $_POST[$field];
            }
            try {
                if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > KNS_SUBJECT_MAX_POST_BYTES) {
                    http_response_code(413);
                    throw new InvalidArgumentException('The classwork form is too large. Keep question data under 1 MiB.');
                }
                if (!kns_subject_csrf_valid($_POST['csrf_token'] ?? null)) {
                    http_response_code(403);
                    throw new InvalidArgumentException('Your form expired. Review the fields and try again.');
                }
                $postedWork = filter_var($_POST['work_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                if ($postedWork === false || $postedWork !== $workId) throw new InvalidArgumentException('The draft could not be verified.');
                $action = $_POST['action'] ?? '';
                if (!in_array($action, ['draft', 'publish'], true)) throw new InvalidArgumentException('Choose Save draft or Publish.');
                $publishing = $action === 'publish';
                if ($publishing && ($_POST['reviewed'] ?? '') !== '1') throw new InvalidArgumentException('Review all question text, choices, answer keys, and points, then confirm review before publishing.');
                $title = kns_classwork_text($values['title'], 180, 'Title');
                $instructions = kns_classwork_text($values['instructions'], 10000, 'Instructions', false);
                $category = $values['category'];
                if (!in_array($category, ['activities', 'quizzes', 'exams', 'projects'], true)) throw new InvalidArgumentException('Choose a valid classwork category.');
                $context = null;
                foreach ($contexts as $candidate) {
                    if (json_encode(array_values($candidate), JSON_UNESCAPED_UNICODE) === $values['context']) $context = $candidate;
                }
                if (!$context) throw new InvalidArgumentException('Choose a verified enrolled section and term.');
                $questions = kns_classwork_questions($values['questions_json'], $publishing);
                $questionJson = json_encode($questions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $status = $publishing ? 'published' : 'draft';
                $data->begin_transaction();
                try {
                    if (!kns_classwork_owned_subject($data, (int) $professor['id'], $subjectId, true)) throw new InvalidArgumentException('Your subject assignment is no longer available.');
                    $query = $data->prepare('SELECT id FROM subject_enrollments WHERE subject_id = ? AND section = ?
                        AND academic_year = ? AND semester = ? AND is_active = 1 ORDER BY id LIMIT 1 FOR UPDATE');
                    $query->bind_param('isss', $subjectId, $context['section'], $context['academic_year'], $context['semester']);
                    $query->execute();
                    $activeClass = $query->get_result()->fetch_assoc();
                    if (!$activeClass) throw new InvalidArgumentException('The selected section and term no longer has a verified active enrollment.');
                    $returnClassId = (int) $activeClass['id'];
                    if ($workId) {
                        $query = $data->prepare("UPDATE subject_classwork SET section = ?, academic_year = ?, semester = ?, category = ?,
                            title = ?, instructions = ?, questions_json = ?, status = ?, published_at = IF(? = 'published', NOW(), NULL)
                            WHERE id = ? AND subject_id = ? AND professor_id = ? AND status = 'draft'");
                        $query->bind_param('sssssssssiii', $context['section'], $context['academic_year'], $context['semester'], $category,
                            $title, $instructions, $questionJson, $status, $status, $workId, $subjectId, $professor['id']);
                        $query->execute();
                        // A no-op draft save may report zero affected rows; verify it is still this author's draft.
                        if (!$query->affected_rows) {
                            $check = $data->prepare("SELECT id FROM subject_classwork WHERE id = ? AND subject_id = ? AND professor_id = ? AND status = 'draft' FOR UPDATE");
                            $check->bind_param('iii', $workId, $subjectId, $professor['id']);
                            $check->execute();
                            if (!$check->get_result()->fetch_assoc()) throw new InvalidArgumentException('This draft has already been published or is unavailable.');
                        }
                    } else {
                        $query = $data->prepare("INSERT INTO subject_classwork
                            (subject_id, professor_id, section, academic_year, semester, category, title, instructions, questions_json, status, published_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = 'published', NOW(), NULL))");
                        $query->bind_param('iisssssssss', $subjectId, $professor['id'], $context['section'], $context['academic_year'],
                            $context['semester'], $category, $title, $instructions, $questionJson, $status, $status);
                        $query->execute();
                        $workId = (int) $data->insert_id;
                    }
                    $data->commit();
                } catch (Throwable $saveError) {
                    $data->rollback();
                    throw $saveError;
                }
                $_SESSION['classwork_saved'] = $publishing ? 'Classwork published. Enrolled students can answer it inside this portal.' : 'Draft saved. Students cannot see it.';
                $returnUrl = $publishing ? kns_professor_url('classwork', $returnClassId)
                    : 'professor_classwork.php?' . http_build_query(['id' => $subjectId, 'class' => $returnClassId, 'work' => $workId]);
                header('Location: ' . $returnUrl, true, 303);
                exit;
            } catch (InvalidArgumentException $saveError) {
                if (http_response_code() < 400) http_response_code(422);
                $error = $saveError->getMessage();
            } catch (Throwable $saveError) {
                error_log('[classwork-save] ' . $saveError->getMessage());
                http_response_code(503);
                $error = 'Classwork could not be saved. Your fields remain below; try again.';
            }
        }
        $query = $data->prepare('SELECT id, title, status, section, academic_year, semester FROM subject_classwork
            WHERE subject_id = ? AND professor_id = ? ORDER BY id DESC LIMIT 100');
        $query->bind_param('ii', $subjectId, $professor['id']);
        $query->execute();
        $work = $query->get_result()->fetch_all(MYSQLI_ASSOC);
        $responseId = filter_var($_GET['responses'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($responseId) {
            $query = $data->prepare('SELECT sub.answers_json, sub.submitted_at, u.username, w.title, w.questions_json
                FROM subject_classwork_submissions sub JOIN subject_classwork w ON w.id = sub.classwork_id
                JOIN subject_enrollments e ON e.id = sub.enrollment_id JOIN user u ON u.id = e.student_id
                WHERE w.id = ? AND w.subject_id = ? AND w.professor_id = ? ORDER BY sub.id DESC LIMIT 100');
            $query->bind_param('iii', $responseId, $subjectId, $professor['id']);
            $query->execute();
            $responses = $query->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        throw new InvalidArgumentException('Choose one of your assigned subjects first.');
    }
} catch (InvalidArgumentException $pageError) {
    $error = $pageError->getMessage();
} catch (Throwable $pageError) {
    error_log('[professor-classwork] ' . $pageError->getMessage());
    http_response_code(503);
    $error = 'Classwork is temporarily unavailable. Please try again later.';
}
$initialQuestions = json_decode($values['questions_json'], true);
if (!is_array($initialQuestions) || !array_is_list($initialQuestions)) $initialQuestions = [];
$flash = $_SESSION['classwork_saved'] ?? '';
unset($_SESSION['classwork_saved']);
$professorView = 'classwork';
$professorShowPicker = false;
$professorClasses = [];
$professorSelectedClass = null;
if ($ready && isset($data, $professor)) {
    try {
        $professorClasses = kns_professor_classes($data, (int) $professor['id']);
        foreach ($professorClasses as $candidate) {
            $candidateContext = json_encode([$candidate['section'], $candidate['academic_year'], $candidate['semester']], JSON_UNESCAPED_UNICODE);
            if ((int) $candidate['subject_id'] === (int) ($subject['id'] ?? 0)
                && ($values['context'] === '' || $values['context'] === $candidateContext)) {
                $professorSelectedClass = $candidate;
                if ($values['context'] === '') $values['context'] = $candidateContext;
                break;
            }
        }
    } catch (Throwable $contextError) {
        error_log('[professor-builder-context] ' . $contextError->getMessage());
    }
}
$professorPageTitle = $responseId ? 'Student submissions' : ($workId ? 'Edit classwork draft' : 'Create classwork');
$professorPageDescription = $responseId ? 'Review student responses in your professor workspace.' : 'Build or import questions, then review them before publishing.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= kns_subject_escape($professorPageTitle) ?> | Professor workspace</title>
    <link rel="stylesheet" href="assets/css/student-portal.css?v=1">
    <link rel="stylesheet" href="assets/css/classwork.css?v=1">
    <link rel="stylesheet" href="assets/css/professor-portal.css?v=1">
    <script src="assets/js/classwork-builder.js?v=1" defer></script>
    <script src="assets/js/professor-portal.js?v=1" defer></script>
</head>
<?php require __DIR__ . '/app/views/partials/professor_shell_start.php'; ?>
<div class="classwork-page">
    <a class="pf-builder-back" href="<?= kns_subject_escape(kns_professor_url('classwork', $professorClassId)) ?>"><?= kns_ui_icon('arrow') ?> Back to classwork</a>
    <?php if ($error): ?><p class="cw-notice cw-error" role="alert"><?= kns_subject_escape($error) ?></p><?php endif; ?>
    <?php if ($flash): ?><p class="cw-notice" role="status"><?= kns_subject_escape($flash) ?></p><?php endif; ?>
    <?php if (!$ready): ?>
        <p class="cw-notice">Classwork setup is pending. Ask the school administrator to apply the classwork migration and assign verified professor accounts to subjects.</p>
    <?php elseif (!$subject): ?>
        <h2>Your assigned subjects</h2>
        <?php if (!$subjects): ?><p class="cw-notice">No subjects have been assigned to your account. Contact the school office.</p><?php endif; ?>
        <ul><?php foreach ($subjects as $item): ?><li><a href="professor_classwork.php?id=<?= (int) $item['id'] ?>"><?= kns_subject_escape($item['subject_name']) ?> &rarr; Create classwork</a></li><?php endforeach; ?></ul>
    <?php else: ?>
        <h2><?= kns_subject_escape($subject['subject_name']) ?></h2>
        <?php if (!$responseId): ?>
        <?php if (!$contexts): ?><p class="cw-notice">No verified active student enrollments exist for this subject. Ask the school office to connect the section and term before creating classwork.</p><?php endif; ?>
        <form method="post" class="cw-form" id="classwork-form" data-import-url="classwork_import.php" data-max-file-bytes="<?= KNS_SUBJECT_MAX_FILE_BYTES ?>" data-max-questions="<?= KNS_CLASSWORK_MAX_QUESTIONS ?>">
            <fieldset <?= !$contexts ? 'disabled' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= kns_subject_escape(kns_subject_csrf_token()) ?>">
                <input type="hidden" name="subject_id" value="<?= (int) $subjectId ?>">
                <input type="hidden" name="work_id" value="<?= $workId ?>">
                <input type="hidden" name="questions_json" id="questions-json" value="<?= kns_subject_escape($values['questions_json']) ?>">
                <label>Title<input name="title" maxlength="180" required value="<?= kns_subject_escape($values['title']) ?>"></label>
                <div class="cw-row">
                    <label>Category<select name="category"><?php foreach (['activities' => 'Activity', 'quizzes' => 'Quiz', 'exams' => 'Exam', 'projects' => 'Project'] as $key => $label): ?><option value="<?= $key ?>" <?= $values['category'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
                    <label>Section and term<select name="context" required><option value="">Choose a verified section</option><?php foreach ($contexts as $context): $encoded = json_encode(array_values($context), JSON_UNESCAPED_UNICODE); ?><option value="<?= kns_subject_escape($encoded) ?>" <?= $encoded === $values['context'] ? 'selected' : '' ?>><?= kns_subject_escape(implode(' | ', $context)) ?></option><?php endforeach; ?></select></label>
                </div>
                <label>Instructions<textarea name="instructions" rows="3" maxlength="10000"><?= kns_subject_escape($values['instructions']) ?></textarea></label>
                <section class="cw-card" aria-labelledby="cw-import-title">
                    <h2 id="cw-import-title">Import questions from a file</h2>
                    <p>DOCX, TXT, CSV, or RTF &middot; maximum 5 MiB. Import adds questions to the builder. The source stays private and is discarded after reading.</p>
                    <p>PDF, scanned/image-only questions, and legacy DOC files cannot be read here. Export them to a supported text format.</p>
                    <label for="question-file">Choose or replace source file</label>
                    <input id="question-file" type="file" accept=".docx,.txt,.csv,.rtf" aria-describedby="cw-file-info">
                    <p id="cw-file-info" role="status">No file selected.</p>
                    <div class="cw-actions"><button type="button" id="cw-import" disabled>Import into builder</button><button type="button" id="cw-remove-file" hidden>Remove selected file</button></div>
                    <p id="cw-import-status" role="status" aria-live="polite"></p>
                    <p id="cw-import-error" class="cw-error" role="alert"></p>
                    <ul id="cw-import-notices"></ul>
                    <details id="cw-source-preview" hidden><summary>Extracted text: compare with your source and recover unrecognized content</summary><pre id="cw-source-text"></pre></details>
                    <details><summary>Supported layouts</summary><pre>1. Which value is correct?
A) First choice
B) Second choice
Answer: B

Question 2: Explain your reasoning.
Correct answer: An explicitly supplied sample answer.</pre><p>CSV: one question per row, with headers question, A, B, C, D, answer, points, type. Only question is required. Use multiple_choice or short_answer for type. Quote fields containing commas or newlines.</p></details>
                </section>
                <h2>Questions</h2>
                <p>Default: 1 point. Only explicit source answer keys are imported. Unset keys require manual grading.</p>
                <p id="cw-builder-status" role="status" aria-live="polite"></p>
                <div id="cw-question-list"></div>
                <button type="button" id="cw-add-question">Add question manually</button>
                <div class="cw-card">
                    <p>Review every imported question before publishing. Check the original document for missing content. Publishing makes these questions available to the selected section inside the Student Portal.</p>
                    <label class="cw-check"><input type="checkbox" name="reviewed" value="1" id="cw-reviewed"> I reviewed question text, types, choices, answer keys, and points, including any import warnings.</label>
                    <div class="cw-actions"><button type="submit" name="action" value="draft">Save draft</button><button type="submit" name="action" value="publish" id="cw-publish">Publish</button></div>
                    <p>Drafts remain private. Published questions are locked to preserve submitted answers.</p>
                </div>
            </fieldset>
        </form>
        <noscript><p class="cw-notice">Enable JavaScript to import files and edit questions. No source file has been imported.</p></noscript>
        <script type="application/json" id="cw-initial-questions"><?= json_encode($initialQuestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
        <?php endif; ?>
        <section class="cw-card">
            <h2>Your classwork in this subject</h2>
            <?php if (!$work): ?><p>No drafts or published classwork yet.</p><?php endif; ?>
            <ul><?php foreach ($work as $item): ?><li><strong><?= kns_subject_escape($item['title']) ?></strong> &middot; <?= kns_subject_escape($item['status'] . ' | ' . $item['section'] . ' | ' . $item['academic_year'] . ' | ' . $item['semester']) ?>
                <?php if ($item['status'] === 'draft'): ?><a href="?id=<?= (int) $subjectId ?>&amp;work=<?= (int) $item['id'] ?>">Edit draft</a><?php else: ?><a href="?id=<?= (int) $subjectId ?>&amp;responses=<?= (int) $item['id'] ?>#cw-responses">View submissions</a><?php endif; ?>
            </li><?php endforeach; ?></ul>
            <a href="professor_classwork.php?id=<?= (int) $subjectId ?>">Create another classwork</a>
        </section>
        <?php if (!empty($_GET['responses'])): ?>
        <section class="cw-card" id="cw-responses"><h2>Student submissions</h2><p>Latest 100 submissions. Answers are saved for professor review; no automatic grades are assigned.</p>
            <?php if (!$responses): ?><p>No submissions are available.</p><?php endif; ?>
            <?php foreach ($responses as $response): $answers = json_decode($response['answers_json'], true); $questions = json_decode($response['questions_json'], true); ?>
                <details><summary><?= kns_subject_escape($response['username'] . ' | ' . $response['title'] . ' | ' . $response['submitted_at']) ?></summary>
                    <ol><?php foreach ($questions as $index => $question): $answer = $answers[$index] ?? ''; $key = $question['answer']; if ($question['type'] === 'multiple_choice') { $answer = $question['choices'][(int) $answer] ?? ''; if ($key !== '') $key = $question['choices'][(int) $key] ?? ''; } ?>
                        <li><p class="cw-text"><?= kns_subject_escape($question['text']) ?></p><p class="cw-text">Response: <?= kns_subject_escape($answer) ?></p><p class="cw-text">Professor key: <?= $key !== '' ? kns_subject_escape($key) : 'Unset; review manually' ?></p></li>
                    <?php endforeach; ?></ol>
                </details>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/app/views/partials/professor_shell_end.php'; ?>
