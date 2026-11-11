<?php
session_start();
require_once __DIR__ . '/subject_support.php';
require_once __DIR__ . '/student_ui.php';
header('X-Frame-Options: SAMEORIGIN');
$subject_mode = ($_GET['view'] ?? '') === 'message' ? 'message' : 'classroom';
$subject_embedded = ($_GET['embed'] ?? '') === '1';

if (!isset($_SESSION['username'], $_SESSION['usertype']) || $_SESSION['usertype'] !== 'student') {
    header('Location: login.php');
    exit;
}
$subject_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$subject_id) {
    http_response_code(400);
    exit('Choose a valid subject from your dashboard.');
}
try {
    $data = kns_subject_db();
    $student = kns_subject_student($data);
    if (!$student) {
        http_response_code(403);
        exit('Your student account could not be verified.');
    }
    $query = $data->prepare('SELECT id, subject_name, instructor, description FROM subjects WHERE id = ?');
    $query->bind_param('i', $subject_id);
    $query->execute();
    $subject = $query->get_result()->fetch_assoc();
    if (!$subject) {
        http_response_code(404);
        exit('Subject not found.');
    }
    $schema_ready = kns_subject_schema_ready($data);
    $enrollments = $schema_ready ? kns_subject_enrollments($data, (int) $student['id'], $subject_id) : [];
    $enrollment = null;
    $requested_enrollment = filter_input(INPUT_GET, 'enrollment', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (isset($_GET['enrollment'])) {
        foreach ($enrollments as $candidate) {
            if ($requested_enrollment && (int) $candidate['id'] === $requested_enrollment) {
                $enrollment = $candidate;
                break;
            }
        }
        if (!$enrollment) {
            http_response_code(403);
            exit('This subject enrollment is not available to your account.');
        }
    } elseif (count($enrollments) === 1) {
        $enrollment = $enrollments[0];
    }
    $access_note = '';
    if (!$schema_ready) {
        $access_note = 'Subject messaging is not available yet. Please contact the school office.';
        http_response_code(503);
    } elseif (!$enrollments) {
        $access_note = 'Your enrollment for this subject has not been verified yet. Contact the school office to access its private conversation.';
        http_response_code(403);
    } elseif (!$enrollment) {
        $access_note = 'Select your enrolled section and term below to open its private conversation.';
    }
    $success_msg = '';
    $error_msg = '';
    $topic_value = $subject['subject_name'];
    $message_value = '';
    if ($enrollment && isset($_SESSION['subject_message_success'][$enrollment['id']])) {
        $success_msg = $_SESSION['subject_message_success'][$enrollment['id']];
        unset($_SESSION['subject_message_success'][$enrollment['id']]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > KNS_SUBJECT_MAX_POST_BYTES) {
                http_response_code(413);
                throw new InvalidArgumentException('The upload is too large. Use up to 3 attachments, no more than 5 MiB each.');
            }
            if (!kns_subject_csrf_valid($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                throw new InvalidArgumentException('Your message form expired. Reload this page and try again.');
            }
            $posted_enrollment = filter_var($_POST['enrollment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$enrollment || !$posted_enrollment || $posted_enrollment !== (int) $enrollment['id']) {
                http_response_code(403);
                throw new InvalidArgumentException('Your subject enrollment could not be verified. Contact the school office.');
            }
            if (!isset($_POST['send_message']) || !is_string($_POST['subject_module'] ?? null)
                || !is_string($_POST['message'] ?? null)) {
                http_response_code(400);
                throw new InvalidArgumentException('The message form is invalid.');
            }
            $topic_value = trim($_POST['subject_module']);
            $message_value = trim($_POST['message']);
            if (!mb_check_encoding($topic_value, 'UTF-8') || !$topic_value || mb_strlen($topic_value, 'UTF-8') > 100) {
                throw new InvalidArgumentException('Enter a module or topic of at most 100 characters.');
            }
            if (!mb_check_encoding($message_value, 'UTF-8') || mb_strlen($message_value, 'UTF-8') > 5000) {
                throw new InvalidArgumentException('Your message must contain no more than 5,000 characters.');
            }
            $attachments = kns_subject_validate_uploads($_FILES);
            if ($message_value === '' && !$attachments) {
                throw new InvalidArgumentException('Write a message or attach at least one photo or file.');
            }
            kns_subject_save_message($data, $enrollment, $student, $subject, $topic_value, $message_value, $attachments);
            $_SESSION['subject_message_success'][$enrollment['id']] = 'Message saved to your private subject conversation.';
            header('Location: subjectview.php?' . http_build_query(array_merge(['id' => $subject_id, 'enrollment' => $enrollment['id']], isset($_GET['view']) ? ['view' => $subject_mode] : [], $subject_embedded ? ['embed' => '1'] : [])) . '#subject-conversation', true, 303);
            exit;
        } catch (InvalidArgumentException $error) {
            if (http_response_code() < 400) {
                http_response_code(422);
            }
            $error_msg = $error->getMessage() . ' Attachments must be selected again after an unsuccessful send.';
        } catch (Throwable $error) {
            error_log('[subject-message] ' . $error->getMessage());
            http_response_code(503);
            $error_msg = 'Your message could not be saved. Please try again. Select attachments again before sending.';
        }
    }
    $history = $enrollment ? kns_subject_history($data, (int) $enrollment['id']) : ['messages' => [], 'attachments' => []];
    $csrf_token = kns_subject_csrf_token();
} catch (Throwable $error) {
    error_log('[subject-page] ' . $error->getMessage());
    http_response_code(503);
    exit('The subject page is temporarily unavailable. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $subject_mode === 'message' ? 'Private message' : 'Subject classroom' ?> | <?= kns_subject_escape($subject['subject_name']) ?></title>
    <link rel="stylesheet" href="student-portal.css?v=1">
    <link rel="stylesheet" href="subject-page.css?v=2">
    <script src="subject-page.js?v=2" defer></script>
</head>
<body class="subject-shell" data-embedded="<?= $subject_embedded ? 'true' : 'false' ?>" data-subject-mode="<?= $subject_mode ?>">
    <header class="subject-head">
        <?php if (!$subject_embedded): ?><a href="studenthome.php?view=subjects" class="subject-back"><?= kns_ui_icon('arrow') ?> Back to my subjects</a><?php endif; ?>
        <p class="ui-eyebrow"><?= $subject_mode === 'message' ? 'Subject conversation' : 'Subject classroom' ?><?php if ($subject_mode !== 'message' && $enrollment): ?> &middot; <?= kns_subject_escape($enrollment['academic_year']) ?> &middot; <?= kns_subject_escape($enrollment['semester']) ?><?php endif; ?></p>
        <h1><?= $subject_mode === 'message' ? 'Private message' : kns_subject_escape($subject['subject_name']) ?></h1>
        <?php if ($subject_mode !== 'message'): ?>
            <p class="subject-instructor"><?= kns_ui_icon('user') ?> <?= kns_subject_escape($subject['instructor']) ?></p>
            <p class="subject-intro"><?= kns_subject_escape($subject['description']) ?></p>
        <?php endif; ?>
    </header>
    <?php if ($subject_mode === 'message'): ?>
        <div class="message-professor"><span class="soft-icon"><?= kns_ui_icon('user') ?></span><div><strong><?= kns_subject_escape($subject['instructor']) ?></strong><p><?= kns_subject_escape($subject['subject_name']) ?></p></div></div>
    <?php endif; ?>
    <?php if (count($enrollments) > 1): ?>
        <form method="GET" action="subjectview.php" class="enrollment-picker">
            <input type="hidden" name="id" value="<?= (int) $subject_id ?>">
            <input type="hidden" name="view" value="<?= $subject_mode ?>">
            <?php if ($subject_embedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
            <label for="subject-enrollment">Section and term</label>
            <div><select name="enrollment" id="subject-enrollment" required>
                <option value="">Choose your enrollment</option>
                <?php foreach ($enrollments as $option): ?><option value="<?= (int) $option['id'] ?>" <?= $enrollment && $enrollment['id'] === $option['id'] ? 'selected' : '' ?>><?= kns_subject_escape($option['section'] . ' | ' . $option['academic_year'] . ' | ' . $option['semester']) ?></option><?php endforeach; ?>
            </select><button type="submit">Use this section</button></div>
        </form>
    <?php endif; ?>

    <main class="subject-content" <?= $subject_mode === 'message' ? 'hidden' : '' ?>>
        <?php if ($access_note): ?><p class="subject-notice" role="status"><?= kns_subject_escape($access_note) ?></p><?php endif; ?>
        <div class="subject-summary">
            <div><small>Assigned items</small><strong>Not connected</strong></div>
            <div><small>Task categories</small><strong>4 types</strong></div>
            <div><small>Instructor</small><strong><?= kns_subject_escape($subject['instructor']) ?></strong></div>
        </div>
        <section id="subject-work" aria-labelledby="subject-work-title">
            <div class="classroom-work-heading"><div><h2 id="subject-work-title">Activities &amp; assessments</h2><p>Classwork and requirements for this subject.</p></div><span class="subject-count-badge">0 available items</span></div>
            <?php if ($enrollment): ?><p class="subject-context">Section <?= kns_subject_escape($enrollment['section']) ?> &middot; <?= kns_subject_escape($enrollment['academic_year']) ?> &middot; <?= kns_subject_escape($enrollment['semester']) ?></p><?php endif; ?>
            <div class="subject-work-filters" role="group" aria-label="Filter subject work">
                <?php foreach (['all' => 'All', 'activities' => 'Activities', 'quizzes' => 'Quizzes', 'exams' => 'Exams', 'projects' => 'Projects'] as $category => $label): ?>
                    <button class="subject-work-filter" type="button" data-work-filter="<?= $category ?>" aria-pressed="<?= $category === 'all' ? 'true' : 'false' ?>"><?= $label ?> <span aria-label="0 available items">0</span></button>
                <?php endforeach; ?>
            </div>
            <div class="subject-work-empty" role="status" aria-live="polite"><span class="soft-icon"><?= kns_ui_icon('record') ?></span><h3 id="subject-work-empty-title">Instructor-posted work not connected yet</h3><p>Activities, quizzes, exams, and projects will appear here when the school connects its instructor-posted work records. No assignment or submission data is available in this portal yet.</p></div>
        </section>
    </main>

    <section class="message-panel" <?= $subject_mode !== 'message' ? 'hidden' : '' ?> aria-label="Private subject message">
                    <section class="subject-conversation message-thread" id="subject-conversation" aria-labelledby="subject-conversation-title">
                        <h2 class="visually-hidden" id="subject-conversation-title">Private subject conversation</h2>
                        <?php if (!$enrollment): ?>
                            <div class="thread-empty"><span class="thread-empty-icon"><?= kns_ui_icon('chat') ?></span><h3>Verify your enrollment</h3><p><?= kns_subject_escape($access_note ?: 'Select your enrolled section and term to open this conversation.') ?></p></div>
                        <?php elseif (!$history['messages']): ?>
                            <div class="thread-empty"><span class="thread-empty-icon"><?= kns_ui_icon('chat') ?></span><h3>No messages yet</h3><p>Start a conversation with the instructor about this subject.</p></div>
                        <?php else: ?>
                            <?php if (count($history['messages']) === 100): ?><p class="text-muted small">Showing the latest 100 messages.</p><?php endif; ?>
                            <ol class="subject-conversation-list">
                                <?php foreach ($history['messages'] as $entry): ?>
                                    <li class="subject-message">
                                        <div class="subject-message-header">
                                            <strong><?= (int) $entry['sender_id'] === (int) $student['id'] ? 'You' : 'Instructor' ?></strong>
                                            <time><?= kns_subject_escape($entry['created_at']) ?></time>
                                        </div>
                                        <p class="subject-message-topic"><?= kns_subject_escape($entry['subject_module']) ?></p>
                                        <?php if ($entry['message'] !== ''): ?><div class="subject-message-text"><?= kns_subject_escape($entry['message']) ?></div><?php endif; ?>
                                        <?php if (!empty($history['attachments'][$entry['id']])): ?>
                                            <div class="subject-message-attachments">
                                                <?php foreach ($history['attachments'][$entry['id']] as $attachment): ?>
                                                    <a class="subject-message-attachment" href="subject_attachment.php?id=<?= (int) $attachment['id'] ?>&amp;download=1">
                                                        <?php if ($attachment['attachment_kind'] === 'photo'): ?>
                                                            <img src="subject_attachment.php?id=<?= (int) $attachment['id'] ?>" alt="<?= kns_subject_escape($attachment['original_name']) ?>" loading="lazy" width="240" height="160">
                                                        <?php endif; ?>
                                                        <strong><?= kns_subject_escape($attachment['original_name']) ?></strong>
                                                        <small><?= kns_subject_file_size((int) $attachment['byte_size']) ?> &middot; Download</small>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>
        <div class="message-composer">
            <?php if ($success_msg): ?><p class="subject-notice success" role="status"><?= kns_subject_escape($success_msg) ?></p><?php endif; ?>
            <?php if ($error_msg): ?><p class="subject-notice danger" role="alert"><?= kns_subject_escape($error_msg) ?></p><?php endif; ?>
            <p class="message-access-note"><?= $enrollment ? 'Private to this subject conversation. Up to 3 attachments, 5 MiB each.' : 'Sending is locked until your subject enrollment is verified.' ?></p>
            <form method="POST" action="subjectview.php?<?= kns_subject_escape(http_build_query(array_merge(['id' => $subject_id], $enrollment ? ['enrollment' => $enrollment['id']] : [], ['view' => 'message'], $subject_embedded ? ['embed' => '1'] : []))) ?>#subject-conversation" enctype="multipart/form-data" class="subject-message-form" id="subject-message-form" data-max-attachments="<?= KNS_SUBJECT_MAX_ATTACHMENTS ?>" data-max-file-bytes="<?= KNS_SUBJECT_MAX_FILE_BYTES ?>">
                <fieldset <?= !$enrollment || $subject_mode !== 'message' ? 'disabled' : '' ?>>
                    <input type="hidden" name="csrf_token" value="<?= kns_subject_escape($csrf_token) ?>">
                    <input type="hidden" name="enrollment_id" value="<?= (int) ($enrollment['id'] ?? 0) ?>">
                    <details class="message-topic"><summary>Topic: <?= kns_subject_escape($topic_value) ?></summary><label for="subject-message-topic">Module / Topic</label><input id="subject-message-topic" name="subject_module" value="<?= kns_subject_escape($topic_value) ?>" maxlength="100" required></details>
                    <ul class="subject-selected-attachments" id="subject-selected-attachments" aria-label="Selected attachments"></ul>
                    <div class="message-compose-row">
                        <div class="subject-attach-controls">
                            <label class="subject-attach-picker" for="subject-photos"><span class="visually-hidden">Attach photo</span><?= kns_ui_icon('photo') ?><input type="file" id="subject-photos" name="photos[]" accept=".jpg,.jpeg,.png,.gif,.webp" multiple aria-describedby="subject-attachment-help subject-attachment-count"></label>
                            <label class="subject-attach-picker" for="subject-files"><span class="visually-hidden">Attach file</span><?= kns_ui_icon('file') ?><input type="file" id="subject-files" name="files[]" accept=".pdf,.txt,.docx,.xlsx,.pptx" multiple aria-describedby="subject-attachment-help subject-attachment-count"></label>
                        </div>
                        <label class="visually-hidden" for="subject-message">Your Question or Help Request</label>
                        <textarea id="subject-message" name="message" rows="2" maxlength="5000" placeholder="Write a message about this subject..." aria-describedby="subject-attachment-help"><?= kns_subject_escape($message_value) ?></textarea>
                        <button type="submit" name="send_message" class="message-send" aria-label="Send to Professor" title="Send to Professor"><?= kns_ui_icon('send') ?></button>
                    </div>
                    <div class="message-compose-help"><span>Add a photo or a file</span><span>Up to 3 files · 5 MiB each</span></div>
                    <p class="visually-hidden" id="subject-attachment-help">Up to 3 attachments per message, 5 MiB each. Photos: JPG, PNG, GIF, WebP. Files: PDF, TXT, DOCX, XLSX, PPTX.</p>
                    <p class="subject-attachment-count" id="subject-attachment-count" role="status" aria-live="polite">0 of 3 attachments selected.</p>
                    <p class="subject-attachment-error" id="subject-attachment-error" role="alert"></p>
                </fieldset>
            </form>
            <noscript><p class="subject-attachment-help">Enable JavaScript for attachment previews and individual removal. Your message is still sent through the secure subject form.</p></noscript>
            <script type="application/json" id="subject-attachment-policy"><?= json_encode(kns_subject_file_types(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        </div>
    </section>
</body>
</html>
