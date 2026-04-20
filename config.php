<?php
/**
 * Конфигурация базы данных и полей
 */

// Настройки подключения к БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'landings_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Конфигурация полей для редактирования
// Чтобы добавить новое поле, добавьте его в БД и в этот массив
$fieldConfig = [
    'title' => [
        'label' => 'Заголовок (Title)',
        'type' => 'textarea',
        'rows' => 2,
        'placeholder' => 'Введите заголовок страницы...'
    ],
    'description' => [
        'label' => 'Описание (Description)',
        'type' => 'textarea',
        'rows' => 4,
        'placeholder' => 'Введите описание страницы...'
    ],
    'keywords' => [
        'label' => 'Ключевые слова (Keywords)',
        'type' => 'textarea',
        'rows' => 3,
        'placeholder' => 'Введите ключевые слова через запятую...'
    ],
    'h1' => [
        'label' => 'Заголовок H1',
        'type' => 'textarea',
        'rows' => 2,
        'placeholder' => 'Введите главный заголовок H1...'
    ]
];

// Данные для авторизации (hardcode)
define('AUTH_LOGIN', 'admin');
define('AUTH_PASSWORD', 'admin');
