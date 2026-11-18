<?php
session_start();
require_once __DIR__ . '/app/helpers/portal_support.php';
header('Cache-Control: no-store');
$csrf = kns_subject_csrf_token();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Professor registration | KNS Portal</title><link rel="stylesheet" href="assets/css/login.css?v=4"><script src="assets/js/portal-forms.js" defer></script></head>
<body class="student-login">
<header class="portal-header"><a class="portal-brand" href="login.php"><span><strong>Kolehiyo ng Subic</strong><span class="brand-caption">KNS Portal</span></span></a><nav class="portal-nav" aria-label="Account navigation"><a href="login.php">Back to log in</a></nav></header>
<main class="portal-layout">
<section class="welcome-panel"><div class="welcome-content"><p class="portal-eyebrow">Professor workspace</p><div class="welcome-message"><h1>A place to teach.<span>Room to grow.</span></h1><p class="welcome-description">Bring your subjects, classwork and class conversations together.</p></div><p class="welcome-footer">Learn with purpose. Grow with confidence.</p></div></section>
<section class="login-panel" aria-labelledby="professor-register-title"><div class="login-card"><p class="card-eyebrow">Faculty account</p><h2 id="professor-register-title">Create your account</h2><p class="card-intro">Use your unique faculty ID. Your account is ready immediately after registration.</p>
<form method="post" action="portal_action.php" class="registration-form" data-portal-form>
<input type="hidden" name="action" value="professor_register"><input type="hidden" name="csrf_token" value="<?= kns_subject_escape($csrf) ?>">
<div class="form-field"><label for="faculty-name">Full name</label><input id="faculty-name" name="full_name" maxlength="120" autocomplete="name" required></div>
<div class="form-field"><label for="faculty-id">Faculty ID</label><input id="faculty-id" name="faculty_id" minlength="2" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]{1,49}" autocomplete="username" autocapitalize="none" spellcheck="false" required><p class="field-hint">2–50 letters, numbers, dots, underscores or hyphens. IDs are case-insensitive.</p></div>
<div class="form-field"><label for="faculty-email">School email</label><input id="faculty-email" type="email" name="email" maxlength="191" autocomplete="email" required></div>
<div class="form-field"><label for="faculty-password">Password</label><input id="faculty-password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required><p class="field-hint">Use 12–72 bytes. Your password is stored only as a secure hash.</p></div>
<p data-form-status role="status" aria-live="polite"></p><button class="login-button" type="submit">Create professor account &rarr;</button>
</form><p class="auth-switch">Already registered? <a href="login.php">Log in with your faculty ID</a></p></div></section>
</main></body></html>
