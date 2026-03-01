-- ========================================================
-- SlipScan Database Schema
-- Database: slipscan (MySQL)
-- Sprint 1 - Day 1
-- ========================================================

CREATE DATABASE IF NOT EXISTS slipscan
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE slipscan;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin', 'user') DEFAULT 'user',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: slips
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS slips (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    image_path    VARCHAR(500) NOT NULL,
    sender_name   VARCHAR(255) DEFAULT NULL,
    bank_name     VARCHAR(100) DEFAULT NULL,
    amount        DECIMAL(15,2) DEFAULT NULL,
    slip_date     DATE DEFAULT NULL,
    slip_time     TIME DEFAULT NULL,
    ref_no        VARCHAR(100) DEFAULT NULL,
    receiver_name VARCHAR(255) DEFAULT NULL,
    receiver_acct VARCHAR(50) DEFAULT NULL,
    raw_ocr       JSON DEFAULT NULL,
    is_fake       TINYINT(1) DEFAULT 0,
    is_duplicate  TINYINT(1) DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: slip_hashes (for Sprint 2 - Duplicate Detection)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS slip_hashes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    slip_id     INT NOT NULL,
    hash        VARCHAR(64) UNIQUE NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (slip_id) REFERENCES slips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Index for performance
-- --------------------------------------------------------
CREATE INDEX idx_slips_user_id   ON slips(user_id);
CREATE INDEX idx_slips_ref_no    ON slips(ref_no);
CREATE INDEX idx_slips_created   ON slips(created_at);
