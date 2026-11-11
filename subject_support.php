<?php
// Backend helpers used only by the subject page and its authenticated downloads.
const KNS_SUBJECT_MAX_ATTACHMENTS = 3;
const KNS_SUBJECT_MAX_FILE_BYTES = 5 * 1024 * 1024;
const KNS_SUBJECT_MAX_POST_BYTES = 17 * 1024 * 1024;

function kns_subject_db(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    // Defaults match the existing portal. Overrides support a separate local test database.
    $data = new mysqli(
        getenv('KNS_SUBJECT_DB_HOST') ?: 'localhost',
        getenv('KNS_SUBJECT_DB_USER') ?: 'root',
        getenv('KNS_SUBJECT_DB_PASSWORD') !== false ? getenv('KNS_SUBJECT_DB_PASSWORD') : '',
        getenv('KNS_SUBJECT_DB_NAME') ?: 'althea_database'
    );
    $data->set_charset('utf8mb4');
    return $data;
}

function kns_subject_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function kns_subject_student(mysqli $data): ?array
{
    if (!isset($_SESSION['username'], $_SESSION['usertype']) || $_SESSION['usertype'] !== 'student'
        || !is_string($_SESSION['username'])) {
        return null;
    }
    $query = $data->prepare("SELECT id, username FROM user WHERE username = ? AND usertype = 'student' LIMIT 2");
    $query->bind_param('s', $_SESSION['username']);
    $query->execute();
    $rows = $query->get_result()->fetch_all(MYSQLI_ASSOC);
    return count($rows) === 1 ? $rows[0] : null;
}

function kns_subject_schema_ready(mysqli $data): bool
{
    $result = $data->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
        AND table_name IN ('subject_enrollments', 'subject_messages', 'subject_message_attachments')");
    return (int) $result->fetch_row()[0] === 3;
}

