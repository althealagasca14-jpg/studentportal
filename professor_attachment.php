<?php
session_start();
require_once __DIR__ . '/app/helpers/professor_support.php';

function kns_professor_attachment_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    exit($message);
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    kns_professor_attachment_fail(405, 'Method not allowed.');
}
if (($_SESSION['usertype'] ?? '') !== 'professor') {
    kns_professor_attachment_fail(401, 'Log in as a professor to access this attachment.');
}
$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$attachmentId) kns_professor_attachment_fail(404, 'Attachment not found.');
try {
    $data = kns_subject_db();
    $professor = kns_classwork_professor($data);
    if (!$professor || !kns_classwork_schema_ready($data)) kns_professor_attachment_fail(404, 'Attachment not found.');
    $query = $data->prepare("SELECT a.storage_name, a.original_name, a.mime_type, a.byte_size, a.attachment_kind
        FROM subject_message_attachments a JOIN subject_messages m ON m.id = a.message_id
        JOIN subject_enrollments e ON e.id = m.enrollment_id AND e.is_active = 1
        JOIN user student ON student.id = e.student_id AND student.usertype = 'student'
        JOIN subject_sections p ON p.subject_id=e.subject_id AND p.section=e.section AND p.academic_year=e.academic_year AND p.semester=e.semester
        WHERE a.id = ? AND p.professor_id = ? LIMIT 1");
    $query->bind_param('ii', $attachmentId, $professor['id']);
    $query->execute();
    $attachment = $query->get_result()->fetch_assoc();
    if (!$attachment || !preg_match('/\A[a-f0-9]{64}\.bin\z/', $attachment['storage_name'])) {
        kns_professor_attachment_fail(404, 'Attachment not found.');
    }
    $directory = kns_subject_storage_dir();
    $path = realpath($directory . DIRECTORY_SEPARATOR . $attachment['storage_name']);
    $prefix = $directory . DIRECTORY_SEPARATOR;
    if (!$path || strncasecmp($path, $prefix, strlen($prefix)) !== 0 || !is_file($path)
        || !is_readable($path) || filesize($path) !== (int) $attachment['byte_size']) {
        kns_professor_attachment_fail(404, 'Attachment not found.');
    }
    $extension = strtolower(pathinfo($attachment['original_name'], PATHINFO_EXTENSION));
    $type = kns_subject_file_types()[$extension] ?? null;
    if (!$type || $type['mime'] !== $attachment['mime_type'] || $type['kind'] !== $attachment['attachment_kind']) {
        kns_professor_attachment_fail(404, 'Attachment not found.');
    }
    $stream = @fopen($path, 'rb');
    if (!$stream) kns_professor_attachment_fail(404, 'Attachment not found.');
    $download = ($_GET['download'] ?? '') === '1' || $attachment['attachment_kind'] !== 'photo';
    $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $attachment['original_name']);
    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Length: ' . $attachment['byte_size']);
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $fallback
        . '"; filename*=UTF-8\'\'' . rawurlencode($attachment['original_name']));
    header('Cache-Control: private, no-store');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox; frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    session_write_close();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') @fpassthru($stream);
    fclose($stream);
} catch (Throwable $error) {
    error_log('[professor-attachment] ' . $error->getMessage());
    kns_professor_attachment_fail(503, 'The attachment is temporarily unavailable.');
}
