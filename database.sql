CREATE DATABASE IF NOT EXISTS ortho_mess_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE ortho_mess_management;

CREATE TABLE IF NOT EXISTS messes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mess_name VARCHAR(100) NOT NULL,
    invite_code VARCHAR(30) NOT NULL,
    mess_code VARCHAR(30) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_invite_code (invite_code),
    UNIQUE KEY unique_mess_code (mess_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mess_id INT UNSIGNED NOT NULL,
    user_code VARCHAR(30) DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) DEFAULT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'member',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_code (user_code),
    UNIQUE KEY unique_mess_phone (mess_id, phone),
    CONSTRAINT users_mess_fk
        FOREIGN KEY (mess_id) REFERENCES messes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mess_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    meal_date DATE NOT NULL,
    breakfast DECIMAL(6, 2) NOT NULL DEFAULT 0,
    lunch DECIMAL(6, 2) NOT NULL DEFAULT 0,
    dinner DECIMAL(6, 2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY unique_daily_meal (mess_id, user_id, meal_date),
    KEY meals_user_date (user_id, meal_date),
    CONSTRAINT meals_mess_fk
        FOREIGN KEY (mess_id) REFERENCES messes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT meals_user_fk
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mess_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY transactions_mess_date (mess_id, transaction_date),
    KEY transactions_user_type (user_id, type),
    CONSTRAINT transactions_mess_fk
        FOREIGN KEY (mess_id) REFERENCES messes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT transactions_user_fk
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
