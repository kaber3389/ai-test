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
    
    case 'save_field':
        saveField($pdo);
        break;
    
    case 'generate_ai':
        generateAI();
        break;
    
    case 'get_rk_campaigns':
        getRkCampaigns($pdo);
        break;
    
    case 'create_rk_campaign':
        createRkCampaign($pdo);
        break;
    
    case 'delete_rk_campaign':
        deleteRkCampaign($pdo);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}

function getLandings(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT id, landing_name FROM landings_data ORDER BY landing_name ASC");
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

function getRkCampaigns(PDO $pdo): void {
    $landingId = $_POST['landing_id'] ?? null;
    
    if (!$landingId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID лендинга']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, rk_name FROM rk_campaigns WHERE landing_id = :landing_id ORDER BY rk_name ASC");
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

function createRkCampaign(PDO $pdo): void {
    $landingId = $_POST['landing_id'] ?? null;
    $rkName = $_POST['rk_name'] ?? null;
    
    if (!$landingId || !$rkName) {
        echo json_encode(['success' => false, 'error' => 'Не указаны необходимые параметры']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO rk_campaigns (landing_id, rk_name) VALUES (:landing_id, :rk_name)");
        $stmt->execute([
            'landing_id' => $landingId,
            'rk_name' => $rkName
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Рекламная кампания успешно создана'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}

function deleteRkCampaign(PDO $pdo): void {
    $rkId = $_POST['rk_id'] ?? null;
    
    if (!$rkId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID РК']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM rk_campaigns WHERE id = :id");
        $stmt->execute(['id' => $rkId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Рекламная кампания успешно удалена'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ]);
    }
}
