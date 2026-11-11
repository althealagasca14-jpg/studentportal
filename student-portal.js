(() => {
    "use strict";
    const shell = document.body;
    const main = document.getElementById("student-main");
    const sidebar = document.getElementById("student-sidebar");
    const menu = document.querySelector(".menu-toggle");
    const shade = document.querySelector(".sidebar-shade");
    const mobile = matchMedia("(max-width:760px)");
    shell.classList.add("has-ui-script");
    let menuOpen = false;
    const setMenu = (open, restoreFocus = true) => {
        menuOpen = open && mobile.matches;
        shell.classList.toggle("menu-open", menuOpen);
        sidebar.inert = mobile.matches && !menuOpen;
        sidebar.setAttribute("aria-hidden", String(mobile.matches && !menuOpen));
        main.inert = menuOpen;
        shade.hidden = !menuOpen;
        menu.setAttribute("aria-expanded", String(menuOpen));
        if (menuOpen) sidebar.querySelector(".sidebar-close").focus();
        else if (restoreFocus && mobile.matches) menu.focus();
    };
    menu.addEventListener("click", () => setMenu(true));
    shade.addEventListener("click", () => setMenu(false));
    sidebar.querySelector(".sidebar-close").addEventListener("click", () => setMenu(false));
    mobile.addEventListener("change", () => setMenu(false, false));
    sidebar.addEventListener("keydown", (event) => {
        if (!menuOpen) return;
        if (event.key === "Escape") { event.preventDefault(); setMenu(false); }
        if (event.key === "Tab") {
            const controls = [...sidebar.querySelectorAll("a,button")].filter(item => item.getClientRects().length);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    setMenu(false, false);

    const subjects = JSON.parse(document.getElementById("student-subject-data").textContent);
    const cards = [...document.querySelectorAll(".subject-card")];
    const search = document.getElementById("subject-search");
    const filters = [...document.querySelectorAll("[data-subject-filter]")];
    const empty = document.getElementById("subject-search-empty");
    const updateSubjects = () => {
        const allView = shell.dataset.currentView === "subjects";
        const phrase = search.value.trim().toLocaleLowerCase();
        const selected = Object.fromEntries(filters.map(filter => [filter.dataset.subjectFilter, filter.value]));
        let visible = 0;
        for (const card of cards) {
            const subject = subjects.find(item => item.id === Number(card.dataset.subjectId));
            const matchesText = (subject.name + " " + subject.instructor).toLocaleLowerCase().includes(phrase);
            const hasFilters = Object.values(selected).some(Boolean);
            const matchesTerm = !hasFilters || subject.enrollments.some(row =>
                Object.entries(selected).every(([field, value]) => !value || row[field] === value));
            const show = allView ? matchesText && matchesTerm : visible < 3;
            card.hidden = !show;
            if (show) visible++;
        }
        empty.hidden = visible > 0;
        document.getElementById("subject-results-count").textContent = "Showing " + visible + " of " + subjects.length + " subjects";
    };
    const setView = (view, push = false, scroll = true) => {
        const allView = view === "subjects";
        shell.dataset.currentView = allView ? "subjects" : "dashboard";
        document.getElementById("student-overview").hidden = allView;
        document.getElementById("subject-tools").hidden = !allView;
        document.getElementById("subject-count-badge").hidden = !allView;
        document.querySelector(".view-all").hidden = allView;
        document.getElementById("student-eyebrow").textContent = allView ? "Your classes" : "Student overview";
        const title = document.getElementById("student-page-title");
        title.textContent = allView ? "My subjects" : title.dataset.welcome;
        document.getElementById("student-page-description").textContent = allView
            ? "Browse your subjects and open a class portal." : "Here’s a clear view of your workload and subjects.";
        document.getElementById("subjects-title").textContent = allView ? "Your current classes" : "My subjects";
        document.getElementById("subjects-description").textContent = allView
            ? "Open a subject to view its portal page." : "Quick access to your subject portals";
        document.querySelectorAll(".student-nav [data-student-nav]").forEach(link => {
            if (link.dataset.studentNav === (allView ? "subjects" : "dashboard")) link.setAttribute("aria-current", "page");
            else link.removeAttribute("aria-current");
        });
        document.title = (allView ? "My Subjects" : "Student Dashboard") + " | Kolehiyo ng Subic";
        updateSubjects();
        if (push) {
            const url = new URL(location.href);
            url.hash = "";
            if (allView) url.searchParams.set("view", "subjects"); else url.searchParams.delete("view");
            history.pushState({view}, "", url);
        }
        setMenu(false, false);
        if (scroll) window.scrollTo({top:0,behavior:"auto"});
    };
    document.querySelectorAll("[data-student-nav]").forEach(link => link.addEventListener("click", event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        setView(link.dataset.studentNav, true);
    }));
    window.addEventListener("popstate", () => setView(new URL(location.href).searchParams.get("view") === "subjects" ? "subjects" : "dashboard"));
    search.addEventListener("input", updateSubjects);
    filters.forEach(filter => filter.addEventListener("change", updateSubjects));
    document.getElementById("reset-subject-filters").addEventListener("click", () => {
        search.value = ""; filters.forEach(filter => { filter.value = ""; }); updateSubjects(); search.focus();
    });
    setView(shell.dataset.currentView, false, false);

    const dialog = document.getElementById("subject-dialog");
    const frame = document.getElementById("subject-dialog-frame");
    const close = dialog.querySelector(".subject-dialog-close");
    let trigger = null;
    const focusFrame = (position) => frame.contentWindow.postMessage({type:"kns-subject-focus",position}, location.origin);
    document.querySelectorAll("[data-subject-open]").forEach(link => link.addEventListener("click", event => {
        if (typeof dialog.showModal !== "function" || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin) return;
        event.preventDefault();
        trigger = link;
        const message = link.dataset.subjectOpen === "message";
        url.searchParams.set("embed", "1");
        url.searchParams.set("view", message ? "message" : "classroom");
        dialog.classList.toggle("is-message", message);
        dialog.classList.remove("is-loaded");
        dialog.setAttribute("aria-label", message ? "Private subject message" : "Subject classroom");
        frame.title = (message ? "Private message — " : "Subject classroom — ") + link.closest(".subject-card").querySelector("h3").textContent;
        frame.src = url.href;
        dialog.showModal();
        shell.classList.add("modal-open");
        close.focus();
    }));
    close.addEventListener("click", () => dialog.close());
    close.addEventListener("keydown", event => {
        if (event.key === "Tab" && dialog.classList.contains("is-loaded")) {
            event.preventDefault(); focusFrame(event.shiftKey ? "last" : "first");
        }
    });
    dialog.addEventListener("keydown", event => {
        if (event.key === "Escape") { event.preventDefault(); dialog.close(); }
    });
    dialog.addEventListener("click", event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
    dialog.addEventListener("close", () => {
        if (dialog.open) return;
        shell.classList.remove("modal-open"); frame.src = "about:blank"; trigger?.focus();
    });
    frame.addEventListener("load", () => {
        if (dialog.open && frame.src !== "about:blank") dialog.classList.add("is-loaded");
    });
    window.addEventListener("message", event => {
        if (event.origin !== location.origin || event.source !== frame.contentWindow || !dialog.open) return;
        if (event.data?.type === "kns-subject-close") dialog.close();
        if (event.data?.type === "kns-subject-focus-close") close.focus();
        if (event.data?.type === "kns-subject-ready") { dialog.classList.add("is-loaded"); focusFrame("first"); }
    });
})();
