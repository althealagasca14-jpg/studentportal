<?php
session_start();

if (!isset($_SESSION['username'] ) || $_SESSION['usertype'] !== 'admin') {
    header("location: login.php");
    exit();
}

require_once __DIR__ . '/app/helpers/portal_support.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try { $adminDb = kns_subject_db(); $adminUser = kns_portal_user($adminDb, ['admin']); }
catch (Throwable $error) { http_response_code(403); exit('Please sign in with an active administrator account.'); }
$adminCsrf = kns_subject_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= kns_subject_escape($adminCsrf) ?>">
    <script src="assets/js/portal-forms.js" defer></script>
    <link rel="icon" href="assets/images/logo.jpg" type="image/jpeg"><title>Overview · Faculty administration · KNS Portal</title>
    <link rel="stylesheet" href="assets/css/admin-portal.css">
    <script src="assets/js/admin-portal.js" defer></script>
</head>
<body class="admin-shell">
<svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="i-cap" viewBox="0 0 24 24"><path d="m2 9 10-5 10 5-10 5L2 9Zm4 2v6c4 3 8 3 12 0v-6m4-2v8"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m1-17a3 3 0 0 1 0 6m3 11v-2a6 6 0 0 0-2-4"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 10 10 0 0 1-4-.8L3 21l1.8-5.5A10 10 0 0 1 4 11.5 8.5 8.5 0 1 1 21 11.5Z"/></symbol>
    <symbol id="i-book" viewBox="0 0 24 24"><path d="M5 3h15v18H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 0v14h15M8 7h8M8 10h6"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18M7 15l5-5 4 3 5-7"/></symbol>
    <symbol id="i-settings" viewBox="0 0 24 24"><path d="m9 3-.7 3-2.6 1L3 6l-2 4 2.5 2-.2 3L1 17l2 4 3-1 2.5 1L9 24h5l.6-3 2.6-1 2.8 1 2-4-2.5-2 .2-3 2.3-2-2-4-3 1-2.5-1L14 3Z" transform="translate(1 -1) scale(.9)"/><circle cx="12" cy="11" r="3"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="10" cy="10" r="6.5"/><path d="m15 15 6 6"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M4 12h16m-6-6 6 6-6 6"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v12m-4-4 4 4 4-4M4 16v5h16v-5"/></symbol>
    <symbol id="i-clip" viewBox="0 0 24 24"><path d="m8 13 7-7a3 3 0 0 1 4 4L9 20a5 5 0 0 1-7-7L13 2a2 2 0 0 1 3 3L6 15a1 1 0 0 0 2 2l8-8"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24"><path d="m3 3 19 9-19 9 4-9-4-9Zm4 9h15"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4Z"/><path d="m8 12 3 3 5-6"/></symbol>
</svg>
<a class="admin-skip" href="#admin-main">Skip to workspace</a>
<aside class="admin-sidebar" aria-label="Faculty administration">
    <div class="sidebar-top">
        <a class="admin-brand" href="#overview"><span class="brand-mark"><svg class="icon"><use href="#i-cap"/></svg></span><span><strong>KNS Portal</strong><small>Faculty administration</small></span></a>
        <button class="icon-button menu-toggle" id="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="admin-navigation"><svg class="icon"><use href="#i-menu"/></svg></button>
    </div>
    <div class="sidebar-navigation" id="admin-navigation">
        <p class="nav-label">Workspace</p>
        <nav class="admin-nav" aria-label="Workspace navigation">
            <a href="#overview" data-screen-link="overview" aria-current="page"><svg class="icon"><use href="#i-grid"/></svg><span>Overview</span></a>
            <a href="#professors" data-screen-link="professors"><svg class="icon"><use href="#i-users"/></svg><span>Professor accounts</span></a>
            <a href="#messages" data-screen-link="messages"><svg class="icon"><use href="#i-chat"/></svg><span>Admin–professor messages</span><span class="nav-count" aria-label="Unread professor messages">0</span></a>
            <a href="#subjects" data-screen-link="subjects"><svg class="icon"><use href="#i-book"/></svg><span>Subjects &amp; sections</span></a>
            <a href="#reports" data-screen-link="reports"><svg class="icon"><use href="#i-chart"/></svg><span>Reports</span></a>
        </nav>
        <div class="sidebar-bottom">
            <p class="nav-label">Administration</p>
            <nav class="admin-nav" aria-label="Administration navigation"><a href="#settings" data-screen-link="settings"><svg class="icon"><use href="#i-settings"/></svg><span>Settings</span></a></nav>
            <div class="sidebar-account"><span class="avatar avatar-admin">AD</span><span><strong>Portal administrator</strong><small>Administrator account</small></span><span class="account-dot" aria-hidden="true"></span></div>
        </div>
    </div>