function kns_subject_enrollments(mysqli $data, int $studentId, int $subjectId): array
{
    $query = $data->prepare('SELECT id, student_id, subject_id, section, academic_year, semester
        FROM subject_enrollments WHERE student_id = ? AND subject_id = ? AND is_active = 1
        ORDER BY academic_year DESC, semester, section, id');
    $query->bind_param('ii', $studentId, $subjectId);
    $query->execute();
    return $query->get_result()->fetch_all(MYSQLI_ASSOC);
}

function kns_subject_csrf_token(): string
{
    if (empty($_SESSION['subject_message_csrf'])) {
        $_SESSION['subject_message_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['subject_message_csrf'];
}

function kns_subject_csrf_valid($token): bool
{
    return is_string($token) && isset($_SESSION['subject_message_csrf'])
        && hash_equals($_SESSION['subject_message_csrf'], $token);
}

function kns_subject_file_types(): array
{
    return [
        'jpg' => ['kind' => 'photo', 'mime' => 'image/jpeg'],
        'jpeg' => ['kind' => 'photo', 'mime' => 'image/jpeg'],
        'png' => ['kind' => 'photo', 'mime' => 'image/png'],
        'gif' => ['kind' => 'photo', 'mime' => 'image/gif'],
        'webp' => ['kind' => 'photo', 'mime' => 'image/webp'],
        'pdf' => ['kind' => 'file', 'mime' => 'application/pdf'],
        'txt' => ['kind' => 'file', 'mime' => 'text/plain'],
        'docx' => ['kind' => 'file', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'part' => 'word/document.xml', 'content' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'],
        'xlsx' => ['kind' => 'file', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'part' => 'xl/workbook.xml', 'content' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'],
        'pptx' => ['kind' => 'file', 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'part' => 'ppt/presentation.xml', 'content' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml']
    ];
}

function kns_subject_office_valid(string $path, array $type): bool
{
    $zip = new ZipArchive();
    if (@$zip->open($path) !== true) {
        return false;
    }
    try {
        if ($zip->numFiles > 2000 || $zip->locateName($type['part']) === false) {
            return false;
        }
        $expanded = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            if (!$entry || !empty($entry['encryption_method'])
                || preg_match('~(^|[\\\\/])\.\.([\\\\/]|$)|vbaProject\.bin$~i', $entry['name'])) {
                return false;
            }
            $expanded += $entry['size'];
            if ($expanded > 50 * 1024 * 1024) {
                return false;
            }
        }
        $typesEntry = $zip->statName('[Content_Types].xml');
        if (!$typesEntry || $typesEntry['size'] > 65536) {
            return false;
        }
        $xml = @$zip->getFromName('[Content_Types].xml');
        if ($xml === false || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return false;
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                return false;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $mainPartFound = false;
        foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override') as $part) {
            $content = $part->getAttribute('ContentType');
            if (stripos($content, 'macro') !== false || stripos($content, 'vba') !== false) {
                return false;
            }
            if ($part->getAttribute('PartName') === '/' . $type['part'] && $content === $type['content']) {
                $mainPartFound = true;
            }
        }
        return $mainPartFound;
    } finally {
        $zip->close();
    }
}

function kns_subject_validate_uploads(array $files): array
{
    $pending = [];
    foreach ($files as $field => $upload) {
        if (!in_array($field, ['photos', 'files'], true) || !is_array($upload)
            || !isset($upload['name'], $upload['tmp_name'], $upload['error'], $upload['size'])
            || !is_array($upload['name']) || !is_array($upload['tmp_name'])
            || !is_array($upload['error']) || !is_array($upload['size'])) {
            throw new InvalidArgumentException('Choose attachments using Attach photo or Attach file.');
        }
        foreach ($upload['error'] as $index => $error) {
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('An attachment could not be uploaded. Each file must be no larger than 5 MiB.');
            }
            if (!isset($upload['name'][$index], $upload['tmp_name'][$index])
                || !is_string($upload['name'][$index]) || !is_string($upload['tmp_name'][$index])) {
                throw new InvalidArgumentException('The attachment upload is invalid.');
            }
            $pending[] = [
                'name' => $upload['name'][$index],
                'path' => $upload['tmp_name'][$index],
                'kind' => $field === 'photos' ? 'photo' : 'file'
            ];
        }
    }
    if (count($pending) > KNS_SUBJECT_MAX_ATTACHMENTS) {
        throw new InvalidArgumentException('You can attach up to 3 files per message.');
    }
    $types = kns_subject_file_types();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $validated = [];
    foreach ($pending as $file) {
        if (!is_uploaded_file($file['path'])) {
            throw new InvalidArgumentException('The attachment upload is invalid.');
        }
        $size = filesize($file['path']);
        if (!$size || $size > KNS_SUBJECT_MAX_FILE_BYTES) {
            throw new InvalidArgumentException('Each attachment must contain data and be no larger than 5 MiB.');
        }
        $name = basename(str_replace('\\', '/', $file['name']));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
        if (!$name || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 180) {
            throw new InvalidArgumentException('Use a valid attachment filename of at most 180 characters.');
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $type = $types[$extension] ?? null;
        if (!$type || $type['kind'] !== $file['kind']) {
            throw new InvalidArgumentException('Photos must be JPG, PNG, GIF or WebP. Files must be PDF, TXT, DOCX, XLSX or PPTX.');
        }
        $mime = $finfo->file($file['path']);
        if (isset($type['part'])) {
            if (!in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed', $type['mime']], true)
                || !kns_subject_office_valid($file['path'], $type)) {
                throw new InvalidArgumentException('That Office attachment is invalid, encrypted, or contains unsupported content.');
            }
        } elseif ($mime !== $type['mime'] && !($extension === 'txt' && $mime === 'application/octet-stream')) {
            throw new InvalidArgumentException('An attachment does not match its file extension.');
        }
        if ($type['kind'] === 'photo') {
            $image = @getimagesize($file['path']);
            if (!$image || ($image['mime'] ?? '') !== $type['mime']
                || $image[0] < 1 || $image[1] < 1) {
                throw new InvalidArgumentException('Choose a valid JPG, PNG, GIF or WebP photo.');
            }
        } elseif ($extension === 'txt') {
            $text = file_get_contents($file['path']);
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text) || !mb_check_encoding($text, 'UTF-8')) {
                throw new InvalidArgumentException('Text attachments must be UTF-8 text without binary content.');
            }
        }
        $validated[] = [
            'temporary_path' => $file['path'], 'original_name' => $name,
            'mime_type' => $type['mime'], 'byte_size' => $size, 'attachment_kind' => $type['kind']
        ];
    }
    return $validated;
}

function kns_subject_storage_dir(bool $create = false): string
{
    $directory = getenv('KNS_SUBJECT_STORAGE_DIR')
        ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'studentportal-private' . DIRECTORY_SEPARATOR . 'subject-attachments';
    $directory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory);
    if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $directory) || preg_match('~(^|[\\\\/])\.\.([\\\\/]|$)~', $directory)) {
        throw new RuntimeException('Invalid private storage configuration.');
    }
    $parent = $directory;
    $suffix = [];
    while (!is_dir($parent)) {
        $suffix[] = basename($parent);
        $next = dirname($parent);
        if ($next === $parent) {
            throw new RuntimeException('Private storage parent is unavailable.');
        }
        $parent = $next;
    }
    $resolved = rtrim(realpath($parent), DIRECTORY_SEPARATOR);
    if ($suffix) {
        $resolved .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, array_reverse($suffix));
    }
    $publicRoots = [dirname(__DIR__), $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)];
    foreach ($publicRoots as $root) {
        $public = realpath($root);
        if (!$public) {
            continue;
        }
        $prefix = rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strcasecmp($resolved, $public) === 0 || strncasecmp($resolved, $prefix, strlen($prefix)) === 0) {
            throw new RuntimeException('Attachments must be stored outside the public web directory.');
        }
    }
    if (!is_dir($resolved)) {
        if (!$create || !@mkdir($resolved, 0700, true)) {
            throw new RuntimeException('Private attachment storage is unavailable.');
        }
    }
    return realpath($resolved);
}

