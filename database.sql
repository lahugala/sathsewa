CREATE DATABASE IF NOT EXISTS sathsewa_society;
USE sathsewa_society;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    membership_number VARCHAR(50),
    membership_date DATE,
    nic VARCHAR(20) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    status ENUM('Active', 'Inactive', 'Suspended') NOT NULL DEFAULT 'Active',
    status_reason VARCHAR(255) NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS member_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    old_status ENUM('Active', 'Inactive', 'Suspended') NULL,
    new_status ENUM('Active', 'Inactive', 'Suspended') NOT NULL,
    reason VARCHAR(255),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dependents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    relationship VARCHAR(100) NOT NULL,
    birth_year INT NOT NULL,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    payment_year INT NOT NULL,
    payment_month INT NOT NULL,
    paid_date DATE NOT NULL,
    member_fee DECIMAL(10,2) DEFAULT 0.00,
    share_capital DECIMAL(10,2) DEFAULT 0.00,
    special_charges DECIMAL(10,2) DEFAULT 0.00,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_month (member_id, payment_year, payment_month)
);

CREATE TABLE IF NOT EXISTS monthly_special_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    charge_year INT NOT NULL,
    charge_month INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    charge_scope ENUM('all', 'targeted') NOT NULL DEFAULT 'all',
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS special_charge_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    charge_id INT NOT NULL,
    member_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (charge_id) REFERENCES monthly_special_charges(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_charge_member (charge_id, member_id)
);

CREATE TABLE IF NOT EXISTS member_benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    benefit_type ENUM('death_gratuity', 'special_donation') NOT NULL,
    paid_date DATE NOT NULL,
    dependent_name VARCHAR(255),
    relationship VARCHAR(100),
    aid_nature VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS member_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    issued_date DATE NOT NULL,
    principal_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    term_months INT NOT NULL,
    total_interest DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_payable DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    installment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('Active', 'Overdue', 'Settled', 'Cancelled') NOT NULL DEFAULT 'Active',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS loan_installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    installment_no INT NOT NULL,
    due_date DATE NOT NULL,
    principal_component DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    interest_component DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_date DATE NULL,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending', 'Partially Paid', 'Paid') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES member_loans(id) ON DELETE CASCADE,
    UNIQUE KEY unique_loan_installment (loan_id, installment_no)
);

-- Insert a default admin user: username 'admin', password 'admin' (hashed with bcrypt)
INSERT IGNORE INTO users (username, password_hash) VALUES ('admin', '$2y$10$dqC4K5dr.GyLs5GOeAJSM.ai/qK97Jcp4ao090Mp5tggmh2KmQv3y');