<a class="text-link" style="margin:16px;color:#d8eee2 !important" href="logout.php">Sign out</a>
</aside>
<main class="admin-main" id="admin-main">
    <p class="sample-notice" id="admin-load-error" role="alert" hidden></p>
    <header class="admin-topbar">
        <p class="breadcrumb">KNS Portal <span aria-hidden="true">/</span> <strong id="breadcrumb-label">Overview</strong></p>
        <div class="topbar-actions"><span class="preview-status"><span></span>Database connected</span><button class="icon-button" type="button" aria-label="Notifications" id="open-notifications"><svg class="icon"><use href="#i-bell"/></svg></button><a class="avatar avatar-blue" href="#settings" aria-label="Open admin settings">AD</a></div>
    </header>
    <noscript><p class="sample-notice">Enable JavaScript to use the connected admin forms and navigation.</p></noscript>

    <section class="admin-screen" id="screen-overview" aria-labelledby="overview-title" data-screen="overview">
        <div class="page-heading"><div><p class="eyebrow">Faculty administration</p><h1 id="overview-title" tabindex="-1">Faculty command center</h1><p>Manage professor access and stay connected with faculty in one place.</p></div><button class="button primary" type="button" data-register><svg class="icon"><use href="#i-plus"/></svg>Register Professor</button></div>
        <div class="metrics overview-metrics">
            <article class="metric accent-blue"><div class="metric-top"><h2>Professor accounts</h2><span class="metric-icon blue"><svg class="icon"><use href="#i-users"/></svg></span></div><strong>—</strong><p>Registered professor accounts</p></article>
            <article class="metric accent-red"><div class="metric-top"><h2>Unread professor messages</h2><span class="metric-icon rose"><svg class="icon"><use href="#i-chat"/></svg></span></div><strong>—</strong><p>From professor accounts only</p></article>
            <article class="metric accent-green"><div class="metric-top"><h2>Admin reply rate</h2><span class="metric-icon green"><svg class="icon"><use href="#i-check"/></svg></span></div><strong>—</strong><p>Replies to professor messages</p></article>
            <article class="metric accent-gold"><div class="metric-top"><h2>Current conversation</h2><span class="metric-icon gold"><svg class="icon"><use href="#i-chat"/></svg></span></div><strong class="metric-topic">No conversations</strong><p>Private faculty communication</p></article>
        </div>
        <div class="overview-grid">
            <article class="panel conversation-preview">
                <div class="panel-heading"><div><h2><span class="heading-icon"><svg class="icon"><use href="#i-chat"/></svg></span>Admin–professor messages</h2><p>Private conversations between admin and professor accounts only.</p></div><a class="text-link" href="#messages">View all conversations <span aria-hidden="true">→</span></a></div>
                <div class="preview-tools"><span class="scope-badge">Admin ↔ Professor only</span><label class="search-control"><svg class="icon"><use href="#i-search"/></svg><span class="sr-only">Search recent professor conversations</span><input id="recent-search" type="search" placeholder="Search conversations" maxlength="100"></label></div>
                <div id="recent-conversations"></div>
                <p class="empty-state" id="recent-empty" hidden>No conversations match your search.</p>
                <div class="panel-footnote">Conversations stored in the portal</div>
            </article>
            <div class="overview-side">
                <article class="panel professor-callout"><div class="panel-heading"><div><h2>Professor accounts</h2><p>Set up teaching access for your faculty.</p></div><span class="metric-icon blue"><svg class="icon"><use href="#i-users"/></svg></span></div><ul class="check-list"><li>Assign subjects and sections</li><li>Queue a secure account invitation</li><li>Choose classroom permissions</li></ul><button class="button primary full-width" type="button" data-register><svg class="icon"><use href="#i-plus"/></svg>Register Professor</button><small class="muted">Create a professor account and configure teaching access.</small></article>
                <article class="panel term-card"><p class="eyebrow">Academic coverage</p><h2>Ready for the semester</h2><p>Review teaching assignments and keep faculty in the loop.</p><div><span><strong>—</strong>Subjects</span><span><strong>—</strong>Sections</span></div><a class="text-link" href="#subjects">Review subjects &amp; sections →</a></article>
            </div>
        </div>
        <p class="screen-note">Counts and records are loaded from the portal database.</p>
    </section>

    <section class="admin-screen" id="screen-professors" aria-labelledby="professors-title" data-screen="professors" hidden>
        <div class="page-heading"><div><p class="eyebrow">Faculty directory</p><h1 id="professors-title" tabindex="-1">Professor accounts</h1><p>Create and manage faculty profiles, teaching access, and account status.</p></div><button class="button primary" type="button" data-register><svg class="icon"><use href="#i-plus"/></svg>Register Professor</button></div>
        <div class="metrics three"><article class="metric"><h2>Total professor accounts</h2><strong>—</strong><p>Registered professor accounts</p></article><article class="metric"><h2>Active</h2><strong>—</strong><p>Teaching access enabled</p></article><article class="metric"><h2>Invitations pending</h2><strong>—</strong><p>Awaiting account activation</p></article></div>
        <div class="filter-bar panel">
            <label class="search-control"><svg class="icon"><use href="#i-search"/></svg><span class="sr-only">Search professor, email, or faculty ID</span><input id="professor-search" type="search" placeholder="Search professor or faculty ID" maxlength="100"></label>
            <label><span class="sr-only">Filter professors by department</span><select id="professor-department" data-department-options><option value="">All departments</option></select></label>
            <label><span class="sr-only">Filter professors by status</span><select id="professor-status"><option value="">All statuses</option><option>Active</option><option>Invitation pending</option><option>Suspended</option></select></label>
            <span class="sample-label">Database records</span>
        </div>
        <div class="panel table-panel"><div class="table-scroll" role="region" aria-label="Professor directory" tabindex="0"><table class="directory-table professor-table"><caption class="sr-only">Professor accounts, contact details, assignments, and activity</caption><thead><tr><th scope="col">Professor / email</th><th scope="col">Faculty ID</th><th scope="col">Department</th><th scope="col">Assigned subjects</th><th scope="col">Status</th><th scope="col">Last activity</th><th scope="col">Actions</th></tr></thead><tbody id="professor-rows"></tbody></table></div><div class="empty-state" id="professor-empty" hidden><h2>No matching professors</h2><p>Try another name, department, or account status.</p><button class="button small" type="button" id="reset-professor-filters">Clear filters</button></div><div class="table-footer"><span id="professor-count" role="status"></span><span>Registered faculty accounts</span></div></div>
    </section>

    <section class="admin-screen" id="screen-messages" aria-labelledby="messages-title" data-screen="messages" hidden>
        <div class="page-heading"><div><p class="eyebrow">Private faculty communication</p><h1 id="messages-title" tabindex="-1">Admin–professor messages</h1><p>One-to-one conversations between the portal admin and professor accounts only.</p></div><span class="scope-badge">Admin ↔ Professor only</span></div>
        <div class="messaging-panel panel">
            <aside class="conversation-list" aria-label="Professor conversations"><div class="conversation-list-heading"><h2>Conversations</h2><p>Only admin and professor accounts appear here.</p><label class="search-control"><svg class="icon"><use href="#i-search"/></svg><span class="sr-only">Search professor conversations</span><input id="conversation-search" type="search" placeholder="Search professors" maxlength="100"></label></div><div id="conversation-items"></div><p class="empty-state" id="conversation-empty" hidden>No matching conversations.</p><p class="conversation-disclaimer">Messages and attachments are stored privately.</p></aside>
            <div class="chat-panel">
                <header class="chat-heading"><div class="person"><span class="avatar avatar-blue" id="chat-avatar">MS</span><div><h2 id="chat-name">Choose a professor</h2><p id="chat-department">Admin ↔ Professor only</p></div></div><span class="scope-badge">Admin ↔ Professor</span></header>
                <div class="chat-thread" id="chat-thread" role="log" aria-label="Admin and professor message history" aria-live="polite" aria-relevant="additions"></div>
                <form class="chat-composer" id="message-form" method="dialog">
                    <div class="attachment-preview" id="attachment-preview" hidden><svg class="icon"><use href="#i-clip"/></svg><span id="attachment-name"></span><small>Ready to upload</small><button class="icon-button" id="remove-attachment" type="button" aria-label="Remove attachment preview"><svg class="icon"><use href="#i-close"/></svg></button></div>
                    <label class="sr-only" for="message-text">Message to selected professor</label><textarea id="message-text" name="message" rows="2" maxlength="2000" placeholder="Write a message to this professor…"></textarea>
                    <div class="composer-actions"><div><input class="sr-only" type="file" id="message-attachment" aria-label="Select attachment (maximum 5 MiB)"><label class="button attachment-button" for="message-attachment"><svg class="icon"><use href="#i-clip"/></svg>Attach file</label><span class="composer-note">Maximum 5 MiB · private attachment</span></div><button class="button primary" type="submit"><svg class="icon"><use href="#i-send"/></svg>Send message</button></div>
                </form>
            </div>
        </div>
    </section>

    <section class="admin-screen" id="screen-subjects" aria-labelledby="subjects-title" data-screen="subjects" hidden>
        <div class="page-heading"><div><p class="eyebrow">Teaching structure</p><h1 id="subjects-title" tabindex="-1">Subjects &amp; sections</h1><p>Organize subject offerings, assign professors, and review section coverage.</p></div><button class="button primary" type="button" id="add-subject"><svg class="icon"><use href="#i-plus"/></svg>Add Subject / Section</button></div>
        <div class="metrics three"><article class="metric"><h2>Assigned subjects</h2><strong id="subject-assigned-count">—</strong><p>Across all departments</p></article><article class="metric"><h2>Sections</h2><strong id="subject-section-count">—</strong><p>Current academic coverage</p></article><article class="metric"><h2>Needs professor</h2><strong id="subject-unassigned-count">—</strong><p>Review unassigned coverage</p></article></div>
        <div class="filter-bar panel"><label class="search-control"><svg class="icon"><use href="#i-search"/></svg><span class="sr-only">Search subject code, title, or professor</span><input id="subject-search" type="search" placeholder="Search code, subject, or professor" maxlength="100"></label><label><span class="sr-only">Filter subjects by department</span><select id="subject-department" data-department-options><option value="">All departments</option></select></label><label><span class="sr-only">Filter subjects by assignment</span><select id="subject-status"><option value="">All assignments</option><option>Assigned</option><option>Needs professor</option></select></label><span class="sample-label">Database records</span></div>
        <div class="panel table-panel"><div class="table-scroll" role="region" aria-label="Subjects and sections directory" tabindex="0"><table class="directory-table subject-table"><caption class="sr-only">Subject codes, titles, departments, professor assignments, sections, and student counts</caption><thead><tr><th scope="col">Subject</th><th scope="col">Department</th><th scope="col">Professor</th><th scope="col">Sections</th><th scope="col">Students</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody id="subject-rows"></tbody></table></div><div class="empty-state" id="subject-empty" hidden><h2>No matching subjects</h2><p>Try a different search or assignment filter.</p><button class="button small" type="button" id="reset-subject-filters">Clear filters</button></div><div class="table-footer"><span id="subject-count" role="status"></span><span>Changes are saved to the database</span></div></div>
        <form class="panel settings-panel" id="enrollment-form" method="dialog" style="margin-top:20px"><div class="panel-heading"><div><h2>Enroll a student in a section</h2><p>Only authorized, verified student enrollments should be recorded.</p></div></div><div class="form-grid"><label>Student<select id="enroll-student" name="student_id" required><option value="">Select student</option></select></label><label>Verified course (optional)<select id="enroll-course" name="course_id"><option value="">Keep current course</option></select></label><label>Subject / section<select id="enroll-section" name="section_id" required><option value="">Select section</option></select></label></div><div class="settings-footer"><small>Section capacity is checked before saving.</small><button class="button primary" type="submit">Save enrollment</button></div></form>
    </section>

    <section class="admin-screen" id="screen-reports" aria-labelledby="reports-title" data-screen="reports" hidden>
        <div class="page-heading"><div><p class="eyebrow">Administration insights</p><h1 id="reports-title" tabindex="-1">Reports</h1><p>Review term coverage and admin–professor message activity for a chosen date range.</p></div><button class="button" type="button" id="export-report"><svg class="icon"><use href="#i-download"/></svg>Export Report</button></div>
        <div class="filter-bar panel report-filters"><label><span class="sr-only">Academic year</span><select id="report-year"><option value="">All academic years</option></select></label><label><span class="sr-only">Semester</span><select id="report-semester"><option value="">All semesters</option></select></label><label><span class="sr-only">Report department</span><select id="report-department" data-department-options><option value="">All departments</option></select></label><label class="report-date"><span>Message dates from (optional)</span><input id="report-from" type="date"></label><label class="report-date"><span>Message dates to (optional)</span><input id="report-to" type="date"></label><span class="sample-label">Live database report</span></div>
        <p class="report-context" id="report-context" role="status">All academic years · All semesters · All departments</p>
        <div class="metrics three"><article class="metric"><h2>Professor accounts</h2><strong>—</strong><p>Registered professor accounts</p></article><article class="metric"><h2>Subject coverage</h2><strong>—</strong><p>Subject assignment coverage</p></article><article class="metric"><h2>Admin reply rate</h2><strong>—</strong><p>Admin ↔ professor conversations</p></article></div>
        <div class="report-grid">
            <article class="panel chart-panel"><div class="panel-heading"><div><h2>Monthly faculty activity</h2><p>Subjects and section records created by month.</p></div></div><div class="chart-legend"><span><i class="legend-subjects"></i>Subjects assigned</span><span><i class="legend-sections"></i>Sections assigned</span></div>
                <figure class="activity-figure" aria-labelledby="activity-caption"><div class="activity-chart" role="img" aria-label="Loading monthly activity"><div class="chart-axis" aria-hidden="true"><span>0</span><span>0</span><span>0</span><span>0</span></div><div class="chart-plot" aria-hidden="true"></div></div><figcaption id="activity-caption">Activity based on recorded creation dates</figcaption></figure>
                <details class="chart-data"><summary>View chart values</summary><table><caption class="sr-only">Monthly creation activity</caption><thead><tr><th scope="col">Month</th><th scope="col">Subjects</th><th scope="col">Sections</th></tr></thead><tbody></tbody></table></details>
            </article>
            <article class="panel quick-summary"><h2>Quick summary</h2><p>Database totals for the selected filters.</p><dl><div><dt>Active professors</dt><dd>—</dd></div><div><dt>Pending invitations</dt><dd>—</dd></div><div><dt>Subjects with professors</dt><dd>—</dd></div><div><dt>Assigned sections</dt><dd>—</dd></div><div><dt>Admin–professor conversations</dt><dd>—</dd></div><div><dt>Unread professor conversations</dt><dd>—</dd></div></dl><div class="summary-note"><svg class="icon"><use href="#i-shield"/></svg><span>Admin ↔ Professor only<br><small>Message activity includes faculty accounts.</small></span></div></article>
        </div>
    </section>

    <section class="admin-screen" id="screen-settings" aria-labelledby="settings-title" data-screen="settings" hidden>
        <div class="page-heading"><div><p class="eyebrow">Administration preferences</p><h1 id="settings-title" tabindex="-1">Settings</h1><p>Review your profile, notification preferences, and account security status.</p></div><span class="scope-badge">Account settings</span></div>
        <div class="settings-grid">
            <form class="panel settings-panel" id="profile-form" method="dialog"><div class="panel-heading"><div><h2>Admin profile &amp; contact details</h2><p>Your administrator profile and contact details.</p></div><span class="avatar avatar-blue">AD</span></div><div class="form-grid"><label>First name<input name="first_name" value="" autocomplete="off" maxlength="60" required></label><label>Last name<input name="last_name" value="" autocomplete="off" maxlength="60" required></label><label class="span-two">Institutional email<input name="email" type="email" value="" autocomplete="off" maxlength="120" required></label><label>Contact number <span class="optional">(optional)</span><input name="contact" type="tel" value="" autocomplete="off" maxlength="30"></label><label>Role<input value="Administrator" readonly aria-readonly="true"></label></div><div class="settings-footer"><small>Profile details are stored securely</small><button class="button primary" type="submit">Save profile</button></div></form>
            <form class="panel settings-panel" id="notification-form" method="dialog"><div class="panel-heading"><div><h2>Notification preferences</h2><p>Choose how faculty updates would appear.</p></div></div><label class="toggle-row"><span><strong>Admin–professor messages</strong><small>New messages from professor accounts.</small></span><input type="checkbox" name="messages" role="switch" checked></label><label class="toggle-row"><span><strong>Professor account invitations</strong><small>Invitation status and activation reminders.</small></span><input type="checkbox" name="invitations" role="switch" checked></label><label class="toggle-row"><span><strong>Subject &amp; section updates</strong><small>Teaching assignments and coverage changes.</small></span><input type="checkbox" name="assignments" role="switch"></label><div class="settings-footer"><small>Preferences are saved to your account</small><button class="button primary" type="submit">Save preferences</button></div></form>
            <article class="panel security-panel"><div class="panel-heading"><div><h2>Account security status</h2><p>Review your account password status.</p></div><span class="metric-icon green"><svg class="icon"><use href="#i-shield"/></svg></span></div><div class="security-items"><div><strong>Password</strong><span class="status active" id="security-password">Not loaded</span></div><div><strong>Two-step verification</strong><span class="status pending">Not enabled</span></div><div><strong>Password last changed</strong><span id="security-review">Not recorded</span></div></div><button class="button" type="button" id="open-security">Manage password</button></article>
        </div>
        <div class="settings-grid" style="margin-top:20px">
            <form class="panel settings-panel" id="department-form" method="dialog"><h2>Departments</h2><p class="muted">Create departments for faculty and subject records.</p><div class="form-grid" style="margin-top:16px"><label class="span-two">Department name<input name="name" maxlength="100" required></label></div><div class="settings-footer"><button class="button primary" type="submit">Add department</button></div></form>
            <form class="panel settings-panel" id="course-form" method="dialog"><h2>Courses</h2><div class="form-grid" style="margin-top:16px"><label>Course code<input name="code" maxlength="30" required></label><label>Course name<input name="name" maxlength="150" required></label><label class="span-two">Department<select name="department_id" data-department-options required><option value="">Select department</option></select></label></div><div class="settings-footer"><button class="button primary" type="submit">Add course</button></div></form>
            <article class="panel settings-panel security-panel"><h2>Account email delivery</h2><p id="mail-status" class="muted">Loading email queue status…</p><p class="screen-note">Queued messages are delivered by the school email worker. Queueing does not confirm delivery.</p></article>
        </div>
        <article class="panel settings-panel" style="margin-top:20px"><h2>Contact &amp; admission requests</h2><div class="table-scroll"><table class="directory-table"><thead><tr><th>Category</th><th>Sender</th><th>Message</th><th>Status</th><th>Received</th><th>Action</th></tr></thead><tbody id="request-rows"></tbody></table></div><p class="empty-state" id="request-empty" hidden>No requests received.</p></article>
        <p class="screen-note">Saved changes apply to your account. Password updates require your current password.</p>
    </section>
    <footer class="workspace-footer"><span>KNS Portal <span aria-hidden="true">·</span> Faculty administration</span><span>Connected to the portal database</span></footer>
