-- CDN API Database Schema
-- Generated for CodeIgniter 3 CDN API

-- Create database if needed (uncomment for new database)
-- CREATE DATABASE cdn_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE cdn_api;

-- API Keys table
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `api_key` VARCHAR(64) NOT NULL,
  `name` VARCHAR(100) NULL DEFAULT NULL,
  `rate_limit` INT(11) NOT NULL DEFAULT 60,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `expired_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_api_key` (`api_key`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_expired_at` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files table
CREATE TABLE IF NOT EXISTS `files` (
  `id` CHAR(36) NOT NULL,
  `api_key_id` INT(11) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `path` VARCHAR(500) NOT NULL,
  `mime` VARCHAR(100) NOT NULL,
  `size` BIGINT(20) NOT NULL,
  `visibility` ENUM('public','private') NOT NULL DEFAULT 'public',
  `folder` VARCHAR(100) NULL DEFAULT NULL,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_api_key_id` (`api_key_id`),
  KEY `idx_visibility` (`visibility`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_folder` (`folder`),
  CONSTRAINT `fk_files_api_key` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limits table
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `api_key_id` INT(11) NOT NULL,
  `endpoint` VARCHAR(100) NOT NULL,
  `request_count` INT(11) NOT NULL DEFAULT 0,
  `window_start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_api_endpoint_window` (`api_key_id`, `endpoint`, `window_start`),
  KEY `idx_api_key_id` (`api_key_id`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_window_start` (`window_start`),
  CONSTRAINT `fk_rate_limits_api_key` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample API key for testing
INSERT INTO `api_keys` (`api_key`, `name`, `rate_limit`, `is_active`) 
VALUES ('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'Test API Key', 100, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_files_composite` ON `files` (`api_key_id`, `visibility`, `deleted_at`);
CREATE INDEX IF NOT EXISTS `idx_files_search` ON `files` (`original_name`, `api_key_id`);
CREATE INDEX IF NOT EXISTS `idx_rate_limits_composite` ON `rate_limits` (`api_key_id`, `endpoint`, `window_start`);

-- Add view for active files
CREATE OR REPLACE VIEW `active_files` AS
SELECT 
    f.*,
    ak.name as api_key_name
FROM `files` f
INNER JOIN `api_keys` ak ON f.api_key_id = ak.id
WHERE f.deleted_at IS NULL
AND (f.expires_at IS NULL OR f.expires_at > NOW())
AND ak.is_active = 1
AND (ak.expired_at IS NULL OR ak.expired_at > NOW());

-- Add view for API statistics
CREATE OR REPLACE VIEW `api_stats` AS
SELECT 
    ak.id as api_key_id,
    ak.name as api_key_name,
    COUNT(f.id) as total_files,
    SUM(f.size) as total_size,
    COUNT(CASE WHEN f.visibility = 'public' THEN 1 END) as public_files,
    COUNT(CASE WHEN f.visibility = 'private' THEN 1 END) as private_files,
    COUNT(CASE WHEN f.expires_at IS NOT NULL AND f.expires_at > NOW() THEN 1 END) as expiring_files,
    COUNT(CASE WHEN f.deleted_at IS NULL AND (f.expires_at IS NULL OR f.expires_at > NOW()) THEN 1 END) as active_files
FROM `api_keys` ak
LEFT JOIN `files` f ON ak.id = f.api_key_id
WHERE ak.is_active = 1
GROUP BY ak.id, ak.name;

-- Stored procedure for cleanup expired files
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `cleanup_expired_files`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Soft delete expired files
    UPDATE `files` 
    SET `deleted_at` = NOW()
    WHERE `expires_at` < NOW() 
    AND `deleted_at` IS NULL;
    
    -- Delete expired API keys
    DELETE FROM `api_keys` 
    WHERE `expired_at` < NOW() 
    AND `is_active` = 0;
    
    -- Clean up old rate limit records (older than 48 hours)
    DELETE FROM `rate_limits` 
    WHERE `window_start` < DATE_SUB(NOW(), INTERVAL 48 HOUR);
    
    COMMIT;
END //
DELIMITER ;

-- Trigger to update file timestamps
DELIMITER //
CREATE TRIGGER IF NOT EXISTS `files_before_insert`
BEFORE INSERT ON `files`
FOR EACH ROW
BEGIN
    IF NEW.created_at IS NULL OR NEW.created_at = '0000-00-00 00:00:00' THEN
        SET NEW.created_at = NOW();
    END IF;
END //
DELIMITER ;

-- Trigger to log file deletions
DELIMITER //
CREATE TRIGGER IF NOT EXISTS `files_before_update`
BEFORE UPDATE ON `files`
FOR EACH ROW
BEGIN
    IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN
        -- Log file deletion (you could create a separate log table)
        INSERT INTO `files` (id, api_key_id, original_name, stored_name, path, mime, size, visibility, created_at, deleted_at)
        VALUES (OLD.id, OLD.api_key_id, OLD.original_name, OLD.stored_name, OLD.path, OLD.mime, OLD.size, OLD.visibility, OLD.created_at, NEW.deleted_at)
        ON DUPLICATE KEY UPDATE deleted_at = NEW.deleted_at;
    END IF;
END //
DELIMITER ;

-- Sample data for testing (optional)
-- INSERT INTO `files` (`id`, `api_key_id`, `original_name`, `stored_name`, `path`, `mime`, `size`, `visibility`) VALUES
-- ('sample-file-id-1', 1, 'test.jpg', 'uuid-generated-name.jpg', '/path/to/storage/', 'image/jpeg', 1024, 'public');

-- Final setup
-- Set database engine and character set
ALTER DATABASE DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;