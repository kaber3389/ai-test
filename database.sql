-- SQL-скрипт для создания таблицы и тестовых данных
-- Выполните этот скрипт в вашей базе данных

CREATE DATABASE IF NOT EXISTS landings_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE landings_db;

-- Таблица для хранения данных лендингов
-- Каждая запись = лендинг + рекламная кампания (один лендинг может иметь много РК)
CREATE TABLE IF NOT EXISTS landings_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landing_name VARCHAR(255) NOT NULL COMMENT 'Название лендинга',
    rk_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Название рекламной кампании',
    title VARCHAR(255) DEFAULT '' COMMENT 'Meta title',
    description TEXT DEFAULT '' COMMENT 'Meta description',
    keywords TEXT DEFAULT '' COMMENT 'Meta keywords',
    h1 VARCHAR(255) DEFAULT '' COMMENT 'Заголовок H1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_landing_rk (landing_name, rk_name),
    INDEX idx_landing_name (landing_name),
    INDEX idx_rk_name (rk_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Тестовые данные (несколько лендингов с разными РК)
INSERT INTO landings_data (landing_name, rk_name, title, description, keywords, h1) VALUES
('Главная страница', '', 'Добро пожаловать в нашу компанию', 'Мы предоставляем лучшие услуги на рынке', 'компания, услуги, качество', 'Добро пожаловать'),
('Главная страница', 'Весеннее предложение 2024', 'Весенняя акция - скидки до 50%', 'Успейте воспользоваться выгодными предложениями', 'акция, скидка, весна, предложение', 'Весеннее предложение'),
('Главная страница', 'Контекстная реклама Яндекс', 'Профессиональные услуги от лидеров рынка', 'Лучшие специалисты для вашего бизнеса', 'услуги, профессионалы, яндекс, реклама', 'Наши преимущества'),
('Лендинг продукта А', '', 'Продукт А - лучшее решение', 'Инновационный продукт для вашего бизнеса', 'продукт, инновации, бизнес', 'Продукт А'),
('Лендинг продукта А', 'Google Ads кампания', 'Продукт А со скидкой 20%', 'Ограниченное предложение от производителя', 'продукт а, google, реклама, скидка', 'Специальное предложение'),
('Услуги компании', '', 'Наши услуги для вас', 'Полный спектр профессиональных услуг', 'услуги, профессионалы, помощь', 'Что мы предлагаем'),
('Услуги компании', 'Таргетированная реклама VK', 'Профессиональные услуги для бизнеса', 'Комплексные решения для роста ваших продаж', 'услуги, бизнес, vk, таргет', 'Почему выбирают нас'),
('Контакты', '', 'Свяжитесь с нами', 'Мы всегда на связи с нашими клиентами', 'контакты, связь, поддержка', 'Контактная информация');
