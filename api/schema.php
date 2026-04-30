<?php
function ensure_app_schema($pdo) {
    static $done = false;
    if ($done) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'status'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE members ADD COLUMN status ENUM('Active', 'Inactive', 'Suspended') NOT NULL DEFAULT 'Active' AFTER contact_number");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'status_reason'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE members ADD COLUMN status_reason VARCHAR(255) NULL AFTER status");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS member_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            old_status ENUM('Active', 'Inactive', 'Suspended') NULL,
            new_status ENUM('Active', 'Inactive', 'Suspended') NOT NULL,
            reason VARCHAR(255),
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS monthly_special_charges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            charge_year INT NOT NULL,
            charge_month INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            charge_scope ENUM('all', 'targeted') NOT NULL DEFAULT 'all',
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $stmt = $pdo->query("SHOW COLUMNS FROM monthly_special_charges LIKE 'charge_scope'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE monthly_special_charges ADD COLUMN charge_scope ENUM('all', 'targeted') NOT NULL DEFAULT 'all' AFTER amount");
    }

    $stmt = $pdo->query("SHOW INDEX FROM monthly_special_charges WHERE Key_name = 'unique_charge_period'");
    if ($stmt->fetch()) {
        $pdo->exec("ALTER TABLE monthly_special_charges DROP INDEX unique_charge_period");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS special_charge_targets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            charge_id INT NOT NULL,
            member_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (charge_id) REFERENCES monthly_special_charges(id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            UNIQUE KEY unique_charge_member (charge_id, member_id)
        )
    ");

    $pdo->exec("
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
        )
    ");

    $pdo->exec("
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
        )
    ");

    $pdo->exec("
        INSERT INTO member_status_history (member_id, old_status, new_status, reason)
        SELECT m.id, NULL, m.status, 'Initial status migration'
        FROM members m
        WHERE m.is_deleted = 0
          AND NOT EXISTS (
              SELECT 1
              FROM member_status_history h
              WHERE h.member_id = m.id
          )
    ");

    $done = true;
}
?>
