-- Создаём базу данных с именем MyBaseSQL
-- Устанавливаем кодировку utf8mb4, чтобы поддерживать все символы (включая эмодзи и кириллицу)
CREATE DATABASE IF NOT EXISTS MyBaseSQL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Активируем базу данных для последующего создания таблиц
USE MyBaseSQL;

-- Таблица threads: хранит список тредов на бордах
CREATE TABLE IF NOT EXISTS threads (
    id INT AUTO_INCREMENT PRIMARY KEY,                 -- Уникальный идентификатор треда (автоинкремент)
    board VARCHAR(10) NOT NULL,                        -- Название борды (например, 'b' или 'ph')
    title VARCHAR(255) NOT NULL,                       -- Заголовок треда
    content TEXT NOT NULL,                             -- Основной текст треда (содержимое)
    media_path VARCHAR(500),                           -- Путь к прикреплённому медиафайлу (изображение, видео и т.п.)
    media_thumb_path VARCHAR(500),                     -- Путь к миниатюре изображения или видео
    ip_address VARCHAR(45),                            -- IP-адрес создателя треда (до 45 символов для IPv6)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP     -- Дата и время создания треда, по умолчанию — текущее
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;               -- Используем InnoDB и кодировку utf8mb4

-- Таблица posts: хранит все посты (ответы) внутри тредов
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,                 -- Уникальный идентификатор поста
    thread_id INT NOT NULL,                            -- ID треда, к которому относится пост (внешний ключ)
    parent_id INT DEFAULT NULL,                        -- ID поста, на который это сообщение является ответом (для вложенности)
    content TEXT NOT NULL,                             -- Текст поста
    media_path VARCHAR(500),                           -- Путь к прикреплённому файлу (если есть)
    ip_address VARCHAR(45),                            -- IP-адрес автора поста
    is_op BOOLEAN DEFAULT FALSE,                       -- Флаг: true — если это автор треда (OP), false — обычный пользователь
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,    -- Дата и время создания поста
    FOREIGN KEY (thread_id) REFERENCES threads(id)     -- Связь: каждый пост связан с тредом
        ON DELETE CASCADE,                             -- При удалении треда — удаляются все связанные посты
    FOREIGN KEY (parent_id) REFERENCES posts(id)       -- Связь: пост может быть ответом на другой пост
        ON DELETE CASCADE                              -- При удалении родительского поста — удаляются его ответы
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;               -- Используем InnoDB и кодировку utf8mb4
