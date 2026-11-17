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
try { $entryDb=kns_subject_db();$entryUser=kns_portal_user($entryDb,['professor']);if(!kns_portal_permission($entryDb,(int)$entryUser['id'],'view_classes')) {header('Location: faculty_messages.php');exit;} }
catch(Throwable $error) {http_response_code(403);exit('Please sign in with an active professor account.');}
$professorView = in_array($_GET['view'] ?? '', ['classwork', 'messages', 'students'], true) ? $_GET['view'] : 'classwork';
$professorClasses = [];
$professorSelectedClass = null;
$assignedSubjects = $classwork = $roster = [];
$conversation = null;
$history = ['messages' => [], 'attachments' => []];
$dashboardError = '';
$replyValue = '';
$topicValue = '';
$ready = false;
try {
    $data = kns_subject_db();
    $professor = kns_classwork_professor($data);
    if (!$professor) {
        http_response_code(403);
        exit('Your professor account could not be verified.');
    }
    $ready = kns_classwork_schema_ready($data);
    if ($ready) {
        $professorClasses = kns_professor_classes($data, (int) $professor['id']);
        if (isset($_GET['class'])) {
            $requestedClass = filter_var($_GET['class'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            foreach ($professorClasses as $candidate) {
                if ($requestedClass && (int) $candidate['class_id'] === $requestedClass) $professorSelectedClass = $candidate;
            }
            if (!$professorSelectedClass) {
                http_response_code(403);
                throw new InvalidArgumentException('The selected class is unavailable to your account. Choose one of your assigned classes.');
            }
        } else {
            $professorSelectedClass = $professorClasses[0] ?? null;
        }
        if ($professorSelectedClass) {
            $topicValue = $professorSelectedClass['subject_name'];
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                $replyValue = is_string($_POST['message'] ?? null) ? $_POST['message'] : '';
                $topicValue = is_string($_POST['topic'] ?? null) ? $_POST['topic'] : $topicValue;
                try {
                    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > KNS_SUBJECT_MAX_POST_BYTES) {
                        http_response_code(413);
                        throw new InvalidArgumentException('The message is too large. Use no more than 5,000 characters.');
                    }
                    if (!kns_subject_csrf_valid($_POST['csrf_token'] ?? null)) {
                        http_response_code(403);
                        throw new InvalidArgumentException('Your message form expired. Review your message and try again.');
                    }
                    $postedClass = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    $postedStudent = filter_var($_POST['enrollment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($professorView !== 'messages' || ($_POST['send_reply'] ?? '') !== '1' || !$postedStudent
                        || $postedClass !== (int) $professorSelectedClass['class_id']
                        || !kns_professor_enrollment($data, (int) $professor['id'], $professorSelectedClass, $postedStudent)) {
                        http_response_code(403);
                        throw new InvalidArgumentException('The student conversation could not be verified for this class.');
                    }
                    kns_professor_reply($data, $professor, $professorSelectedClass, $postedStudent, $topicValue, $replyValue);
                    $_SESSION['professor_reply_success'] = 'Your message was saved to this private subject conversation.';
                    header('Location: ' . kns_professor_url('messages', $postedClass, ['student' => $postedStudent]) . '#pf-conversation', true, 303);
                    exit;
                } catch (InvalidArgumentException $error) {
                    if (http_response_code() < 400) http_response_code(422);
                    $dashboardError = $error->getMessage();
                } catch (Throwable $error) {
                    error_log('[professor-reply] ' . $error->getMessage());
                    http_response_code(503);
                    $dashboardError = 'Your message could not be saved. Your text remains below; please try again.';
                }
            }
            $query = $data->prepare('SELECT id, title, category, status, created_at FROM subject_classwork
                WHERE professor_id = ? AND subject_id = ? AND section = ? AND academic_year = ? AND semester = ?
                ORDER BY id DESC');
            $query->bind_param('iisss', $professor['id'], $professorSelectedClass['subject_id'], $professorSelectedClass['section'],
                $professorSelectedClass['academic_year'], $professorSelectedClass['semester']);
            $query->execute();
            $classwork = $query->get_result()->fetch_all(MYSQLI_ASSOC);
            $roster = kns_professor_roster($data, (int) $professor['id'], $professorSelectedClass);
            if ($professorView === 'messages') {
                $requestedStudent = isset($_GET['student'])
                    ? filter_var($_GET['student'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                    : (int) ($roster[0]['enrollment_id'] ?? 0);
                if ($requestedStudent) {
                    $conversation = kns_professor_enrollment($data, (int) $professor['id'], $professorSelectedClass, $requestedStudent);
                    if ($conversation) $history = kns_subject_history($data, (int) $conversation['id']);
                }
                if (isset($_GET['student']) && !$conversation) {
                    http_response_code(403);
                    $dashboardError = 'This student conversation is not available in the selected class.';
                }
            }
        } else {
            $query = $data->prepare('SELECT s.id, s.subject_name FROM subject_professors p JOIN subjects s ON s.id = p.subject_id
                WHERE p.professor_id = ? ORDER BY s.subject_name');
            $query->bind_param('i', $professor['id']);
            $query->execute();
            $assignedSubjects = $query->get_result()->fetch_all(MYSQLI_ASSOC);
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                http_response_code(403);
                throw new InvalidArgumentException('Choose a verified class before sending a message.');
            }
        }
    }
} catch (InvalidArgumentException $error) {
    $dashboardError = $error->getMessage();
} catch (Throwable $error) {
    error_log('[professor-dashboard] ' . $error->getMessage());
    http_response_code(503);
    $dashboardError = 'Your workspace is temporarily unavailable. Please try again later.';
}
$professorClassId = (int) ($professorSelectedClass['class_id'] ?? 0);
$categoryLabels = ['activities' => 'Activity', 'quizzes' => 'Quiz', 'exams' => 'Exam', 'projects' => 'Project'];
$counts = ['all' => count($classwork), 'activities' => 0, 'quizzes' => 0, 'exams' => 0, 'projects' => 0];
$draftCount = 0;
foreach ($classwork as $item) {
    $counts[$item['category']]++;
    if ($item['status'] === 'draft') $draftCount++;
}
$flash = $_SESSION['classwork_saved'] ?? $_SESSION['professor_reply_success'] ?? '';
unset($_SESSION['classwork_saved'], $_SESSION['professor_reply_success']);
$csrfToken = kns_subject_csrf_token();
$professorPageTitle = 'Professor portal';
$professorPageDescription = 'Manage classwork and communicate with your students.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102d23">
    <title>Professor workspace | KNS Portal</title>
    <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg">
    <link rel="stylesheet" href="assets/css/professor-portal.css?v=1">
    <script src="assets/js/professor-portal.js?v=1" defer></script>
</head>
<?php require __DIR__ . '/app/views/partials/professor_shell_start.php'; ?>
    <?php if ($dashboardError): ?><p class="pf-notice pf-error" role="alert"><?= kns_subject_escape($dashboardError) ?></p><?php endif; ?>
    <?php if ($flash): ?><p class="pf-notice" role="status"><?= kns_subject_escape($flash) ?></p><?php endif; ?>
    <?php if (!$ready): ?>
        <section class="pf-panel pf-empty"><span class="pf-empty-icon"><?= kns_ui_icon('book') ?></span><h2>Your workspace is being prepared</h2><p>Ask the school office to connect professor subject assignments and verified class enrollments.</p></section>
    <?php elseif (!$professorSelectedClass): ?>
        <section class="pf-panel"><div class="pf-panel-heading"><div><h2>Your assigned subjects</h2><p>Classwork and conversations become available when a section and term has verified student enrollments.</p></div></div>
            <?php if (!$assignedSubjects): ?><div class="pf-empty"><span class="pf-empty-icon"><?= kns_ui_icon('book') ?></span><h3>No classes assigned yet</h3><p>Contact the school office to connect your subjects.</p></div><?php endif; ?>
            <?php foreach ($assignedSubjects as $item): ?><a class="pf-work-row" href="professor_classwork.php?id=<?= (int) $item['id'] ?>"><span class="pf-item-icon"><?= kns_ui_icon('book') ?></span><span class="pf-item-copy"><strong><?= kns_subject_escape($item['subject_name']) ?></strong><small>Enrollment setup pending</small></span><?= kns_ui_icon('arrow') ?></a><?php endforeach; ?>
        </section>
    <?php elseif ($professorView === 'classwork'): ?>
        <section class="pf-panel" aria-labelledby="pf-work-title">
            <div class="pf-panel-heading"><div><h2 id="pf-work-title">Classwork for this class</h2><p>Activities, quizzes, exams, and projects <span class="pf-dot">·</span> <?= count($classwork) ?> <?= count($classwork) === 1 ? 'item' : 'items' ?><?= $draftCount ? ' · ' . $draftCount . ' draft' . ($draftCount === 1 ? '' : 's') : '' ?></p></div>
                <a class="pf-button pf-primary" href="professor_classwork.php?id=<?= (int) $professorSelectedClass['subject_id'] ?>&amp;class=<?= $professorClassId ?>"><span aria-hidden="true">+</span> Create classwork</a>
            </div>
            <div class="pf-filters" role="group" aria-label="Filter classwork">
                <?php foreach (['all' => 'All'] + $categoryLabels as $key => $label): ?><button type="button" data-pf-filter="<?= $key ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"><?= $label ?><span><?= $counts[$key] ?></span></button><?php endforeach; ?>
            </div>
            <div class="pf-work-list">
                <?php foreach ($classwork as $item): $target = 'professor_classwork.php?' . http_build_query(['id' => $professorSelectedClass['subject_id'], 'class' => $professorClassId] + ($item['status'] === 'draft' ? ['work' => $item['id']] : ['responses' => $item['id']])); ?>
                    <a class="pf-work-row" data-pf-category="<?= kns_subject_escape($item['category']) ?>" href="<?= kns_subject_escape($target . ($item['status'] === 'published' ? '#cw-responses' : '')) ?>">
                        <span class="pf-item-icon pf-category-<?= kns_subject_escape($item['category']) ?>"><?= substr($categoryLabels[$item['category']], 0, 1) ?></span>
                        <span class="pf-item-copy"><strong><?= kns_subject_escape($item['title']) ?></strong><small><?= kns_subject_escape($categoryLabels[$item['category']] . ' · ' . $professorSelectedClass['section'] . ' · Created ' . substr($item['created_at'], 0, 10)) ?></small></span>
                        <span class="pf-status <?= $item['status'] === 'draft' ? 'is-draft' : '' ?>"><?= ucfirst($item['status']) ?></span><span class="pf-row-arrow"><?= kns_ui_icon('arrow') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="pf-empty" id="pf-work-empty" <?= $classwork ? 'hidden' : '' ?>><span class="pf-empty-icon"><?= kns_ui_icon('record') ?></span><h3><?= $classwork ? 'No items in this category' : 'Create your first classwork' ?></h3><p>Build questions manually or import a document, then review and publish for this class.</p></div>
            <p class="pf-panel-footnote">Drafts stay private. Select a draft to edit, or a published item to view submissions.</p>
        </section>
    <?php elseif ($professorView === 'students'): ?>
        <section class="pf-panel" aria-labelledby="pf-students-title">
            <div class="pf-panel-heading"><div><h2 id="pf-students-title">Students in this class <span class="pf-count"><?= count($roster) ?></span></h2><p>Verified active enrollments for <?= kns_subject_escape($professorSelectedClass['section']) ?>.</p></div><label class="pf-search"><?= kns_ui_icon('search') ?><span class="pf-visually-hidden">Search student accounts</span><input type="search" data-pf-student-search placeholder="Search students"></label></div>
            <div class="pf-table-scroll"><table class="pf-student-table"><thead><tr><th scope="col">Student account</th><th scope="col">Section</th><th scope="col">Enrollment</th><th scope="col"><span class="pf-visually-hidden">Actions</span></th></tr></thead><tbody>
                <?php foreach ($roster as $member): ?><tr data-pf-student="<?= kns_subject_escape($member['username']) ?>"><td><span class="pf-student-identity"><span class="pf-avatar"><?= kns_subject_escape(kns_professor_initials($member['username'])) ?></span><strong><?= kns_subject_escape($member['username']) ?></strong></span></td><td><?= kns_subject_escape($professorSelectedClass['section']) ?></td><td><span class="pf-status">Active</span></td><td><a class="pf-text-link" href="<?= kns_subject_escape(kns_professor_url('messages', $professorClassId, ['student' => $member['enrollment_id']])) ?>"><?= kns_ui_icon('chat') ?> Message</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <p class="pf-empty" id="pf-student-empty" <?= $roster ? 'hidden' : '' ?>>No matching student accounts.</p>
            <p class="pf-panel-footnote"><?= kns_subject_escape($professorSelectedClass['academic_year'] . ' · ' . $professorSelectedClass['semester']) ?>. Student account IDs are shown because verified full names are not available.</p>
        </section>
    <?php else: ?>
        <section class="pf-panel pf-messages" aria-label="Private student conversations">
            <aside class="pf-conversations" aria-label="Students in this class"><div class="pf-conversation-heading"><h2>Student messages</h2><p><?= count($roster) ?> class <?= count($roster) === 1 ? 'conversation' : 'conversations' ?></p></div>
                <?php foreach ($roster as $member): ?><a class="pf-conversation-link" href="<?= kns_subject_escape(kns_professor_url('messages', $professorClassId, ['student' => $member['enrollment_id']])) ?>" <?= $conversation && (int) $conversation['id'] === (int) $member['enrollment_id'] ? 'aria-current="true"' : '' ?>><span class="pf-avatar"><?= kns_subject_escape(kns_professor_initials($member['username'])) ?></span><span><strong><?= kns_subject_escape($member['username']) ?></strong><small><?= kns_subject_escape($member['last_message_at'] ? ($member['last_message'] !== '' ? mb_substr($member['last_message'], 0, 65) : 'Photo or file attachment') : 'No messages yet') ?></small></span></a><?php endforeach; ?>
            </aside>
            <div class="pf-conversation" id="pf-conversation">
                <?php if (!$conversation): ?><div class="pf-empty"><span class="pf-empty-icon"><?= kns_ui_icon('chat') ?></span><h3>Choose a student conversation</h3><p>Messages are private to the student's subject enrollment.</p></div>
                <?php else: ?>
                    <header class="pf-thread-heading"><span class="pf-avatar"><?= kns_subject_escape(kns_professor_initials($conversation['username'])) ?></span><div><h2><?= kns_subject_escape($conversation['username']) ?></h2><p><?= kns_subject_escape($professorSelectedClass['subject_name'] . ' · ' . $professorSelectedClass['section']) ?></p></div><span class="pf-private">Private</span></header>
                    <div class="pf-thread" aria-label="Message history">
                        <?php if (!$history['messages']): ?><div class="pf-empty"><span class="pf-empty-icon"><?= kns_ui_icon('chat') ?></span><h3>Start a conversation</h3><p>Send a message about this class. Student photos and files will appear here when shared.</p></div><?php endif; ?>
                        <?php if (count($history['messages']) === 100): ?><p class="pf-history-note">Showing the latest 100 messages.</p><?php endif; ?>
                        <?php foreach ($history['messages'] as $entry): ?><article class="pf-message <?= (int) $entry['sender_id'] === (int) $professor['id'] ? 'is-mine' : '' ?>"><div class="pf-message-meta"><strong><?= (int) $entry['sender_id'] === (int) $professor['id'] ? 'You' : ((int) $entry['sender_id'] === (int) $conversation['student_id'] ? kns_subject_escape($conversation['username']) : 'Instructor') ?></strong><time><?= kns_subject_escape($entry['created_at']) ?></time></div><p class="pf-message-topic"><?= kns_subject_escape($entry['subject_module']) ?></p><?php if ($entry['message'] !== ''): ?><p class="pf-message-text"><?= kns_subject_escape($entry['message']) ?></p><?php endif; ?>
                            <?php foreach ($history['attachments'][$entry['id']] ?? [] as $attachment): ?><a class="pf-attachment" href="professor_attachment.php?id=<?= (int) $attachment['id'] ?>&amp;download=1"><?php if ($attachment['attachment_kind'] === 'photo'): ?><img src="professor_attachment.php?id=<?= (int) $attachment['id'] ?>" alt="<?= kns_subject_escape($attachment['original_name']) ?>" loading="lazy" width="240" height="160"><?php endif; ?><strong><?= kns_subject_escape($attachment['original_name']) ?></strong><small><?= kns_subject_file_size((int) $attachment['byte_size']) ?> · Download</small></a><?php endforeach; ?>
                        </article><?php endforeach; ?>
                    </div>
                    <form class="pf-reply-form" method="post" action="<?= kns_subject_escape(kns_professor_url('messages', $professorClassId, ['student' => $conversation['id']])) ?>#pf-conversation">
                        <input type="hidden" name="csrf_token" value="<?= kns_subject_escape($csrfToken) ?>"><input type="hidden" name="class_id" value="<?= $professorClassId ?>"><input type="hidden" name="enrollment_id" value="<?= (int) $conversation['id'] ?>">
                        <label class="pf-topic-label" for="pf-reply-topic">Module / topic<input id="pf-reply-topic" name="topic" maxlength="100" required value="<?= kns_subject_escape($topicValue) ?>"></label>
                        <label class="pf-visually-hidden" for="pf-reply">Message to student</label><textarea id="pf-reply" name="message" rows="3" maxlength="5000" required placeholder="Write a message to this student…"><?= kns_subject_escape($replyValue) ?></textarea>
                        <div class="pf-reply-actions"><span>Private to this subject conversation</span><button class="pf-button pf-primary" type="submit" name="send_reply" value="1"><?= kns_ui_icon('send') ?> Send message</button></div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
<?php require __DIR__ . '/app/views/partials/professor_shell_end.php'; ?>
