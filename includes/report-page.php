<section class="content">
    <?php require __DIR__ . '/topbar.php'; ?>

    <section class="page-hero report-hero">
        <div>
            <span class="eyebrow"><span class="dot"></span> Revisión humana</span>
            <h2>Reportar un ejercicio.</h2>
            <p>Cuéntanos qué está mal para poder revisar el contenido, ajustar la generación y retirar ejercicios problemáticos.</p>
        </div>

        <div class="hero-play-card">
            <small>Ejercicio seleccionado</small>
            <strong data-report-preview-title>Sin ejercicio cargado</strong>
            <p data-report-preview-meta>Vuelve al reto y pulsa Reportar en el ejercicio que quieras revisar.</p>
        </div>
    </section>

    <section class="report-layout">
        <form class="panel report-form" data-report-form>
            <input type="hidden" name="question">
            <input type="hidden" name="problem_id">
            <input type="hidden" name="set_id">
            <input type="hidden" name="domain">
            <input type="hidden" name="subtopic">
            <input type="hidden" name="difficulty">
            <input type="hidden" name="correct_answer">
            <input type="hidden" name="selected_answer">
            <input type="hidden" name="options">

            <div class="panel-header">
                <div>
                    <h2>Detalles del reporte</h2>
                    <p>Mientras más claro sea el reporte, más rápido podremos corregir el problema.</p>
                </div>
            </div>

            <label>
                Tipo de problema
                <select name="reason" required>
                    <option value="">Selecciona una opción</option>
                    <option value="Respuesta incorrecta">Respuesta incorrecta</option>
                    <option value="Formato roto o confuso">Formato roto o confuso</option>
                    <option value="Lenguaje incorrecto">Lenguaje incorrecto</option>
                    <option value="No es adecuado para niños">No es adecuado para niños</option>
                    <option value="No corresponde al tema">No corresponde al tema</option>
                    <option value="Otro problema">Otro problema</option>
                </select>
            </label>

            <label>
                Explica qué has visto
                <textarea name="details" rows="6" required placeholder="Ejemplo: usa una moneda incorrecta, la respuesta marcada no coincide, la pregunta se repite o está mal redactada."></textarea>
            </label>

            <label>
                Email de contacto opcional
                <input type="email" name="reporter_email" placeholder="tu@email.com">
            </label>

            <div class="report-actions">
                <button class="primary-button" type="submit">Enviar reporte</button>
                <a class="secondary-button focus-ring" href="javascript:history.back()">Volver</a>
                <span data-report-status aria-live="polite"></span>
            </div>
        </form>

        <aside class="panel report-preview">
            <div class="panel-header">
                <div>
                    <h2>Contenido enviado</h2>
                    <p>Esto se guardará junto al reporte para revisarlo.</p>
                </div>
            </div>
            <div data-report-preview class="report-preview-box">
                <p>No hay ejercicio seleccionado todavía.</p>
            </div>
        </aside>
    </section>
</section>
