<?php
// Explicit, optional demo data for local previews. Never available as an HTTP endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/app/classwork/classwork_support.php';

function kns_demo_account(mysqli $data, string $username, string $role, string $password): int
{
    $query = $data->prepare('SELECT id, usertype, password FROM user WHERE username = ? LIMIT 2 FOR UPDATE');
    $query->bind_param('s', $username);
    $query->execute();
    $rows = $query->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($rows) {
        if (count($rows) !== 1 || $rows[0]['usertype'] !== $role || $rows[0]['password'] !== $password) {
            throw new RuntimeException('The demo username is already in use or has been changed: ' . $username . '. No existing account was overwritten.');
        }
        return (int) $rows[0]['id'];
    }
    // Match the portal's existing authentication storage; this seed does not change login behavior.
    $query = $data->prepare('INSERT INTO user (username, usertype, password) VALUES (?, ?, ?)');
    $query->bind_param('sss', $username, $role, $password);
    $query->execute();
    return (int) $data->insert_id;
}

$data = kns_subject_db();
if (!kns_classwork_schema_ready($data)) {
    throw new RuntimeException('Apply the subject and classwork migrations before creating the demo.');
}
$professorUsername = 'professor.demo';
$professorPassword = 'DemoProf!2026';
$studentUsername = 'student.demo';
$studentPassword = 'DemoStudent!2026';
$subjectName = 'DEMO - Introduction to Computing';
$instructor = 'Demo Professor';
$description = '[PORTAL DEMO] Fictional classroom for local previews. These records do not represent real school enrollment.';
$section = 'DEMO-A';
$year = '2026-2027';
$semester = '1st Semester';
$title = '[DEMO] Computer Fundamentals Quiz';

