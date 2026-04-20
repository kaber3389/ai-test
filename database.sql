-- SQL-скрипт для создания таблицы и тестовых данных
-- Выполните этот скрипт в вашей базе данных

CREATE DATABASE IF NOT EXISTS landings_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE landings_db;

-- Таблица для хранения данных лендингов
CREATE TABLE IF NOT EXISTS landings_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landing_name VARCHAR(255) NOT NULL COMMENT 'Название лендинга',
    title VARCHAR(255) DEFAULT '' COMMENT 'Meta title',
    description TEXT DEFAULT '' COMMENT 'Meta description',
    keywords TEXT DEFAULT '' COMMENT 'Meta keywords',
    h1 VARCHAR(255) DEFAULT '' COMMENT 'Заголовок H1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для хранения рекламных кампаний (RK)
CREATE TABLE IF NOT EXISTS rk_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landing_id INT NOT NULL COMMENT 'ID лендинга (внешний ключ)',
    rk_name VARCHAR(255) NOT NULL COMMENT 'Название рекламной кампании',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (landing_id) REFERENCES landings_data(id) ON DELETE CASCADE,
    INDEX idx_landing_id (landing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Тестовые данные (несколько лендингов)
INSERT INTO landings_data (landing_name, title, description, keywords, h1) VALUES
('Главная страница', 'Добро пожаловать в нашу компанию', 'Мы предоставляем лучшие услуги на рынке', 'компания, услуги, качество', 'Добро пожаловать'),
('Лендинг продукта А', 'Продукт А - лучшее решение', 'Инновационный продукт для вашего бизнеса', 'продукт, инновации, бизнес', 'Продукт А'),
('Услуги компании', 'Наши услуги для вас', 'Полный спектр профессиональных услуг', 'услуги, профессионалы, помощь', 'Что мы предлагаем'),
('Контакты', 'Свяжитесь с нами', 'Мы всегда на связи с нашими клиентами', 'контакты, связь, поддержка', 'Контактная информация');

-- Тестовые данные для рекламных кампаний
INSERT INTO rk_campaigns (landing_id, rk_name) VALUES
(1, 'Рекламная кампания 1'),
(1, 'Весеннее предложение 2024'),
(2, 'Рекламная кампания 2'),
(2, 'Продвижение продукта А'),
(3, 'Рекламная кампания 3'),
(4, 'Рекламная кампания 4');
