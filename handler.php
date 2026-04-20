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

// ============================================================
// NEUROAPI CONFIGURATION
// ============================================================
define('NEUROAPI_KEY', 'sk-NireE2orzRrRR4QdlSZGwKXD3pJQUJjy4zbKZU5PjAeYDGc0');
define('NEUROAPI_BASE_URL', 'https://neuroapi.host/v1');
define('NEUROAPI_MODEL', 'gpt-5.2');
define('REQUEST_TIMEOUT', 30);

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
 * Получить список всех лендингов
 * 
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
 * Получить список РК для выбранного лендинга
 * 
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
 * Получить данные записи РК
 * 
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
 * Сохранить значение поля
 * 
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
 * Сгенерировать AI текст через NeuroAPI
 * 
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
    
    // Получаем контекст: landing_name и rk_name
    $landingName = '';
    $rkName = '';
    
    try {
        $stmt = $pdo->prepare("
            SELECT l.landing_name, lr.rk_name 
            FROM landings_rk lr 
            JOIN landings l ON lr.landing_id = l.id 
            WHERE lr.id = :id
        ");
        $stmt->execute(['id' => $entryId]);
        $context = $stmt->fetch();
        
        if ($context) {
            $landingName = $context['landing_name'];
            $rkName = $context['rk_name'];
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка получения контекста']);
        return;
    }
    
    // Формируем промпт с контекстом
    $prompt = buildPrompt($fieldName, $landingName, $rkName);
    
    // Делаем запрос к NeuroAPI
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
 * Построить промпт для генерации контента
 * 
 * @param string $fieldName - Имя поля
 * @param string $landingName - Название лендинга
 * @param string $rkName - Название рекламной кампании
 * @return string
 */
function buildPrompt(string $fieldName, string $landingName, string $rkName): string {
    $contextParts = [];
    
    if ($landingName) {
        $contextParts[] = "лендинг \"{$landingName}\"";
    }
    
    if ($rkName) {
        $contextParts[] = "рекламная кампания \"{$rkName}\"";
    }
    
    $context = !empty($contextParts) 
        ? " с учетом тематики " . implode(' и ', $contextParts)
        : '';
    
    $prompts = [
        'title' => "Ты — профессиональный SEO-маркетолог. Напиши meta title (заголовок страницы) для{$context}. " .
            "Заголовок должен быть до 60 символов, содержать ключевые слова и привлекать клики. " .
            "Ответ возвращай только текстом заголовка без кавычек и дополнительного форматирования.",
        
        'description' => "Ты — профессиональный SEO-маркетолог. Напиши meta description (описание страницы) для{$context}. " .
            "Описание должно быть до 160 символов, содержать призыв к действию и ключевые слова. " .
            "Ответ возвращай только текстом описания без кавычек и дополнительного форматирования.",
        
        'keywords' => "Ты — профессиональный SEO-маркетолог. Подбери ключевые слова (keywords) для{$context}. " .
            "Слова должны быть релевантными тематике, перечисли их через запятую (5-10 слов). " .
            "Ответ возвращай только списком слов через запятую без кавычек и дополнительного форматирования.",
        
        'h1' => "Ты — профессиональный маркетолог. Напиши главный заголовок H1 для{$context}. " .
            "Заголовок должен быть кратким (до 50 символов), цепляющим и отражать суть предложения. " .
            "Ответ возвращай только текстом заголовка без кавычек и дополнительного форматирования."
    ];
    
    return $prompts[$fieldName] ?? "Напиши текст для поля {$fieldName}.";
}

/**
 * Вызов NeuroAPI через cURL
 * 
 * @param string $prompt - Текст промпта
 * @return string|null - Сгенерированный текст или null при ошибке
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
        ],
        'temperature' => 0.7,
        'max_tokens' => 200
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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
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
    
    // Очищаем от возможных markdown-обёрток
    $text = preg_replace('/^```(?:json)?\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    
    return trim($text);
}

/**
 * Создать новую запись РК
 * 
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
 * Удалить запись РК
 * 
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
