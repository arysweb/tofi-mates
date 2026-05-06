(function () {
    const difficultyPanel = document.querySelector("[data-mates-difficulty]");
    const practiceShell = document.querySelector("[data-practice='true']");
    const difficultyStorageKey = "sparkle_mates_difficulty";
    const clientKeyStorageKey = "sparkle_client_key";
    const practiceSetTtlMs = 30 * 60 * 1000;

    if (difficultyPanel) {
        setupDifficultySelector(difficultyPanel);
    }

    if (practiceShell) {
        setupMatesPractice(practiceShell);
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
            "sparkle_practice_set",
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

            setLoading(true);

            try {
                const response = await fetch("api/practice-problems.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        client_key: state.clientKey,
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

        function getClientKey() {
            const saved = localStorage.getItem(clientKeyStorageKey);

            if (saved) {
                return saved;
            }

            const key = "kid_" + Date.now().toString(36) + "_" + Math.random().toString(36).slice(2, 10);
            localStorage.setItem(clientKeyStorageKey, key);

            return key;
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
            hintButton.textContent = "Pista";
            hintButton.addEventListener("click", () => {
                hint.textContent = problem.hint;
            });

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
            card.appendChild(label);
            card.appendChild(title);
            card.appendChild(answers);
            card.appendChild(actions);
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

        async function saveAnswer(problemId, answer, fallbackResult) {
            try {
                const response = await fetch("api/mates-answer.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
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
})();
