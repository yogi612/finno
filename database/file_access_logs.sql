CREATE TABLE IF NOT EXISTS file_access_logs (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36),
    file_id CHAR(36),
    access_type VARCHAR(32),
    ip_address VARCHAR(45),
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Add indexes as needed for performance
