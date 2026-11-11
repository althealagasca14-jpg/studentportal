<?php
// CLI integration checks. No production fixtures, table drops, or external dependencies.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/subject_support.php';
$root = dirname(__DIR__);
$source = kns_subject_db();
$reuseDatabase = getenv('KNS_SUBJECT_TEST_DATABASE') ?: null;
$databaseName = $reuseDatabase ?: 'kns_subject_test_' . bin2hex(random_bytes(6));
$testSchemaOwned = false;
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kns-subject-test-' . bin2hex(random_bytes(8));
$server = null;
$test = null;
$failures = 0;
$checks = 0;
$passed = false;
function subject_test_assert(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function subject_test_request(string $url, ?string $cookie = null, ?array $post = null): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 20
    ]);
    if ($cookie !== null) curl_setopt($curl, CURLOPT_COOKIE, 'PHPSESSID=' . $cookie);
    if ($post !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    $response = curl_exec($curl);
    if ($response === false) throw new RuntimeException('Test HTTP request failed: ' . curl_error($curl));
    $result = [
        'status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'headers' => substr($response, 0, curl_getinfo($curl, CURLINFO_HEADER_SIZE)),
        'body' => substr($response, curl_getinfo($curl, CURLINFO_HEADER_SIZE))
    ];
    curl_close($curl);
    return $result;
}
function subject_test_session(string $directory, string $username, string $role, string $token): string
{
    $id = bin2hex(random_bytes(16));
    file_put_contents($directory . '/sess_' . $id, 'username|' . serialize($username)
        . 'usertype|' . serialize($role) . 'subject_message_csrf|' . serialize($token));
    return $id;
}
function subject_test_cleanup_files(string $directory): void
{
    $resolved = realpath($directory);
    $prefix = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'kns-subject-test-';
    if (!$resolved || strncasecmp($resolved, $prefix, strlen($prefix)) !== 0) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    unset($iterator);
    @rmdir($resolved);
}
$originalCounts = [];
foreach (['user', 'subjects', 'messages', 'inquiries', 'subject_enrollments', 'subject_messages', 'subject_message_attachments'] as $table) {
    $originalCounts[$table] = (int) $source->query('SELECT COUNT(*) FROM ' . $table)->fetch_row()[0];
}
try {
    if (!preg_match('/\\Akns_subject_test_[a-f0-9]{12}\\z/', $databaseName)) {
        throw new RuntimeException('Invalid isolated test database name.');
    }
    if (!$reuseDatabase) {
        $source->query('CREATE DATABASE ' . $databaseName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    }
    $test = kns_subject_db();
    $test->select_db($databaseName);
    if ($reuseDatabase) {
        $tables = array_column($test->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
        $expected = ['subject_enrollments', 'subject_message_attachments', 'subject_messages', 'subjects', 'user'];
        sort($tables); sort($expected);
        if ($tables !== $expected) throw new RuntimeException('The reused test schema is not the expected empty fixture schema.');
        foreach ($tables as $table) {
            if ((int) $test->query('SELECT COUNT(*) FROM ' . $table)->fetch_row()[0] !== 0) {
                throw new RuntimeException('Refusing to reuse a test schema containing records.');
            }
        }
    } else {
        foreach (['user', 'subjects'] as $table) {
            $test->query($source->query('SHOW CREATE TABLE ' . $table)->fetch_row()[1]);
        }
    }
    $testSchemaOwned = true;
    $test->multi_query(file_get_contents($root . '/subject_features.sql'));
    do {
        if ($result = $test->store_result()) $result->free();
    } while ($test->more_results() && $test->next_result());
    mkdir($temporaryRoot, 0700, true);
    mkdir($temporaryRoot . '/sessions', 0700);
    $token = bin2hex(random_bytes(32));
    $students = [];
    foreach (['student-one' => 'student', 'student-two' => 'student', 'test-admin' => 'admin'] as $name => $role) {
        $query = $test->prepare('INSERT INTO user (username, usertype) VALUES (?, ?)');
        $query->bind_param('ss', $name, $role);
        $query->execute();
        $students[$name] = (int) $test->insert_id;
    }
    $subjects = [];
    foreach (['Attachment test A', 'Attachment test B'] as $name) {
        $instructor = 'Test instructor';
        $description = 'Isolated integration fixture';
        $query = $test->prepare('INSERT INTO subjects (subject_name, instructor, description) VALUES (?, ?, ?)');
        $query->bind_param('sss', $name, $instructor, $description);
        $query->execute();
        $subjects[] = (int) $test->insert_id;
    }
    $enrollments = [];
    foreach ([
        [$students['student-one'], $subjects[0], 'A', '2026-2027', '1st Semester'],
        [$students['student-one'], $subjects[0], 'B', '2025-2026', '2nd Semester'],
        [$students['student-two'], $subjects[0], 'A', '2026-2027', '1st Semester']
    ] as $record) {
        $query = $test->prepare('INSERT INTO subject_enrollments (student_id, subject_id, section, academic_year, semester) VALUES (?, ?, ?, ?, ?)');
        $query->bind_param('iisss', $record[0], $record[1], $record[2], $record[3], $record[4]);
        $query->execute();
        $enrollments[] = (int) $test->insert_id;
    }
    $sessions = [];
    foreach (['student-one' => 'student', 'student-two' => 'student', 'test-admin' => 'admin'] as $name => $role) {
        $sessions[$name] = subject_test_session($temporaryRoot . '/sessions', $name, $role, $token);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $number, $error);
    if (!$socket) throw new RuntimeException('Cannot reserve a local test port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $environment = getenv();
    $environment['KNS_SUBJECT_DB_NAME'] = $databaseName;
    $environment['KNS_SUBJECT_STORAGE_DIR'] = $temporaryRoot . '/private';
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $temporaryRoot . '/sessions',
        '-d', 'display_errors=0', '-S', $address, '-t', $root],
        [0 => ['pipe', 'r'], 1 => ['file', $temporaryRoot . '/server.log', 'a'], 2 => ['file', $temporaryRoot . '/server.log', 'a']],
        $pipes, $root, $environment, ['bypass_shell' => true, 'create_no_window' => true]);
    if (!is_resource($server)) throw new RuntimeException('Could not start the isolated PHP test server.');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $number, $error, .1);
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(100000);
    }
    subject_test_assert($ready, 'Test server did not start.');
    $url = $base . '/subjectview.php?id=' . $subjects[0] . '&enrollment=' . $enrollments[0];
    $studentCookie = $sessions['student-one'];
    subject_test_assert(subject_test_request($url)['status'] === 302, 'Anonymous page request was not redirected.');
    subject_test_assert(subject_test_request($base . '/subject_attachment.php?id=1')['status'] === 401, 'Anonymous attachment request accepted.');
    subject_test_assert(subject_test_request($base . '/subject_attachment.php?id=1', $sessions['test-admin'])['status'] === 401, 'Admin session accepted by student attachment endpoint.');
    subject_test_assert(subject_test_request($base . '/subjectview.php?id=' . $subjects[1], $studentCookie)['status'] === 403, 'Unenrolled subject accepted.');
    subject_test_assert(subject_test_request($base . '/subjectview.php?id=' . $subjects[1] . '&enrollment=' . $enrollments[0], $studentCookie)['status'] === 403, 'Cross-subject enrollment accepted.');
    subject_test_assert(subject_test_request($url, $sessions['student-two'])['status'] === 403, 'Another student opened a conversation.');
    $page = subject_test_request($url, $studentCookie);
    subject_test_assert($page['status'] === 200 && str_contains($page['body'], 'Instructor-posted work not connected yet'), 'Real-source unavailable state missing.');
    subject_test_assert(!str_contains($page['body'], 'Module 4 Assignment') && !str_contains($page['body'], 'Module 1: Introduction'), 'Hard-coded assignments remain.');
    subject_test_assert(str_contains($page['body'], '2026-2027') && str_contains($page['body'], '1st Semester'), 'Enrollment term not shown.');

    $png = $temporaryRoot . '/photo.png';
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $text = $temporaryRoot . '/notes.txt';
    file_put_contents($text, "Subject attachment test notes.\n");
    $large = $temporaryRoot . '/large.txt';
    file_put_contents($large, str_repeat('A', KNS_SUBJECT_MAX_FILE_BYTES + 1));
    $exact = $temporaryRoot . '/exact.txt';
    file_put_contents($exact, str_repeat('A', KNS_SUBJECT_MAX_FILE_BYTES));
    $empty = $temporaryRoot . '/empty.txt';
    file_put_contents($empty, '');
    $docx = $temporaryRoot . '/document.docx';
    $zip = new ZipArchive();
    $zip->open($docx, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><document/>');
    $zip->close();
    $fakeDocx = $temporaryRoot . '/fake.docx';
    $zip = new ZipArchive();
    $zip->open($fakeDocx, ZipArchive::CREATE);
    $zip->addFromString('not-an-office-document.txt', 'Invalid package');
    $zip->close();
    $post = ['csrf_token' => $token, 'enrollment_id' => (string) $enrollments[0], 'subject_module' => 'Test topic', 'message' => 'Text-only fixture', 'send_message' => '1'];
    subject_test_assert(subject_test_request($url, $studentCookie, $post)['status'] === 303, 'Text-only send failed.');
    $photoPost = array_merge($post, ['message' => '<script>not executable</script>', 'photos[0]' => new CURLFile($png, 'image/png', 'photo.png'),
        'files[0]' => new CURLFile($text, 'text/plain', '<notes>.txt')]);
    subject_test_assert(subject_test_request($url, $studentCookie, $photoPost)['status'] === 303, 'Photo and file upload failed.');
    $onlyAttachments = array_merge($post, ['message' => '', 'photos[0]' => new CURLFile($png, 'image/png', 'one.png'),
        'photos[1]' => new CURLFile($png, 'image/png', 'two.png'), 'files[0]' => new CURLFile($text, 'text/plain', 'notes.txt')]);
    subject_test_assert(subject_test_request($url, $studentCookie, $onlyAttachments)['status'] === 303, 'Three attachment-only send failed.');
    subject_test_assert(subject_test_request($url, $studentCookie, array_merge($post, ['message' => '', 'files[0]' => new CURLFile($docx, 'application/zip', 'document.docx')]))['status'] === 303, 'Valid Office document rejected.');
    $exactResponse = subject_test_request($url, $studentCookie, array_merge($post, ['message' => '', 'files[0]' => new CURLFile($exact, 'text/plain', 'exact.txt')]));
    subject_test_assert($exactResponse['status'] === 303, 'Exact 5 MiB boundary rejected: ' . substr(strip_tags($exactResponse['body']), 0, 600));
    $beforeMessages = (int) $test->query('SELECT COUNT(*) FROM subject_messages')->fetch_row()[0];
    $beforeAttachments = (int) $test->query('SELECT COUNT(*) FROM subject_message_attachments')->fetch_row()[0];
    foreach ([
        [403, array_merge($post, ['csrf_token' => 'wrong'])],
        [403, array_merge($post, ['enrollment_id' => (string) $enrollments[2]])],
        [422, array_merge($post, ['message' => ''])],
        [422, array_merge($post, ['files[0]' => new CURLFile($large, 'text/plain', 'large.txt')])],
        [422, array_merge($post, ['files[0]' => new CURLFile($empty, 'text/plain', 'empty.txt')])],
        [422, array_merge($post, ['photos[0]' => new CURLFile($text, 'image/png', 'forged.png')])],
        [422, array_merge($post, ['files[0]' => new CURLFile($text, 'text/plain', 'program.php')])],
        [422, array_merge($post, ['files[0]' => new CURLFile($fakeDocx, 'application/zip', 'fake.docx')])],
        [422, array_merge($onlyAttachments, ['files[1]' => new CURLFile($text, 'text/plain', 'fourth.txt')])]
    ] as [$expected, $payload]) {
        $response = subject_test_request($url, $studentCookie, $payload);
        subject_test_assert($response['status'] === $expected, 'Upload/CSRF validation returned ' . $response['status'] . ' instead of ' . $expected . ': ' . substr(strip_tags($response['body']), 0, 600));
    }
    subject_test_assert((int) $test->query('SELECT COUNT(*) FROM subject_messages')->fetch_row()[0] === $beforeMessages, 'A rejected upload left a message.');
    subject_test_assert((int) $test->query('SELECT COUNT(*) FROM subject_message_attachments')->fetch_row()[0] === $beforeAttachments, 'A rejected upload left metadata.');
    $page = subject_test_request($url, $studentCookie);
    subject_test_assert(str_contains($page['body'], '&lt;script&gt;not executable&lt;/script&gt;') && str_contains($page['body'], '&lt;notes&gt;.txt'), 'Conversation content is not escaped.');
    subject_test_assert(!str_contains($page['body'], $temporaryRoot) && !str_contains($page['body'], '.bin'), 'Private storage paths leaked.');
    $otherContext = subject_test_request($base . '/subjectview.php?id=' . $subjects[0] . '&enrollment=' . $enrollments[1], $studentCookie);
    subject_test_assert(!str_contains($otherContext['body'], 'Text-only fixture') && !str_contains($otherContext['body'], 'not executable'), 'Conversation history crossed section/year/semester contexts.');
    $photo = $test->query("SELECT * FROM subject_message_attachments WHERE attachment_kind = 'photo' ORDER BY id LIMIT 1")->fetch_assoc();
    $downloadUrl = $base . '/subject_attachment.php?id=' . $photo['id'];
    $download = subject_test_request($downloadUrl, $studentCookie);
    subject_test_assert($download['status'] === 200 && $download['body'] === file_get_contents($png), 'Authorized photo stream failed.');
    subject_test_assert(str_contains(strtolower($download['headers']), 'content-disposition: inline') && str_contains(strtolower($download['headers']), 'x-content-type-options: nosniff'), 'Photo security headers missing.');
    subject_test_assert(subject_test_request($downloadUrl, $sessions['student-two'])['status'] === 404, 'Another student downloaded the attachment.');
    $download = subject_test_request($downloadUrl . '&download=1', $studentCookie);
    subject_test_assert(str_contains(strtolower($download['headers']), 'content-disposition: attachment'), 'Explicit photo download not forced.');
    $file = $test->query("SELECT id FROM subject_message_attachments WHERE attachment_kind = 'file' ORDER BY id LIMIT 1")->fetch_assoc();
    $download = subject_test_request($base . '/subject_attachment.php?id=' . $file['id'], $studentCookie);
    subject_test_assert($download['status'] === 200 && str_contains(strtolower($download['headers']), 'content-disposition: attachment'), 'File download not forced.');
    $test->query('UPDATE subject_enrollments SET is_active = 0 WHERE id = ' . (int) $enrollments[0]);
    subject_test_assert(subject_test_request($downloadUrl, $studentCookie)['status'] === 404, 'Revoked enrollment still downloads.');
    subject_test_assert(subject_test_request($url, $studentCookie, $post)['status'] === 403, 'Revoked enrollment still sends.');
    $test->query('UPDATE subject_enrollments SET is_active = 1 WHERE id = ' . (int) $enrollments[0]);

    rename($temporaryRoot . '/private', $temporaryRoot . '/private-backup');
    file_put_contents($temporaryRoot . '/private', 'Storage failure fixture');
    $failedStorage = subject_test_request($url, $studentCookie, $photoPost);
    subject_test_assert($failedStorage['status'] === 503 && !str_contains($failedStorage['body'], $temporaryRoot), 'Storage failure not handled safely.');
    subject_test_assert((int) $test->query('SELECT COUNT(*) FROM subject_messages')->fetch_row()[0] === $beforeMessages, 'Storage failure did not roll back its message.');
    unlink($temporaryRoot . '/private');
    rename($temporaryRoot . '/private-backup', $temporaryRoot . '/private');
    $oldStorage = getenv('KNS_SUBJECT_STORAGE_DIR');
    putenv('KNS_SUBJECT_STORAGE_DIR=' . $root . '/unsafe-test-upload-directory');
    $blocked = false;
    try { kns_subject_storage_dir(true); } catch (RuntimeException $error) { $blocked = true; }
    if ($oldStorage === false) putenv('KNS_SUBJECT_STORAGE_DIR');
    else putenv('KNS_SUBJECT_STORAGE_DIR=' . $oldStorage);
    subject_test_assert($blocked && !is_dir($root . '/unsafe-test-upload-directory'), 'Public upload directory accepted.');
    foreach (glob($temporaryRoot . '/private/*') ?: [] as $path) {
        subject_test_assert((bool) preg_match('/\A[a-f0-9]{64}\.bin\z/', basename($path)), 'Unsafe stored filename.');
    }
    echo 'PASS: ' . $checks . " integration checks (uploads, content validation, CSRF, enrollment isolation, protected downloads, rollback).\n";
    if (getenv('KNS_SUBJECT_UI_REVIEW') === '1') {
        $manifest = ['base' => $base, 'url' => $url, 'cookie' => $studentCookie,
            'photo' => $png, 'file' => $text, 'large' => $large, 'csrf' => $token,
            'directory' => $temporaryRoot, 'database' => $databaseName];
        file_put_contents($temporaryRoot . '/manifest.json', json_encode($manifest));
        echo 'UI_REVIEW_MANIFEST=' . $temporaryRoot . "/manifest.json\n";
        flush();
        $deadline = microtime(true) + 600;
        while (!is_file($temporaryRoot . '/ui-review-complete') && microtime(true) < $deadline) usleep(100000);
    }
    $passed = true;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($test && $testSchemaOwned) {
        // The database was newly created by this run. Keep empty schemas; never DROP tables.
        foreach (['subject_message_attachments', 'subject_messages', 'subject_enrollments', 'subjects', 'user'] as $table) {
            try { $test->query('DELETE FROM ' . $table); } catch (Throwable $ignored) {}
        }
        $test->close();
    }
    foreach ($originalCounts as $table => $count) {
        if ((int) $source->query('SELECT COUNT(*) FROM ' . $table)->fetch_row()[0] !== $count) {
            fwrite(STDERR, 'FAIL: production data changed in ' . $table . "\n");
            $passed = false;
        }
    }
    subject_test_cleanup_files($temporaryRoot);
    echo 'Isolated test schema retained empty (no tables dropped): ' . $databaseName . "\n";
    echo "Production row counts unchanged; temporary sessions and files removed.\n";
}
exit($passed ? 0 : 1);
