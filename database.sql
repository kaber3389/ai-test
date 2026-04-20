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

-- Тестовые данные (несколько лендингов)
INSERT INTO landings_data (landing_name, title, description, keywords, h1) VALUES
('Главная страница', 'Добро пожаловать в нашу компанию', 'Мы предоставляем лучшие услуги на рынке', 'компания, услуги, качество', 'Добро пожаловать'),
('Лендинг продукта А', 'Продукт А - лучшее решение', 'Инновационный продукт для вашего бизнеса', 'продукт, инновации, бизнес', 'Продукт А'),
('Услуги компании', 'Наши услуги для вас', 'Полный спектр профессиональных услуг', 'услуги, профессионалы, помощь', 'Что мы предлагаем'),
('Контакты', 'Свяжитесь с нами', 'Мы всегда на связи с нашими клиентами', 'контакты, связь, поддержка', 'Контактная информация');
