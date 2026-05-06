<?php
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$pageRoutes = [
    'home' => [
        'label' => 'Inicio',
        'icon' => 'H',
        'include' => 'dashboard.php',
        'title' => 'Buenas tardes, Mia.',
        'subtitle' => 'Elige un reto y mantén viva tu racha de práctica.',
    ],
    'math' => [
        'label' => 'Mates',
        'icon' => '+',
        'include' => 'topic-page.php',
        'title' => 'Mates para practicar.',
        'subtitle' => 'Sumas, restas y problemas generados cuando el niño los necesita.',
    ],
    'logic' => [
        'label' => 'Lógica',
        'icon' => '?',
        'include' => 'topic-page.php',
        'title' => 'Retos de lógica.',
        'subtitle' => 'Series, formas y pistas para pensar jugando.',
    ],
    'time' => [
        'label' => 'Tiempo',
        'icon' => '12',
        'include' => 'topic-page.php',
        'title' => 'Tiempo y relojes.',
        'subtitle' => 'Ejercicios cortos sobre horas, rutinas y duración.',
    ],
    'money' => [
        'label' => 'Dinero',
        'icon' => '$',
        'include' => 'topic-page.php',
        'title' => 'Dinero en juegos.',
        'subtitle' => 'Monedas, precios y cambios con ejemplos fáciles.',
    ],
    'rewards' => [
        'label' => 'Logros',
        'icon' => '*',
        'include' => 'rewards-page.php',
        'title' => 'Logros y rachas.',
        'subtitle' => 'Motivación simple basada en práctica real.',
    ],
    'progress' => [
        'label' => 'Progreso',
        'icon' => '%',
        'include' => 'progress-page.php',
        'title' => 'Progreso tranquilo.',
        'subtitle' => 'Una vista clara de práctica, racha y temas favoritos.',
    ],
    'practice' => [
        'label' => 'Practicar',
        'icon' => '>',
        'include' => 'practice-page.php',
        'title' => 'Reto listo para jugar.',
        'subtitle' => 'Una pantalla de práctica estática para probar el flujo visual.',
        'nav' => false,
    ],
];

