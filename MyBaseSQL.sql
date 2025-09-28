-- Create a database named MyBaseSQL
-- Set the character set to utf8mb4 to support all characters (including emojis and Cyrillic)
CREATE DATABASE IF NOT EXISTS MyBaseSQL CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Select the database for subsequent table creation
USE MyBaseSQL;

-- Table `threads`: stores the list of threads on boards
CREATE TABLE IF NOT EXISTS threads (
    id INT AUTO_INCREMENT PRIMARY KEY,                 -- Unique thread ID (auto-increment)
    board VARCHAR(10) NOT NULL,                         -- Board name (e.g., 'b' or 'ph')
    title VARCHAR(255) NOT NULL,                        -- Thread title
    content TEXT NOT NULL,                              -- Main thread content (text)
    media_path VARCHAR(500),                            -- Path to attached media file (image, video, etc.)
    media_thumb_path VARCHAR(500),                      -- Path to media thumbnail image or video preview
    ip_address VARCHAR(45),                             -- IP address of thread creator (up to 45 chars for IPv6)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP      -- Creation timestamp, defaults to current time
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table `posts`: stores all posts (replies) inside threads
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,                 -- Unique post ID
    thread_id INT NOT NULL,                             -- Thread ID this post belongs to (foreign key)
    parent_id INT DEFAULT NULL,                         -- ID of the post this message replies to (for nesting)
    content TEXT NOT NULL,                              -- Post content text
    media_path VARCHAR(500),                            -- Path to attached media file (if any)
    ip_address VARCHAR(45),                             -- IP address of the post author
    is_op BOOLEAN DEFAULT FALSE,                        -- Flag: true if original poster (OP), false otherwise
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,    -- Creation timestamp
    FOREIGN KEY (thread_id) REFERENCES threads(id)     -- Relation: each post belongs to a thread
        ON DELETE CASCADE,                              -- Delete all related posts when thread is deleted
    FOREIGN KEY (parent_id) REFERENCES posts(id)       -- Relation: post can reply to another post
        ON DELETE CASCADE                               -- Delete all replies when parent post is deleted
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
