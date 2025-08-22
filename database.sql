-- SQL schema for the application
-- User approval: status is 'pending' by default. Admin must set to 'active' (approved) or 'rejected'.
-- No automatic approval or rejection.

CREATE TABLE users (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
);

CREATE TABLE profiles (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role VARCHAR(50) NOT NULL,
    referral_code VARCHAR(255) DEFAULT NULL,
    channel_code VARCHAR(50) NOT NULL,
    is_approved TINYINT(1) DEFAULT NULL, -- NULL=pending, 1=approved, 0=rejected
    kyc_completed TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE applications (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    disbursed_date DATE,
    channel_code VARCHAR(50),
    dealing_person_name VARCHAR(255),
    customer_name VARCHAR(255),
    mobile_number VARCHAR(20),
    rc_number VARCHAR(100),
    engine_number VARCHAR(100),
    chassis_number VARCHAR(100),
    old_hp TINYINT(1) DEFAULT 0,
    existing_lender VARCHAR(255),
    case_type VARCHAR(100),
    financer_name VARCHAR(255),
    loan_amount DECIMAL(15,2),
    rate_of_interest DECIMAL(5,2),
    tenure_months INT,
    rc_collection_method VARCHAR(100),
    channel_name VARCHAR(100),
    pdd_status VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE documents (
    id CHAR(36) PRIMARY KEY,
    application_id CHAR(36) NOT NULL,
    document_type VARCHAR(100),
    context VARCHAR(100),
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    storage_path VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(100),
    upload_status VARCHAR(50) DEFAULT 'completed',
    uploaded_at DATETIME NOT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id)
);

CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255),
    message TEXT,
    type VARCHAR(50) DEFAULT 'info',
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE auth_tokens (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    provider VARCHAR(50),
    access_token TEXT,
    refresh_token TEXT,
    expires_at DATETIME,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE password_resets (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE audit_logs (
    id CHAR(36) PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    user_id CHAR(36),
    event_data TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    timestamp DATETIME NOT NULL
);

CREATE TABLE kyc_submissions (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    application_id CHAR(36),
    submitted_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (application_id) REFERENCES applications(id)
);

CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    can_edit_application TINYINT(1) DEFAULT 0,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS role_tab_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    tab_name VARCHAR(100) NOT NULL,
    can_access TINYINT(1) DEFAULT 0,
    updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS manager_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manager_user_id CHAR(36) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    can_view TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS file_access_logs (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36),
    file_id CHAR(36),
    access_type VARCHAR(32),
    ip_address VARCHAR(45),
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Add indexes as needed for performance
