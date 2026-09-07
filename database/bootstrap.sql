DROP DATABASE IF EXISTS sgd_lean_db;

CREATE DATABASE sgd_lean_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sgd_lean_db;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

INSERT INTO users (name, email, password) VALUES ('system', 'system@sgd.local', '');

CREATE TABLE groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (created_by) REFERENCES users(id)
);


CREATE TABLE files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    storage_name VARCHAR(255) UNIQUE NOT NULL,
    size BIGINT UNSIGNED NOT NULL,
	parent_id INT UNSIGNED NULL,
    
    owner_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accessed_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (group_id) REFERENCES groups(id)
);

CREATE TABLE directories (
    id INT UNSIGNED UNIQUE NOT NULL,
    FOREIGN KEY (id) REFERENCES files(id) ON DELETE CASCADE  
);

    
ALTER TABLE files ADD FOREIGN KEY (parent_id) REFERENCES directories(id);

CREATE TABLE documents (
    id INT UNSIGNED UNIQUE NOT NULL,
    mime_type VARCHAR(255),
    FOREIGN KEY (id) REFERENCES files(id) ON DELETE CASCADE
);

-- Permissons (from low bit): execute write read delete share download
-- Examples
-- read & share             0b010100 = 20 
-- read & write             0b000110 = 6 
-- read & write & share     0b010110 = 22 
-- all                      0b111111 = 63

-- By default will enable read

-- Relations below a quite similar
CREATE TABLE file_user_shares (
    user_id INT UNSIGNED NOT NULL,
    file_id INT UNSIGNED NOT NULL,
    permission INT DEFAULT 4, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, file_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
);

CREATE TABLE group_user_shares (
    group_id INT UNSIGNED NOT NULL,
    file_id INT UNSIGNED NOT NULL,
    permission INT DEFAULT 4,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (group_id, file_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
);

-- This can it up storage space real quick
-- I'm thinking it not including
-- CREATE TABLE file_access_log(
--     user_id INT UNSIGNED NOT NULL,
--     file_id INT UNSIGNED NOT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY user_id REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY file_id REFERENCES files(id) ON DELETE CASCADE
-- );
