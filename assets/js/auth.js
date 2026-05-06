(function () {
    const forms = Array.from(document.querySelectorAll("[data-auth-form]"));

    forms.forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            await submitAuthForm(form);
        });
    });

    async function submitAuthForm(form) {
        const endpoint = form.dataset.authEndpoint;
        const status = form.querySelector("[data-auth-status]");
        const submitButton = form.querySelector("button[type='submit']");

        if (!endpoint) {
            return;
        }

        setStatus(status, "Comprobando datos...");
        setButtonLoading(submitButton, true);

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            const response = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || "No se pudo completar la acción.");
            }

            setStatus(status, "Listo. Entrando...");
            window.location.href = data.redirect || "index.php";
        } catch (error) {
            setStatus(status, error.message || "No se pudo completar la acción.");
            setButtonLoading(submitButton, false);
        }
    }

    function setStatus(node, message) {
        if (node) {
            node.textContent = message;
        }
    }

    function setButtonLoading(button, isLoading) {
        if (!button) {
            return;
        }

        button.disabled = isLoading;
        button.classList.toggle("is-loading", isLoading);
    }
})();
