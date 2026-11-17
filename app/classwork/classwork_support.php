<?php
require_once dirname(__DIR__) . '/helpers/portal_support.php';

const KNS_CLASSWORK_MAX_QUESTIONS = 200;
const KNS_CLASSWORK_MAX_TEXT_BYTES = 1024 * 1024;

function kns_classwork_schema_ready(mysqli $data): bool
{
    $result = $data->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
        AND table_name IN ('subject_professors', 'subject_classwork', 'subject_classwork_submissions')");
    return (int) $result->fetch_row()[0] === 3 && kns_subject_schema_ready($data);
}

function kns_classwork_professor(mysqli $data): ?array
{
    try {
        $user=kns_portal_user($data,['professor']);
        if(!kns_portal_permission($data,(int)$user['id'],'view_classes')) return null;
        return ['id'=>$user['id'],'username'=>$user['username']];
    } catch(InvalidArgumentException $error) { return null; }
}

function kns_classwork_owned_subject(mysqli $data, int $professorId, int $subjectId, bool $lock = false): ?array
{
    $query = $data->prepare('SELECT s.id, s.subject_name, s.instructor FROM subject_professors p
        JOIN subjects s ON s.id = p.subject_id JOIN user u ON u.id = p.professor_id
        WHERE p.professor_id = ? AND p.subject_id = ? AND u.usertype = \'professor\''
        . ($lock ? ' FOR UPDATE' : ''));
    $query->bind_param('ii', $professorId, $subjectId);
    $query->execute();
    return $query->get_result()->fetch_assoc();
}

function kns_classwork_text($value, int $max, string $label, bool $required = true): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        throw new InvalidArgumentException($label . ' must be valid text.');
    }
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max) {
        throw new InvalidArgumentException($label . ' must contain ' . ($required ? '1' : '0') . ' to ' . $max . ' characters.');
    }
    return $value;
}

function kns_classwork_questions($json, bool $publishing): array
{
    if (!is_string($json) || strlen($json) > KNS_CLASSWORK_MAX_TEXT_BYTES) {
        throw new InvalidArgumentException('The question builder is too large (maximum 1 MiB of question data).');
    }
    try {
        $rows = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new InvalidArgumentException('The question builder could not be read. Keep this page open and try again.');
    }
    if (!is_array($rows) || !array_is_list($rows) || count($rows) > KNS_CLASSWORK_MAX_QUESTIONS
        || ($publishing && !$rows)) {
        throw new InvalidArgumentException('Use 1 to 200 questions before publishing. Empty drafts are allowed.');
    }
    $questions = [];
    foreach ($rows as $index => $row) {
        $label = 'Question ' . ($index + 1);
        if (!is_array($row) || !in_array($row['type'] ?? null, ['multiple_choice', 'short_answer'], true)) {
            throw new InvalidArgumentException($label . ' has an invalid type.');
        }
        $text = kns_classwork_text($row['text'] ?? null, 10000, $label, $publishing);
        $points = $row['points'] ?? null;
        if (!is_scalar($points) || !is_numeric($points) || !is_finite((float) $points)
            || (float) $points < 0.01 || (float) $points > 1000
            || abs(round((float) $points, 2) - (float) $points) > 0.000001) {
            throw new InvalidArgumentException($label . ': points must be between 0.01 and 1,000, with at most two decimal places.');
        }
        $choices = [];
        if ($row['type'] === 'multiple_choice') {
            if (!is_array($row['choices'] ?? null) || !array_is_list($row['choices'])
                || count($row['choices']) > 26 || ($publishing && count($row['choices']) < 2)) {
                throw new InvalidArgumentException($label . ' needs 2 to 26 choices before publishing.');
            }
            foreach ($row['choices'] as $choice) {
                $choices[] = kns_classwork_text($choice, 3000, $label . ' choice', $publishing);
            }
        }
        $answer = kns_classwork_text($row['answer'] ?? '', 3000, $label . ' correct answer', false);
        if ($row['type'] === 'multiple_choice' && $answer !== ''
            && (!ctype_digit($answer) || !isset($choices[(int) $answer]))) {
            throw new InvalidArgumentException($label . ': select a correct choice or leave the key unset.');
        }
        $warnings = [];
        if (isset($row['warnings'])) {
            if (!is_array($row['warnings']) || count($row['warnings']) > 20) {
                throw new InvalidArgumentException($label . ': review notices could not be saved.');
            }
            foreach ($row['warnings'] as $warning) {
                $warnings[] = kns_classwork_text($warning, 4000, $label . ' review notice');
            }
        }
        $questions[] = ['text' => $text, 'type' => $row['type'], 'points' => (float) $points,
            'choices' => $choices, 'answer' => $answer, 'warnings' => array_values(array_unique($warnings))];
    }
    return $questions;
}

function kns_classwork_for_student(mysqli $data, array $enrollment): array
{
    $query = $data->prepare("SELECT w.id, w.title, w.instructions, w.category, w.questions_json,w.due_at,g.score,g.max_score,g.feedback,
        sub.answers_json, sub.submitted_at FROM subject_classwork w
        JOIN subject_professors p ON p.subject_id = w.subject_id AND p.professor_id = w.professor_id
        JOIN user u ON u.id = p.professor_id AND u.usertype = 'professor'
        JOIN subject_sections ss ON ss.subject_id=w.subject_id AND ss.section=w.section AND ss.academic_year=w.academic_year AND ss.semester=w.semester AND ss.professor_id=w.professor_id
        LEFT JOIN subject_classwork_submissions sub ON sub.classwork_id = w.id AND sub.enrollment_id = ?
        LEFT JOIN classwork_grades g ON g.submission_id=sub.id
        WHERE w.subject_id = ? AND w.section = ? AND w.academic_year = ? AND w.semester = ?
        AND w.status = 'published' ORDER BY w.id DESC");
    $query->bind_param('iisss', $enrollment['id'], $enrollment['subject_id'], $enrollment['section'],
        $enrollment['academic_year'], $enrollment['semester']);
    $query->execute();
    $rows = $query->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['questions'] = json_decode($row['questions_json'], true, 64, JSON_THROW_ON_ERROR);
        // Never pass the private key to student markup or client-side JSON.
        foreach ($row['questions'] as &$question) {
            unset($question['answer'], $question['warnings']);
        }
        unset($question, $row['questions_json']);
        $row['answers'] = $row['answers_json'] !== null
            ? json_decode($row['answers_json'], true, 64, JSON_THROW_ON_ERROR) : [];
        unset($row['answers_json']);
    }
    unset($row);
    return $rows;
}