$topicPages = [
    'math' => [
        'class' => 'math',
        'icon' => '+',
        'eyebrow' => 'Categoría principal',
        'title' => 'Mates',
        'description' => 'Un espacio simple para elegir subtemas como sumar o restar. Después, el sistema puede pedir ejercicios nuevos a la IA según nivel, edad y progreso.',
        'cta' => 'Generar reto de mates',
        'heroCard' => [
            'label' => 'Reto sugerido',
            'title' => 'Restas rápidas',
            'text' => '5 preguntas suaves con números hasta 20.',
            'meta' => 'Reto corto',
        ],
        'stats' => ['8 subtemas', '24 retos hoy', 'Nivel suave'],
        'subtopics' => [
            ['slug' => 'sumar', 'icon' => '+', 'title' => 'Sumar', 'text' => 'Operaciones rápidas, completar números y problemas de juntar.', 'pill' => 'Listo'],
            ['slug' => 'restar', 'icon' => '-', 'title' => 'Restar', 'text' => 'Quitar, comparar y encontrar lo que falta sin presión.', 'pill' => 'Popular'],
            ['slug' => 'comparar', 'icon' => '=', 'title' => 'Comparar', 'text' => 'Mayor, menor, igual y pequeñas decisiones con números.', 'pill' => 'Corto'],
            ['slug' => 'problemas', 'icon' => '#', 'title' => 'Problemas', 'text' => 'Historias sencillas para leer, pensar y resolver.', 'pill' => 'Contexto'],
        ],
        'sample' => [
            'label' => 'Vista previa',
            'question' => 'Mia tiene 14 pegatinas y regala 5. ¿Cuántas le quedan?',
            'answers' => ['7', '9', '11'],
            'correct' => 1,
        ],
        'daily' => 'Completa 3 retos de cualquier subtema para ver tu progreso.',
        'coach' => 'Empieza con restas si quieres un reto corto.',
    ],
    'logic' => [
        'class' => 'logic',
        'icon' => '?',
        'eyebrow' => 'Pensar jugando',
        'title' => 'Lógica',
        'description' => 'Retos visuales y verbales que no dependen de una lección fija. La IA puede variar patrones, pistas y dificultad en cada intento.',
        'cta' => 'Generar reto de lógica',
        'heroCard' => [
            'label' => 'Reto sugerido',
            'title' => 'Detective de series',
            'text' => 'Busca el patrón y elige qué sigue.',
            'meta' => 'Con pistas suaves',
        ],
        'stats' => ['6 subtemas', '18 retos hoy', 'Con pistas'],
        'subtopics' => [
            ['slug' => 'series', 'icon' => '1', 'title' => 'Series', 'text' => 'Patrones de números, colores y objetos que continúan.', 'pill' => 'Visual'],
            ['slug' => 'formas', 'icon' => '◆', 'title' => 'Formas', 'text' => 'Encuentra figuras, tamaños y piezas que faltan.', 'pill' => 'Juego'],
            ['slug' => 'adivinanzas', 'icon' => '?', 'title' => 'Adivinanzas', 'text' => 'Preguntas cortas para razonar sin memorizar.', 'pill' => 'Pistas'],
            ['slug' => 'ordenar', 'icon' => '↔', 'title' => 'Ordenar', 'text' => 'Clasificar, agrupar y decidir qué va primero.', 'pill' => 'Suave'],
        ],
        'sample' => [
            'label' => 'Vista previa',
            'question' => 'Rojo, azul, rojo, azul... ¿qué color sigue?',
            'answers' => ['Rojo', 'Verde', 'Azul'],
            'correct' => 0,
        ],
        'daily' => 'Haz 2 retos con pistas y 1 sin pista para ganar una medalla.',
        'coach' => 'Las series son perfectas para calentar.',
    ],
    'time' => [
        'class' => 'time',
        'icon' => '12',
        'eyebrow' => 'Rutinas y relojes',
        'title' => 'Tiempo',
        'description' => 'Ejercicios para entender horas, media hora, rutinas y duración con ejemplos de la vida diaria.',
        'cta' => 'Generar reto de tiempo',
        'heroCard' => [
            'label' => 'Reto sugerido',
            'title' => 'Reto reloj',
            'text' => 'Lee horas en punto y medias horas.',
            'meta' => '10 minutos',
        ],
        'stats' => ['5 subtemas', '15 retos hoy', 'Con ejemplos'],
        'subtopics' => [
            ['slug' => 'relojes', 'icon' => '12', 'title' => 'Relojes', 'text' => 'Leer horas en punto, media hora y cuartos.', 'pill' => 'Básico'],
            ['slug' => 'rutinas', 'icon' => '☀', 'title' => 'Rutinas', 'text' => 'Mañana, tarde y noche con acciones cotidianas.', 'pill' => 'Diario'],
            ['slug' => 'duracion', 'icon' => '30', 'title' => 'Duración', 'text' => 'Cuánto tarda una actividad y qué pasa después.', 'pill' => 'Práctico'],
            ['slug' => 'antes-despues', 'icon' => '→', 'title' => 'Antes y después', 'text' => 'Ordenar eventos y reconocer momentos del día.', 'pill' => 'Orden'],
        ],
        'sample' => [
            'label' => 'Vista previa',
            'question' => 'Si meriendas a las 5:00 y juegas 30 minutos, ¿a qué hora terminas?',
            'answers' => ['5:15', '5:30', '6:00'],
            'correct' => 1,
        ],
        'daily' => 'Practica 10 minutos con relojes para sumar progreso hoy.',
        'coach' => 'Prueba relojes si quieres algo visual.',
    ],
    'money' => [
        'class' => 'money',
        'icon' => '$',
        'eyebrow' => 'Pequeñas compras',
        'title' => 'Dinero',
        'description' => 'Monedas, precios y cambios explicados con situaciones sencillas: una merienda, una tienda o un juguete.',
        'cta' => 'Generar reto de dinero',
        'heroCard' => [
            'label' => 'Reto sugerido',
            'title' => 'Reto de monedas',
            'text' => 'Compra, paga y descubre cuánto queda.',
            'meta' => 'Vida real',
        ],
        'stats' => ['7 subtemas', '20 retos hoy', 'Vida real'],
        'subtopics' => [
            ['slug' => 'monedas', 'icon' => '$', 'title' => 'Monedas', 'text' => 'Reconocer, contar y combinar monedas.', 'pill' => 'Visual'],
            ['slug' => 'precios', 'icon' => '€', 'title' => 'Precios', 'text' => 'Elegir objetos y comparar cuánto cuestan.', 'pill' => 'Precio'],
            ['slug' => 'cambio', 'icon' => '-', 'title' => 'Cambio', 'text' => 'Calcular cuánto sobra después de comprar.', 'pill' => 'Reto'],
            ['slug' => 'ahorrar', 'icon' => '+', 'title' => 'Ahorrar', 'text' => 'Juntar pequeñas cantidades para una meta.', 'pill' => 'Meta'],
        ],
        'sample' => [
            'label' => 'Vista previa',
            'question' => 'Tienes 10 monedas y una fruta cuesta 6. ¿Cuánto te queda?',
            'answers' => ['2', '4', '6'],
            'correct' => 1,
        ],
        'daily' => 'Resuelve 3 compras pequeñas para ver tu progreso.',
        'coach' => 'Cambio es una buena práctica para hoy.',
    ],
];
