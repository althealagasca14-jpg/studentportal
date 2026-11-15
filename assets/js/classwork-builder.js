(() => {
    "use strict";
    const form = document.getElementById("classwork-form");
    if (!form) return;
    const list = document.getElementById("cw-question-list");
    const json = document.getElementById("questions-json");
    const reviewed = document.getElementById("cw-reviewed");
    const builderStatus = document.getElementById("cw-builder-status");
    const questions = JSON.parse(document.getElementById("cw-initial-questions").textContent);
    const maxQuestions = Number(form.dataset.maxQuestions);
    const fileInput = document.getElementById("question-file");
    const importButton = document.getElementById("cw-import");
    const removeFile = document.getElementById("cw-remove-file");
    const importStatus = document.getElementById("cw-import-status");
    const importError = document.getElementById("cw-import-error");
    let request = null;
    let sequence = 0;
    let fieldId = 0;
    const node = (tag, text, className) => {
        const element = document.createElement(tag);
        if (text !== undefined) element.textContent = text;
        if (className) element.className = className;
        return element;
    };
    const sync = () => {
        json.value = JSON.stringify(questions);
        reviewed.checked = false;
        builderStatus.textContent = questions.length + " of " + maxQuestions + " questions.";
        document.getElementById("cw-add-question").disabled = questions.length >= maxQuestions;
    };
    const button = (text, handler) => {
        const element = node("button", text);
        element.type = "button";
        element.addEventListener("click", handler);
        return element;
    };
    const field = (caption, element) => {
        const label = node("label", caption);
        element.id = "cw-field-" + (++fieldId);
        label.htmlFor = element.id;
        label.append(element);
        return label;
    };
    const render = () => {
        list.replaceChildren();
        questions.forEach((q, index) => {
            q.choices = Array.isArray(q.choices) ? q.choices : [];
            q.answer = String(q.answer ?? "");
            const card = node("section", undefined, "cw-card cw-question");
            const heading = node("h3", "Question " + (index + 1));
            card.append(heading);
            if (q.warnings?.length) {
                const warnings = node("ul", undefined, "cw-notice");
                [...new Set(q.warnings)].forEach(warning => warnings.append(node("li", warning)));
                card.append(warnings);
                card.append(button("Mark these notices reviewed", () => { q.warnings = []; render(); sync(); }));
            }
            const text = node("textarea");
            text.value = q.text || "";
            text.rows = 3;
            text.maxLength = 10000;
            text.addEventListener("input", () => { q.text = text.value; sync(); });
            card.append(field("Question text", text));
            const row = node("div", undefined, "cw-row");
            const type = node("select");
            [["multiple_choice", "Multiple choice"], ["short_answer", "Short answer"]].forEach(([value, caption]) => {
                const option = node("option", caption);
                option.value = value;
                type.append(option);
            });
            type.value = q.type;
            type.addEventListener("change", () => {
                q.type = type.value;
                q.answer = "";
                if (q.type === "multiple_choice" && !q.choices.length) q.choices.push("", "");
                render(); sync();
            });
            const points = node("input");
            points.type = "number"; points.min = "0.01"; points.max = "1000"; points.step = "0.01";
            points.value = q.points ?? 1;
            points.required = true;
            points.addEventListener("input", () => { q.points = points.value; sync(); });
            row.append(field("Question type", type), field("Points", points));
            card.append(row);
            if (q.type === "multiple_choice") {
                q.choices.forEach((choice, choiceIndex) => {
                    const choiceRow = node("div", undefined, "cw-choice-row");
                    const input = node("textarea");
                    input.rows = 2; input.maxLength = 3000; input.value = choice;
                    input.addEventListener("input", () => {
                        q.choices[choiceIndex] = input.value;
                        const option = answerSelect.options[choiceIndex + 1];
                        option.textContent = String.fromCharCode(65 + choiceIndex) + ") " + input.value;
                        sync();
                    });
                    choiceRow.append(field("Choice " + String.fromCharCode(65 + choiceIndex), input),
                        button("Remove choice " + String.fromCharCode(65 + choiceIndex), () => {
                            q.choices.splice(choiceIndex, 1);
                            if (q.answer !== "") {
                                if (Number(q.answer) === choiceIndex) q.answer = "";
                                else if (Number(q.answer) > choiceIndex) q.answer = String(Number(q.answer) - 1);
                            }
                            render(); sync();
                        }));
                    card.append(choiceRow);
                });
                const addChoice = button("Add choice", () => { q.choices.push(""); render(); sync(); });
                addChoice.disabled = q.choices.length >= 26;
                card.append(addChoice);
            }
            const answerSelect = q.type === "multiple_choice" ? node("select") : node("textarea");
            if (q.type === "multiple_choice") {
                const unset = node("option", "No explicit key / manual grading");
                unset.value = "";
                answerSelect.append(unset);
                q.choices.forEach((choice, choiceIndex) => {
                    const option = node("option", String.fromCharCode(65 + choiceIndex) + ") " + choice);
                    option.value = String(choiceIndex);
                    answerSelect.append(option);
                });
            } else {
                answerSelect.rows = 2;
                answerSelect.maxLength = 3000;
            }
            answerSelect.value = q.answer;
            answerSelect.addEventListener("input", () => { q.answer = answerSelect.value; sync(); });
            card.append(field("Correct answer (optional; never guessed)", answerSelect));
            card.append(button("Remove question " + (index + 1), () => { questions.splice(index, 1); render(); sync(); }));
            list.append(card);
        });
    };
    document.getElementById("cw-add-question").addEventListener("click", () => {
        if (questions.length >= maxQuestions) return;
        questions.push({text: "", type: "multiple_choice", points: 1, choices: ["", ""], answer: "", warnings: []});
        render(); sync();
        list.lastElementChild.querySelector("textarea").focus();
    });
    const selectedFile = () => {
        sequence++;
        if (request) request.abort();
        request = null;
        importError.textContent = "";
        importStatus.textContent = "";
        const file = fileInput.files[0];
        removeFile.hidden = !file;
        importButton.disabled = true;
        if (!file) {
            document.getElementById("cw-file-info").textContent = "No file selected. Questions already added remain editable below.";
            return;
        }
        const extension = file.name.split(".").pop().toLowerCase();
        document.getElementById("cw-file-info").textContent =
            file.name + " | " + extension.toUpperCase() + " | " + file.size.toLocaleString() + " bytes (" + (file.size / 1048576).toFixed(2) + " MiB)";
        if (!["docx", "txt", "csv", "rtf"].includes(extension)) {
            importError.textContent = "Unsupported file. Use DOCX, TXT, CSV, or RTF. Export PDF/DOC questions to a supported format.";
        } else if (!file.size || file.size > Number(form.dataset.maxFileBytes)) {
            importError.textContent = "Choose a nonempty file no larger than 5 MiB.";
        } else importButton.disabled = false;
    };
    fileInput.addEventListener("change", selectedFile);
    removeFile.addEventListener("click", () => { fileInput.value = ""; selectedFile(); });
    importButton.addEventListener("click", async () => {
        const file = fileInput.files[0];
        if (!file || importButton.disabled) return;
        const version = ++sequence;
        request = new AbortController();
        const body = new FormData();
        body.append("question_file", file);
        body.append("csrf_token", form.elements.csrf_token.value);
        body.append("subject_id", form.elements.subject_id.value);
        importButton.disabled = true;
        importError.textContent = "";
        importStatus.textContent = "Reading questions. Existing builder fields remain editable.";
        try {
            const response = await fetch(form.dataset.importUrl, {
                method: "POST", body, credentials: "same-origin", signal: request.signal
            });
            const result = await response.json();
            if (version !== sequence) return;
            if (!response.ok || result.error) throw new Error(result.error || "The file could not be read.");
            if (!Array.isArray(result.questions) || !result.questions.length) throw new Error("No questions were recognized. Nothing was imported.");
            if (questions.length + result.questions.length > maxQuestions) throw new Error("The builder supports at most 200 questions. Remove some questions or split the source. Nothing was added.");
            questions.push(...result.questions);
            render(); sync();
            importStatus.textContent = "Added " + result.questions.length + " questions and " + result.choice_count +
                " choices from " + result.file.name + ". Review every question and all notices before publishing.";
            const notices = document.getElementById("cw-import-notices");
            notices.append(node("li", result.file.name + ": " + result.questions.length + " questions, " + result.choice_count + " choices."));
            result.notices.forEach(notice => notices.append(node("li", result.file.name + ": " + notice)));
            const sourceText = document.getElementById("cw-source-text");
            sourceText.textContent += (sourceText.textContent ? "\n\n" : "") + "Source: " + result.file.name + "\n" + result.source_text;
            document.getElementById("cw-source-preview").hidden = false;
        } catch (error) {
            if (error.name === "AbortError" || version !== sequence) return;
            importStatus.textContent = "Nothing was added from the selected file.";
            importError.textContent = (error instanceof SyntaxError ? "The server could not read this import response." : error.message) +
                " You can continue editing questions manually.";
        } finally {
            if (version === sequence) { request = null; importButton.disabled = false; }
        }
    });
    form.addEventListener("input", event => {
        if (event.target !== reviewed) reviewed.checked = false;
    });
    form.addEventListener("submit", event => {
        json.value = JSON.stringify(questions);
        if (request) {
            event.preventDefault();
            builderStatus.textContent = "Wait for the file import to finish, then review the questions before saving or publishing.";
        } else if (new TextEncoder().encode(json.value).length > 1048576) {
            event.preventDefault();
            builderStatus.textContent = "Question data exceeds 1 MiB. Split this classwork before saving.";
        } else if (event.submitter?.value === "publish" && !reviewed.checked) {
            event.preventDefault();
            builderStatus.textContent = "Review all questions and confirm review before publishing.";
            reviewed.focus();
        }
    });
    render(); sync();
})();
