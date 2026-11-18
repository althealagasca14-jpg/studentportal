<?php
// Shared only by the professor dashboard and its existing classwork builder.
$professorView = $professorView ?? 'classwork';
$professorPageTitle = $professorPageTitle ?? 'Professor portal';
$professorPageDescription = $professorPageDescription ?? 'Manage classwork and communicate with your students.';
$professorClasses = $professorClasses ?? [];
$professorSelectedClass = $professorSelectedClass ?? null;
$professorClassId = (int) ($professorSelectedClass['class_id'] ?? 0);
$professorShowPicker = $professorShowPicker ?? true;
?>
<body class="subject-shell professor-shell">
<a href="#professor-main" class="pf-skip">Skip to workspace</a>
<aside class="pf-sidebar" aria-label="Professor workspace">
    <a class="pf-brand" href="<?= kns_subject_escape(kns_professor_url('classwork', $professorClassId)) ?>"><span class="pf-brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 6.5C9.5 5 6 4.5 3 5v14c3-.5 6.5 0 9 1.5m0-14c2.5-1.5 6-2 9-1.5v14c-3-.5-6.5 0-9 1.5m0-14v14"/></svg></span><span><strong>KNS Portal</strong><small>Professor workspace</small></span></a>
    <p class="pf-nav-label">Workspace</p>
    <nav class="pf-nav" aria-label="Class management"><a href="classes.php">Subjects &amp; sections</a></nav>
    <nav class="pf-nav" aria-label="Workspace navigation">
        <?php foreach (['classwork' => ['record', 'Classwork'], 'messages' => ['chat', 'Student messages'], 'students' => ['user', 'Students']] as $pfKey => $pfItem): ?>
            <a href="<?= kns_subject_escape(kns_professor_url($pfKey, $professorClassId)) ?>" <?= $professorView === $pfKey ? 'aria-current="page"' : '' ?>><?= kns_ui_icon($pfItem[0]) ?><span><?= $pfItem[1] ?></span></a>
        <?php endforeach; ?>
    </nav>
    <div class="pf-account">
        <div><span class="pf-avatar"><?= kns_subject_escape(kns_professor_initials($professor['username'] ?? 'Professor')) ?></span><span><strong><?= kns_subject_escape($professor['username'] ?? 'Professor') ?></strong><small>Professor account</small></span></div>
        <a href="logout.php"><?= kns_ui_icon('logout') ?> Sign out</a>
    </div>
</aside>
<main class="pf-main" id="professor-main">
    <header class="pf-heading pf-heading--portal">
        <div><p class="pf-eyebrow">Faculty workspace</p><h1><?= kns_subject_escape($professorPageTitle) ?></h1><p><?= kns_subject_escape($professorPageDescription) ?></p></div>
        <?php if ($professorSelectedClass): ?><span class="pf-term"><?= kns_subject_escape('AY ' . $professorSelectedClass['academic_year'] . ' · ' . $professorSelectedClass['semester']) ?></span><?php endif; ?>
    </header>
    <?php if ($professorShowPicker && $professorClasses): ?>
        <section class="pf-class-picker" aria-labelledby="pf-class-label">
            <span class="pf-class-icon"><?= kns_ui_icon('book') ?></span><div><h2 id="pf-class-label">Selected class</h2><p>Each post and conversation stays within this class context.</p></div>
            <form action="professorhome.php" method="get">
                <input type="hidden" name="view" value="<?= kns_subject_escape($professorView) ?>">
                <label class="pf-visually-hidden" for="pf-class-select">Subject, section, and term</label>
                <select id="pf-class-select" name="class">
                    <?php foreach ($professorClasses as $pfClass): ?><option value="<?= (int) $pfClass['class_id'] ?>" <?= (int) $pfClass['class_id'] === $professorClassId ? 'selected' : '' ?>><?= kns_subject_escape($pfClass['subject_name'] . ' · ' . $pfClass['section'] . ' · ' . $pfClass['academic_year'] . ' · ' . $pfClass['semester']) ?></option><?php endforeach; ?>
                </select><button type="submit" class="pf-button pf-picker-go">Open class</button>
            </form>
        </section>
    <?php endif; ?>
    <?php if($professorSelectedClass):?><nav class="pf-tabs" aria-label="Class communication"><a href="classroom.php?class=<?= $professorClassId ?>&amp;tab=updates">Class updates</a><a href="classroom.php?class=<?= $professorClassId ?>&amp;tab=chat">Official group chat</a></nav><?php endif;?>
    <nav class="pf-tabs" aria-label="Selected class">
        <?php foreach (['classwork' => 'Classwork', 'messages' => 'Student messages', 'students' => 'Students'] as $pfKey => $pfLabel): ?><a href="<?= kns_subject_escape(kns_professor_url($pfKey, $professorClassId)) ?>" <?= $professorView === $pfKey ? 'aria-current="page"' : '' ?>><?= $pfLabel ?></a><?php endforeach; ?>
    </nav>
