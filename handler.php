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

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_landings':
        getLandings($pdo);
        break;
    
    case 'get_landing_data':
        getLandingData($pdo);
        break;
    
    case 'get_landing_data_by_entry':
        getLandingDataByEntry($pdo);
        break;
    
    case 'save_field':
        saveField($pdo);
        break;
    
    case 'generate_ai':
        generateAI();
        break;
    
    case 'get_rk_list':
        getRkList($pdo);
        break;
    
    case 'get_unique_rk_names':
        getUniqueRkNames($pdo);
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

function getLandings(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT DISTINCT landing_name FROM landings_data ORDER BY landing_name ASC");
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

function getLandingData(PDO $pdo): void {
    global $fieldConfig;
    
    $landingId = $_POST['landing_id'] ?? null;
    
    if (!$landingId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID лендинга']);
        return;
    }
    
    $fields = array_keys($fieldConfig);
    $fieldsStr = implode(', ', $fields);
    
    try {
        $sql = "SELECT id, landing_name, $fieldsStr FROM landings_data WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $landingId]);
        $data = $stmt->fetch();
        
        if ($data) {
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Лендинг не найден'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}

function getRkList(PDO $pdo): void {
    $landingName = $_POST['landing_name'] ?? null;
    
    if (!$landingName) {
        echo json_encode(['success' => false, 'error' => 'Не указано название лендинга']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT id, rk_name FROM landings_data WHERE landing_name = :landing_name AND rk_name != '' ORDER BY rk_name ASC");
        $stmt->execute(['landing_name' => $landingName]);
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
 * Получить список всех уникальных RK для добавления
 */
function getUniqueRkNames(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT DISTINCT rk_name FROM landings_data WHERE rk_name != '' ORDER BY rk_name ASC");
        $rkNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'data' => $rkNames
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}

function createRkEntry(PDO $pdo): void {
    $landingName = $_POST['landing_name'] ?? null;
    $rkName = $_POST['rk_name'] ?? null;
    
    if (!$landingName || !$rkName) {
        echo json_encode(['success' => false, 'error' => 'Не указаны необходимые параметры']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO landings_data (landing_name, rk_name) VALUES (:landing_name, :rk_name)");
        $stmt->execute([
            'landing_name' => $landingName,
            'rk_name' => $rkName
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Рекламная кампания успешно создана'
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

function deleteRkEntry(PDO $pdo): void {
    $entryId = $_POST['entry_id'] ?? null;
    
    if (!$entryId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID записи']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM landings_data WHERE id = :id");
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

function saveField(PDO $pdo): void {
    global $fieldConfig;
    
    $landingId = $_POST['landing_id'] ?? null;
    $fieldName = $_POST['field_name'] ?? null;
    $value = $_POST['value'] ?? '';
    
    if (!$landingId || !$fieldName) {
        echo json_encode(['success' => false, 'error' => 'Не указаны необходимые параметры']);
        return;
    }
    
    if (!array_key_exists($fieldName, $fieldConfig)) {
        echo json_encode(['success' => false, 'error' => 'Недопустимое имя поля']);
        return;
    }
    
    try {
        $sql = "UPDATE landings_data SET $fieldName = :value WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'value' => $value,
            'id' => $landingId
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
 * Получить данные лендинга по ID записи (для выбранной РК)
 */
function getLandingDataByEntry(PDO $pdo): void {
    global $fieldConfig;
    
    $entryId = $_POST['entry_id'] ?? null;
    
    if (!$entryId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID записи']);
        return;
    }
    
    $fields = array_keys($fieldConfig);
    $fieldsStr = implode(', ', $fields);
    
    try {
        $sql = "SELECT id, landing_name, rk_name, $fieldsStr FROM landings_data WHERE id = :id";
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

function generateAI(): void {
    global $fieldConfig;
    
    $landingId = $_POST['landing_id'] ?? null;
    $fieldName = $_POST['field_name'] ?? null;
    $currentValue = $_POST['current_value'] ?? '';
    
    if (!$landingId || !$fieldName) {
        echo json_encode(['success' => false, 'error' => 'Не указаны необходимые параметры']);
        return;
    }
    
    if (!array_key_exists($fieldName, $fieldConfig)) {
        echo json_encode(['success' => false, 'error' => 'Недопустимое имя поля']);
        return;
    }
    
    $generatedText = generateMockAIText($fieldName, $currentValue);
    
    echo json_encode([
        'success' => true,
        'generated_text' => $generatedText
    ]);
}

function generateMockAIText(string $fieldName, string $currentValue): string {
    $templates = [
        'rk_name' => [
            'Рекламная кампания 2024',
            'Весеннее предложение',
            'Контекстная реклама Яндекс',
            'Таргетированная реклама VK',
            'Google Ads продвижение',
            'Сезонная распродажа',
            'Новогодняя акция'
        ],
        'title' => [
            'Лучшие решения для вашего бизнеса | Компания Pro',
            'Профессиональные услуги высокого качества',
            'Инновационные технологии для современного мира',
            'Ваш надёжный партнёр в цифровую эпоху'
        ],
        'description' => [
            'Мы предлагаем широкий спектр услуг для развития вашего бизнеса. Наша команда профессионалов готова помочь вам достичь поставленных целей с помощью современных технологий и индивидуального подхода.',
            'Откройте для себя новые возможности вместе с нами. Мы предоставляем качественные решения, которые помогут вашему бизнесу расти и развиваться в условиях современной конкуренции.',
            'Доверьте свои задачи профессионалам. Многолетний опыт, современные методики и персональный подход к каждому клиенту — наши главные преимущества.'
        ],
        'keywords' => [
            'бизнес, услуги, профессионалы, качество, развитие, технологии, решения, компания',
            'онлайн сервис, цифровые решения, автоматизация, эффективность, рост',
            'маркетинг, продвижение, SEO, контекстная реклама, социальные сети'
        ],
        'h1' => [
            'Добро пожаловать в мир инноваций',
            'Ваш успех начинается здесь',
            'Профессиональные решения для вашего бизнеса',
            'Создаём будущее вместе с вами'
        ]
    ];
    
    if (isset($templates[$fieldName])) {
        $options = $templates[$fieldName];
        return $options[array_rand($options)];
    }
    
    return 'Сгенерированный текст для поля ' . $fieldName;
}
