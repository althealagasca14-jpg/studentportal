<?php
require_once dirname(__DIR__) . '/classwork/classwork_support.php';
require_once __DIR__ . '/student_ui.php';

function kns_professor_url(string $view = 'classwork', int $classId = 0, array $extra = []): string
{
    return 'professorhome.php?' . http_build_query(array_merge(['view' => $view], $classId ? ['class' => $classId] : [], $extra));
}

function kns_professor_classes(mysqli $data, int $professorId): array
{
    $query = $data->prepare("SELECT MIN(e.id) AS class_id, s.id AS subject_id, s.subject_name,
        e.section, e.academic_year, e.semester FROM subject_professors p
        JOIN subjects s ON s.id = p.subject_id
        JOIN subject_enrollments e ON e.subject_id = s.id AND e.is_active = 1
        JOIN user student ON student.id = e.student_id AND student.usertype = 'student'
        WHERE p.professor_id = ? GROUP BY s.id, s.subject_name, e.section, e.academic_year, e.semester
        ORDER BY e.academic_year DESC, e.semester, s.subject_name, e.section");
    $query->bind_param('i', $professorId);
    $query->execute();
    return $query->get_result()->fetch_all(MYSQLI_ASSOC);
}

function kns_professor_roster(mysqli $data, int $professorId, array $class): array
{
    $query = $data->prepare("SELECT e.id AS enrollment_id, e.student_id, u.username,
        (SELECT m.message FROM subject_messages m WHERE m.enrollment_id = e.id ORDER BY m.id DESC LIMIT 1) AS last_message,
        (SELECT m.created_at FROM subject_messages m WHERE m.enrollment_id = e.id ORDER BY m.id DESC LIMIT 1) AS last_message_at
        FROM subject_enrollments e JOIN user u ON u.id = e.student_id AND u.usertype = 'student'
        JOIN subject_professors p ON p.subject_id = e.subject_id AND p.professor_id = ?
        WHERE e.subject_id = ? AND e.section = ? AND e.academic_year = ? AND e.semester = ? AND e.is_active = 1
        ORDER BY u.username, e.id");
    $query->bind_param('iisss', $professorId, $class['subject_id'], $class['section'], $class['academic_year'], $class['semester']);
    $query->execute();
    return $query->get_result()->fetch_all(MYSQLI_ASSOC);
}

function kns_professor_enrollment(mysqli $data, int $professorId, array $class, int $enrollmentId, bool $lock = false): ?array
{
    $query = $data->prepare("SELECT e.id, e.student_id, e.subject_id, e.section, e.academic_year, e.semester, u.username
        FROM subject_enrollments e JOIN user u ON u.id = e.student_id AND u.usertype = 'student'
        JOIN subject_professors p ON p.subject_id = e.subject_id AND p.professor_id = ?
        WHERE e.id = ? AND e.subject_id = ? AND e.section = ? AND e.academic_year = ? AND e.semester = ?
        AND e.is_active = 1" . ($lock ? ' FOR UPDATE' : ''));
    $query->bind_param('iiisss', $professorId, $enrollmentId, $class['subject_id'], $class['section'], $class['academic_year'], $class['semester']);
    $query->execute();
    return $query->get_result()->fetch_assoc();
}

function kns_professor_reply(mysqli $data, array $professor, array $class, int $enrollmentId, string $topic, string $message): void
{
    $topic = kns_classwork_text($topic, 100, 'Topic');
    $message = kns_classwork_text($message, 5000, 'Message');
    $data->begin_transaction();
    try {
        if (!kns_classwork_owned_subject($data, (int) $professor['id'], (int) $class['subject_id'], true)) {
            throw new InvalidArgumentException('Your subject assignment is no longer available.');
        }
        $enrollment = kns_professor_enrollment($data, (int) $professor['id'], $class, $enrollmentId, true);
        if (!$enrollment) throw new InvalidArgumentException('This student is no longer enrolled in the selected class.');
        $query = $data->prepare('INSERT INTO subject_messages
            (enrollment_id, sender_id, recipient_name, subject_module, message) VALUES (?, ?, ?, ?, ?)');
        $query->bind_param('iisss', $enrollmentId, $professor['id'], $enrollment['username'], $topic, $message);
        $query->execute();
        $data->commit();
    } catch (Throwable $error) {
        $data->rollback();
        throw $error;
    }
}

function kns_professor_initials(string $name): string
{
    return mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
}
