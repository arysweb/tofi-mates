<?php
$practiceStats = getPracticeStats();
$weeklyCounts = $practiceStats['weekly_counts'];
$weekDays = [
    1 => 'Lun',
    2 => 'Mar',
    3 => 'Mié',
    4 => 'Jue',
    5 => 'Vie',
    6 => 'Sáb',
    7 => 'Dom',
];
$todayNumber = (int) date('N');
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Esta semana</h2>
            <p>Aciertos reales guardados esta semana.</p>
        </div>
        <a class="text-link focus-ring" href="index.php?page=progress">Ver informe</a>
    </div>

    <div class="weekly-grid" aria-label="Actividad de aprendizaje semanal">
        <?php foreach ($weekDays as $dayNumber => $dayLabel): ?>
            <?php $count = $weeklyCounts[$dayNumber] ?? 0; ?>
            <div class="day <?php echo $count > 0 ? 'done' : ''; ?> <?php echo $dayNumber === $todayNumber ? 'today' : ''; ?>">
                <span><?php echo e($dayLabel); ?></span>
                <span class="day-circle"><?php echo e($count); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
