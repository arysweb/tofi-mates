(function () {
    const difficultyPanel = document.querySelector("[data-mates-difficulty]");
    const practiceShell = document.querySelector("[data-practice='true']");
    const reportForm = document.querySelector("[data-report-form]");
    const difficultyStorageKey = "panda_mates_difficulty";
    const clientKeyStorageKey = "panda_client_key";
    const practiceSetTtlMs = 30 * 60 * 1000;
    const reportDraftStorageKey = "panda_problem_report_draft";
    const csrfToken = document.documentElement.dataset.csrf || "";

    if (difficultyPanel) {
        setupDifficultySelector(difficultyPanel);
    }

    if (practiceShell) {
        setupMatesPractice(practiceShell);
    }

    if (reportForm) {
        setupReportForm(reportForm);
    }

    function setupDifficultySelector(panel) {
        const buttons = Array.from(panel.querySelectorAll("[data-difficulty]"));
        const links = Array.from(document.querySelectorAll("[data-mates-practice-link]"));
        const savedDifficulty = localStorage.getItem(difficultyStorageKey) || "easy";

        setDifficulty(savedDifficulty);

        buttons.forEach((button) => {
            button.addEventListener("click", () => {
                setDifficulty(button.dataset.difficulty || "easy");
            });
        });

        function setDifficulty(difficulty) {
            localStorage.setItem(difficultyStorageKey, difficulty);

            buttons.forEach((button) => {
                button.classList.toggle("active", button.dataset.difficulty === difficulty);
            });

            links.forEach((link) => {
                const url = new URL(link.href);
                url.searchParams.set("difficulty", difficulty);
                link.href = url.toString();
            });
        }
    }

    function getClientKey() {
        const saved = localStorage.getItem(clientKeyStorageKey);

        if (saved) {
            return saved;
        }

        const key = "kid_" + Date.now().toString(36) + "_" + Math.random().toString(36).slice(2, 10);
        localStorage.setItem(clientKeyStorageKey, key);

        return key;
    }

    async function setupMatesPractice(shell) {
        const state = {
            difficulty: shell.dataset.difficulty || localStorage.getItem(difficultyStorageKey) || "easy",
            domain: shell.dataset.domain || "math",
            problems: [],
            setId: null,
            subtopic: shell.dataset.subtopic || "sumar",
            clientKey: getClientKey(),
        };
        const practiceStorageKey = [
            "panda_practice_set",
            state.domain,
            state.subtopic,
            state.difficulty,
        ].join(":");

        const problemsList = shell.querySelector("[data-problems-list]");
        const hint = shell.querySelector("[data-problem-hint]");
        const status = shell.querySelector("[data-practice-status]");
        const nextProblem = shell.querySelector("[data-next-problem]");

        nextProblem?.addEventListener("click", () => loadProblemSet(true));

        await loadProblemSet(false);

        async function loadProblemSet(forceNew) {
            if (!forceNew && restoreSavedProblemSet()) {
                return;
            }

            if (forceNew) {
                sessionStorage.removeItem(practiceStorageKey);
            }

            setLoading(true);

            try {
                const response = await fetch("api/practice-problems.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        client_key: state.clientKey,
                        csrf_token: csrfToken,
                        domain: state.domain,
                        subtopic: state.subtopic,
                        difficulty: state.difficulty,
                        force_new: forceNew,
                    }),
                });

                const data = await response.json();

                if (!response.ok || !Array.isArray(data.problems) || data.problems.length === 0) {
                    throw new Error(data.error || "No problems returned");
                }

                state.problems = data.problems;
                state.setId = data.set_id || null;
                saveProblemSet(data);
                renderProblemSet(data.problems);
            } catch (error) {
                showError();
            } finally {
                setLoading(false);
            }
        }

        function restoreSavedProblemSet() {
            const saved = readSavedProblemSet();

            if (!saved) {
                return false;
            }

            state.problems = saved.problems;
            state.setId = saved.set_id || null;
            renderProblemSet(saved.problems);
            return true;
        }

        function readSavedProblemSet() {
            try {
                const saved = JSON.parse(sessionStorage.getItem(practiceStorageKey) || "null");

                if (!saved || !Array.isArray(saved.problems) || saved.problems.length === 0) {
                    return null;
                }

                if (Date.now() - Number(saved.saved_at || 0) > practiceSetTtlMs) {
                    sessionStorage.removeItem(practiceStorageKey);
                    return null;
                }

                return saved;
            } catch (error) {
                sessionStorage.removeItem(practiceStorageKey);
                return null;
            }
        }

        function saveProblemSet(data) {
            sessionStorage.setItem(practiceStorageKey, JSON.stringify({
                problems: data.problems,
                provider: data.provider || null,
                saved_at: Date.now(),
                set_id: data.set_id || null,
            }));
        }

        function renderProblemSet(problems) {
            if (!problemsList || !hint || !status) {
                return;
            }

            problemsList.innerHTML = "";
            hint.textContent = "Pulsa Pista en cualquier ejercicio si te quedas atascado.";
            status.textContent = problems.length + " retos preparados";

            problems.forEach((problem, index) => {
                problemsList.appendChild(createProblemCard(problem, index));
            });
        }

        function createProblemCard(problem, index) {
            const card = document.createElement("article");
            card.className = "practice-problem-card";

            const header = document.createElement("div");
            header.className = "practice-card-header";

            const label = document.createElement("span");
            label.className = "problem-number";
            label.textContent = "Reto " + (index + 1);

            const title = document.createElement("h2");
            title.textContent = problem.question;

            const answers = document.createElement("div");
            answers.className = "practice-answers";

            const actions = document.createElement("div");
            actions.className = "practice-problem-actions";

            const hintButton = document.createElement("button");
            hintButton.type = "button";
            hintButton.className = "hint-action";
            hintButton.textContent = "Pista";
            hintButton.addEventListener("click", () => {
                hint.textContent = problem.hint;
            });

            const reportButton = document.createElement("button");
            reportButton.type = "button";
            reportButton.className = "report-action";
            reportButton.textContent = "Reportar";
            reportButton.addEventListener("click", () => openReportPage(problem));

            const feedback = document.createElement("div");
            feedback.className = "answer-feedback";
            feedback.hidden = true;

            problem.options.forEach((option) => {
                const button = document.createElement("button");
                button.type = "button";
                button.textContent = option;
                button.addEventListener("click", () => checkAnswer(problem, answers, feedback, button, option));
                answers.appendChild(button);
            });

            actions.appendChild(hintButton);
            actions.appendChild(reportButton);
            header.appendChild(label);
            header.appendChild(actions);
            card.appendChild(header);
            card.appendChild(title);
            card.appendChild(answers);
            card.appendChild(feedback);

            return card;
        }

        async function checkAnswer(problem, answers, feedback, selectedButton, answer) {
            if (!problem || !feedback) {
                return;
            }

            const buttons = Array.from(answers.querySelectorAll("button"));
            buttons.forEach((button) => {
                button.disabled = true;
            });

            let result = {
                correct_answer: problem.correct_answer,
                explanation: problem.explanation,
                is_correct: answer === problem.correct_answer,
            };
            problem.selected_answer = answer;

            if (problem.id) {
                result = await saveAnswer(problem.id, answer, result);
            }

            buttons.forEach((button) => {
                button.classList.toggle("is-correct", button.textContent === result.correct_answer);
                button.classList.toggle("is-wrong", button === selectedButton && !result.is_correct);
            });

            feedback.hidden = false;
            feedback.innerHTML = "";
            feedback.appendChild(buildFeedback(result));
        }

        function openReportPage(problem) {
            sessionStorage.setItem(reportDraftStorageKey, JSON.stringify({
                client_key: state.clientKey,
                correct_answer: problem.correct_answer || "",
                difficulty: state.difficulty,
                domain: state.domain,
                options: Array.isArray(problem.options) ? problem.options : [],
                problem_id: problem.id || null,
                question: problem.question || "",
                selected_answer: problem.selected_answer || "",
                set_id: state.setId,
                subtopic: state.subtopic,
            }));
            window.location.href = "index.php?page=report-problem";
        }

        async function saveAnswer(problemId, answer, fallbackResult) {
            try {
                const response = await fetch("api/mates-answer.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        problem_id: problemId,
                        selected_answer: answer,
                    }),
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || "Answer not saved");
                }

                return data;
            } catch (error) {
                return fallbackResult;
            }
        }

        function buildFeedback(result) {
            const wrapper = document.createElement("div");
            const title = document.createElement("strong");
            const list = document.createElement("ul");

            title.textContent = result.is_correct ? "¡Muy bien!" : "Casi.";

            splitExplanation(result.explanation).forEach((line) => {
                const item = document.createElement("li");
                item.textContent = line;
                list.appendChild(item);
            });

            wrapper.appendChild(title);
            wrapper.appendChild(list);

            return wrapper;
        }

        function splitExplanation(explanation) {
            return String(explanation || "")
                .replace(/,\s+luego\s+/gi, ".|Luego ")
                .replace(/,\s+después\s+/gi, ".|Después ")
                .replace(/([.!?])\s+(?=Primero|Luego|Después|Así|Por eso|Entonces|Ahora)/g, "$1|")
                .split("|")
                .map((line) => line.trim())
                .filter(Boolean)
                .slice(0, 4);
        }

        function setLoading(isLoading) {
            if (!problemsList || !status) {
                return;
            }

            if (isLoading) {
                status.textContent = "Preparando 3 retos...";
                renderLoadingCards();
            }

            if (nextProblem) {
                nextProblem.disabled = isLoading;
            }
        }

        function showError() {
            if (!problemsList || !status) {
                return;
            }

            status.textContent = "No se pudo cargar";
            problemsList.innerHTML = '<article class="practice-problem-card"><h2>No pude preparar los retos ahora mismo.</h2></article>';
        }

        function renderLoadingCards() {
            problemsList.innerHTML = "";

            for (let index = 0; index < 3; index += 1) {
                const card = document.createElement("article");
                card.className = "practice-problem-card is-loading";
                card.innerHTML = '<span class="problem-number">Reto ' + (index + 1) + '</span><h2>Preparando ejercicio...</h2><div class="practice-answers"><button type="button" disabled></button><button type="button" disabled></button><button type="button" disabled></button><button type="button" disabled></button></div>';
                problemsList.appendChild(card);
            }
        }
    }

    function setupReportForm(form) {
        const status = document.querySelector("[data-report-status]");
        const preview = document.querySelector("[data-report-preview]");
        const previewTitle = document.querySelector("[data-report-preview-title]");
        const previewMeta = document.querySelector("[data-report-preview-meta]");
        const draft = readReportDraft();

        if (draft) {
            hydrateReportForm(form, draft, preview, previewTitle, previewMeta);
        }

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            if (!draft) {
                setReportStatus(status, "No hay ejercicio seleccionado para reportar.");
                return;
            }

            const formData = new FormData(form);
            const payload = {
                client_key: getClientKey(),
                correct_answer: formData.get("correct_answer") || "",
                csrf_token: csrfToken,
                details: formData.get("details") || "",
                difficulty: formData.get("difficulty") || "",
                domain: formData.get("domain") || "",
                options: parseOptions(formData.get("options")),
                problem_id: formData.get("problem_id") || null,
                question: formData.get("question") || "",
                reason: formData.get("reason") || "",
                reporter_email: formData.get("reporter_email") || "",
                selected_answer: formData.get("selected_answer") || "",
                set_id: formData.get("set_id") || null,
                subtopic: formData.get("subtopic") || "",
            };

            setReportStatus(status, "Enviando reporte...");

            try {
                const response = await fetch("api/report-problem.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || "Report not saved");
                }

                sessionStorage.removeItem(reportDraftStorageKey);
                form.reset();
                setReportStatus(status, "Reporte enviado. Gracias, lo revisaremos.");
            } catch (error) {
                setReportStatus(status, "No se pudo enviar el reporte ahora mismo.");
            }
        });
    }

    function readReportDraft() {
        try {
            return JSON.parse(sessionStorage.getItem(reportDraftStorageKey) || "null");
        } catch (error) {
            return null;
        }
    }

    function hydrateReportForm(form, draft, preview, previewTitle, previewMeta) {
        setFormValue(form, "question", draft.question || "");
        setFormValue(form, "problem_id", draft.problem_id || "");
        setFormValue(form, "set_id", draft.set_id || "");
        setFormValue(form, "domain", draft.domain || "");
        setFormValue(form, "subtopic", draft.subtopic || "");
        setFormValue(form, "difficulty", draft.difficulty || "");
        setFormValue(form, "correct_answer", draft.correct_answer || "");
        setFormValue(form, "selected_answer", draft.selected_answer || "");
        setFormValue(form, "options", JSON.stringify(draft.options || []));

        if (previewTitle) {
            previewTitle.textContent = draft.question || "Ejercicio seleccionado";
        }

        if (previewMeta) {
            previewMeta.textContent = [draft.domain, draft.subtopic, draft.difficulty].filter(Boolean).join(" · ");
        }

        if (preview) {
            preview.innerHTML = "";
            const question = document.createElement("strong");
            const list = document.createElement("ul");
            question.textContent = draft.question || "Sin pregunta";
            (Array.isArray(draft.options) ? draft.options : []).forEach((option) => {
                const item = document.createElement("li");
                item.textContent = option;
                list.appendChild(item);
            });
            preview.appendChild(question);
            preview.appendChild(list);
        }
    }

    function setFormValue(form, name, value) {
        const field = form.elements[name];

        if (field) {
            field.value = value;
        }
    }

    function parseOptions(value) {
        try {
            const options = JSON.parse(String(value || "[]"));
            return Array.isArray(options) ? options : [];
        } catch (error) {
            return [];
        }
    }

    function setReportStatus(node, message) {
        if (node) {
            node.textContent = message;
        }
    }
})();
