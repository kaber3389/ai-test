# Система администрирования лендингов

Простая система управления контентом для нескольких лендингов на чистом PHP.

## Стек технологий

- **PHP 8+** (без фреймворков)
- **MySQL** (PDO с prepared statements)
- **jQuery** (для AJAX-запросов)
- **Tailwind CSS** (для стилизации)

## Структура файлов

```
/workspace
├── index.php        # Страница входа (авторизация)
├── admin.php        # Основной интерфейс админки
├── handler.php      # API для обработки AJAX-запросов
├── db.php           # Подключение к базе данных
├── config.php       # Конфигурация (поля, авторизация)
├── logout.php       # Выход из системы
├── database.sql     # SQL-скрипт для создания БД
└── README.md        # Этот файл
```

## Установка

### 1. Создайте базу данных

Выполните SQL-скрипт `database.sql` в вашей MySQL:

```bash
mysql -u root -p < database.sql
```

Или импортируйте через phpMyAdmin.

### 2. Настройте подключение к БД

Откройте `config.php` и измените параметры подключения при необходимости:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'landings_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Авторизация

Логин: `admin`  
Пароль: `admin`

## Возможности

### Управление полями через конфиг

Все поля для редактирования определены в `config.php`. Чтобы добавить новое поле:

1. Добавьте колонку в таблицу `landings_data`
2. Добавьте поле в массив `$fieldConfig` в `config.php`

```php
$fieldConfig = [
    'title' => [
        'label' => 'Заголовок (Title)',
        'type' => 'textarea',
        'rows' => 2,
        'placeholder' => 'Введите заголовок страницы...'
    ],
    // Новое поле:
    'custom_field' => [
        'label' => 'Моё поле',
        'type' => 'textarea',
        'rows' => 3,
        'placeholder' => 'Введите значение...'
    ]
];
```

### Функционал админки

- **Выбор лендинга**: Выпадающий список для выбора нужного лендинга
- **Редактирование полей**: Каждое поле в отдельном блоке
- **Сохранение**: AJAX-сохранение каждого поля отдельно
- **Генерация через ИИ**: Кнопка для имитации генерации контента
- **Предварительный просмотр**: Ответ ИИ показывается перед применением
- **Toast уведомления**: Уведомления об успешном сохранении/ошибках
- **Спиннер**: Блокировка интерфейса во время запросов

### Безопасность

- Проверка сессии для всех страниц
- Prepared statements для всех SQL-запросов
- Экранирование вывода (htmlspecialchars)
- Проверка имён полей через whitelist в конфиге

## API (handler.php)

### Действия

| action | Описание | Параметры |
|--------|----------|-----------|
| `get_landings` | Получить список лендингов | - |
| `get_landing_data` | Получить данные лендинга | `landing_id` |
| `save_field` | Сохранить поле | `landing_id`, `field_name`, `value` |
| `generate_ai` | Сгенерировать текст (ИИ) | `landing_id`, `field_name` |

### Формат ответа

```json
{
    "success": true,
    "data": {...},
    "error": "..." // только при ошибке
}
```

## Интеграция с реальным AI

Для подключения реального AI (OpenAI, Anthropic и др.) отредактируйте функцию `generateAI()` в `handler.php`:

```php
function generateAI() {
    // ... получение параметров ...
    
    // Пример вызова OpenAI API
    $apiKey = 'your-api-key';
    $prompt = "Сгенерируй текст для поля {$fieldName}...";
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => $prompt]]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    $generatedText = $result['choices'][0]['message']['content'];
    
    echo json_encode([
        'success' => true,
        'generated_text' => $generatedText
    ]);
}
```

## Лицензия

MIT
