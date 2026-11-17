<?php
session_start();
require_once __DIR__ . '/app/classwork/classwork_support.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Submit answers from the subject classroom.');
}
if (($_SESSION['usertype'] ?? '') !== 'student') {
    http_response_code(403);
    exit('Log in as a student before submitting answers.');
}
$subjectId = filter_var($_POST['subject_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$enrollmentId = filter_var($_POST['enrollment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$workId = filter_var($_POST['classwork_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$subjectId || !$enrollmentId || !$workId) {
    http_response_code(400);
    exit('The classwork submission is invalid. Return to your subject classroom.');
}
try {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > KNS_SUBJECT_MAX_POST_BYTES) {
        http_response_code(413);
        throw new InvalidArgumentException('Your answers are too large. Keep each text answer under 10,000 characters and the total under 1 MiB.');
    }
    if (!kns_subject_csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        throw new InvalidArgumentException('Your form expired. Reload the subject classroom before submitting.');
    }
    $data = kns_subject_db();
    $student = kns_subject_student($data);
    if (!$student || !kns_classwork_schema_ready($data)) {
        http_response_code(403);
        throw new InvalidArgumentException('Your student account or classwork access could not be verified.');
    }
    $data->begin_transaction();
    try {
        $query = $data->prepare('SELECT id, subject_id, section, academic_year, semester FROM subject_enrollments
            WHERE id = ? AND student_id = ? AND subject_id = ? AND is_active = 1 FOR UPDATE');
        $query->bind_param('iii', $enrollmentId, $student['id'], $subjectId);
        $query->execute();
        $enrollment = $query->get_result()->fetch_assoc();
        if (!$enrollment) {
            http_response_code(403);
            throw new InvalidArgumentException('This enrollment is not available to your student account.');
        }
        $query = $data->prepare("SELECT w.questions_json FROM subject_classwork w
            JOIN subject_professors p ON p.subject_id = w.subject_id AND p.professor_id = w.professor_id
            JOIN user u ON u.id = p.professor_id AND u.usertype = 'professor' AND u.account_status='active' JOIN subject_sections ss ON ss.subject_id=w.subject_id AND ss.section=w.section AND ss.academic_year=w.academic_year AND ss.semester=w.semester AND ss.professor_id=w.professor_id
            WHERE w.id = ? AND w.subject_id = ? AND w.section = ? AND w.academic_year = ? AND w.semester = ?
            AND w.status = 'published' FOR UPDATE");
        $query->bind_param('iisss', $workId, $subjectId, $enrollment['section'], $enrollment['academic_year'], $enrollment['semester']);
        $query->execute();
        $work = $query->get_result()->fetch_assoc();
        if (!$work) {
            http_response_code(403);
            throw new InvalidArgumentException('This published classwork is not available to your section and term.');
        }
        $questions = json_decode($work['questions_json'], true, 64, JSON_THROW_ON_ERROR);
        $posted = $_POST['answers'] ?? null;
        if (!is_array($posted) || count($posted) !== count($questions)) throw new InvalidArgumentException('Answer every question before submitting.');
        $answers = [];
        foreach ($questions as $index => $question) {
            $answer = kns_classwork_text($posted[$index] ?? null, 10000, 'Answer ' . ($index + 1));
            if ($question['type'] === 'multiple_choice'
                && (!ctype_digit($answer) || !isset($question['choices'][(int) $answer]))) {
                throw new InvalidArgumentException('Choose one listed choice for question ' . ($index + 1) . '.');
            }
            $answers[] = $answer;
        }
        $json = json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > KNS_CLASSWORK_MAX_TEXT_BYTES) throw new InvalidArgumentException('Your total answers exceed 1 MiB. Shorten them before submitting.');
        $query = $data->prepare('INSERT INTO subject_classwork_submissions (classwork_id, enrollment_id, answers_json) VALUES (?, ?, ?)');
        $query->bind_param('iis', $workId, $enrollmentId, $json);
        $query->execute();
        $data->commit();
    } catch (Throwable $saveError) {
        $data->rollback();
        throw $saveError;
    }
    unset($_SESSION['classwork_retry'][$enrollmentId][$workId]);
    $_SESSION['classwork_notice'][$enrollmentId] = 'Your answers were submitted inside the Student Portal.';
} catch (Throwable $error) {
    if ($error instanceof mysqli_sql_exception && $error->getCode() === 1062) {
        $_SESSION['classwork_error'][$enrollmentId] = 'This classwork was already submitted. Your original answers have been kept.';
    } elseif ($error instanceof InvalidArgumentException) {
        $_SESSION['classwork_error'][$enrollmentId] = $error->getMessage();
    } else {
        error_log('[classwork-submit] ' . $error->getMessage());
        $_SESSION['classwork_error'][$enrollmentId] = 'Your answers could not be saved. Try again.';
    }
    // Retain bounded plain responses only after a valid CSRF check; never retain uploads.
    if (kns_subject_csrf_valid($_POST['csrf_token'] ?? null) && is_array($_POST['answers'] ?? null)
        && count($_POST['answers']) <= KNS_CLASSWORK_MAX_QUESTIONS) {
        $retry = [];
        $bytes = 0;
        foreach ($_POST['answers'] as $key => $value) {
            if (!is_int($key) || !is_string($value) || !mb_check_encoding($value, 'UTF-8')) continue;
            $bytes += strlen($value);
            if ($bytes > KNS_CLASSWORK_MAX_TEXT_BYTES || mb_strlen($value, 'UTF-8') > 10000) break;
            $retry[$key] = $value;
        }
        // Keep only the most recent failed attempt, bounded to 1 MiB.
        $_SESSION['classwork_retry'] = [$enrollmentId => [$workId => $retry]];
    }
}
$parameters = ['id' => $subjectId, 'enrollment' => $enrollmentId];
if (($_POST['embedded'] ?? '') === '1') $parameters['embed'] = '1';
header('Location: subjectview.php?' . http_build_query($parameters) . '#classwork-' . $workId, true, 303);
