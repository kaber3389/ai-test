CREATE DATABASE IF NOT EXISTS landings_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE landings_db;

CREATE TABLE IF NOT EXISTS landings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landing_name VARCHAR(255) NOT NULL COMMENT 'Название лендинга',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_landing_name (landing_name),
    INDEX idx_landing_name (landing_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS landings_rk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landing_id INT NOT NULL COMMENT 'ID лендинга (FK)',
    rk_name VARCHAR(255) NOT NULL COMMENT 'Название рекламной кампании',
    title VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Meta title',

    description TEXT NULL COMMENT 'Meta description',
    keywords TEXT NULL COMMENT 'Meta keywords',

    h1 VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Заголовок H1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_landing FOREIGN KEY (landing_id) REFERENCES landings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_landing_rk (landing_id, rk_name),
    INDEX idx_landing_id (landing_id),
    INDEX idx_rk_name (rk_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO landings (landing_name) VALUES
('Главная страница'),
('Лендинг продукта А'),
('Услуги компании'),
('Контакты');

INSERT INTO landings_rk (landing_id, rk_name, title, description, keywords, h1) VALUES
(1, '', 'Добро пожаловать в нашу компанию', 'Мы предоставляем лучшие услуги на рынке', 'компания, услуги, качество', 'Добро пожаловать'),
(1, 'Весеннее предложение 2024', 'Весенняя акция - скидки до 50%', 'Успейте воспользоваться выгодными предложениями', 'акция, скидка, весна, предложение', 'Весеннее предложение'),
(1, 'Контекстная реклама Яндекс', 'Профессиональные услуги от лидеров рынка', 'Лучшие специалисты для вашего бизнеса', 'услуги, профессионалы, яндекс, реклама', 'Наши преимущества'),
(2, '', 'Продукт А - лучшее решение', 'Инновационный продукт для вашего бизнеса', 'продукт, инновации, бизнес', 'Продукт А'),
(2, 'Google Ads кампания', 'Продукт А со скидкой 20%', 'Ограниченное предложение от производителя', 'продукт а, google, реклама, скидка', 'Специальное предложение'),
(3, '', 'Наши услуги для вас', 'Полный спектр профессиональных услуг', 'услуги, профессионалы, помощь', 'Что мы предлагаем'),
(3, 'Таргетированная реклама VK', 'Профессиональные услуги для бизнеса', 'Комплексные решения для роста ваших продаж', 'услуги, бизнес, vk, таргет', 'Почему выбирают нас'),
(4, '', 'Свяжитесь с нами', 'Мы всегда на связи с нашими клиентами', 'контакты, связь, поддержка', 'Контактная информация');
