CREATE TABLE IF NOT EXISTS role_tab_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    tab_name VARCHAR(100) NOT NULL,
    can_access TINYINT(1) DEFAULT 0,
    updated_at DATETIME NOT NULL
);
