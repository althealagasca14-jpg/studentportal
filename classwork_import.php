<?php
session_start();
require_once __DIR__ . '/app/classwork/classwork_import_parser.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        throw new InvalidArgumentException('Use the question importer in Create Classwork.');
    }
    if (($_SESSION['usertype'] ?? '') !== 'professor') {
        http_response_code(403);
        throw new InvalidArgumentException('Log in as a professor before importing questions.');
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > KNS_SUBJECT_MAX_POST_BYTES) {
        http_response_code(413);
        throw new InvalidArgumentException('The upload is too large. Each source file must be no larger than 5 MiB.');
    }
    if (!kns_subject_csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        throw new InvalidArgumentException('The form expired. Reload Create Classwork before importing.');
    }
    $data = kns_subject_db();
    $professor = kns_classwork_professor($data);
    $subjectId = filter_var($_POST['subject_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$professor || !$subjectId || !kns_classwork_schema_ready($data)
        || !kns_portal_permission($data,(int)$professor['id'],'manage_classwork')
        || !kns_classwork_owned_subject($data, (int) $professor['id'], $subjectId)) {
        http_response_code(403);
        throw new InvalidArgumentException('This subject is not available to your professor account.');
    }
    if (count($_FILES) !== 1 || !is_array($_FILES['question_file'] ?? null)) {
        throw new InvalidArgumentException('Choose one question source file.');
    }
    $result = kns_import_source($_FILES['question_file']);
    if (!$result['questions']) {
        throw new InvalidArgumentException('No questions could be recognized. Nothing was imported. Use numbered questions with A)/B) choices, or CSV with a question header. You can continue editing manually.');
    }
    // Reuse saving limits, but permit incomplete questions to remain editable.
    kns_classwork_questions(json_encode($result['questions'], JSON_THROW_ON_ERROR), false);
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $error) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['error' => $error->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('[classwork-import] ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'The file could not be read. Try a simpler export, or continue editing questions manually.']);
}
// PHP removes the upload temporary file at the end of this request. It is never moved or linked.