function kns_subject_save_message(mysqli $data, array $enrollment, array $student, array $subject, string $topic, string $body, array $attachments): int
{
    $paths = [];
    $data->begin_transaction();
    try {
        // Lock and recheck enrollment so revocation cannot race the upload.
        $check = $data->prepare('SELECT id FROM subject_enrollments
            WHERE id = ? AND student_id = ? AND subject_id = ? AND is_active = 1 FOR UPDATE');
        $check->bind_param('iii', $enrollment['id'], $student['id'], $subject['id']);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('Your enrollment could not be verified. Contact the school office.');
        }
        $insert = $data->prepare('INSERT INTO subject_messages
            (enrollment_id, sender_id, recipient_name, subject_module, message) VALUES (?, ?, ?, ?, ?)');
        $insert->bind_param('iisss', $enrollment['id'], $student['id'], $subject['instructor'], $topic, $body);
        $insert->execute();
        $messageId = (int) $data->insert_id;
        if ($attachments) {
            $directory = kns_subject_storage_dir(true);
            $attachmentQuery = $data->prepare('INSERT INTO subject_message_attachments
                (message_id, storage_name, original_name, mime_type, byte_size, attachment_kind) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($attachments as $attachment) {
                do {
                    $storageName = bin2hex(random_bytes(32)) . '.bin';
                    $path = $directory . DIRECTORY_SEPARATOR . $storageName;
                } while (file_exists($path));
                if (!@move_uploaded_file($attachment['temporary_path'], $path)) {
                    throw new RuntimeException('Could not store an attachment.');
                }
                $paths[] = $path;
                @chmod($path, 0600);
                $attachmentQuery->bind_param('isssis', $messageId, $storageName, $attachment['original_name'],
                    $attachment['mime_type'], $attachment['byte_size'], $attachment['attachment_kind']);
                $attachmentQuery->execute();
            }
        }
        $data->commit();
        return $messageId;
    } catch (Throwable $error) {
        $data->rollback();
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        throw $error;
    }
}

function kns_subject_history(mysqli $data, int $enrollmentId): array
{
    $query = $data->prepare('SELECT id, sender_id, subject_module, message, created_at
        FROM subject_messages WHERE enrollment_id = ? ORDER BY id DESC LIMIT 100');
    $query->bind_param('i', $enrollmentId);
    $query->execute();
    $messages = array_reverse($query->get_result()->fetch_all(MYSQLI_ASSOC));
    $query = $data->prepare('SELECT a.id, a.message_id, a.original_name, a.mime_type, a.byte_size, a.attachment_kind
        FROM subject_message_attachments a
        JOIN (SELECT id FROM subject_messages WHERE enrollment_id = ? ORDER BY id DESC LIMIT 100) m ON m.id = a.message_id
        ORDER BY a.id');
    $query->bind_param('i', $enrollmentId);
    $query->execute();
    $attachments = [];
    foreach ($query->get_result()->fetch_all(MYSQLI_ASSOC) as $attachment) {
        $attachments[$attachment['message_id']][] = $attachment;
    }
    return ['messages' => $messages, 'attachments' => $attachments];
}

function kns_subject_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MiB'
        : number_format($bytes / 1024, 1) . ' KiB';
}