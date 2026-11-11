(() => {
    "use strict";

    // The same authenticated PHP page is used standalone and inside its dashboard dialog.
    const embedded = document.body.dataset.embedded === "true" && window.parent !== window;
    const controls = () => [...document.querySelectorAll('a[href],button,input:not([type="hidden"]),textarea,select,summary')]
        .filter(item => !item.matches(":disabled") && item.getClientRects().length && !item.closest("[hidden]"));
    const notifyParent = (type) => window.parent.postMessage({type}, location.origin);
    if (embedded) {
        document.addEventListener("keydown", event => {
            if (event.key === "Escape") { event.preventDefault(); notifyParent("kns-subject-close"); }
            if (event.key === "Tab") {
                const items = controls();
                if (!items.length || (event.shiftKey && document.activeElement === items[0]) ||
                    (!event.shiftKey && document.activeElement === items[items.length - 1])) {
                    event.preventDefault(); notifyParent("kns-subject-focus-close");
                }
            }
        });
        window.addEventListener("message", event => {
            if (event.origin !== location.origin || event.source !== window.parent || event.data?.type !== "kns-subject-focus") return;
            const items = controls();
            const target = event.data.position === "last" ? items[items.length - 1] : items[0];
            if (target) target.focus({preventScroll:true}); else notifyParent("kns-subject-focus-close");
        });
        notifyParent("kns-subject-ready");
    }

    const work = document.getElementById("subject-work");
    const categoryNames = { all: "Instructor-posted work", activities: "Activities", quizzes: "Quizzes", exams: "Exams", projects: "Projects" };
    if (work) {
        work.querySelectorAll("[data-work-filter]").forEach((button) => {
            button.addEventListener("click", () => {
                work.querySelectorAll("[data-work-filter]").forEach((filter) => {
                    filter.setAttribute("aria-pressed", String(filter === button));
                });
                document.getElementById("subject-work-empty-title").textContent =
                    categoryNames[button.dataset.workFilter] + " not connected yet";
            });
        });
    }

    const form = document.getElementById("subject-message-form");
    if (!form || form.querySelector("fieldset").disabled) return;
    const photoInput = document.getElementById("subject-photos");
    const fileInput = document.getElementById("subject-files");
    const preview = document.getElementById("subject-selected-attachments");
    const error = document.getElementById("subject-attachment-error");
    const counter = document.getElementById("subject-attachment-count");
    const message = document.getElementById("subject-message");
    const types = JSON.parse(document.getElementById("subject-attachment-policy").textContent);
    const limit = Number(form.dataset.maxAttachments);
    const maxBytes = Number(form.dataset.maxFileBytes);
    const sendButton = form.querySelector('button[name="send_message"]');
    let selected = [];
    const updateSend = () => { sendButton.disabled = !message.value.trim() && !selected.length; };
    const formatSize = (bytes) => bytes >= 1048576
        ? (bytes / 1048576).toFixed(1) + " MiB"
        : bytes >= 1024 ? (bytes / 1024).toFixed(1) + " KiB" : bytes + " B";

    const syncInputs = () => {
        const photos = new DataTransfer();
        const files = new DataTransfer();
        selected.forEach((item) => (item.kind === "photo" ? photos : files).items.add(item.file));
        photoInput.files = photos.files;
        fileInput.files = files.files;
    };

    const render = () => {
        preview.replaceChildren();
        selected.forEach((item, index) => {
            const row = document.createElement("li");
            row.className = "subject-selected-attachment";
            if (item.kind === "photo") {
                const image = document.createElement("img");
                image.src = item.url;
                image.alt = "Preview of " + item.file.name;
                row.append(image);
            } else {
                const icon = document.createElement("span");
                icon.className = "subject-selected-file-icon";
                icon.textContent = item.extension.toUpperCase();
                icon.setAttribute("aria-hidden", "true");
                row.append(icon);
            }
            const info = document.createElement("span");
            info.className = "subject-selected-file-info";
            const name = document.createElement("strong");
            name.textContent = item.file.name;
            const size = document.createElement("small");
            size.textContent = formatSize(item.file.size);
            info.append(name, size);
            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "subject-remove-attachment";
            remove.textContent = "Remove";
            remove.setAttribute("aria-label", "Remove " + item.file.name);
            remove.addEventListener("click", () => {
                if (item.url) URL.revokeObjectURL(item.url);
                selected.splice(index, 1);
                syncInputs();
                render();
                error.textContent = "";
                const next = preview.querySelectorAll("button")[Math.min(index, selected.length - 1)];
                (next || (item.kind === "photo" ? photoInput : fileInput)).focus();
            });
            row.append(info, remove);
            preview.append(row);
        });
        counter.textContent = selected.length + " of " + limit + " attachments selected.";
        message.setCustomValidity("");
        updateSend();
    };

    const addFiles = (input, kind) => {
        const incoming = [...input.files];
        const errors = [];
        for (const file of incoming) {
            const extension = file.name.includes(".") ? file.name.split(".").pop().toLowerCase() : "";
            const type = types[extension];
            const mimeAllowed = type && (!file.type || file.type === type.mime ||
                (type.part && ["application/zip", "application/x-zip", "application/x-zip-compressed"].includes(file.type)));
            if (!type || type.kind !== kind || !mimeAllowed) {
                errors.push(file.name + ": choose a supported " + (kind === "photo" ? "photo" : "file") + " type.");
            } else if (!file.size || file.size > maxBytes) {
                errors.push(file.name + ": each attachment must contain data and be no larger than 5 MiB.");
            } else if ([...file.name].length > 180) {
                errors.push("Use a filename of at most 180 characters.");
            } else if (selected.length >= limit) {
                errors.push("You can attach up to " + limit + " files per message.");
            } else {
                selected.push({ file, kind, extension, url: kind === "photo" ? URL.createObjectURL(file) : null });
            }
        }
        syncInputs();
        render();
        error.textContent = [...new Set(errors)].join(" ");
    };
    photoInput.addEventListener("change", () => addFiles(photoInput, "photo"));
    fileInput.addEventListener("change", () => addFiles(fileInput, "file"));
    message.addEventListener("input", () => { message.setCustomValidity(""); updateSend(); });
    form.addEventListener("submit", (event) => {
        if (!message.value.trim() && !selected.length) {
            event.preventDefault();
            message.setCustomValidity("Write a message or attach at least one photo or file.");
            message.reportValidity();
        }
    });
    window.addEventListener("pagehide", (event) => {
        if (event.persisted) return;
        selected.forEach((item) => { if (item.url) URL.revokeObjectURL(item.url); });
    });
    render();
})();