$lock = $data->query("SELECT GET_LOCK('studentportal_professor_demo_seed', 10)")->fetch_row()[0];
if ((int) $lock !== 1) throw new RuntimeException('Another demo setup is running. Try again later.');
try {
    $data->begin_transaction();
    try {
        $professorId = kns_demo_account($data, $professorUsername, 'professor', $professorPassword);
        $studentId = kns_demo_account($data, $studentUsername, 'student', $studentPassword);

        $query = $data->prepare('SELECT id, instructor, description FROM subjects WHERE subject_name = ? LIMIT 2 FOR UPDATE');
        $query->bind_param('s', $subjectName);
        $query->execute();
        $subjects = $query->get_result()->fetch_all(MYSQLI_ASSOC);
        if ($subjects) {
            if (count($subjects) !== 1 || $subjects[0]['instructor'] !== $instructor
                || strpos((string) $subjects[0]['description'], '[PORTAL DEMO]') !== 0) {
                throw new RuntimeException('The demo subject name is already in use. No existing subject was overwritten.');
            }
            $subjectId = (int) $subjects[0]['id'];
        } else {
            $query = $data->prepare('INSERT INTO subjects (subject_name, instructor, description) VALUES (?, ?, ?)');
            $query->bind_param('sss', $subjectName, $instructor, $description);
            $query->execute();
            $subjectId = (int) $data->insert_id;
        }

        $query = $data->prepare('SELECT subject_id FROM subject_professors WHERE subject_id = ? AND professor_id = ? FOR UPDATE');
        $query->bind_param('ii', $subjectId, $professorId);
        $query->execute();
        if (!$query->get_result()->fetch_assoc()) {
            $query = $data->prepare('INSERT INTO subject_professors (subject_id, professor_id) VALUES (?, ?)');
            $query->bind_param('ii', $subjectId, $professorId);
            $query->execute();
        }

        $query = $data->prepare('SELECT id, is_active FROM subject_enrollments WHERE student_id = ? AND subject_id = ?
            AND section = ? AND academic_year = ? AND semester = ? FOR UPDATE');
        $query->bind_param('iisss', $studentId, $subjectId, $section, $year, $semester);
        $query->execute();
        $enrollment = $query->get_result()->fetch_assoc();
        if ($enrollment) {
            if (!(int) $enrollment['is_active']) throw new RuntimeException('The demo enrollment was deactivated. It was left unchanged.');
            $enrollmentId = (int) $enrollment['id'];
        } else {
            $query = $data->prepare('INSERT INTO subject_enrollments (student_id, subject_id, section, academic_year, semester)
                VALUES (?, ?, ?, ?, ?)');
            $query->bind_param('iisss', $studentId, $subjectId, $section, $year, $semester);
            $query->execute();
            $enrollmentId = (int) $data->insert_id;
        }

        $query = $data->prepare('SELECT id, status FROM subject_classwork WHERE subject_id = ? AND professor_id = ?
            AND title = ? AND section = ? AND academic_year = ? AND semester = ? LIMIT 2 FOR UPDATE');
        $query->bind_param('iissss', $subjectId, $professorId, $title, $section, $year, $semester);
        $query->execute();
        $existing = $query->get_result()->fetch_all(MYSQLI_ASSOC);
        if ($existing) {
            if (count($existing) !== 1) throw new RuntimeException('Multiple sample quizzes have this title. No classwork was changed.');
            $workId = (int) $existing[0]['id'];
            $status = $existing[0]['status'];
        } else {
            $questions = kns_classwork_questions(json_encode([
                ['type' => 'multiple_choice', 'text' => 'Which device is used to type text into a computer?',
                    'choices' => ['Monitor', 'Keyboard', 'Speaker', 'Printer'], 'answer' => '1', 'points' => 1],
                ['type' => 'multiple_choice', 'text' => 'What does CPU stand for?',
                    'choices' => ['Central Power Unit', 'Computer Processing Utility', 'Central Processing Unit', 'Control Program User'],
                    'answer' => '2', 'points' => 1],
                ['type' => 'short_answer', 'text' => 'Name one input device and describe its purpose.',
                    'choices' => [], 'answer' => 'A keyboard lets you type text and commands.', 'points' => 3],
            ], JSON_THROW_ON_ERROR), false);
            $questionJson = json_encode($questions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $instructions = 'This is a sample draft for the DEMO classroom. Review and edit its three questions, answer keys, and points. Confirm review, then choose Publish when ready to try the student view.';
            $category = 'quizzes';
            $query = $data->prepare("INSERT INTO subject_classwork
                (subject_id, professor_id, section, academic_year, semester, category, title, instructions, questions_json, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
            $query->bind_param('iisssssss', $subjectId, $professorId, $section, $year, $semester, $category, $title, $instructions, $questionJson);
            $query->execute();
            $workId = (int) $data->insert_id;
            $status = 'draft';
        }
        $data->commit();
    } catch (Throwable $error) {
        $data->rollback();
        throw $error;
    }
} finally {
    $data->query("SELECT RELEASE_LOCK('studentportal_professor_demo_seed')");
}

echo 'Demo ready. Existing non-demo records were not modified.', PHP_EOL;
echo 'Professor account ID: ', $professorUsername, PHP_EOL;
echo 'Professor password: ', $professorPassword, PHP_EOL;
echo 'Student account ID: ', $studentUsername, PHP_EOL;
echo 'Student password: ', $studentPassword, PHP_EOL;
echo 'Subject ID: ', $subjectId, '; enrollment ID: ', $enrollmentId, '; classwork ID: ', $workId, '; status: ', $status, PHP_EOL;
echo 'Professor subject: http://localhost/studentportal/professor_classwork.php?id=', $subjectId, PHP_EOL;
if ($status === 'draft') echo 'Edit sample draft: http://localhost/studentportal/professor_classwork.php?id=', $subjectId, '&work=', $workId, PHP_EOL;
echo 'Student subject (after professor publishes): http://localhost/studentportal/subjectview.php?id=', $subjectId, '&enrollment=', $enrollmentId, PHP_EOL;
