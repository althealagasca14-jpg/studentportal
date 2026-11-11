<?php
error_reporting(0);
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#075638">
    <title>Log in | Kolehiyo ng Subic</title>
    <link rel="icon" href="logo.jpg" type="image/jpeg">
    <link rel="stylesheet" href="login.css?v=3">
    <script src="login.js?v=3" defer></script>
</head>
<body class="student-login">
    <a class="skip-link" href="#login-identifier">Skip to login</a>
    <header class="portal-header">
        <a class="portal-brand" href="index.html" aria-label="Kolehiyo ng Subic home">
            <span class="school-seal school-seal--nav"><img src="logo.jpg" alt="" width="1024" height="768"></span>
            <span><strong>Kolehiyo ng Subic</strong><span class="brand-caption">Student Portal</span></span>
        </a>
        <nav class="portal-nav" aria-label="Main navigation">
            <a href="index.html">Home</a>
            <a href="#school-help">Contact</a>
            <a href="#admission-help">Admission</a>
            <a class="nav-login" href="login.php" aria-current="page">Log in <span aria-hidden="true">&rarr;</span></a>
        </nav>
    </header>

    <main class="portal-layout">
        <section class="welcome-panel" aria-labelledby="welcome-title">
            <div class="welcome-content">
                <div class="school-identity">
                    <span class="school-seal school-seal--hero"><img src="logo.jpg" alt="Kolehiyo ng Subic school seal" width="1024" height="768"></span>
                    <div><p class="school-name">Kolehiyo ng Subic</p><p class="school-location">Zambales, Philippines</p></div>
                </div>
                <div class="welcome-message">
                    <p class="portal-eyebrow"><span aria-hidden="true"></span> Student Portal</p>
                    <h1 id="welcome-title">Your next chapter <span>starts here.</span></h1>
                    <p class="welcome-description">Sign in to stay connected with your classes, academic records, and the services that support your college journey.</p>
                </div>
                <p class="welcome-footer">Learn with purpose. Grow with confidence.</p>
            </div>
        </section>

        <section class="login-panel" aria-labelledby="login-title">
            <div class="auth-card">
                <div class="auth-card-inner">
                    <section class="login-card auth-face auth-face--login" id="login-face" aria-labelledby="login-title" aria-hidden="false">
                        <p class="card-eyebrow">Welcome back</p>
                        <h2 id="login-title" tabindex="-1">Log in to your account</h2>
                        <p class="card-intro" aria-live="polite">Enter your student details to continue to the portal.</p>

                        <?php if (isset($_SESSION['loginMessage']) && !empty($_SESSION['loginMessage'])): ?>
                            <div class="login-alert" role="alert">
                                <?php echo htmlspecialchars($_SESSION['loginMessage'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php unset($_SESSION['loginMessage']); ?>
                        <?php endif; ?>

                        <form class="login-fields" action="login_check.php" method="POST">
                            <fieldset class="login-form-controls">
                                <fieldset class="login-role-picker">
                                    <legend>Log in as</legend>
                                    <div class="login-role-options">
                                        <label class="login-role-option">
                                            <input type="radio" name="login_role" value="student" checked>
                                            <span>Student</span>
                                        </label>
                                        <label class="login-role-option">
                                            <input type="radio" name="login_role" value="admin">
                                            <span>Admin</span>
                                        </label>
                                        <label class="login-role-option">
                                            <input type="radio" name="login_role" value="professor">
                                            <span>Professor</span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div class="form-field">
                                    <label for="login-identifier">Student number</label>
                                    <div class="input-wrap">
                                        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2H4Z"/></svg>
                                        <input id="login-identifier" type="text" name="username" placeholder="e.g. 2026-01234" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label for="login-password">Password</label>
                                    <div class="input-wrap">
                                        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v3"/></svg>
                                        <input id="login-password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                                        <button class="password-toggle" type="button" aria-label="Show password" aria-controls="login-password" aria-pressed="false" hidden>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-options">
                                    <label class="remember-option" for="remember-me"><input id="remember-me" type="checkbox" name="remember_me" aria-describedby="remember-hint"> <span>Remember me</span></label>
                                    <button class="text-button" type="button" id="forgot-password" aria-haspopup="dialog" aria-controls="recovery-dialog" hidden>Forgot password?</button>
                                </div>
                                <p class="field-hint" id="remember-hint">Remembers your student number on this device.</p>
                                <p class="field-hint" id="remember-status" role="status"></p>
                                <button class="login-button" type="submit" name="submit" value="LOG IN">Log in <span aria-hidden="true">&rarr;</span></button>
                            </fieldset>
                        </form>

                        <p class="security-note">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg>
                            Keep your password private. Log out after use.
                        </p>
                        <p class="auth-switch" id="student-register-prompt" hidden>New student? <button class="text-button" id="show-registration" type="button" aria-controls="registration-face">Register here</button></p>
                        <footer class="card-footer">
                            <span>Having trouble signing in?</span>
                            <details id="school-help" class="office-help">
                                <summary>Contact your school office.</summary>
                                <div class="office-help-content">
                                    <p>Visit the school office for help with account access or password recovery. Have your student number, admin username, or professor account ID ready.</p>
                                    <p id="admission-help" tabindex="-1">For admission requirements and application assistance, please contact the school office.</p>
                                </div>
                            </details>
                        </footer>
                        <noscript><p class="field-hint">Use your student number for Student, username for Admin, or account ID for Professor. Enable JavaScript to update the field labels, show your password, remember your account identifier, or view recovery guidance. You can still log in or contact the school office.</p></noscript>
                    </section>
                    <section class="login-card auth-face auth-face--register" id="registration-face" aria-labelledby="registration-title" aria-hidden="true" inert>
                        <p class="card-eyebrow">Student registration</p>
                        <h2 id="registration-title" tabindex="-1">Create your account</h2>
                        <p class="card-intro">Enter the details associated with your student account.</p>

                        <!-- UI only: no registration endpoint exists in this project. -->
                        <form class="registration-form" id="student-registration-form" method="POST">
                            <fieldset class="registration-fields" disabled>
                                <div class="form-field">
                                    <label for="registration-full-name">Full name</label>
                                    <div class="input-wrap">
                                        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2H4Z"/></svg>
                                        <input id="registration-full-name" type="text" name="full_name" placeholder="Enter your full name" autocomplete="name" required>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label for="registration-student-number">Student number</label>
                                    <div class="input-wrap">
                                        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2H4Z"/></svg>
                                        <input id="registration-student-number" type="text" name="student_number" placeholder="2026-12345" pattern="[0-9]{4}-[0-9]{5}" minlength="10" maxlength="10" title="Use YYYY-12345, for example 2026-12345." aria-describedby="registration-number-hint" autocomplete="off" autocapitalize="none" spellcheck="false" required>
                                    </div>
                                    <p class="field-hint registration-number-hint" id="registration-number-hint">Use the format YYYY-12345, for example 2026-12345.</p>
                                </div>
                                <div class="form-field">
                                    <label for="registration-email">Email address</label>
                                    <div class="input-wrap">
                                        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m3 6 9 7 9-7"/></svg>
                                        <input id="registration-email" type="email" name="email" placeholder="you@example.com" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label for="registration-password">Password</label>
                                    <div class="input-wrap">
                                        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v3"/></svg>
                                        <input id="registration-password" type="password" name="password" placeholder="Create a password" autocomplete="new-password" required>
                                        <button class="password-toggle" id="registration-password-toggle" type="button" aria-label="Show password" aria-controls="registration-password" aria-pressed="false" hidden>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <button class="login-button" type="submit">Create student account <span aria-hidden="true">&rarr;</span></button>
                            </fieldset>
                        </form>
                        <p class="registration-note">Student registration only. Use the name and student number on your school records.</p>
                        <p class="registration-status" id="registration-status" role="status" tabindex="-1" hidden></p>
                        <p class="auth-switch registration-switch">Already have an account? <button class="text-button" id="show-login" type="button" aria-controls="login-face">Back to log in</button></p>
                    </section>
                </div>
            </div>

        </section>
    </main>

    <dialog class="recovery-dialog" id="recovery-dialog" aria-labelledby="recovery-title" aria-describedby="recovery-intro">
        <div class="recovery-content">
            <button class="dialog-close" type="button" aria-label="Close password recovery"><span aria-hidden="true">&times;</span></button>
            <span class="recovery-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v3"/></svg>
            </span>
            <p class="card-eyebrow">Account support</p>
            <h2 id="recovery-title">Forgot your password?</h2>
            <p id="recovery-intro">Enter your student number, username, account ID, or registered school email to view account recovery guidance.</p>
            <!-- No recovery endpoint exists. This form only shows school-office guidance. -->
            <form id="recovery-form" method="dialog">
                <div class="form-field">
                    <label for="recovery-identifier">Account identifier or school email</label>
                    <input id="recovery-identifier" type="text" placeholder="Account identifier or school email" autocomplete="off" autocapitalize="none" spellcheck="false" required autofocus>
                </div>
                <button class="login-button" type="submit">Continue <span aria-hidden="true">&rarr;</span></button>
            </form>
            <div class="recovery-confirmation" id="recovery-confirmation" role="status" tabindex="-1" hidden>
                <p class="confirmation-title">Your next step</p>
                <p>For account recovery, contact the school office with your student number, username, account ID, or registered school email. Staff can guide you through verifying account ownership.</p>
                <p>No recovery message has been sent from this page.</p>
                <a class="office-link" href="#school-help">View school office help <span aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </dialog>
</body>
</html>
