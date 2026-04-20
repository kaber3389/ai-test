<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Неавторизованный доступ']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

define('NEUROAPI_KEY', 'sk-nUHv8HRYhJYImjpn8QzJIw6X6HOzT21l5Y2snIy5KxNi3PlU');
define('NEUROAPI_BASE_URL', 'https://neuroapi.host/v1');
define('NEUROAPI_MODEL', 'claude-opus-4-6');
define('REQUEST_TIMEOUT', 300);

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_landings':
        getLandings($pdo);
        break;
    
    case 'get_rk_list':
        getRkList($pdo);
        break;
    
    case 'get_entry_data':
        getEntryData($pdo);
        break;
    
    case 'save_field':
        saveField($pdo);
        break;
    
    case 'generate_ai':
        generateAI();
        break;
    
    case 'create_rk_entry':
        createRkEntry($pdo);
        break;
    
    case 'delete_rk_entry':
        deleteRkEntry($pdo);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}

/**
 * @param PDO $pdo
 * @return void
 */
function getLandings(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT id, landing_name FROM landings ORDER BY landing_name ASC");
        $landings = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $landings
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}

/**
 * @param PDO $pdo
 * @return void
 */
function getRkList(PDO $pdo): void {
    $landingId = $_POST['landing_id'] ?? null;
    
    if (!$landingId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID лендинга']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, rk_name FROM landings_rk WHERE landing_id = :landing_id ORDER BY rk_name ASC");
        $stmt->execute(['landing_id' => $landingId]);
        $campaigns = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $campaigns
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}

/**
 * @param PDO $pdo
 * @return void
 */
function getEntryData(PDO $pdo): void {
    global $fieldConfig;
    
    $entryId = $_POST['entry_id'] ?? null;
    
    if (!$entryId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID записи']);
        return;
    }
    
    $fields = array_keys($fieldConfig);
    $fieldsStr = implode(', ', $fields);
    
    try {
        $sql = "SELECT lr.id, lr.landing_id, l.landing_name, lr.rk_name, $fieldsStr 
                FROM landings_rk lr 
                JOIN landings l ON lr.landing_id = l.id 
                WHERE lr.id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $entryId]);
        $data = $stmt->fetch();
        
        if ($data) {
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Запись не найдена'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}

/**
 * @param PDO $pdo
 * @return void
 */
function saveField(PDO $pdo): void {
    global $fieldConfig;
    
    $entryId = $_POST['entry_id'] ?? null;
    $fieldName = $_POST['field_name'] ?? null;
    $value = $_POST['value'] ?? '';
    
    if (!$entryId || !$fieldName) {
        echo json_encode(['success' => false, 'error' => 'Не указаны необходимые параметры']);
        return;
    }
    
    if (!array_key_exists($fieldName, $fieldConfig)) {
        echo json_encode(['success' => false, 'error' => 'Недопустимое имя поля']);
        return;
    }
    
    try {
        $sql = "UPDATE landings_rk SET $fieldName = :value WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'value' => $value,
            'id' => $entryId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Поле успешно сохранено'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}

/**
 * @return void
 */
function generateAI(): void {
    global $fieldConfig, $pdo;
    
    $fieldName = $_POST['field_name'] ?? null;
    $entryId = $_POST['entry_id'] ?? null;
    
    if (!$fieldName) {
        echo json_encode(['success' => false, 'error' => 'Не указано имя поля']);
        return;
    }
    
    if (!array_key_exists($fieldName, $fieldConfig)) {
        echo json_encode(['success' => false, 'error' => 'Недопустимое имя поля']);
        return;
    }
    
    if (!$entryId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID записи']);
        return;
    }

    $landingName = '';
    $landingMotive = null;

    try {
        $stmt = $pdo->prepare("
            SELECT l.landing_name, lr.landing_motive 
            FROM landings_rk lr 
            JOIN landings l ON lr.landing_id = l.id 
            WHERE lr.id = :id
        ");
        $stmt->execute(['id' => $entryId]);
        $context = $stmt->fetch();
        
        if ($context) {
            $landingName = $context['landing_name'];
            $landingMotive = $context['landing_motive'];
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        return;
    }

    $prompt = buildPrompt($fieldName, $landingName, $landingMotive);
    $generatedText = callNeuroAPI($prompt);
    
    if ($generatedText === null) {
        echo json_encode(['success' => false, 'error' => 'Ошибка при вызове NeuroAPI']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'generated_text' => $generatedText
    ]);
}

/**
 * @param string $fieldName
 * @param string $landingName
 * @param ?string $landingMotive
 * @return string
 */
function buildPrompt(string $fieldName, string $landingName, ?string $landingMotive = null): string {
    $contextParts = [];
    
    if ($landingName) {
        $contextParts[] = "лендинг \"{$landingName}\"";
    }

    if ($landingMotive) {
        $contextParts[] = "Мотив лендинга: \"{$landingMotive}\"";
    }
    
    $context = !empty($contextParts) 
        ? " с учетом тематики " . implode(' и ', $contextParts)
        : '';

    $prompts = [
        'title' => "Ты — маркетолог справочной системы Кадры. Твоя задача: составить заголовок рекламного объявления для темы: {$context}.\n" .
            "ПРАВИЛА:\n" .
            "1. Длина строго до 56 символов с пробелами.\n" .
            "2. Структура: [Что ищет клиент] + [сообщение, что это есть в Системе Кадры].\n" .
            "3. Используй конструкцию «есть в Системе Кадры» или «в Системе Кадры», соблюдая грамматику.\n" .
            "ПРИМЕР: Образцы локальных актов есть в Системе Кадры.\n" .
            "Ответ возвращай только текстом заголовка без кавычек.",

        'lid' => "Ты — маркетолог справочной системы Кадры. Напиши текст объявления (лид) для темы: {$context}.\n" .
            "ПРАВИЛА:\n" .
            "1. Длина строго до 81 символа с пробелами.\n" .
            "2. Это должна быть продающая фраза: что именно ценное получит кадровик для своей работы (решение, безопасность, экономия времени).\n" .
            "Ответ возвращай только текстом без кавычек.",

        'button_text' => "Ты — эксперт по лидогенерации. Напиши текст для кнопки призыва к действию (CTA) на лендинге по теме: {$context}.\n" .
            "ПРАВИЛА:\n" .
            "1. Текст должен быть коротким (2-4 слова).\n" .
            "2. Используй сильные глаголы: Скачать, Получить, Забрать, Узнать.\n" .
            "ПРИМЕР: Получить бесплатный доступ, Скачать пакет образцов.\n" .
            "Ответ возвращай только текстом кнопки.",

        'oz_title' => "Ты — маркетолог. Напиши короткий заголовок для формы захвата (над полями ввода данных) для темы: {$context}.\n" .
            "ПРАВИЛА:\n" .
            "1. Заголовок должен пояснять, что получит пользователь после заполнения формы.\n" .
            "2. Лаконичность и выгода.\n" .
            "ПРИМЕР: Доступ на 10 дней + образцы.\n" .
            "Ответ возвращай только текстом без кавычек.",

        'mag_comment' => "Ты — руководитель отдела продаж. Напиши краткий скрипт (1-2 предложения) для менеджера, который будет звонить по заявке на тему: {$context}.\n" .
            "ПРАВИЛА:\n" .
            "1. Упомяни, что клиент искал конкретные материалы.\n" .
            "2. Предложи открыть бесплатный доступ или сделать скидку на подписку.\n" .
            "Ответ возвращай только текстом комментария."
    ];
    
    return $prompts[$fieldName] ?? "Напиши текст для поля {$fieldName}.";
}

/**
 * @param string $prompt
 * @return string|null
 */
function callNeuroAPI(string $prompt): ?string {
    $url = NEUROAPI_BASE_URL . '/chat/completions';
    
    $data = [
        'model' => NEUROAPI_MODEL,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    ];

    $ch = curl_init($url);
    
    if ($ch === false) {
        error_log('cURL initialization failed');
        return null;
    }
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . NEUROAPI_KEY
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);

    if ($response === false || $error) {
        error_log('cURL error: ' . $error);
        return null;
    }

    if ($httpCode !== 200) {
        error_log('NeuroAPI HTTP error: ' . $httpCode . ' Response: ' . $response);
        return null;
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        error_log('Invalid NeuroAPI response structure: ' . $response);
        return null;
    }
    
    $text = trim($result['choices'][0]['message']['content']);

    $text = preg_replace('/^```(?:json)?\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    
    return trim($text);
}

/**
 * @param PDO $pdo
 * @return void
 */
function createRkEntry(PDO $pdo): void {
    $landingId = $_POST['landing_id'] ?? null;
    $rkName = $_POST['rk_name'] ?? null;
    
    if (!$landingId || !$rkName) {
        echo json_encode(['success' => false, 'error' => 'Не указаны необходимые параметры']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO landings_rk (landing_id, rk_name) VALUES (:landing_id, :rk_name)");
        $stmt->execute([
            'landing_id' => $landingId,
            'rk_name' => $rkName
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Рекламная кампания успешно создана',
            'entry_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode([
                'success' => false,
                'error' => 'Такая связка лендинг+РК уже существует'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Ошибка базы данных: ' . $e->getMessage()
            ]);
        }
    }
}

/**
 * @param PDO $pdo
 * @return void
 */
function deleteRkEntry(PDO $pdo): void {
    $entryId = $_POST['entry_id'] ?? null;
    
    if (!$entryId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID записи']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM landings_rk WHERE id = :id");
        $stmt->execute(['id' => $entryId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Запись успешно удалена'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}
