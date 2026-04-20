<?php

declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'landings_db');
define('DB_USER', 'root');
define('DB_PASS', 'root');

$fieldConfig = [
    'landing_motive' => [
        'label' => 'Тематика ленгинга',
        'type' => 'textarea',
        'rows' => 2,
        'placeholder' => 'Введите тематику ленгинга...'
    ],
    'title' => [
        'label' => 'Заголовок (Title)',
        'type' => 'textarea',
        'rows' => 2,
        'placeholder' => 'Введите заголовок страницы...'
    ],
    'lid' => [
        'label' => 'Описание (Description)',
        'type' => 'textarea',
        'rows' => 4,
        'placeholder' => 'Введите описание страницы...'
    ],
    'button_text' => [
        'label' => 'Текст на кнопке',
        'type' => 'textarea',
        'rows' => 3,
        'placeholder' => 'Текст на кнопке...'
    ],
    'oz_title' => [
        'label' => 'Заголовок формы',
        'type' => 'textarea',
        'rows' => 2,
        'placeholder' => 'Введите Заголовок формы...'
    ],
    'mag_comment' => [
        'label' => 'Коммент в форме (скрытый)',
        'type' => 'textarea',
        'rows' => 2,
        'placeholder' => 'Введите коммент в форме...'
    ],
    'img_path' => [
        'label' => 'Путь к изображению',
        'type' => 'input',
        'placeholder' => 'Введите путь к изображению...'
    ]
];

define('AUTH_LOGIN', 'admin');
define('AUTH_PASSWORD', 'admin');
