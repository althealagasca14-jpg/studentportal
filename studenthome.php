<?php
session_start();
require_once __DIR__ . '/app/helpers/portal_support.php';
require_once __DIR__ . '/app/helpers/student_ui.php';
if (!isset($_SESSION['username'], $_SESSION['usertype']) || $_SESSION['usertype'] !== 'student') {
    header('Location: login.php');
    exit;
}
try {
    $data = kns_subject_db();
    $student = kns_portal_user($data, ['student']);
    if (!$student) {
        http_response_code(403);
        exit('Your student account could not be verified.');
    }
    $subjects = $data->query('SELECT id, subject_name, instructor, description FROM subjects ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $enrollmentRows = [];
    if (kns_subject_schema_ready($data)) {
        $query = $data->prepare('SELECT e.subject_id,e.section,e.academic_year,e.semester,COALESCE(c.code,\'\') AS course FROM subject_enrollments e LEFT JOIN courses c ON c.id=COALESCE(e.course_id,?) WHERE e.student_id=? AND e.is_active=1');
        $query->bind_param('ii', $student['course_id'], $student['id']);
        $query->execute();
        $enrollmentRows = $query->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (InvalidArgumentException $error) {
    http_response_code(403); exit('Your account session is no longer valid. Please sign in again.');
} catch (Throwable $error) {
    error_log('[student-dashboard] ' . $error->getMessage());
    http_response_code(503);
    exit('The student portal is temporarily unavailable. Please try again later.');
}
$bySubject = [];
$choices = ['academic_year' => [], 'section' => [], 'semester' => [], 'course' => []];
$terms = [];
foreach ($enrollmentRows as $row) {
    $bySubject[$row['subject_id']][] = $row;
    foreach ($choices as $field => $values) {
        if ($row[$field] !== '') $choices[$field][$row[$field]] = $row[$field];
    }
    $terms[$row['academic_year'] . ' · ' . $row['semester']] = true;
}
foreach ($choices as &$values) { $values = array_values($values); sort($values); }
unset($values);
$tasks = kns_portal_rows($data, "SELECT DISTINCT w.id,e.id AS enrollment_id,w.due_at,w.created_at,sub.id AS submission_id FROM subject_enrollments e
    JOIN subject_classwork w ON w.subject_id=e.subject_id AND w.section=e.section AND w.academic_year=e.academic_year AND w.semester=e.semester AND w.status='published'
    JOIN subject_sections ss ON ss.subject_id=w.subject_id AND ss.section=w.section AND ss.academic_year=w.academic_year AND ss.semester=w.semester AND ss.professor_id=w.professor_id
    JOIN user p ON p.id=w.professor_id AND p.account_status='active'
    LEFT JOIN subject_classwork_submissions sub ON sub.classwork_id=w.id AND sub.enrollment_id=e.id WHERE e.student_id=? AND e.is_active=1", [$student['id']]);
$totalTasks=count($tasks);$completed=count(array_filter($tasks,fn($task)=>$task['submission_id']!==null));$remaining=$totalTasks-$completed;
$completion=$totalTasks?(int)round($completed/$totalTasks*100):0;
$weekStart=strtotime('today -'.date('w').' days');$weekly=array_fill(0,7,0);
foreach($tasks as $task) if($task['due_at']) { $day=(int)floor((strtotime($task['due_at'])-$weekStart)/86400);if($day>=0&&$day<7)$weekly[$day]++; }
$studentNotifications=kns_portal_rows($data,'SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 20',[$student['id']]);
$view = ($_GET['view'] ?? '') === 'subjects' ? 'subjects' : 'dashboard';
$hasEnrollments = count($bySubject) > 0;
$termLabel = count($terms) === 1 ? array_key_first($terms) : (count($terms) > 1 ? 'Multiple enrolled terms' : 'Term not linked');
$subjectData = [];
foreach ($subjects as $subject) {
    $subjectData[] = ['id' => (int) $subject['id'], 'name' => $subject['subject_name'], 'instructor' => $subject['instructor'],
        'enrollments' => $bySubject[$subject['id']] ?? []];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#133e30">
    <title>Student Portal | Kolehiyo ng Subic</title>
    <link rel="stylesheet" href="assets/css/student-portal.css?v=1">
    <script src="assets/js/student-portal.js?v=1" defer></script><script src="assets/js/portal-forms.js" defer></script>
</head>
<body class="student-shell" data-current-view="<?= $view ?>">
    <a href="#student-main" class="ui-skip">Skip to content</a>
    <aside class="student-sidebar" id="student-sidebar" aria-label="Student menu">
        <div>
            <div class="sidebar-brand">
                <span class="brand-mark"><?= kns_ui_icon('cap') ?></span>
                <span><strong>KNS Portal</strong><small>Student Subject Portal</small></span>
                <button class="icon-button sidebar-close" type="button" aria-label="Close menu"><?= kns_ui_icon('close') ?></button>
            </div>
            <p class="sidebar-caption">Student menu</p>
            <nav class="student-nav">
                <a href="classes.php"><?= kns_ui_icon('book') ?><span>My classes / join</span></a>
                <a href="studenthome.php" data-student-nav="dashboard" <?= $view === 'dashboard' ? 'aria-current="page"' : '' ?>><?= kns_ui_icon('home') ?><span>Dashboard</span></a>
                <a href="studenthome.php?view=subjects#subjectsSection" data-student-nav="subjects" <?= $view === 'subjects' ? 'aria-current="page"' : '' ?>><?= kns_ui_icon('book') ?><span>My Subjects</span></a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <div class="sidebar-account"><span class="account-avatar"><?= kns_subject_escape(mb_strtoupper(mb_substr($student['username'], 0, 2))) ?></span><span><strong><?= kns_subject_escape($student['username']) ?></strong><small>Student Account</small></span></div>
            <a class="sidebar-signout" href="logout.php"><?= kns_ui_icon('logout') ?>Sign out</a>
        </div>
    </aside>
    <button class="sidebar-shade" type="button" aria-label="Close student menu" hidden></button>
    <main class="student-main" id="student-main" tabindex="-1">
        <header class="student-heading portal-page-heading">
            <div class="heading-copy">
                <button class="icon-button menu-toggle" type="button" aria-label="Open student menu" aria-controls="student-sidebar" aria-expanded="false"><?= kns_ui_icon('menu') ?></button>
                <p class="ui-eyebrow" id="student-eyebrow"><?= $view === 'dashboard' ? 'Student overview' : 'Your classes' ?></p>
                <h1 id="student-page-title" data-welcome="<?= kns_subject_escape('Welcome back, ' . $student['username']) ?>"><?= $view === 'dashboard' ? kns_subject_escape('Welcome back, ' . $student['username']) : 'My subjects' ?></h1>
                <p class="heading-description" id="student-page-description"><?= $view === 'dashboard' ? 'Here’s a clear view of your workload and subjects.' : 'Browse your subjects and open a class portal.' ?></p>
            </div>
            <div class="heading-actions"><span class="term-pill"><span></span><?= kns_subject_escape($termLabel) ?></span>
                <details class="student-notifications"><summary class="icon-button" aria-label="Notifications"><?= kns_ui_icon('bell') ?></summary><div><?php if (!$studentNotifications): ?>No notifications yet.<?php endif; ?><?php foreach ($studentNotifications as $notice): ?><p><strong><?= kns_subject_escape($notice['title']) ?></strong><br><?= kns_subject_escape($notice['body']) ?><small><?= kns_subject_escape($notice['created_at']) ?></small></p><?php endforeach; ?><?php if ($studentNotifications): ?><form method="post" action="portal_action.php" data-portal-form data-reload="true"><input type="hidden" name="csrf_token" value="<?= kns_subject_escape(kns_subject_csrf_token()) ?>"><input type="hidden" name="action" value="notifications_read"><p data-form-status role="status"></p><button type="submit">Mark all read</button></form><?php endif; ?></div></details>
            </div>
        </header>

        <section id="student-overview" <?= $view !== 'dashboard' ? 'hidden' : '' ?> aria-label="Student overview">
            <div class="student-metrics">
                <?php foreach ([
                    ['Total tasks', $totalTasks, 'Published items in your enrolled classes', 'record'],
                    ['Completed', $completed, 'Answers submitted for professor review', 'check'],
                    ['Remaining', $remaining, 'Published items awaiting your submission', 'clock'],
                    ['Workload status', $remaining ? 'In progress' : 'Up to date', $remaining ? 'Continue your remaining classwork' : 'No outstanding published items', 'trend']
                ] as $metric): ?>
                    <article class="ui-card metric-tile">
                        <div class="metric-heading"><h2><?= $metric[0] ?></h2><span class="soft-icon"><?= kns_ui_icon($metric[3]) ?></span></div>
                        <p class="metric-value <?= $metric[0] === 'Workload status' ? 'metric-status' : '' ?>"><?= $metric[1] ?></p>
                        <p class="metric-caption"><?= $metric[2] ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="student-charts">
                <section class="ui-card workload-card" aria-labelledby="workload-title">
                    <div class="panel-heading"><div><h2 id="workload-title">Weekly workload</h2><p>Tasks due this week · Sunday to Saturday</p></div><span class="chart-key"><i></i>Workload level</span></div>
                    <div class="workload-chart" role="img" aria-label="Weekly task due-date counts from enrolled classes">
                        <div class="workload-grid"><span></span><span></span><span></span><span></span></div>
                        <div class="workload-live-bars" aria-label="<?= kns_subject_escape(implode(', ', $weekly)) ?>"><?php foreach ($weekly as $amount): ?><div><span><?= $amount ?></span><i style="height:<?= $amount ? max(8, (int)round($amount/max(1,max($weekly))*100)) : 0 ?>%"></i></div><?php endforeach; ?></div>
                        <div class="chart-days"><?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day): ?><span><?= $day ?></span><?php endforeach; ?></div>
                    </div>
                </section>
                <section class="ui-card completion-card" aria-labelledby="completion-title">
                    <div class="panel-heading"><div><h2 id="completion-title">Completion rate</h2><p>Your current term progress</p></div></div>
                    <div class="completion-ring" role="img" aria-label="<?= $completion ?> percent completed" style="background:conic-gradient(#169b66 <?= $completion ?>%,#e9efec 0)"><span><strong><?= $completion ?>%</strong><small>completed</small></span></div>
                    <p class="completion-caption"><?= $completed ?> of <?= $totalTasks ?> published items submitted</p><p class="completion-note">Only verified enrolled classes are included. Tasks without a due date are excluded from the weekly chart.</p>
                </section>
            </div>
        </section>

        <section id="subjectsSection" aria-labelledby="subjects-title">
            <div class="subjects-heading"><div><h2 id="subjects-title"><?= $view === 'subjects' ? 'Your current classes' : ($hasEnrollments ? 'My enrolled subjects' : 'My subjects') ?></h2><p id="subjects-description"><?= $view === 'subjects' ? 'Open a subject to view its portal page.' : 'Quick access to your subject portals' ?></p></div>
                <a class="view-all" href="studenthome.php?view=subjects#subjectsSection" data-student-nav="subjects" <?= $view === 'subjects' ? 'hidden' : '' ?>>View all subjects <?= kns_ui_icon('arrow') ?></a>
                <span class="subject-count-badge" id="subject-count-badge" <?= $view !== 'subjects' ? 'hidden' : '' ?>><?= count($subjects) ?> subjects</span>
            </div>
            <div id="subject-tools" <?= $view !== 'subjects' ? 'hidden' : '' ?>>
                <div class="subject-search-row"><label class="subject-search" for="subject-search"><?= kns_ui_icon('search') ?><input type="search" id="subject-search" placeholder="Search by subject or professor" autocomplete="off"></label><p id="subject-results-count" role="status" aria-live="polite">Showing <?= count($subjects) ?> of <?= count($subjects) ?> subjects</p></div>
                <section class="ui-card subject-filter-panel" aria-labelledby="subject-filter-title">
                    <div class="filter-panel-heading"><span class="soft-icon"><?= kns_ui_icon('filter') ?></span><div><h3 id="subject-filter-title">Filter subjects</h3><p>Narrow your list by academic year, course, section, or semester.</p></div></div>
                    <div class="subject-filter-grid">
                        <?php foreach (['academic_year' => ['Academic year', 'All academic years'], 'course' => ['Course', 'All courses'], 'section' => ['Section', 'All sections'], 'semester' => ['Semester', 'All semesters']] as $field => $labels): ?>
                            <label><?= $labels[0] ?><select data-subject-filter="<?= $field ?>" <?= empty($choices[$field]) ? 'disabled title="No verified records for this filter yet."' : '' ?>><option value=""><?= $labels[1] ?></option>
                                <?php foreach ($choices[$field] ?? [] as $choice): ?><option value="<?= kns_subject_escape($choice) ?>"><?= kns_subject_escape($choice) ?></option><?php endforeach; ?>
                            </select></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="filter-hint">Filter choices come from your verified enrollment records. Course choices come from the enrolled student profile.</p>
                </section>
            </div>
            <?php if (!$hasEnrollments): ?><p class="catalog-note">Enrollment verification is pending. These are the subjects available in the portal.</p><?php endif; ?>
            <div class="subject-grid" id="subject-grid">
                <?php foreach ($subjects as $index => $subject): ?>
                    <article class="ui-card subject-card portal-subject-card" data-subject-id="<?= (int) $subject['id'] ?>" <?= $view === 'dashboard' && $index >= 3 ? 'hidden' : '' ?>>
                        <div class="subject-card-top"><span class="soft-icon"><?= kns_ui_icon('record') ?></span><span class="subject-status"><?= isset($bySubject[$subject['id']]) ? 'Enrolled' : 'Subject' ?></span></div>
                        <h3><?= kns_subject_escape($subject['subject_name']) ?></h3>
                        <p class="subject-professor"><?= kns_ui_icon('user') ?><?= kns_subject_escape($subject['instructor']) ?></p>
                        <p class="subject-description"><?= kns_subject_escape($subject['description']) ?></p>
                        <div class="subject-card-actions">
                            <a href="subjectview.php?id=<?= (int) $subject['id'] ?>" class="subject-open" data-subject-open="classroom">Open subject page <?= kns_ui_icon('arrow') ?></a>
                            <a href="subjectview.php?id=<?= (int) $subject['id'] ?>&amp;view=message" class="subject-message-open" data-subject-open="message"><?= kns_ui_icon('chat') ?>Message</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="ui-card subject-search-empty" id="subject-search-empty" <?= $subjects ? 'hidden' : '' ?>><span class="soft-icon"><?= kns_ui_icon('search') ?></span><h3>No subjects found</h3><p>Try a different search or change your filters.</p><button type="button" id="reset-subject-filters">Clear search and filters</button></div>
        </section>
    </main>
    <dialog class="subject-dialog" id="subject-dialog" aria-label="Subject classroom">
        <button class="icon-button subject-dialog-close" type="button" aria-label="Close subject panel"><?= kns_ui_icon('close') ?></button>
        <p class="subject-dialog-loading" role="status">Loading subject…</p>
        <iframe id="subject-dialog-frame" title="Subject classroom" src="about:blank"></iframe>
    </dialog>
    <script type="application/json" id="student-subject-data"><?= json_encode($subjectData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</body>
</html>