</main>

<dialog class="admin-dialog" id="professor-dialog" aria-labelledby="professor-dialog-title" aria-describedby="professor-dialog-description">
    <form id="professor-form" method="dialog">
        <header class="dialog-heading"><div><p class="eyebrow">Faculty directory</p><h2 id="professor-dialog-title">Register Professor</h2></div><button class="icon-button" type="button" data-close-dialog aria-label="Close professor form"><svg class="icon"><use href="#i-close"/></svg></button></header>
        <div class="dialog-body"><p class="sample-notice" id="professor-dialog-description">Changes create or update a real professor account. Activation links expire after 48 hours.</p>
            <div class="form-grid"><label>First name<input id="professor-first" name="first_name" maxlength="60" autocomplete="off" required autofocus></label><label>Last name<input id="professor-last" name="last_name" maxlength="60" autocomplete="off" required></label><label>Faculty ID<input id="professor-id" name="faculty_id" placeholder="e.g. FAC-1050" maxlength="30" required></label><label>Institutional email<input id="professor-email" name="email" type="email" placeholder="name@kns.example" maxlength="120" autocomplete="off" required></label><label>Contact number <span class="optional">(optional)</span><input id="professor-contact" name="contact" type="tel" maxlength="30" autocomplete="off"></label><label>Department<select id="professor-form-department" name="department" data-department-options required><option value="">Select department</option></select></label><label id="professor-status-field" hidden>Account status<select id="professor-form-status" name="status"><option value="active">Active</option><option value="pending">Invitation pending</option><option value="suspended">Suspended</option></select></label><label id="professor-temporary-field">Initial password <span class="optional">(required when activation email is off)</span><input id="professor-temporary" name="temporary_password" type="password" minlength="12" maxlength="72" autocomplete="new-password"></label><label class="span-two">Account role<input value="Professor" readonly aria-readonly="true"><small>The role is fixed to Professor.</small></label></div>
            <fieldset class="permission-group"><legend>Teaching permissions</legend><label class="checkbox-row"><input id="permission-classes" type="checkbox" name="classes" checked><span>View assigned subjects &amp; sections</span></label><label class="checkbox-row"><input id="permission-classwork" type="checkbox" name="classwork" checked><span>Create and manage classwork</span></label><label class="checkbox-row"><input id="permission-grades" type="checkbox" name="grades"><span>Record grades for assigned classes</span></label></fieldset>
            <fieldset class="permission-group"><legend>Communication &amp; activation</legend><label class="checkbox-row"><input id="permission-messages" type="checkbox" name="messages" checked><span>Allow admin–professor messaging<small>Private conversations with the portal admin.</small></span></label><label class="checkbox-row"><input id="professor-activation" type="checkbox" name="activation" checked><span>Queue secure activation email<small>Queues an activation link. A configured email worker is required for delivery.</small></span></label></fieldset>
        </div><footer class="dialog-footer"><button class="button" type="button" data-close-dialog>Cancel</button><button class="button primary" id="professor-save" type="submit">Save professor</button></footer>
    </form>
