-- =====================================================
-- BOM/BOQ Web Application - Complete Database Schema
-- =====================================================

CREATE DATABASE IF NOT EXISTS bom_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bom_db;

-- =====================================================
-- Table: users
-- Stores user accounts with roles and departments
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'guest') NOT NULL DEFAULT 'guest',
    department VARCHAR(100) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: documents
-- Stores BOM and BOQ documents
-- =====================================================
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ref_no VARCHAR(100) NOT NULL,
    type ENUM('bom', 'boq') NOT NULL,
    status ENUM('pending', 'posted', 'completed', 'unposted') DEFAULT 'pending',
    department VARCHAR(100) DEFAULT NULL,
    project_name VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    date_value DATE DEFAULT NULL,
    html_content LONGTEXT NOT NULL,
    original_type VARCHAR(10) DEFAULT NULL,
    original_index INT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    posted_date TIMESTAMP NULL DEFAULT NULL,
    completed_date TIMESTAMP NULL DEFAULT NULL,
    unposted_date TIMESTAMP NULL DEFAULT NULL,
    last_edited TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_ref_no (ref_no),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: activity_logs
-- Stores all user activity and system logs
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id VARCHAR(50) UNIQUE NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    username VARCHAR(50) NOT NULL,
    user_id INT DEFAULT NULL,
    role VARCHAR(20) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    page VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_timestamp (timestamp),
    INDEX idx_action (action),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- End of Schema
-- =====================================================
