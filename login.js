/* Enhancements for login.php only; authentication continues to use login_check.php. */
(() => {
    "use strict";

    const loginForm = document.querySelector(".login-fields");
    const loginIdentifier = document.getElementById("login-identifier");
    const identifierLabel = document.querySelector('label[for="login-identifier"]');
    const cardIntro = document.querySelector(".card-intro");
    const password = document.getElementById("login-password");
    const passwordToggle = document.querySelector(".password-toggle");
    const remember = document.getElementById("remember-me");
    const rememberHint = document.getElementById("remember-hint");
    const rememberStatus = document.getElementById("remember-status");
    const roleOptions = loginForm.querySelectorAll('input[name="login_role"]');
    const roleSettings = {
        student: {
            label: "Student number",
            placeholder: "e.g. 2026-01234",
            instruction: "Enter your student details to continue to the portal.",
            storageKey: "kns.portal.studentNumber"
        },
        admin: {
            label: "Username",
            placeholder: "Enter your username",
            instruction: "Enter your admin username and password to continue.",
            storageKey: "kns.portal.adminUsername"
        },
        professor: {
            label: "Account ID",
            placeholder: "Enter your account ID",
            instruction: "Enter your professor account ID and password to continue.",
            storageKey: "kns.portal.professorAccountId"
        }
    };
    let selectedRole = loginForm.querySelector('input[name="login_role"]:checked').value;

    // Remember only the identifier for each role. Never store passwords or tokens.
    const restoreRememberedIdentifier = () => {
        const settings = roleSettings[selectedRole];
        loginIdentifier.value = "";
        remember.checked = false;
        remember.disabled = false;
        rememberStatus.textContent = "";
        rememberHint.textContent = "Remembers your " + settings.label.toLowerCase() + " on this device.";
        try {
            const savedIdentifier = localStorage.getItem(settings.storageKey);
            if (savedIdentifier) {
                loginIdentifier.value = savedIdentifier;
                remember.checked = true;
            }
        } catch {
            remember.disabled = true;
            rememberHint.textContent = "Remember me is unavailable in this browser.";
        }
    };

    const updateRememberedIdentifier = () => {
        if (remember.disabled) return;
        try {
            const storageKey = roleSettings[selectedRole].storageKey;
            if (remember.checked && loginIdentifier.value.trim()) {
                localStorage.setItem(storageKey, loginIdentifier.value.trim());
            } else {
                localStorage.removeItem(storageKey);
            }
            rememberStatus.textContent = "";
        } catch {
            remember.checked = false;
            remember.disabled = true;
            rememberStatus.textContent = "Your account identifier could not be saved on this device.";
        }
    };

    const updateLoginRole = () => {
        const settings = roleSettings[selectedRole];
        identifierLabel.textContent = settings.label;
        loginIdentifier.placeholder = settings.placeholder;
        cardIntro.textContent = settings.instruction;
        // Clear credentials and reset password visibility when switching account types.
        password.value = "";
        password.type = "password";
        passwordToggle.setAttribute("aria-pressed", "false");
        passwordToggle.setAttribute("aria-label", "Show password");
        restoreRememberedIdentifier();
    };

    roleOptions.forEach((option) => {
        option.addEventListener("change", () => {
            if (!option.checked) return;
            selectedRole = option.value;
            updateLoginRole();
        });
    });
    updateLoginRole();

    remember.addEventListener("change", updateRememberedIdentifier);
    loginIdentifier.addEventListener("input", () => {
        if (remember.checked) updateRememberedIdentifier();
    });
    // The selected role controls the UI only. The server verifies the actual account role.
    // Keep the existing username/password fields and authentication endpoint.
    loginForm.addEventListener("submit", updateRememberedIdentifier);


    passwordToggle.hidden = false;
    passwordToggle.addEventListener("click", () => {
        const show = password.type === "password";
        password.type = show ? "text" : "password";
        passwordToggle.setAttribute("aria-pressed", String(show));
        passwordToggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
    });

    const dialog = document.getElementById("recovery-dialog");
    const forgotPassword = document.getElementById("forgot-password");
    const recoveryForm = document.getElementById("recovery-form");
    const recoveryIdentifier = document.getElementById("recovery-identifier");
    const confirmation = document.getElementById("recovery-confirmation");

    if (typeof dialog.showModal === "function") {
        forgotPassword.hidden = false;
        forgotPassword.addEventListener("click", () => {
            recoveryForm.reset();
            recoveryIdentifier.setCustomValidity("");
            recoveryForm.hidden = false;
            confirmation.hidden = true;
            recoveryIdentifier.value = loginIdentifier.value.trim();
            dialog.showModal();
            document.body.classList.add("modal-open");
            recoveryIdentifier.focus();
        });

        dialog.querySelector(".dialog-close").addEventListener("click", () => dialog.close());
        dialog.addEventListener("click", (event) => {
            if (event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right ||
                event.clientY < bounds.top || event.clientY > bounds.bottom) {
                dialog.close();
            }
        });

        dialog.addEventListener("keydown", (event) => {
            if (event.key !== "Tab") return;
            const controls = Array.from(dialog.querySelectorAll(
                'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            )).filter((control) => !control.disabled && control.getClientRects().length);
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        // Native dialog handles Escape and makes the page behind it inert.
        dialog.addEventListener("close", () => {
            document.body.classList.remove("modal-open");
            recoveryForm.reset();
            forgotPassword.focus();
        });

        recoveryForm.addEventListener("submit", (event) => {
            event.preventDefault();
            if (!recoveryIdentifier.value.trim()) {
                recoveryIdentifier.setCustomValidity("Enter your account identifier or registered school email.");
                recoveryIdentifier.reportValidity();
                return;
            }
            // There is no backend recovery service. Do not submit, store, or echo identifiers.
            recoveryForm.hidden = true;
            confirmation.hidden = false;
            confirmation.focus();
            recoveryForm.reset();
        });
        recoveryIdentifier.addEventListener("input", () => recoveryIdentifier.setCustomValidity(""));
    } else {
        // Keep school-office assistance available in browsers without modal support.
        forgotPassword.hidden = false;
        forgotPassword.addEventListener("click", () => {
            const help = document.getElementById("school-help");
            help.open = true;
            help.querySelector("summary").focus();
        });
    }

    // Contact and Admission use local guidance because the repository has no such routes.
    document.querySelectorAll('a[href="#school-help"], a[href="#admission-help"]').forEach((link) => {
        link.addEventListener("click", (event) => {
            const target = document.getElementById(link.hash.slice(1));
            if (!target) return;
            event.preventDefault();
            if (dialog.open) dialog.close();
            if (registrationIsActive) setRegistrationPanel(false, false);
            const details = target.closest("details");
            if (details) details.open = true;
            const focusTarget = target.tagName === "DETAILS" ? target.querySelector("summary") : target;
            // Wait for dialog focus restoration before moving focus to school-office help.
            requestAnimationFrame(() => {
                focusTarget.focus();
                target.scrollIntoView({ block: "nearest" });
            });
        });
    });
    // Registration is a local UI preview until a backend handler is available.
    const authCard = document.querySelector(".auth-card");
    const loginFace = document.getElementById("login-face");
    const registrationFace = document.getElementById("registration-face");
    const loginControls = loginForm.querySelector(".login-form-controls");
    const registrationForm = document.getElementById("student-registration-form");
    const registrationControls = registrationForm.querySelector(".registration-fields");
    const registrationPrompt = document.getElementById("student-register-prompt");
    const showRegistration = document.getElementById("show-registration");
    const showLogin = document.getElementById("show-login");
    const registrationPassword = document.getElementById("registration-password");
    const registrationPasswordToggle = document.getElementById("registration-password-toggle");
    const registrationFullName = document.getElementById("registration-full-name");
    const registrationStatus = document.getElementById("registration-status");
    let registrationIsActive = false;

    function setRegistrationPanel(show, moveFocus = true) {
        if (show && selectedRole !== "student") return;
        registrationIsActive = show;
        // Disabled fieldsets exclude inactive inputs from validation and FormData.
        loginControls.disabled = show;
        registrationControls.disabled = !show;
        loginFace.inert = show;
        registrationFace.inert = !show;
        loginFace.setAttribute("aria-hidden", String(show));
        registrationFace.setAttribute("aria-hidden", String(!show));
        authCard.classList.toggle("is-registering", show);
        authCard.closest(".login-panel").setAttribute(
            "aria-labelledby", show ? "registration-title" : "login-title"
        );
        password.type = "password";
        passwordToggle.setAttribute("aria-pressed", "false");
        passwordToggle.setAttribute("aria-label", "Show password");
        registrationPassword.type = "password";
        registrationPasswordToggle.setAttribute("aria-pressed", "false");
        registrationPasswordToggle.setAttribute("aria-label", "Show password");
        if (!show) registrationPassword.value = "";
        if (moveFocus) {
            const focusTarget = show ? registrationFullName : loginIdentifier;
            focusTarget.focus({ preventScroll: true });
            const bounds = focusTarget.getBoundingClientRect();
            if (bounds.top < 16 || bounds.bottom > window.innerHeight - 16) {
                focusTarget.scrollIntoView({ block: "nearest", inline: "nearest" });
            }
        }
    }

    const updateRegistrationAvailability = () => {
        registrationPrompt.hidden = selectedRole !== "student";
        if (selectedRole !== "student" && registrationIsActive) {
            setRegistrationPanel(false);
        }
    };
    roleOptions.forEach((option) => {
        option.addEventListener("change", updateRegistrationAvailability);
    });

    showRegistration.addEventListener("click", () => setRegistrationPanel(true));
    showLogin.addEventListener("click", () => setRegistrationPanel(false));
    document.querySelector(".skip-link").addEventListener("click", () => {
        if (registrationIsActive) setRegistrationPanel(false, false);
    });

    // Inert panels cannot receive focus; guard scripted submissions as well.
    loginForm.addEventListener("submit", (event) => {
        if (!registrationIsActive) return;
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);

    registrationPasswordToggle.hidden = false;
    registrationPasswordToggle.addEventListener("click", () => {
        const show = registrationPassword.type === "password";
        registrationPassword.type = show ? "text" : "password";
        registrationPasswordToggle.setAttribute("aria-pressed", String(show));
        registrationPasswordToggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
    });

    registrationFullName.addEventListener("input", () => {
        registrationFullName.setCustomValidity(
            registrationFullName.value && !registrationFullName.value.trim()
                ? "Enter your full name." : ""
        );
    });
    registrationForm.addEventListener("input", () => {
        registrationStatus.hidden = true;
        registrationStatus.textContent = "";
    });
    registrationForm.addEventListener("submit", (event) => {
        // No requests, stored registration data, emails, or account creation.
        event.preventDefault();
        if (!registrationIsActive) return;
        if (!registrationFullName.value.trim()) {
            registrationFullName.setCustomValidity("Enter your full name.");
            registrationFullName.reportValidity();
            return;
        }
        if (!registrationForm.reportValidity()) return;
        registrationStatus.textContent = "Online registration is not available yet. Please contact the school office to register. No account has been created.";
        registrationStatus.hidden = false;
        registrationStatus.focus({ preventScroll: true });
        registrationStatus.scrollIntoView({ block: "nearest", inline: "nearest" });
    });

    updateRegistrationAvailability();

})();
