(function () {
    const difficultyPanel = document.querySelector("[data-mates-difficulty]");
    const practiceShell = document.querySelector("[data-mates-practice='true']");

    if (difficultyPanel) {
        setupDifficultySelector(difficultyPanel);
    }

    if (practiceShell) {
        setupMatesPractice(practiceShell);
    }

    function setupDifficultySelector(panel) {
        const buttons = Array.from(panel.querySelectorAll("[data-difficulty]"));
        const links = Array.from(document.querySelectorAll("[data-mates-practice-link]"));
        const savedDifficulty = localStorage.getItem("tofimates_mates_difficulty") || "easy";

        setDifficulty(savedDifficulty);

        buttons.forEach((button) => {
            button.addEventListener("click", () => {
                setDifficulty(button.dataset.difficulty || "easy");
            });
        });

        function setDifficulty(difficulty) {
            localStorage.setItem("tofimates_mates_difficulty", difficulty);

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
            currentIndex: 0,
            difficulty: shell.dataset.difficulty || localStorage.getItem("tofimates_mates_difficulty") || "easy",
            problems: [],
            setId: null,
            subtopic: shell.dataset.subtopic || "sumar",
        };

        const question = shell.querySelector("[data-problem-question]");
        const options = shell.querySelector("[data-problem-options]");
        const hint = shell.querySelector("[data-problem-hint]");
        const feedback = shell.querySelector("[data-answer-feedback]");
        const status = shell.querySelector("[data-practice-status]");
        const showHint = shell.querySelector("[data-show-hint]");
        const nextProblem = shell.querySelector("[data-next-problem]");

        showHint?.addEventListener("click", () => {
            const problem = getCurrentProblem();

            if (!hint || !problem) {
                return;
            }

            hint.hidden = false;
            hint.textContent = problem.hint;
        });

        nextProblem?.addEventListener("click", () => {
            if (state.currentIndex < state.problems.length - 1) {
                state.currentIndex += 1;
                renderProblem(getCurrentProblem());
                return;
            }

            loadProblemSet();
        });

        await loadProblemSet();

        async function loadProblemSet() {
            setLoading(true);

            try {
                const response = await fetch("api/mates-problem.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        subtopic: state.subtopic,
                        difficulty: state.difficulty,
                    }),
                });

                const data = await response.json();

                if (!response.ok || !Array.isArray(data.problems) || data.problems.length === 0) {
                    throw new Error(data.error || "No problems returned");
                }

                state.currentIndex = 0;
                state.problems = data.problems;
                state.setId = data.set_id || null;
                renderProblem(getCurrentProblem());
            } catch (error) {
                showError();
            } finally {
                setLoading(false);
            }
        }

        function renderProblem(problem) {
            if (!problem || !question || !options || !hint || !feedback) {
                return;
            }

            question.textContent = problem.question;
            options.innerHTML = "";
            feedback.hidden = true;
            feedback.textContent = "";
            hint.hidden = true;
            hint.textContent = problem.hint;
            status.textContent = "Reto " + (state.currentIndex + 1) + " de " + state.problems.length;

            problem.options.forEach((option) => {
                const button = document.createElement("button");
                button.type = "button";
                button.textContent = option;
                button.addEventListener("click", () => checkAnswer(button, option));
                options.appendChild(button);
            });
        }

        async function checkAnswer(selectedButton, answer) {
            const problem = getCurrentProblem();

            if (!problem || !feedback) {
                return;
            }

            const buttons = Array.from(options.querySelectorAll("button"));
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
            feedback.textContent = result.is_correct
                ? "¡Muy bien! " + result.explanation
                : "Casi. " + result.explanation;
            status.textContent = result.is_correct ? "Respuesta correcta" : "Revisa la explicación";
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

        function getCurrentProblem() {
            return state.problems[state.currentIndex] || null;
        }

        function setLoading(isLoading) {
            if (!question || !options || !status) {
                return;
            }

            if (isLoading) {
                status.textContent = "Preparando reto...";
                question.textContent = "Preparando un reto de mates...";
                options.innerHTML = "";
            }

            if (nextProblem) {
                nextProblem.disabled = isLoading;
            }
        }

        function showError() {
            if (!question || !options || !status) {
                return;
            }

            status.textContent = "No se pudo cargar";
            question.textContent = "No pude preparar el reto ahora mismo.";
            options.innerHTML = "";
        }
    }
})();
