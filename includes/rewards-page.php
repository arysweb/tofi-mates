<?php $practiceStats = getPracticeStats(); ?>
<section class="content">
    <?php require __DIR__ . '/topbar.php'; ?>

    <section class="page-hero rewards-hero">
        <div>
            <span class="eyebrow"><span class="dot"></span> Registro de práctica</span>
            <h2>Todo sale de ejercicios reales.</h2>
            <p>Esta página resume lo que ya existe en la base de datos: ejercicios guardados, respuestas y aciertos reales.</p>
            <div class="hero-actions">
                <a class="primary-button focus-ring" href="index.php?page=practice&topic=math&subtopic=sumar">Preparar reto</a>
                <a class="secondary-button focus-ring" href="index.php">Volver al panel</a>
            </div>
        </div>

        <div class="reward-total-card">
            <small>Grupos preparados</small>
            <strong><?php echo e($practiceStats['total_sets']); ?></strong>
            <span>Guardados en la base de datos.</span>
        </div>
    </section>

    <section class="reward-grid">
        <article class="panel streak-card">
            <div class="panel-header">
                <div>
                    <h2>Racha actual</h2>
                    <p>Días seguidos con respuestas correctas.</p>
                </div>
            </div>
            <div class="streak-number"><?php echo e($practiceStats['streak_days']); ?></div>
            <p>Calculada desde las fechas reales de respuestas correctas.</p>
        </article>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2>Resumen de práctica</h2>
                    <p>Actividad simple leída desde la base de datos.</p>
                </div>
            </div>

            <div class="achievement-list">
                <?php foreach ($practiceStats['domain_counts'] as $domain => $count): ?>
                    <div><span><?php echo e(substr(practiceDomainLabel($domain), 0, 1)); ?></span> <?php echo e($count); ?> aciertos de <?php echo e(strtolower(practiceDomainLabel($domain))); ?></div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section class="panel" id="base-ejercicios">
        <div class="panel-header">
            <div>
                <h2>Base de ejercicios</h2>
                <p>Contenido guardado para reutilizar sin gastar tokens cada vez.</p>
            </div>
        </div>

        <div class="badge-gallery">
            <article class="big-badge unlocked">
                <span>+</span>
                <h3><?php echo e($practiceStats['stored_problems']); ?> ejercicios</h3>
                <p>Problemas activos guardados en caché.</p>
            </article>
            <article class="big-badge unlocked">
                <span>?</span>
                <h3><?php echo e($practiceStats['answers']); ?> respuestas</h3>
                <p>Respuestas registradas por los niños.</p>
            </article>
            <article class="big-badge">
                <span>%</span>
                <h3><?php echo e($practiceStats['correct_answers']); ?> correctas</h3>
                <p>Respuestas correctas guardadas.</p>
            </article>
            <article class="big-badge">
                <span>12</span>
                <h3><?php echo e($practiceStats['week_sets']); ?> esta semana</h3>
                <p>Respuestas correctas desde el lunes.</p>
            </article>
        </div>
    </section>
</section>
