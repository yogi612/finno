CREATE TABLE IF NOT EXISTS user_field_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    can_edit TINYINT(1) DEFAULT 0,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
