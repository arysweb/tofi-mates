<section class="content">
    <?php require __DIR__ . '/topbar.php'; ?>

    <section class="page-hero rewards-hero">
        <div>
            <span class="eyebrow"><span class="dot"></span> Logros de práctica</span>
            <h2>Reconocer el esfuerzo sin añadir tiendas ni misiones.</h2>
            <p>Esta página resume rachas e insignias ganadas por resolver ejercicios reales en cualquier tema.</p>
            <div class="hero-actions">
                <a class="primary-button focus-ring" href="#insignias">Ver insignias</a>
                <a class="secondary-button focus-ring" href="index.php">Volver al panel</a>
            </div>
        </div>

        <div class="reward-total-card">
            <small>Retos completados</small>
            <strong>34</strong>
            <span>Ejercicios resueltos esta semana.</span>
        </div>
    </section>

    <section class="reward-grid">
        <article class="panel streak-card">
            <div class="panel-header">
                <div>
                    <h2>Racha actual</h2>
                    <p>Practicar cualquier tema mantiene vivo el contador.</p>
                </div>
            </div>
            <div class="streak-number">12</div>
            <p>Días seguidos con al menos un reto completado.</p>
        </article>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2>Resumen de práctica</h2>
                    <p>Actividad simple para ver qué se ha trabajado.</p>
                </div>
            </div>

            <div class="achievement-list">
                <div><span>+</span> 14 retos de mates completados</div>
                <div><span>?</span> 9 retos de lógica completados</div>
                <div><span>$</span> 7 retos de dinero completados</div>
            </div>
        </article>
    </section>

    <section class="panel" id="insignias">
        <div class="panel-header">
            <div>
                <h2>Insignias</h2>
                <p>Reconocimientos por esfuerzo, variedad y constancia.</p>
            </div>
        </div>

        <div class="badge-gallery">
            <article class="big-badge unlocked">
                <span>+</span>
                <h3>Pensadora rápida</h3>
                <p>Ganada por completar 5 retos cortos.</p>
            </article>
            <article class="big-badge unlocked">
                <span>*</span>
                <h3>Súper constancia</h3>
                <p>Ganada por practicar 7 días seguidos.</p>
            </article>
            <article class="big-badge">
                <span>$</span>
                <h3>Compradora lista</h3>
                <p>Desbloquea con 10 retos de dinero.</p>
            </article>
            <article class="big-badge">
                <span>?</span>
                <h3>Detective lógica</h3>
                <p>Desbloquea resolviendo series y pistas.</p>
            </article>
        </div>
    </section>
</section>