</dialog>

<dialog class="admin-dialog" id="subject-dialog" aria-labelledby="subject-dialog-title" aria-describedby="subject-dialog-description">
    <form id="subject-form" method="dialog">
        <header class="dialog-heading"><div><p class="eyebrow">Teaching structure</p><h2 id="subject-dialog-title">Add Subject / Section</h2></div><button class="icon-button" type="button" data-close-dialog aria-label="Close subject form"><svg class="icon"><use href="#i-close"/></svg></button></header>
        <div class="dialog-body"><p class="sample-notice" id="subject-dialog-description">Subject and section changes are saved to the portal database.</p><div class="form-grid"><label>Subject code<input id="subject-code" name="code" placeholder="e.g. CS 310" maxlength="20" required autofocus></label><label>Subject name<input id="subject-name" name="title" placeholder="e.g. Software Engineering" maxlength="100" required></label><label>Department<select id="subject-form-department" name="department" data-department-options required><option value="">Select department</option></select></label><label>Assigned professor<select id="subject-professor" name="professor"><option value="">Unassigned</option></select></label><label>Section<input id="subject-section" name="section" placeholder="e.g. BSCS 3-A" maxlength="40" required></label><label>Academic year<input id="subject-year" name="year" placeholder="2026-2027" pattern="[0-9]{4}-[0-9]{4}" required></label><label>Semester<select id="subject-semester" name="semester"><option>First Semester</option><option>Second Semester</option><option>1st Semester</option><option>2nd Semester</option><option>Summer</option></select></label><label>Capacity<input id="subject-capacity" name="capacity" type="number" min="1" max="300" value="40" required></label></div></div>
        <footer class="dialog-footer"><button class="button" type="button" data-close-dialog>Cancel</button><button class="button primary" id="subject-save" type="submit">Save subject / section</button></footer>
    </form>
