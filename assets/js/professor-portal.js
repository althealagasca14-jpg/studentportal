(() => {
    "use strict";
    const classSelect = document.getElementById("pf-class-select");
    if (classSelect) {
        classSelect.form.classList.add("has-auto-submit");
        classSelect.addEventListener("change", () => classSelect.form.requestSubmit());
    }
    const filters = [...document.querySelectorAll("[data-pf-filter]")];
    const work = [...document.querySelectorAll("[data-pf-category]")];
    const workEmpty = document.getElementById("pf-work-empty");
    filters.forEach(button => {
        button.addEventListener("click", () => {
            filters.forEach(filter => filter.setAttribute("aria-pressed", String(filter === button)));
            let visible = 0;
            work.forEach(item => {
                item.hidden = button.dataset.pfFilter !== "all" && item.dataset.pfCategory !== button.dataset.pfFilter;
                if (!item.hidden) visible++;
            });
            if (workEmpty) {
                workEmpty.hidden = visible > 0;
                workEmpty.querySelector("h3").textContent = "No items in this category";
            }
        });
    });
    const search = document.querySelector("[data-pf-student-search]");
    if (search) {
        const rows = [...document.querySelectorAll("[data-pf-student]")];
        const empty = document.getElementById("pf-student-empty");
        search.addEventListener("input", () => {
            const term = search.value.trim().toLocaleLowerCase();
            let visible = 0;
            rows.forEach(row => {
                row.hidden = !row.dataset.pfStudent.toLocaleLowerCase().includes(term);
                if (!row.hidden) visible++;
            });
            empty.hidden = visible > 0;
        });
    }
    const thread = document.querySelector(".pf-thread");
    if (thread) requestAnimationFrame(() => { thread.scrollTop = thread.scrollHeight; });
})();
