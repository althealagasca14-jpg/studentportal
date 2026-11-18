/* Local design simulation. No requests, persistence, or authentication. */
"use strict";
(() => {
  const data = JSON.parse(JSON.stringify(window.KNS_SAMPLE_DATA));
  const state = { ...data, nextId: 100, role: "student", workFilter: "All", threadId: "", alertResolved: false, alertDismissed: false, readUpdates: [], menuOpen: false };
  const app = document.getElementById("app");
  const modal = document.getElementById("modal");
  const modalContent = document.getElementById("modal-content");
  let route = {};
  let draftFiles = [];
  let toastTimer;
  let dialogTrigger;
  const paths = {
    book: '<path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-2H4z"/><path d="M20 4h-4a3 3 0 0 0-3 3v14a4 4 0 0 1 4-2h3z"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
    chat: '<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H8l-5 2 1.5-5A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/>',
    work: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h3"/>',
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
    search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    arrow: '<path d="M4 12h16m-5-5 5 5-5 5"/>',
    back: '<path d="M20 12H4m5-5-5 5 5 5"/>',
    check: '<path d="m5 12 4 4L19 6"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    users: '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m3 10v-3a6 6 0 0 0-2-4"/>',
    file: '<path d="M14 2H5v20h14V7Z"/><path d="M14 2v6h5M8 13h8M8 17h6"/>',
    upload: '<path d="M12 16V3m-5 5 5-5 5 5M4 15v6h16v-6"/>',
    send: '<path d="m3 3 19 9-19 9 4-9-4-9Zm4 9h15"/>',
    close: '<path d="m6 6 12 12M6 18 18 6"/>',
    spark: '<path d="m12 2 2.5 7.5L22 12l-7.5 2.5L12 22l-2.5-7.5L2 12l7.5-2.5Z"/>',
    pin: '<path d="m16 3 5 5-5 1-3 5-3-3 5-3ZM10 14l-7 7"/>',
    lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/>',
    logout: '<path d="M9 4H4v16h5m5-13 5 5-5 5M9 12h12"/>',
    menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    chevron: '<path d="m8 5 7 7-7 7"/>',
    leaf: '<path d="M20 3C9 1 2 7 5 15s17 5 15-12Z"/><path d="m4 21 11-11"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6M17 2v6M3 11h18"/>',
    copy: '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V3H3v13h5"/>'
  };
  const icon = (name, cls = "") => '<svg class="icon ' + cls + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (paths[name] || paths.book) + "</svg>";
  const e = value => String(value ?? "").replace(/[&<>"']/g, char => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char]));
  const uid = prefix => prefix + "-" + state.nextId++;
  const profile = () => state.profiles[state.role];
  const classById = id => state.classes.find(item => item.id === id);
  const workById = id => state.work.find(item => item.id === id);
  const ownedClasses = role => state.classes.filter(item => role === "professor" ? item.professorId === state.profiles.professor.id : state.enrolled.includes(item.id));
  const classWork = id => state.work.filter(item => item.classId === id && (state.role === "professor" || item.status === "Published"));
  const visibleWork = () => state.work.filter(item => ownedClasses(state.role).some(cls => cls.id === item.classId) && (state.role === "professor" || item.status === "Published"));
  const mySubmission = id => state.submissions.find(item => item.workId === id && item.studentId === state.profiles.student.id);
  const go = path => { if (location.hash === "#" + path) render(); else location.hash = path; };
  const classPath = (id, tab = "updates") => "/" + state.role + "/subject/" + id + "/" + tab;
  const badge = (text, tone = "") => '<span class="badge ' + tone + '">' + e(text) + "</span>";
  const avatar = (initials, tone = "") => '<span class="avatar ' + tone + '">' + e(initials) + "</span>";
  const dueLabel = date => date ? new Date(date + "T12:00:00").toLocaleDateString("en-US", { month: "short", day: "numeric" }) : "No due date";
  const filesize = size => size < 1048576 ? Math.max(1, Math.round(size / 1024)) + " KB" : (size / 1048576).toFixed(1) + " MB";
  const empty = (title, copy, button = "") => '<div class="empty">' + icon("leaf") + "<h3>" + e(title) + "</h3><p>" + e(copy) + "</p>" + button + "</div>";
  const button = (label, action, extra = "", cls = "button primary") => '<button type="button" class="' + cls + '" data-action="' + action + '" ' + extra + ">" + label + "</button>";
  function toast(message) {
    clearTimeout(toastTimer);
    const element = document.getElementById("toast");
    element.textContent = message;
    element.classList.add("visible");
    toastTimer = setTimeout(() => element.classList.remove("visible"), 4200);
  }
  function clearFiles() {
    draftFiles.forEach(file => { if (file.url) URL.revokeObjectURL(file.url); });
    draftFiles = [];
  }
  function openDialog(title, html, wide = false) {
    if (modal.open) modal.close();
    clearFiles();
    dialogTrigger = document.activeElement;
    modal.classList.toggle("wide", wide);
    modalContent.innerHTML = '<header class="modal-heading"><div><p class="eyebrow">KNS Classroom</p><h2 id="modal-title">' + e(title) + '</h2></div><button type="button" class="icon-button" data-action="close-dialog" aria-label="Close dialog">' + icon("close") + "</button></header>" + html;
    modal.showModal();
  }
  function showError(form, message) {
    let element = form.querySelector(".form-error");
    if (!element) { element = document.createElement("p"); element.className = "form-error"; element.setAttribute("role", "alert"); form.prepend(element); }
    element.textContent = message;
  }
  const formEnd = label => '<p class="form-error" role="alert"></p><div class="form-footer"><button class="button secondary" type="button" data-action="close-dialog">Cancel</button><button class="button primary" type="submit">' + label + "</button></div>";
  const previewNote = '<p class="form-note">' + icon("leaf") + " Design preview. Changes last until you reload.</p>";
  function authView(kind, role) {
    const register = kind === "register";
    state.role = role;
    const person = state.profiles[role];
    return '<div class="auth-layout"><section class="auth-story"><a class="brand" href="#/login"><span class="brand-mark">' + icon("book") + '</span><span>KNS Classroom<small>A little more connected.</small></span></a><div class="auth-story-content"><span class="story-tag">' + icon("spark") + ' Space to learn. Room to grow.</span><h1>Your classroom.<br>Your people.<br><em>Your next chapter.</em></h1><p>Good things happen when learning comes together. Meet your classes, share ideas, and make something you’re proud of.</p><div class="story-art" aria-hidden="true"><div class="art-grid"></div><div class="art-orbit"></div><div class="art-card art-card-one">' + icon("book") + '<span>Ideas into action<small>One classroom, many possibilities.</small></span></div><div class="art-card art-card-two"><span class="art-check">' + icon("check") + "</span><span>You’ve got this.</span></div><div class=\"art-spark\">✳</div></div></div><div class=\"auth-story-footer\"><span>Learn with purpose.</span><span>Grow together. ↗</span></div></section><section class=\"auth-form-side\"><div class=\"auth-top\"><span class=\"preview-pill\"><span></span> Interactive design preview</span><span>Fictional sample accounts</span></div><main id=\"main\" class=\"auth-card\" tabindex=\"-1\"><p class=\"eyebrow\">" + (register ? "A new beginning" : "Welcome back") + '</p><h2>' + (register ? "Let’s get you connected." : "A good day to learn.") + '</h2><p class="subtitle">' + (register ? "Create a sample " + role + " account to explore the journey." : "Choose your space and take a look around.") + '</p><div class="role-picker" aria-label="Account type">' + ["student", "professor"].map(item => '<a href="#/' + (register ? "register/" : "login/") + item + '" class="' + (role === item ? "selected" : "") + '" ' + (role === item ? 'aria-current="true"' : "") + ">" + icon(item === "student" ? "book" : "users") + (item === "student" ? "Student" : "Professor") + "</a>").join("") + '</div><form data-form="auth" data-role="' + role + '" data-kind="' + kind + '">' +
      (register ? '<label>Full name<input name="fullName" autocomplete="off" placeholder="' + e(person.name) + '" required maxlength="100"></label>' : "") +
      '<label>' + (role === "student" ? "Student number" : "Faculty ID") + '<input name="identifier" autocomplete="off" placeholder="' + e(person.identifier) + '" required maxlength="50"></label>' +
      (register ? '<label>Email address<input name="email" type="email" autocomplete="off" placeholder="' + e(person.email) + '" required maxlength="160"></label>' : "") +
      '<label>Password<span class="password-wrap"><input name="password" type="password" autocomplete="off" placeholder="Use fictional details only" required minlength="8"><button type="button" class="show-password" data-action="toggle-password" aria-label="Show password">Show</button></span></label>' +
      (register ? '<label>Confirm password<input name="confirmPassword" type="password" autocomplete="off" required minlength="8" placeholder="Enter your sample password again"></label>' : '<div class="auth-options"><span>' + icon("lock") + ' Preview access only</span><button class="text-button" type="button" data-action="recovery">Forgot password?</button></div>') +
      '<p class="form-error" role="alert"></p><button class="button primary full" type="submit">' + (register ? "Create sample account" : "Explore my classroom") + icon("arrow") + '</button></form><p class="auth-switch">' + (register ? 'Already have an account? <a href="#/login/' + role + '">Sign in</a>' : 'New here? <a href="#/register/' + role + '">Create an account</a>') + '</p><div class="demo-access"><span>Just looking around?</span><button type="button" data-action="demo" data-role="' + role + '">Explore the ' + role + " demo " + icon("arrow") + '</button></div><p class="auth-disclaimer">This is a design simulation. No account is created and no credentials are saved.</p></main><footer class="auth-bottom">Kolehiyo ng Subic <span>Student &amp; Professor experience</span></footer></section></div>';
  }
  function shell(content, active, title) {
    const person = profile();
    const nav = [["dashboard", "grid", "Overview"], ["subjects", "book", "My classes"], ["classwork", "work", "Classwork"], ["messages", "chat", "Private messages"]];
    if (state.role === "professor") nav.push(["submissions", "file", "Submissions"], ["alerts", "spark", "Class insights"]);
    const classes = ownedClasses(state.role);
    return '<div class="workspace"><aside class="sidebar ' + (state.menuOpen ? "is-open" : "") + '"><a class="brand" href="#/' + state.role + '/dashboard"><span class="brand-mark">' + icon("book") + '</span><span>KNS Classroom<small>Better, together.</small></span></a><div class="sidebar-label">YOUR WORKSPACE</div><nav aria-label="Workspace">' + nav.map(item => '<a href="#/' + state.role + "/" + item[0] + '" class="nav-link ' + (active === item[0] ? "active" : "") + '" ' + (active === item[0] ? 'aria-current="page"' : "") + ">" + icon(item[1]) + "<span>" + item[2] + "</span>" + (item[0] === "alerts" && !state.alertResolved && !state.alertDismissed ? '<span class="nav-dot"></span>' : "") + "</a>").join("") + '</nav><div class="sidebar-label class-label">YOUR CLASSES <span>' + classes.length + '</span></div><div class="sidebar-classes">' + classes.map(cls => '<a href="#' + classPath(cls.id) + '"><span class="class-dot ' + cls.color + '"></span><span>' + e(cls.code) + '<small>' + e(cls.section) + "</small></span></a>").join("") + '</div><div class="sidebar-bottom"><div class="sidebar-note">' + icon("leaf") + '<div>A little progress,<br>every day.</div></div><div class="account">' + avatar(person.initials) + '<span><strong>' + e(person.name) + '</strong><small>' + (state.role === "professor" ? "Professor" : "Student") + '</small></span><button type="button" class="icon-button" data-action="signout" aria-label="Return to sign-in">' + icon("logout") + '</button></div></div></aside><button class="sidebar-scrim ' + (state.menuOpen ? "visible" : "") + '" data-action="menu" aria-label="Close navigation" type="button"></button><div class="workspace-body"><header class="topbar"><button class="icon-button mobile-menu" type="button" data-action="menu" aria-label="Open navigation" aria-expanded="' + state.menuOpen + '">' + icon("menu") + '</button><div class="breadcrumb">Workspace <span>/</span> <strong>' + e(title) + '</strong></div><button class="search-trigger" type="button" data-action="search">' + icon("search") + '<span>Find a class or classwork</span><kbd>⌘ K</kbd></button><button class="icon-button notification-button" data-action="notifications" type="button" aria-label="Open notifications">' + icon("bell") + '<i></i></button>' + avatar(person.initials, "top-avatar") + '</header><div class="review-bar"><span><i></i><b>Design preview</b><span class="review-copy">Fictional data · Resets on reload</span></span><label>Preview as <select id="preview-role" aria-label="Preview as"><option value="student" ' + (state.role === "student" ? "selected" : "") + '>Student</option><option value="professor" ' + (state.role === "professor" ? "selected" : "") + '>Professor</option></select></label></div><main id="main" class="main-content" tabindex="-1">' + content + '</main><footer class="workspace-footer"><span>KNS Classroom</span><span>A space for the work. And the people behind it.</span></footer></div></div>';
  }
  function heading(eyebrow, title, copy, actions = "") {
    return '<div class="page-heading"><div><p class="eyebrow">' + eyebrow + '</p><h1 tabindex="-1">' + title + '</h1><p class="subtitle">' + copy + "</p></div><div class=\"heading-actions\">" + actions + "</div></div>";
  }
  function classCard(cls) {
    const tasks = state.work.filter(w => w.classId === cls.id && w.status === "Published");
    const completed = tasks.filter(w => mySubmission(w.id)).length;
    return '<a class="class-card ' + cls.color + '" href="#' + classPath(cls.id) + '" data-searchable="' + e((cls.title + " " + cls.code + " " + cls.section).toLowerCase()) + '"><div class="class-card-top"><span class="course-symbol">' + e(cls.initials) + '</span><span class="class-card-code">' + e(cls.code) + '</span><span class="card-arrow">' + icon("arrow") + '</span></div><div class="class-card-body"><span class="class-term">' + e(cls.section) + ' <span>·</span> ' + e(cls.semester) + '</span><h3>' + e(cls.title) + '</h3><p>Prof. ' + e(cls.professor) + '</p><div class="class-card-footer">' + (state.role === "professor" ? '<span>' + icon("users") + cls.students + ' students</span><span>' + cls.joinCode + "</span>" : '<span>' + icon("work") + tasks.length + ' classwork</span><span>' + completed + " completed</span>") + "</div></div></a>";
  }
  function workRow(work) {
    const cls = classById(work.classId);
    const submission = mySubmission(work.id);
    const complete = state.role === "student" && submission;
    return '<button class="work-row" type="button" data-action="open-work" data-id="' + work.id + '"><span class="work-symbol ' + cls.color + '">' + icon(complete ? "check" : "work") + '</span><span class="work-copy"><strong>' + e(work.title) + '</strong><small>' + e(cls.code) + " · " + e(work.category) + " · " + work.points + ' pts</small></span><span class="work-row-right">' + (work.status === "Draft" ? badge("Draft", "gray") : complete ? badge(submission.score !== null ? "Graded" : "Submitted", "green") : '<span class="due">' + icon("calendar") + dueLabel(work.due) + "</span>") + icon("chevron") + "</span></button>";
  }
  function insightCard(full = false) {
    const count = new Set(state.questionSignals.filter(item => item.workId === "responsive").map(item => item.studentId)).size;
    if (state.alertResolved || state.alertDismissed) return empty("All caught up.", state.alertResolved ? "Your clarification has been shared with the class." : "There are no active sample alerts.", button("Show sample insight", "restore-alert", "", "button secondary"));
    return '<article class="insight-card"><div class="insight-label">' + icon("spark") + '<span>A LITTLE CLASS INSIGHT</span>' + badge("Needs a look", "amber") + '</div><h3>' + count + ' students.<br>One shared question.</h3><p>Several students are asking about the <strong>responsive landing page</strong>. A quick clarification could help everyone move forward.</p><div class="insight-context"><span class="tiny-stack">' + avatar("KR") + avatar("JN") + avatar("BC") + '</span><span>' + count + ' distinct students · last 24 hours</span></div><div class="insight-actions">' + button("View questions " + icon("arrow"), "alert-details", "", "button dark") + (full ? button("Dismiss", "dismiss-alert", "", "text-button") : "") + '</div><small>Sample grouping by selected activity · threshold: 5 students</small></article>';
  }
  function dashboard() {
    const classes = ownedClasses(state.role);
    const work = visibleWork().filter(w => w.status === "Published");
    const pending = work.filter(w => !mySubmission(w.id)).sort((a, b) => (a.due || "9999").localeCompare(b.due || "9999"));
    const submitted = work.filter(w => mySubmission(w.id));
    const professor = state.role === "professor";
    const actions = button(icon("plus") + (professor ? "Create a class" : "Join a class"), professor ? "create-class" : "join", "", "button primary");
    const metrics = professor ? [
      ["book", classes.length, "Active classes", "A little space for every idea", "mint"],
      ["users", classes.reduce((sum, c) => sum + c.students, 0), "Students connected", "Across your sample sections", "lavender"],
      ["file", state.submissions.filter(s => work.some(w => w.id === s.workId) && s.score === null).length, "Ready for review", "A fresh perspective awaits", "peach"]
    ] : [
      ["book", classes.length, "Classes this semester", "Your learning, all in one place", "mint"],
      ["work", pending.length, "Classwork to do", "One step at a time", "lavender"],
      ["check", submitted.length, "Classwork submitted", "Look at you making progress", "peach"]
    ];
    return heading("YOUR DAY, AT A GLANCE", professor ? "Good morning, Prof. " + e(profile().first) + "." : "A little progress, " + e(profile().first) + ".", professor ? "A new day to spark a little curiosity." : "Here’s what’s happening in your corner of the classroom.", actions) +
      '<div class="dashboard-grid"><div class="dashboard-primary"><section class="welcome-banner"><div><span class="banner-date">' + icon("calendar") + state.today + '</span><h2>' + (professor ? "Great teaching starts<br>with a connection." : "Big ideas start<br>with small steps.") + '</h2><p>' + (professor ? "A question answered. A new idea shared.<br>You’re making room for both." : "Your classes, conversations, and next little win.<br>Let’s make today a good one.") + '</p><span class="term-chip">' + state.term + '</span></div><div class="banner-art" aria-hidden="true"><div class="orbit-ring"></div><div class="floating-book">' + icon("book") + '</div><div class="floating-star">✳</div><div class="floating-note">' + icon("check") + '<span>Keep growing.</span></div><div class="art-dot dot-one"></div><div class="art-dot dot-two"></div></div></section><div class="metrics">' + metrics.map(m => '<article class="metric"><span class="metric-symbol ' + m[4] + '">' + icon(m[0]) + '</span><strong>' + m[1] + '</strong><h3>' + m[2] + '</h3><p>' + m[3] + "</p></article>").join("") + '</div><section class="classes-section"><div class="section-heading"><div><p class="eyebrow">A PLACE TO BELONG</p><h2>Your classes</h2></div><a class="text-link" href="#/' + state.role + '/subjects">View all classes ' + icon("arrow") + '</a></div><div class="class-grid">' + classes.slice(0, 3).map(classCard).join("") + '</div></section></div><aside class="dashboard-secondary">' + (professor ? insightCard() : '<section class="panel week-panel"><div class="section-heading"><h2>This week</h2><span>November</span></div><div class="week-strip">' + [["M", "16"], ["T", "17"], ["W", "18"], ["T", "19"], ["F", "20"]].map((d, i) => '<div class="' + (i === 1 ? "today" : "") + '"><span>' + d[0] + "</span><strong>" + d[1] + "</strong><i></i></div>").join("") + '</div><div class="week-note"><span class="status-dot"></span>You’ve got room to make good things.</div></section>') + '<section class="panel coming-up"><div class="section-heading"><h2>' + (professor ? "Classwork at a glance" : "Coming up next") + '</h2>' + icon("clock") + '</div><p class="muted small">' + (professor ? "A few things your classes are working on." : "A little planning goes a long way.") + '</p>' + (professor ? work : pending).slice(0, 3).map(w => '<button type="button" class="upcoming-item" data-action="open-work" data-id="' + w.id + '"><span class="upcoming-date"><strong>' + (w.due ? Number(w.due.slice(8)) : "—") + '</strong><small>NOV</small></span><span><small>' + e(classById(w.classId).code) + '</small><strong>' + e(w.title) + '</strong><span>' + e(w.category) + " · " + w.points + ' points</span></span></button>').join("") + '<a class="text-link" href="#/' + state.role + '/classwork">See all classwork ' + icon("arrow") + '</a></section><div class="gentle-note">' + icon("leaf") + '<p>Good work takes a little time.<br><strong>You’re right where you need to be.</strong></p></div></aside></div>';
  }
  function subjectsView() {
    const professor = state.role === "professor";
    return heading("YOUR CLASSROOM CONNECTIONS", "A space for every subject.", "Pick up where you left off, or start something new.", button(icon("plus") + (professor ? "Create a class" : "Join a class"), professor ? "create-class" : "join")) +
      '<div class="list-toolbar"><span>' + ownedClasses(state.role).length + ' classes <span class="muted">· ' + state.term + '</span></span><label class="search-field">' + icon("search") + '<input type="search" data-filter="classes" aria-label="Search classes" placeholder="Find a class"></label></div><div class="class-grid full-grid">' + ownedClasses(state.role).map(classCard).join("") + '</div><p class="filter-empty" hidden>No classes match your search.</p>' +
      (professor ? '<div class="subtle-banner">' + icon("users") + '<div><h3>Your class, your little community.</h3><p>Create a subject and section, then share its code so students can join.</p></div>' + button("Create a class " + icon("plus"), "create-class", "", "button secondary") + "</div>" : '<div class="subtle-banner">' + icon("plus") + '<div><h3>There’s more to explore.</h3><p>Have a class code from your professor? Your next classroom is a few letters away.</p></div>' + button("Join a class", "join", "", "button secondary") + "</div>");
  }
  function classworkView(classId = "") {
    let work = classId ? classWork(classId) : visibleWork();
    const categories = ["All", "Activity", "Project", "Quiz", "Exam"];
    const filtered = state.workFilter === "All" ? work : work.filter(item => item.category === state.workFilter);
    return (classId ? "" : heading("A LITTLE FOCUS GOES A LONG WAY", "Make room for your best work.", state.role === "professor" ? "Create, share, and follow the ideas taking shape in your classes." : "Your next step, your latest submission, and everything in between.", state.role === "professor" ? button(icon("plus") + "Create classwork", "create-work") : "")) +
      '<div class="section-heading work-heading"><div><h2>' + (classId ? "Classwork" : "Your classwork") + '</h2><p class="muted small">' + work.length + ' items in this space</p></div>' + (classId && state.role === "professor" ? button(icon("plus") + "Create classwork", "create-work", 'data-class="' + classId + '"') : "") + '</div><div class="filter-tabs" aria-label="Filter classwork">' + categories.map(cat => '<button type="button" data-action="filter-work" data-category="' + cat + '" aria-pressed="' + (cat === state.workFilter) + '" class="' + (cat === state.workFilter ? "active" : "") + '">' + cat + "</button>").join("") + '</div><section class="panel work-list">' + (filtered.length ? filtered.map(workRow).join("") : empty("A fresh page.", "There’s no " + (state.workFilter === "All" ? "classwork" : state.workFilter.toLowerCase()) + " to show here yet.")) + "</section>";
  }
  function updatesView(cls) {
    const updates = state.updates.filter(u => u.classId === cls.id).sort((a, b) => Number(b.pinned) - Number(a.pinned));
    return '<div class="subject-columns"><div><div class="section-heading"><div><h2>Around the classroom</h2><p class="muted small">The latest notes, ideas, and little reminders.</p></div>' + (state.role === "professor" ? button(icon("plus") + "Post update", "post-update", 'data-class="' + cls.id + '"') : "") + '</div><div class="updates-list">' + (updates.length ? updates.map(update => '<article class="panel update-card"><div class="update-meta">' + avatar(update.author.split(" ").map(n => n[0]).slice(0, 2).join(""), "mint") + '<span><strong>Prof. ' + e(update.author) + '</strong><small>' + e(update.time) + '</small></span>' + (update.pinned ? '<span class="pinned">' + icon("pin") + "Pinned note</span>" : "") + '</div><h3>' + e(update.title) + '</h3><p class="preserve">' + e(update.body) + '</p><div class="update-bottom"><span>' + icon("users") + "Shared with " + e(cls.section) + '</span>' + button(state.readUpdates.includes(update.id) ? icon("check") + "Read" : "Mark as read", "read-update", 'data-id="' + update.id + '"', "text-button") + "</div></article>").join("") : empty("The conversation starts here.", "Updates from your professor will appear in this space.")) + '</div></div><aside><section class="panel about-class"><p class="eyebrow">THIS LITTLE COMMUNITY</p><h3>About the class</h3><p>' + e(cls.description) + '</p><dl><div><dt>Professor</dt><dd>Prof. ' + e(cls.professor) + '</dd></div><div><dt>Section</dt><dd>' + e(cls.section) + '</dd></div><div><dt>Semester</dt><dd>' + e(cls.semester) + '</dd></div><div><dt>Academic year</dt><dd>' + e(cls.year) + '</dd></div><div><dt>Classmates</dt><dd>' + cls.students + ' students</dd></div></dl></section>' + (state.role === "professor" ? '<section class="code-card"><span>' + icon("users") + ' A place to join in</span><h3>Share your class code.</h3><button type="button" class="class-code" data-action="class-code" data-id="' + cls.id + '">' + e(cls.joinCode) + icon("copy") + '</button><p>For ' + e(cls.section) + ' · ' + cls.students + " / " + cls.capacity + " seats</p></section>" : '<section class="private-note">' + icon("lock") + '<h3>A question just for your professor?</h3><p>Some conversations deserve a little space of their own.</p><a class="text-link" href="#' + classPath(cls.id, "private") + '">Send a private message ' + icon("arrow") + "</a></section>") + "</aside></div>";
  }
  function subjectView(cls, tab) {
    const tabs = [["updates", "Updates"], ["classwork", "Classwork"], ["chat", "Group chat"], ["private", "Private messages"]];
    if (state.role === "professor") tabs.push(["submissions", "Submissions"]);
    if (!tabs.some(item => item[0] === tab)) tab = "updates";
    return '<a class="back-link" href="#/' + state.role + '/subjects">' + icon("back") + 'All classes</a><section class="subject-hero ' + cls.color + '"><div><div class="subject-meta">' + badge(cls.code) + '<span>' + e(cls.section) + " · " + e(cls.year) + '</span></div><h1 tabindex="-1">' + e(cls.title) + '</h1><p>With Prof. ' + e(cls.professor) + ' <span>·</span> A place to learn together.</p></div><span class="subject-hero-icon" aria-hidden="true">' + icon("book") + '</span></section><nav class="subject-tabs" aria-label="Subject sections">' + tabs.map(item => '<a href="#' + classPath(cls.id, item[0]) + '" ' + (tab === item[0] ? 'aria-current="page"' : "") + ">" + item[1] + (item[0] === "chat" ? '<span class="tab-dot"></span>' : "") + "</a>").join("") + '</nav>' + (tab === "updates" ? updatesView(cls) : tab === "classwork" ? classworkView(cls.id) : tab === "chat" ? groupChat(cls) : tab === "private" ? messagesView(cls.id) : submissionsView(cls.id));
  }

  function topicOptions(classId, selected = "") {
    return '<option value="">General conversation</option>' + state.work.filter(w => w.classId === classId && w.status === "Published").map(w => '<option value="' + w.id + '" ' + (selected === w.id ? "selected" : "") + ">" + e(w.title) + "</option>").join("");
  }
  function messageBubble(message, isGroup = false) {
    const mine = isGroup ? message.author === (state.role === "professor" ? profile().name : profile().name) : message.role === state.role;
    return '<article class="message ' + (mine ? "mine" : "") + '">' + (isGroup ? avatar(message.initials, message.role === "professor" ? "mint" : "lavender") : "") + '<div><div class="message-meta"><strong>' + e(isGroup ? message.author : mine ? "You" : message.role === "professor" ? "Professor" : "Student") + '</strong><time>' + e(message.time) + '</time></div><div class="message-bubble">' + (message.workId && workById(message.workId) ? '<span class="message-topic">' + icon("work") + e(workById(message.workId).title) + "</span>" : "") + '<p class="preserve">' + e(message.text) + "</p></div></div></article>";
  }
  function groupChat(cls) {
    const messages = state.groupMessages.filter(m => m.classId === cls.id);
    return '<section class="panel chat-panel"><header class="chat-header"><span class="channel-icon">' + icon("chat") + '</span><div><h2>The class conversation</h2><p>' + e(cls.section) + ' · Official group chat</p></div><span class="privacy-label">' + icon("users") + 'Everyone in this class</span></header><div class="chat-history" id="chat-history"><div class="chat-date">A little conversation goes a long way.</div>' + (messages.length ? messages.map(m => messageBubble(m, true)).join("") : empty("Say the first hello.", "Share an idea or ask a class question.")) + '</div><form class="message-composer" data-form="group-message" data-class="' + cls.id + '"><label class="topic-picker">About <select name="topic">' + topicOptions(cls.id) + '</select></label><label class="sr-only" for="group-message">Message to your class</label><textarea id="group-message" name="message" rows="2" maxlength="2000" required placeholder="Share an idea. Ask a question. Say hello."></textarea><div class="composer-bottom"><span>' + icon("users") + 'Your classmates can see this conversation.</span><button type="submit" class="button primary">Send ' + icon("send") + '</button></div><p class="form-error" role="alert"></p></form></section>';
  }
  function getThread(classId) {
    let thread = state.threads.find(t => t.classId === classId && t.studentId === state.profiles.student.id);
    if (!thread) {
      thread = { id: uid("thread"), classId, studentId: state.profiles.student.id, student: state.profiles.student.name, initials: state.profiles.student.initials, messages: [] };
      state.threads.push(thread);
    }
    return thread;
  }
  function messagesView(classId = "") {
    const accessible = ownedClasses(state.role).map(c => c.id);
    if (state.role === "student") accessible.forEach(getThread);
    const threads = state.threads.filter(t => accessible.includes(t.classId) && (!classId || t.classId === classId) && (state.role === "professor" || t.studentId === profile().id));
    const selected = threads.find(t => t.id === state.threadId) || threads[0];
    if (selected) state.threadId = selected.id;
    const cls = selected && classById(selected.classId);
    return (classId ? "" : heading("A LITTLE SPACE TO TALK", "Good conversations start here.", state.role === "professor" ? "Personal questions, thoughtful replies, and a connection with each student." : "A question, a concern, or an idea. Your professor is a message away.")) +
      '<section class="panel inbox"><aside class="thread-list"><div class="thread-list-heading"><h2>Private messages</h2><span>' + icon("lock") + '</span></div><label class="search-field thread-search">' + icon("search") + '<input type="search" data-filter="threads" aria-label="Search conversations" placeholder="Find a conversation"></label>' + threads.map(thread => {
        const subject = classById(thread.classId);
        const last = thread.messages[thread.messages.length - 1];
        const name = state.role === "professor" ? thread.student : "Prof. " + subject.professor;
        return '<button type="button" class="thread-link ' + (selected.id === thread.id ? "selected" : "") + '" data-action="select-thread" data-id="' + thread.id + '" data-searchable="' + e((name + " " + subject.code).toLowerCase()) + '">' + avatar(state.role === "professor" ? thread.initials : subject.professor.split(" ").map(n => n[0]).slice(0, 2).join(""), "mint") + '<span><strong>' + e(name) + '</strong><small>' + e(subject.code) + " · " + e(subject.section) + '</small><p>' + e(last ? last.text : "Start a little conversation.") + "</p></span></button>";
      }).join("") + '</aside><div class="conversation">' + (selected ? '<header class="chat-header">' + avatar(state.role === "professor" ? selected.initials : cls.professor.split(" ").map(n => n[0]).slice(0, 2).join(""), "mint") + '<div><h2>' + e(state.role === "professor" ? selected.student : "Prof. " + cls.professor) + '</h2><p>' + e(cls.title) + '</p></div><span class="privacy-label">' + icon("lock") + 'Just the two of you</span></header><div class="chat-history" id="chat-history"><div class="conversation-notice">' + icon("lock") + 'Private to this student and their professor.</div>' + (selected.messages.length ? selected.messages.map(m => messageBubble(m)).join("") : empty("A good place to start.", "Your conversation will appear here.")) + '</div><form class="message-composer" data-form="private-message" data-thread="' + selected.id + '"><label class="topic-picker">About <select name="topic">' + topicOptions(cls.id) + '</select></label><label class="sr-only" for="private-message">Private message</label><textarea id="private-message" name="message" rows="2" maxlength="2000" required placeholder="Write a little note…"></textarea><div class="composer-bottom"><span>Visible in this private conversation only.</span><button type="submit" class="button primary">Send ' + icon("send") + '</button></div><p class="form-error" role="alert"></p></form>' : empty("No conversations yet.", "Your student conversations will appear here.")) + "</div></section>";
  }
  function submissionsView(classId = "") {
    const work = (classId ? classWork(classId) : visibleWork()).filter(w => w.status === "Published");
    const selected = work.find(w => w.id === state.selectedWork) || work[0];
    if (selected) state.selectedWork = selected.id;
    const submissions = selected ? state.submissions.filter(s => s.workId === selected.id) : [];
    return (classId ? "" : heading("SEE THE IDEAS TAKING SHAPE", "A little look at their progress.", "Review sample submissions and share feedback that helps students grow.")) +
      '<section class="panel submissions-panel"><div class="submissions-toolbar"><div><h2>Student submissions</h2><p class="small muted">Fictional sample responses · ' + submissions.length + ' shown</p></div><label>Classwork<select id="submission-work" aria-label="Select classwork for submissions">' + work.map(w => '<option value="' + w.id + '" ' + (w.id === selected?.id ? "selected" : "") + ">" + e(w.title) + "</option>").join("") + '</select></label></div><div class="submission-stats"><span><strong>' + submissions.length + '</strong> sample submissions</span><span><strong>' + submissions.filter(s => s.score === null).length + '</strong> awaiting review</span><span><strong>' + submissions.filter(s => s.score !== null).length + '</strong> graded</span></div><div class="submission-filters"><label class="search-field">' + icon("search") + '<input type="search" data-filter="submissions" aria-label="Search students" placeholder="Find a student"></label><label><span class="sr-only">Submission status</span><select id="submission-status"><option value="all">All statuses</option><option value="pending">Awaiting review</option><option value="graded">Graded</option></select></label></div>' + (submissions.length ? '<div class="table-scroll"><table class="submission-table"><caption class="sr-only">Sample submissions for ' + e(selected.title) + '</caption><thead><tr><th>Student</th><th>Submitted</th><th>Attachments</th><th>Status</th><th>Score</th><th><span class="sr-only">Review</span></th></tr></thead><tbody>' + submissions.map(s => '<tr data-searchable="' + e(s.student.toLowerCase()) + '" data-status="' + (s.score === null ? "pending" : "graded") + '"><td><span class="student-cell">' + avatar(s.initials, "lavender") + '<strong>' + e(s.student) + '</strong></span></td><td>' + e(s.time) + '</td><td>' + s.files.length + ' files</td><td>' + badge(s.score === null ? "Awaiting review" : "Graded", s.score === null ? "amber" : "green") + '</td><td>' + (s.score === null ? "—" : e(s.score) + " / " + selected.points) + '</td><td>' + button("Review " + icon("arrow"), "review-submission", 'data-id="' + s.id + '"', "text-button") + "</td></tr>").join("") + "</tbody></table></div>" : empty("Good things are on the way.", "Sample submissions will appear here when you submit work as a Student.")) + '<p class="table-note">' + icon("leaf") + " These are sample responses, not a complete class roster.</p></section>";
  }
  function alertsView() {
    return heading("LESS GUESSWORK. MORE CONNECTION.", "A little insight can go a long way.", "Notice shared questions and give your class a helpful nudge.") + '<div class="insights-grid">' + insightCard(true) + '<section class="panel insight-explainer"><span class="large-symbol lavender">' + icon("spark") + '</span><h2>Small signals.<br>Thoughtful responses.</h2><p>When several different students ask about the same activity, a sample insight brings it to your attention.</p><ol><li><strong>A shared activity</strong><span>Questions are grouped by the activity selected in the composer.</span></li><li><strong>Different students</strong><span>Repeated messages from one student count once.</span></li><li><strong>A helpful next step</strong><span>Review the questions, then share a clarification with the class.</span></li></ol><p class="small muted">This preview uses seeded signals and local activity selections. It does not analyze text or send notifications.</p></section></div>';
  }
  function filePicker() {
    return '<div class="file-selection"><label class="file-drop" for="local-files">' + icon("upload") + '<strong>Choose a file or photo</strong><span>Up to 3 files · 5 MB each</span><span class="small">JPG, PNG, WebP, PDF, TXT, DOCX, XLSX, PPTX</span><input id="local-files" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.docx,.xlsx,.pptx" multiple></label><div class="file-previews" id="file-previews"></div><button type="button" class="text-button" data-action="sample-file">' + icon("plus") + ' Try a fictional sample attachment</button><p class="file-error form-error" role="alert"></p><p class="small muted">Local preview only. File contents are never uploaded or saved. The selection clears when you close this view.</p></div>';
  }
  function fileChips(files) {
    return files.length ? '<div class="file-chips">' + files.map(f => '<span>' + icon("file") + '<span><strong>' + e(f.name) + '</strong><small>' + e(f.size) + " · " + e(f.kind || "File") + "</small></span></span>").join("") + "</div>" : "";
  }
  function openWork(id) {
    const work = workById(id);
    if (!work) return;
    const cls = classById(work.classId);
    const submission = mySubmission(work.id);
    const details = '<div class="work-detail-meta">' + badge(work.category, cls.color) + '<span>' + e(cls.code) + " · " + e(cls.section) + '</span><span>' + icon("calendar") + dueLabel(work.due) + '</span><span>' + work.points + ' points</span></div><h3 class="dialog-section-title">A little direction</h3><p class="preserve work-instructions">' + e(work.instructions) + '</p>' + (work.resources.length ? '<h3 class="dialog-section-title">Class resources</h3>' + fileChips(work.resources) + '<p class="small muted">Fictional attachment names; no downloads are connected.</p>' : "");
    if (state.role === "professor") {
      openDialog(work.title, details + '<div class="form-footer"><span class="muted small">' + e(work.status) + ' · Sample classwork</span>' + button("View submissions " + icon("arrow"), "work-submissions", 'data-id="' + work.id + '"') + "</div>", true);
    } else if (submission) {
      openDialog(work.title, details + '<div class="submitted-notice">' + icon("check") + '<div><strong>Your work is here.</strong><span>Submitted ' + e(submission.time) + '</span></div></div><h3 class="dialog-section-title">Your response</h3><p class="preserve">' + e(submission.answer || "Files selected for the design preview.") + '</p>' + fileChips(submission.files) + (submission.score !== null ? '<div class="feedback-card"><strong>' + submission.score + " / " + work.points + ' points</strong><p>' + e(submission.feedback) + '</p></div>' : '<p class="small muted">Waiting for a little feedback from your professor.</p>') + '<div class="form-footer">' + button("All done", "close-dialog", "", "button primary") + "</div>", true);
    } else {
      openDialog(work.title, details + '<form data-form="submit-work" data-id="' + work.id + '"><h3 class="dialog-section-title">Your turn to share</h3>' + (work.mode !== "files" ? '<label>Your response<textarea name="answer" rows="4" maxlength="5000" required placeholder="Put your ideas into words…"></textarea></label>' : "") + (work.mode !== "answer" ? filePicker() : "") + previewNote + formEnd("Submit sample work " + icon("arrow")) + "</form>", true);
    }
  }
  function createClass() {
    openDialog("Make room for a new class.", '<p class="subtitle">Create a subject and section, or add a section to one of your subjects.</p><form data-form="create-class"><label>Subject<select id="existing-subject" name="existing"><option value="">Create a new subject</option>' + ownedClasses("professor").filter((c, i, a) => a.findIndex(s => s.code === c.code) === i).map(c => '<option value="' + c.id + '">' + e(c.code) + " · " + e(c.title) + "</option>").join("") + '</select></label><div class="form-grid"><label>Subject code<input id="new-class-code" name="code" maxlength="24" placeholder="e.g. IT 204" required></label><label>Section<input name="section" maxlength="40" placeholder="e.g. BSIT 2C" required></label></div><label>Subject name<input id="new-class-title" name="title" maxlength="100" placeholder="Give your subject a name" required></label><label>A little about this class<textarea name="description" rows="2" maxlength="600" placeholder="What will you explore together?"></textarea></label><div class="form-grid"><label>Academic year<select name="year"><option>2026–2027</option><option>2027–2028</option></select></label><label>Semester<select name="semester"><option>1st semester</option><option>2nd semester</option><option>Summer</option></select></label></div><label>Class capacity<input name="capacity" type="number" min="1" max="300" value="35" required></label>' + previewNote + formEnd("Create class " + icon("plus")) + "</form>", true);
  }
  function classCodeDialog(cls, created = false) {
    openDialog(created ? "A new little community." : "An invitation to join in.", '<div class="class-created"><span class="large-symbol mint">' + icon("check") + '</span><h3>' + e(cls.title) + '</h3><p>' + e(cls.section) + " · " + e(cls.semester) + " · " + e(cls.year) + '</p><label class="code-label">Your class code<input id="share-code" value="' + e(cls.joinCode) + '" readonly></label>' + button(icon("copy") + "Copy class code", "copy-code", "", "button secondary") + '<p class="small muted">Try the code in Student view to join this sample class.</p></div><div class="form-footer">' + button("Open classroom " + icon("arrow"), "open-class", 'data-id="' + cls.id + '"') + "</div>");
  }
  function joinClass() {
    const available = state.classes.find(cls => !state.enrolled.includes(cls.id) && cls.students < cls.capacity);
    openDialog("Your next classroom is here.", '<p class="subtitle">Enter the class code shared by your professor.</p><form data-form="join"><label>Class code<input class="join-input" name="code" maxlength="20" autocomplete="off" placeholder="e.g. NET-4Q8" required></label><div class="sample-hint">' + icon("spark") + '<p>Just exploring? Try <button type="button" class="text-button" data-action="fill-code" data-code="' + e(available?.joinCode || "NET-4Q8") + '">' + e(available?.joinCode || "NET-4Q8") + "</button> for a fictional sample class.</p></div>" + previewNote + formEnd("Find my class " + icon("arrow")) + "</form>");
  }
  function createWork(classId = "") {
    openDialog("A new idea to work on.", '<p class="subtitle">Give your students a little direction and space to make it their own.</p><form data-form="create-work"><label>Class<select name="classId">' + ownedClasses("professor").map(c => '<option value="' + c.id + '" ' + (c.id === classId ? "selected" : "") + ">" + e(c.code) + " · " + e(c.section) + "</option>").join("") + '</select></label><label>Title<input name="title" maxlength="120" placeholder="What will your students create or explore?" required></label><div class="form-grid"><label>Category<select name="category"><option>Activity</option><option>Project</option><option>Quiz</option><option>Exam</option></select></label><label>Points<input name="points" type="number" min="1" max="1000" value="20" required></label></div><label>Instructions<textarea name="instructions" rows="4" maxlength="5000" placeholder="A little guidance goes a long way…" required></textarea></label><div class="form-grid"><label>Due date<input name="due" type="date" value="2026-11-24"></label><label>Response format<select name="mode"><option value="both">Written answer + optional files</option><option value="files">Files only</option><option value="answer">Written answer only</option></select></label></div><h3 class="dialog-section-title">Add a class resource</h3>' + filePicker() + previewNote + '<p class="form-error" role="alert"></p><div class="form-footer"><button class="button secondary" type="submit" name="intent" value="draft">Save sample draft</button><button class="button primary" type="submit" name="intent" value="publish">Publish sample classwork ' + icon("arrow") + "</button></div></form>", true);
  }
  function postUpdate(classId, clarification = false) {
    const cls = classById(classId);
    openDialog(clarification ? "A little clarity for everyone." : "Share a little update.", '<p class="subtitle">A note for ' + e(cls.title) + " · " + e(cls.section) + '.</p><form data-form="post-update" data-class="' + classId + '" data-clarification="' + clarification + '"><label>Title<input name="title" maxlength="150" required value="' + (clarification ? "A quick clarification: responsive landing page" : "") + '" placeholder="What would you like your class to know?"></label><label>Your note<textarea name="body" rows="6" maxlength="4000" required placeholder="Share a reminder, an idea, or a little encouragement…">' + (clarification ? "For the landing page project, please include mobile and desktop screenshots. Choose breakpoints that suit your content, and explain your layout decisions in a short paragraph." : "") + '</textarea></label><label class="checkbox-label"><input type="checkbox" name="pinned" ' + (clarification ? "checked" : "") + '> Pin this note to the top</label>' + previewNote + formEnd("Share with the class " + icon("send")) + "</form>", true);
  }
  function alertDetails() {
    const signals = state.questionSignals.filter(s => s.workId === "responsive");
    openDialog("A shared question, a helpful next step.", '<div class="alert-summary">' + icon("spark") + '<div><strong>' + new Set(signals.map(s => s.studentId)).size + ' different students asked about one activity.</strong><p>Build a responsive landing page · CC 104 · BSIT 2A</p></div></div><p class="small muted">Fictional questions, visible here to the Professor only. This grouping uses the selected activity, not text analysis.</p><div class="question-list">' + signals.map(s => '<article>' + avatar(s.name.split(" ").map(n => n[0]).slice(0, 2).join(""), "lavender") + '<div><strong>' + e(s.name) + '</strong><p>' + e(s.question) + "</p></div></article>").join("") + '</div><div class="form-footer"><span class="muted small">One student counts once.</span>' + button("Post a clarification " + icon("arrow"), "clarify") + "</div>", true);
  }
  function reviewSubmission(id) {
    const submission = state.submissions.find(s => s.id === id);
    const work = submission && workById(submission.workId);
    if (!work) return;
    openDialog("A little look at their work.", '<div class="review-student">' + avatar(submission.initials, "lavender") + '<div><h3>' + e(submission.student) + '</h3><p>' + e(work.title) + " · " + e(submission.time) + '</p></div></div><h3 class="dialog-section-title">Written response</h3><p class="preserve response-text">' + e(submission.answer || "This is a file-based sample submission.") + '</p><h3 class="dialog-section-title">Attachments</h3>' + fileChips(submission.files) + '<p class="small muted">Attachment names are preview metadata. File content is not retained or available for download.</p><form data-form="grade" data-id="' + id + '"><div class="form-grid"><label>Score (out of ' + work.points + ')<input type="number" name="score" step=".01" min="0" max="' + work.points + '" value="' + e(submission.score ?? "") + '" required></label><div></div></div><label>A little feedback<textarea name="feedback" rows="3" maxlength="2000" placeholder="What worked well? What could they try next?">' + e(submission.feedback) + "</textarea></label>" + previewNote + formEnd("Save sample feedback " + icon("check")) + "</form>", true);
  }
  function renderFilePreviews() {
    const container = document.getElementById("file-previews");
    if (!container) return;
    container.innerHTML = draftFiles.map((file, i) => '<div class="file-preview">' + (file.url ? '<img alt="Local preview of ' + e(file.name) + '" src="' + e(file.url) + '">' : '<span class="file-preview-icon">' + icon("file") + '</span>') + '<span><strong>' + e(file.name) + '</strong><small>' + e(file.size) + " · " + (file.sample ? "Fictional sample" : "Local preview") + '</small></span><button class="icon-button" type="button" data-action="remove-file" data-index="' + i + '" aria-label="Remove ' + e(file.name) + '">' + icon("close") + "</button></div>").join("");
  }
  function recordSignal(thread, workId, text) {
    if (!workId || thread.studentId !== state.profiles.student.id || state.role !== "student") return;
    if (!state.questionSignals.some(s => s.studentId === thread.studentId && s.workId === workId)) state.questionSignals.push({ studentId: thread.studentId, name: thread.student, workId, question: text });
  }
  function searchDialog() {
    openDialog("Find your next little step.", '<label class="search-field search-dialog-field">' + icon("search") + '<input type="search" id="global-search" aria-label="Search classes and classwork" placeholder="Search a class or classwork title…" autocomplete="off"></label><div id="search-results"></div>');
    updateSearch("");
    document.getElementById("global-search").focus();
  }
  function updateSearch(value) {
    const query = value.toLowerCase().trim();
    const classes = ownedClasses(state.role).filter(c => (c.title + " " + c.code).toLowerCase().includes(query));
    const work = visibleWork().filter(w => w.title.toLowerCase().includes(query));
    document.getElementById("search-results").innerHTML = '<p class="eyebrow search-section">CLASSES</p>' + classes.map(c => '<a class="search-result" href="#' + classPath(c.id) + '">' + icon("book") + '<span>' + e(c.title) + '<small>' + e(c.code) + " · " + e(c.section) + "</small></span>" + icon("arrow") + "</a>").join("") + '<p class="eyebrow search-section">CLASSWORK</p>' + work.slice(0, 6).map(w => '<button type="button" class="search-result" data-action="open-work" data-id="' + w.id + '">' + icon("work") + '<span>' + e(w.title) + '<small>' + e(classById(w.classId).code) + "</small></span>" + icon("arrow") + "</button>").join("") + (!classes.length && !work.length ? empty("Nothing here just yet.", "Try another class or classwork title.") : "");
  }

  function setMenu(open) {
    state.menuOpen = open;
    const sidebar = document.querySelector(".sidebar");
    sidebar?.classList.toggle("is-open", open);
    document.querySelector(".sidebar-scrim")?.classList.toggle("visible", open);
    const trigger = document.querySelector(".mobile-menu");
    trigger?.setAttribute("aria-expanded", String(open));
    const workspace = document.querySelector(".workspace-body");
    if (workspace) workspace.inert = open;
    if (open) sidebar?.querySelector("a[aria-current], a")?.focus();
    else trigger?.focus();
  }
  matchMedia("(max-width: 760px)").addEventListener("change", event => {
    if (!event.matches && state.menuOpen) setMenu(false);
  });
  function filterRows() {
    const input = app.querySelector('[data-filter="submissions"]');
    const status = document.getElementById("submission-status");
    if (!input) return;
    app.querySelectorAll("tbody tr").forEach(row => { row.hidden = !row.dataset.searchable.includes(input.value.toLowerCase()) || (status.value !== "all" && row.dataset.status !== status.value); });
  }
  document.addEventListener("click", event => {
    if (event.target.closest(".skip-link")) {
      event.preventDefault();
      const main = document.getElementById("main");
      main?.focus(); main?.scrollIntoView({ block: "start" });
      return;
    }
    const target = event.target.closest("[data-action]");
    if (!target) return;
    const action = target.dataset.action;
    if (action === "close-dialog") modal.close();
    else if (action === "demo") { state.role = target.dataset.role; go("/" + state.role + "/dashboard"); }
    else if (action === "signout") go("/login/" + state.role);
    else if (action === "toggle-password") {
      const input = target.previousElementSibling;
      input.type = input.type === "password" ? "text" : "password";
      target.textContent = input.type === "password" ? "Show" : "Hide";
      target.setAttribute("aria-label", input.type === "password" ? "Show password" : "Hide password");
    }
    else if (action === "menu") {
      setMenu(!state.menuOpen);
    }
    else if (action === "recovery") openDialog("A little help getting back in.", '<p class="subtitle">Explore the recovery screen with a fictional email.</p><form data-form="recovery"><label>Email address<input name="email" type="email" placeholder="alex.rivera@example.test" required></label><p class="small muted">This preview will show a confirmation only. No email is sent.</p>' + formEnd("Show sample confirmation") + "</form>");
    else if (action === "create-class") createClass();
    else if (action === "join") joinClass();
    else if (action === "fill-code") modal.querySelector('[name="code"]').value = target.dataset.code;
    else if (action === "class-code") classCodeDialog(classById(target.dataset.id));
    else if (action === "copy-code") {
      const input = document.getElementById("share-code");
      input.select();
      if (navigator.clipboard?.writeText) navigator.clipboard.writeText(input.value).then(() => toast("Class code copied. Share a little connection.")).catch(() => toast("Code selected. Use your device’s copy command."));
      else toast("Code selected. Use your device’s copy command.");
    }
    else if (action === "open-class") { modal.close(); go(classPath(target.dataset.id)); }
    else if (action === "filter-work") { state.workFilter = target.dataset.category; render(false); }
    else if (action === "open-work") openWork(target.dataset.id);
    else if (action === "create-work") createWork(target.dataset.class || route.classId || "");
    else if (action === "work-submissions") { state.selectedWork = target.dataset.id; const work = workById(target.dataset.id); modal.close(); go(classPath(work.classId, "submissions")); }
    else if (action === "review-submission") reviewSubmission(target.dataset.id);
    else if (action === "post-update") postUpdate(target.dataset.class);
    else if (action === "read-update") {
      if (!state.readUpdates.includes(target.dataset.id)) state.readUpdates.push(target.dataset.id);
      render(false); toast("A little note, all caught up.");
    }
    else if (action === "select-thread") { state.threadId = target.dataset.id; render(false); }
    else if (action === "alert-details") alertDetails();
    else if (action === "clarify") postUpdate("web", true);
    else if (action === "dismiss-alert") { state.alertDismissed = true; render(false); toast("Sample insight dismissed."); }
    else if (action === "restore-alert") { state.alertDismissed = false; state.alertResolved = false; render(false); }
    else if (action === "remove-file") {
      const index = Number(target.dataset.index);
      if (draftFiles[index]?.url) URL.revokeObjectURL(draftFiles[index].url);
      draftFiles.splice(index, 1); renderFilePreviews();
    }
    else if (action === "sample-file") {
      if (draftFiles.length >= 3) { toast("Choose up to 3 preview files."); return; }
      draftFiles.push({ name: "sample-project-preview.pdf", size: "240 KB", kind: "PDF", sample: true });
      renderFilePreviews();
    }
    else if (action === "search") searchDialog();
    else if (action === "notifications") {
      openDialog("A few things to keep you connected.", '<div class="notification-list">' + (state.role === "professor" ? '<button type="button" data-action="alert-details"><span class="metric-symbol lavender">' + icon("spark") + '</span><span><strong>A shared question needs a little clarity.</strong><small>Several students asked about the responsive landing page.</small></span>' + icon("arrow") + "</button>" : '<button type="button" data-action="open-work" data-id="semantic"><span class="metric-symbol peach">' + icon("calendar") + '</span><span><strong>A little reminder for tomorrow.</strong><small>Semantic HTML, in your own words · CC 104</small></span>' + icon("arrow") + '</button><a href="#/student/subject/web/updates"><span class="metric-symbol mint">' + icon("book") + '</span><span><strong>A fresh note from Prof. Santos.</strong><small>A little guidance for Friday’s project</small></span>' + icon("arrow") + "</a>") + '</div><p class="small muted">Fictional notifications for this design preview.</p>');
    }
  });
  document.addEventListener("input", event => {
    if (event.target.id === "global-search") updateSearch(event.target.value);
    if (event.target.dataset.filter === "submissions") filterRows();
    if (["classes", "threads"].includes(event.target.dataset.filter)) {
      const query = event.target.value.toLowerCase().trim();
      const selector = event.target.dataset.filter === "classes" ? ".class-card" : ".thread-link";
      app.querySelectorAll(selector).forEach(card => card.hidden = !card.dataset.searchable.includes(query));
      const emptyState = app.querySelector(".filter-empty");
      if (emptyState) emptyState.hidden = Array.from(app.querySelectorAll(selector)).some(card => !card.hidden);
    }
  });
  document.addEventListener("change", event => {
    if (event.target.id === "preview-role") { state.threadId = ""; state.workFilter = "All"; go("/" + event.target.value + "/dashboard"); }
    if (event.target.id === "submission-work") { state.selectedWork = event.target.value; render(false); }
    if (event.target.id === "submission-status") filterRows();
    if (event.target.id === "existing-subject") {
      const cls = classById(event.target.value);
      const code = document.getElementById("new-class-code");
      const title = document.getElementById("new-class-title");
      code.value = cls?.code || ""; title.value = cls?.title || "";
      code.readOnly = !!cls; title.readOnly = !!cls;
    }
    if (event.target.id === "local-files") {
      const error = modal.querySelector(".file-error");
      error.textContent = "";
      const allowed = /\.(jpe?g|png|webp|pdf|txt|docx|xlsx|pptx)$/i;
      for (const file of Array.from(event.target.files)) {
        if (draftFiles.length >= 3) { error.textContent = "Choose up to 3 files. Remove one to choose another."; break; }
        if (!allowed.test(file.name) || file.size < 1 || file.size > 5 * 1024 * 1024) { error.textContent = "Choose a supported, nonempty file up to 5 MB."; continue; }
        const image = ["image/jpeg", "image/png", "image/webp"].includes(file.type);
        draftFiles.push({ name: file.name, size: filesize(file.size), kind: image ? "Image" : file.name.split(".").pop().toUpperCase(), url: image ? URL.createObjectURL(file) : "" });
      }
      event.target.value = "";
      renderFilePreviews();
    }
  });
  document.addEventListener("submit", event => {
    const form = event.target;
    if (!form.matches("[data-form]")) return;
    event.preventDefault();
    if (!form.reportValidity()) return;
    const values = new FormData(form);
    const value = name => String(values.get(name) || "").trim();
    const kind = form.dataset.form;
    if (kind === "auth") {
      state.role = form.dataset.role;
      if (form.dataset.kind === "register") {
        if (values.get("password") !== values.get("confirmPassword")) { showError(form, "Your sample passwords don’t match yet."); return; }
        const person = profile();
        person.name = value("fullName"); person.first = person.name.split(/\s+/)[0];
        person.initials = person.name.split(/\s+/).slice(0, 2).map(n => n[0]).join("").toUpperCase();
        person.identifier = value("identifier"); person.email = value("email");
        if (state.role === "professor") state.classes.filter(c => c.professorId === person.id).forEach(c => c.professor = person.name);
        else {
          state.threads.filter(t => t.studentId === person.id).forEach(t => { t.student = person.name; t.initials = person.initials; });
          state.submissions.filter(s => s.studentId === person.id).forEach(s => { s.student = person.name; s.initials = person.initials; });
        }
      }
      go("/" + state.role + "/dashboard");
      toast(form.dataset.kind === "register" ? "Your sample classroom is ready. Welcome in!" : "Welcome to your classroom design preview.");
    }
    else if (kind === "recovery") {
      openDialog("A little reassurance.", '<div class="class-created"><span class="large-symbol mint">' + icon("check") + '</span><h3>This is where confirmation would appear.</h3><p>A connected system would guide you through recovery. This design preview sends no email.</p></div><div class="form-footer">' + button("Back to sign in", "close-dialog") + "</div>");
    }
    else if (kind === "create-class") {
      const code = value("code").toUpperCase();
      const section = value("section").toUpperCase();
      const title = value("title");
      if (!code || !section || !title) { showError(form, "Give your class a code, section, and name."); return; }
      const existing = classById(value("existing"));
      if (!existing && state.classes.some(c => c.code.toUpperCase() === code)) { showError(form, "That subject code already exists. Choose it from the Subject list to add a section."); return; }
      if (state.classes.some(c => c.code.toUpperCase() === code && c.section.toUpperCase() === section && c.year === value("year") && c.semester === value("semester"))) { showError(form, "That section already exists for this subject and term."); return; }
      const id = uid("class");
      const cls = { id, code, title, section, year: value("year"), semester: value("semester"), professorId: profile().id, professor: profile().name, initials: title.split(/\s+/).slice(0, 2).map(n => n[0]).join("").toUpperCase(), color: ["mint", "lavender", "peach", "sand"][state.nextId % 4], students: 0, capacity: Number(value("capacity")), joinCode: "KNS-" + state.nextId + "A", description: value("description") || "A fresh space to explore ideas and learn together." };
      state.classes.push(cls);
      classCodeDialog(cls, true); renderBehindDialog();
      toast("A new class, a new possibility.");
    }
    else if (kind === "join") {
      const code = value("code").toUpperCase();
      const cls = state.classes.find(c => c.joinCode.toUpperCase() === code);
      if (!cls) { showError(form, "That code doesn’t match a sample class. Check it and try again."); return; }
      if (state.enrolled.includes(cls.id)) { showError(form, "You’re already part of this class. Find it in My classes."); return; }
      if (cls.students >= cls.capacity) { showError(form, "This sample class is full. Try another code."); return; }
      state.enrolled.push(cls.id); cls.students++;
      getThread(cls.id); modal.close(); go(classPath(cls.id));
      toast("You’re part of " + cls.title + ". Welcome in!");
    }
    else if (kind === "create-work") {
      if (!value("title") || !value("instructions")) { showError(form, "Add a title and a little direction for your students."); return; }
      const published = event.submitter?.value !== "draft";
      const resources = draftFiles.map(({ name, size, kind: fileKind }) => ({ name, size, kind: fileKind }));
      const work = { id: uid("work"), classId: value("classId"), title: value("title"), category: value("category"), due: value("due"), points: Number(value("points")), status: published ? "Published" : "Draft", mode: value("mode"), instructions: value("instructions"), resources, responses: 0 };
      state.work.unshift(work); state.workFilter = "All";
      modal.close(); go(classPath(work.classId, "classwork"));
      toast(published ? "Your sample classwork is ready to explore." : "Sample draft saved for a little more thought.");
    }
    else if (kind === "submit-work") {
      const work = workById(form.dataset.id);
      if (mySubmission(work.id)) { showError(form, "This sample work is already submitted."); return; }
      if (work.mode !== "files" && !value("answer")) { showError(form, "Add your written response before submitting."); return; }
      if (work.mode === "files" && !draftFiles.length) { showError(form, "Choose a local file or try a fictional attachment."); return; }
      state.submissions.push({ id: uid("submission"), workId: work.id, studentId: profile().id, student: profile().name, initials: profile().initials, time: "Just now · Design preview", answer: value("answer"), files: draftFiles.map(({ name, size, kind: fileKind }) => ({ name, size, kind: fileKind })), score: null, feedback: "" });
      work.responses++; modal.close(); render(false); toast("A little progress worth celebrating. Sample work submitted!");
    }
    else if (kind === "post-update") {
      if (!value("title") || !value("body")) { showError(form, "Give your note a title and a little substance."); return; }
      state.updates.unshift({ id: uid("update"), classId: form.dataset.class, author: profile().name, time: "Just now", title: value("title"), body: value("body"), pinned: values.has("pinned") });
      if (form.dataset.clarification === "true") state.alertResolved = true;
      modal.close(); go(classPath(form.dataset.class, "updates")); toast("A little update, shared with your class.");
    }
    else if (kind === "group-message" || kind === "private-message") {
      const text = value("message");
      if (!text) { showError(form, "Add a little message before sending."); return; }
      if (kind === "group-message") {
        state.groupMessages.push({ id: uid("message"), classId: form.dataset.class, author: profile().name, role: state.role, initials: profile().initials, text, time: "Just now", workId: value("topic") });
        recordSignal({ studentId: profile().id, student: profile().name }, value("topic"), text);
      } else {
        const thread = state.threads.find(t => t.id === form.dataset.thread);
        thread.messages.push({ role: state.role, text, time: "Just now", workId: value("topic") });
        recordSignal(thread, value("topic"), text);
      }
      render(false);
      const history = document.getElementById("chat-history");
      if (history) history.scrollTop = history.scrollHeight;
      document.querySelector('.message-composer textarea')?.focus();
    }
    else if (kind === "grade") {
      const submission = state.submissions.find(s => s.id === form.dataset.id);
      submission.score = Number(value("score")); submission.feedback = value("feedback");
      modal.close(); render(false); toast("A little feedback can make a big difference. Saved in this preview.");
    }
  });
  function renderBehindDialog() {
    const open = modal.open;
    // Updating the workspace does not touch the separate native dialog.
    drawApp(false);
    if (open) modal.querySelector("input,button")?.focus();
  }
  function parseRoute() {
    const parts = location.hash.replace(/^#\/?/, "").split("/");
    return { area: parts[0] || "login", view: parts[1] || "dashboard", classId: parts[2] || "", tab: parts[3] || "updates" };
  }
  function drawApp(focus = true) {
    route = parseRoute();
    state.menuOpen = false;
    if (["login", "register"].includes(route.area)) {
      const authRole = route.view === "professor" ? "professor" : "student";
      app.innerHTML = authView(route.area, authRole);
      document.title = (route.area === "register" ? "Create an account" : "Welcome") + " · KNS Classroom preview";
    } else {
      state.role = route.area === "professor" ? "professor" : "student";
      let html, title, active = route.view;
      if (route.view === "dashboard") { html = dashboard(); title = "Overview"; }
      else if (route.view === "subjects") { html = subjectsView(); title = "My classes"; }
      else if (route.view === "classwork") { html = classworkView(); title = "Classwork"; }
      else if (route.view === "messages") { html = messagesView(); title = "Private messages"; }
      else if (route.view === "submissions" && state.role === "professor") { html = submissionsView(); title = "Submissions"; }
      else if (route.view === "alerts" && state.role === "professor") { html = alertsView(); title = "Class insights"; }
      else if (route.view === "subject" && ownedClasses(state.role).some(c => c.id === route.classId)) { html = subjectView(classById(route.classId), route.tab); active = "subjects"; title = classById(route.classId).code; }
      else { html = empty("Let’s find your classroom.", "This sample view isn’t available. Head back to your classes.", '<a class="button primary" href="#/' + state.role + '/subjects">My classes ' + icon("arrow") + "</a>"); title = "Find your classroom"; }
      app.innerHTML = shell(html, active, title);
      document.title = title + " · KNS Classroom preview";
    }
    if (focus) {
      window.scrollTo(0, 0);
      requestAnimationFrame(() => (document.querySelector("#main h1") || document.getElementById("main"))?.focus({ preventScroll: true }));
    }
  }
  function render(focus = true) {
    if (modal.open) modal.close();
    clearFiles();
    drawApp(focus);
  }
  modal.addEventListener("close", () => {
    if (modal.open) return;
    clearFiles();
    modalContent.innerHTML = "";
    if (dialogTrigger?.isConnected) dialogTrigger.focus();
  });
  modal.addEventListener("click", event => {
    if (event.target !== modal) return;
    const rect = modal.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) modal.close();
  });
  document.addEventListener("keydown", event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k" && !["login", "register"].includes(route.area)) { event.preventDefault(); searchDialog(); }
    if (event.key === "Escape" && state.menuOpen) {
      setMenu(false);
    }
  });
  addEventListener("hashchange", () => render());
  addEventListener("pagehide", clearFiles);
  render();
})();
