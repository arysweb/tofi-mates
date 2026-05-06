<?php
$practiceStats = getPracticeStats();
$weeklyCounts = $practiceStats['weekly_counts'];
$maxWeeklyCount = max(1, ...array_values($weeklyCounts));
$weekDays = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
$recentSets = $practiceStats['recent_sets'];
?>
<section class="content">
    <?php require __DIR__ . '/topbar.php'; ?>

    <section class="page-hero progress-hero">
        <div>
            <span class="eyebrow"><span class="dot"></span> Vista para familia</span>
            <h2>Pequeños avances, bien visibles.</h2>
            <p>Resumen conectado a las respuestas correctas guardadas en la base de datos.</p>
        </div>

        <div class="reward-total-card">
            <small>Esta semana</small>
            <strong><?php echo e($practiceStats['week_sets']); ?></strong>
            <span>aciertos reales</span>
        </div>
    </section>

    <section class="progress-layout">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2>Actividad semanal</h2>
                    <p>Práctica por día, calculada solo con respuestas correctas.</p>
                </div>
            </div>

            <div class="progress-bars">
                <?php foreach ($weekDays as $dayNumber => $dayLabel): ?>
                    <?php
                    $count = $weeklyCounts[$dayNumber] ?? 0;
                    $height = max(8, (int) round(($count / $maxWeeklyCount) * 84));
                    ?>
                    <div><span><?php echo e($dayLabel); ?></span><strong style="height: <?php echo e($height); ?>%"></strong><em><?php echo e($count); ?></em></div>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="side-stack">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Temas favoritos</h2>
                        <p>Donde Mia responde correctamente más veces.</p>
                    </div>
                </div>
                <div class="topic-stats">
                    <?php foreach ($practiceStats['domain_counts'] as $domain => $count): ?>
                        <span><?php echo e(practiceDomainLabel($domain)); ?> · <?php echo e($count); ?> aciertos</span>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="panel streak-card">
                <div class="panel-header">
                    <div>
                        <h2>Racha</h2>
                        <p>Días con práctica completada.</p>
                    </div>
                </div>
                <div class="streak-number"><?php echo e($practiceStats['streak_days']); ?></div>
            </article>
        </aside>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Historial reciente</h2>
                <p>Últimas respuestas correctas guardadas.</p>
            </div>
        </div>

        <div class="history-list">
            <?php if ($recentSets === []): ?>
                <div><span>+</span><strong>Sin aciertos todavía</strong><em>Responde un reto correctamente</em></div>
            <?php endif; ?>
            <?php foreach ($recentSets as $set): ?>
                <div>
                    <span><?php echo e(substr(practiceDomainLabel((string) $set['domain_slug']), 0, 1)); ?></span>
                    <strong><?php echo e(practiceDomainLabel((string) $set['domain_slug'])); ?> · <?php echo e(practiceSubtopicLabel((string) $set['subtopic_slug'])); ?></strong>
                    <em><?php echo e((string) $set['difficulty']); ?></em>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</section>
