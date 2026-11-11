<?php
session_start();
require_once __DIR__ . '/subject_support.php';
require_once __DIR__ . '/student_ui.php';
if (!isset($_SESSION['username'], $_SESSION['usertype']) || $_SESSION['usertype'] !== 'student') {
    header('Location: login.php');
    exit;
}
try {
    $data = kns_subject_db();
    $student = kns_subject_student($data);
    if (!$student) {
        http_response_code(403);
        exit('Your student account could not be verified.');
    }
    $subjects = $data->query('SELECT id, subject_name, instructor, description FROM subjects ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $enrollmentRows = [];
    if (kns_subject_schema_ready($data)) {
        $query = $data->prepare('SELECT subject_id, section, academic_year, semester FROM subject_enrollments WHERE student_id = ? AND is_active = 1');
        $query->bind_param('i', $student['id']);
        $query->execute();
        $enrollmentRows = $query->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $error) {
    error_log('[student-dashboard] ' . $error->getMessage());
    http_response_code(503);
    exit('The student portal is temporarily unavailable. Please try again later.');
}
$bySubject = [];
$choices = ['academic_year' => [], 'section' => [], 'semester' => []];
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
    <link rel="stylesheet" href="student-portal.css?v=1">
    <script src="student-portal.js?v=1" defer></script>
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
        <header class="student-heading">
            <div class="heading-copy">
                <button class="icon-button menu-toggle" type="button" aria-label="Open student menu" aria-controls="student-sidebar" aria-expanded="false"><?= kns_ui_icon('menu') ?></button>
                <p class="ui-eyebrow" id="student-eyebrow"><?= $view === 'dashboard' ? 'Student overview' : 'Your classes' ?></p>
                <h1 id="student-page-title" data-welcome="<?= kns_subject_escape('Welcome back, ' . $student['username']) ?>"><?= $view === 'dashboard' ? kns_subject_escape('Welcome back, ' . $student['username']) : 'My subjects' ?></h1>
                <p class="heading-description" id="student-page-description"><?= $view === 'dashboard' ? 'Here’s a clear view of your workload and subjects.' : 'Browse your subjects and open a class portal.' ?></p>
            </div>
            <div class="heading-actions"><span class="term-pill"><span></span><?= kns_subject_escape($termLabel) ?></span>
                <details class="student-notifications"><summary class="icon-button" aria-label="Notifications"><?= kns_ui_icon('bell') ?></summary><div>No notifications are available. Notification updates are not connected yet.</div></details>
            </div>
        </header>

        <section id="student-overview" <?= $view !== 'dashboard' ? 'hidden' : '' ?> aria-label="Student overview">
            <div class="student-metrics">
                <?php foreach ([
                    ['Total tasks', '—', 'Waiting for instructor records', 'record'],
                    ['Completed', '—', 'Submission data not connected', 'check'],
                    ['Remaining', '—', 'Task records not connected', 'clock'],
                    ['Workload status', 'Not available', 'Connect task records to view status', 'trend']
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
                    <div class="panel-heading"><div><h2 id="workload-title">Weekly workload</h2><p>Task activity across the week</p></div><span class="chart-key"><i></i>Workload level</span></div>
                    <div class="workload-chart" role="img" aria-label="Weekly workload chart unavailable until instructor task records are connected">
                        <div class="workload-grid"><span></span><span></span><span></span><span></span></div>
                        <p class="chart-unavailable">Your workload will appear here<br><small>Instructor task records are not connected yet.</small></p>
                        <div class="chart-days"><?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day): ?><span><?= $day ?></span><?php endforeach; ?></div>
                    </div>
                </section>
                <section class="ui-card completion-card" aria-labelledby="completion-title">
                    <div class="panel-heading"><div><h2 id="completion-title">Completion rate</h2><p>Your current term progress</p></div></div>
                    <div class="completion-ring" role="img" aria-label="Completion rate unavailable"><span><strong>—</strong><small>not available</small></span></div>
                    <p class="completion-caption">Waiting for submission records</p><p class="completion-note">Your progress will appear when records are connected.</p>
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
                    <p class="filter-hint">Filter choices come from your verified enrollment records. Course data is not available yet.</p>
                </section>
            </div>
            <?php if (!$hasEnrollments): ?><p class="catalog-note">Enrollment verification is pending. These are the subjects available in the portal.</p><?php endif; ?>
            <div class="subject-grid" id="subject-grid">
                <?php foreach ($subjects as $index => $subject): ?>
                    <article class="ui-card subject-card" data-subject-id="<?= (int) $subject['id'] ?>" <?= $view === 'dashboard' && $index >= 3 ? 'hidden' : '' ?>>
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
