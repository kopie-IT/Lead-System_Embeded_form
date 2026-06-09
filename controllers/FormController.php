<?php
// admin/controllers/FormController.php

class FormController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS forms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                form_key VARCHAR(64) UNIQUE NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                destination ENUM('leads','leads_profile','careers') NOT NULL DEFAULT 'leads',
                fields JSON NOT NULL,
                settings JSON,
                is_active TINYINT(1) DEFAULT 1,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS form_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                form_id INT NOT NULL,
                form_key VARCHAR(64) NOT NULL,
                submitted_data JSON NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                status ENUM('processed','failed') DEFAULT 'processed',
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_fsub_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
            )
        ");
    }

    public function generateKey(): string {
        do {
            $key = bin2hex(random_bytes(8));
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM forms WHERE form_key = ?");
            $stmt->execute([$key]);
        } while ((int)$stmt->fetchColumn() > 0);
        return $key;
    }

    public function create(array $data): int {
        $key  = $this->generateKey();
        $stmt = $this->pdo->prepare("
            INSERT INTO forms (form_key, title, description, destination, fields, settings, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $key,
            $data['title'],
            $data['description'] ?? '',
            $data['destination'],
            $data['fields'],
            $data['settings'] ?? '{}',
            $data['created_by'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $stmt = $this->pdo->prepare("
            UPDATE forms
            SET title=?, description=?, destination=?, fields=?, settings=?, is_active=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['destination'],
            $data['fields'],
            $data['settings'] ?? '{}',
            $data['is_active'] ?? 1,
            $id,
        ]);
    }

    public function delete(int $id): void {
        $this->pdo->prepare("DELETE FROM forms WHERE id=?")->execute([$id]);
    }

    public function getById(int $id): ?object {
        $stmt = $this->pdo->prepare("SELECT * FROM forms WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getByKey(string $key): ?object {
        $stmt = $this->pdo->prepare("SELECT * FROM forms WHERE form_key=? AND is_active=1");
        $stmt->execute([$key]);
        return $stmt->fetch() ?: null;
    }

    public function list(): array {
        return $this->pdo->query("
            SELECT f.*,
                   (SELECT COUNT(*) FROM form_submissions fs WHERE fs.form_id = f.id) AS submission_count
            FROM forms f ORDER BY f.created_at DESC
        ")->fetchAll();
    }

    public function toggleActive(int $id): void {
        $this->pdo->prepare("UPDATE forms SET is_active = NOT is_active WHERE id=?")->execute([$id]);
    }

    public function getSubmissions(int $form_id, int $limit = 100): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM form_submissions WHERE form_id=? ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->execute([$form_id, $limit]);
        return $stmt->fetchAll();
    }

    public function logSubmission(int $form_id, string $form_key, array $data, string $status = 'processed', string $error = ''): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO form_submissions (form_id, form_key, submitted_data, ip_address, user_agent, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $form_id,
            $form_key,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $status,
            $error,
        ]);
    }
}