</dialog>
<dialog class="admin-dialog" id="activation-dialog" aria-labelledby="activation-title"><header class="dialog-heading"><h2 id="activation-title">Professor activation link</h2><button class="icon-button" type="button" data-close-dialog aria-label="Close activation link">×</button></header><div class="dialog-body"><p class="sample-notice">Share this confidential one-time link only with the intended professor. The email is queued until the mail worker delivers it.</p><label class="form-grid">Activation link<input id="activation-link" readonly onclick="this.select()"></label></div><footer class="dialog-footer"><button class="button" type="button" data-close-dialog>Close</button></footer></dialog>
<dialog class="admin-dialog" id="notifications-dialog" aria-labelledby="notifications-title"><header class="dialog-heading"><h2 id="notifications-title">Notifications</h2><button class="icon-button" type="button" data-close-dialog aria-label="Close notifications">×</button></header><div class="dialog-body" id="notification-list"></div></dialog>
<dialog class="admin-dialog" id="security-dialog" aria-labelledby="password-title"><header class="dialog-heading"><h2 id="password-title">Change password</h2><button class="icon-button" type="button" data-close-dialog aria-label="Close password form">×</button></header><form method="post" action="portal_action.php" data-portal-form><input type="hidden" name="action" value="password"><input type="hidden" name="csrf_token" value="<?= kns_subject_escape($adminCsrf) ?>"><div class="dialog-body form-grid"><label class="span-two">Current password<input name="current_password" type="password" autocomplete="current-password" required></label><label>New password<input name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>Confirm new password<input name="confirm_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><p data-form-status role="status" class="span-two"></p></div><footer class="dialog-footer"><button class="button primary" type="submit">Change password</button></footer></form></dialog>
<div class="toast-region" id="demo-toast" role="status" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
