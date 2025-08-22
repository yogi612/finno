CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    can_edit_application TINYINT(1) DEFAULT 0,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
