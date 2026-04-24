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
