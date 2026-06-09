<?php
// controllers/BackupController.php

class BackupController {
    private PDO $pdo;
    private string $backupDir;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->backupDir = __DIR__ . '/../backups';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        $this->ensureTable();
    }

    private function ensureTable(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS backup_history (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                filename    VARCHAR(255) NOT NULL,
                type        ENUM('database','files','full') NOT NULL,
                size_bytes  BIGINT DEFAULT 0,
                created_by  VARCHAR(100) DEFAULT 'system',
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    // ── Database backup ──────────────────────────────────────────────────────

    public function backupDatabase(string $createdBy = 'system'): array {
        $timestamp = date('Y-m-d_H-i-s');
        $filename  = "db_backup_{$timestamp}.sql";
        $filepath  = $this->backupDir . '/' . $filename;

        $sql = $this->dumpDatabase();
        if (file_put_contents($filepath, $sql) === false) {
            return ['success' => false, 'message' => 'Failed to write database backup file.'];
        }

        $size = filesize($filepath);
        $this->logBackup($filename, 'database', $size, $createdBy);

        return ['success' => true, 'filename' => $filename, 'size' => $size];
    }

    private function dumpDatabase(): string {
        $output  = "-- Al Fauzan Advisory — Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- -----------------------------------------------\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Table structure
            $createStmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            $output .= "-- Table: `{$table}`\n";
            $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $output .= $createStmt[1] . ";\n\n";

            // Table data
            $rows = $this->pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $output .= "INSERT INTO `{$table}` ({$columns}) VALUES\n";
                $valueLines = [];
                foreach ($rows as $row) {
                    $values = array_map(function ($val) {
                        if ($val === null) return 'NULL';
                        return "'" . addslashes($val) . "'";
                    }, array_values($row));
                    $valueLines[] = '(' . implode(', ', $values) . ')';
                }
                $output .= implode(",\n", $valueLines) . ";\n\n";
            }
        }

        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $output;
    }

    // ── Files backup ─────────────────────────────────────────────────────────

    public function backupFiles(string $createdBy = 'system'): array {
        $timestamp = date('Y-m-d_H-i-s');
        $filename  = "files_backup_{$timestamp}.zip";
        $filepath  = $this->backupDir . '/' . $filename;

        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'message' => 'ZipArchive extension is not available.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => 'Failed to create ZIP archive.'];
        }

        $root     = realpath(__DIR__ . '/..');
        $excluded = ['backups', 'logs', '.git', 'node_modules', '.kilo'];

        $this->addDirToZip($zip, $root, $root, $excluded);
        $zip->close();

        $size = filesize($filepath);
        $this->logBackup($filename, 'files', $size, $createdBy);

        return ['success' => true, 'filename' => $filename, 'size' => $size];
    }

    private function addDirToZip(ZipArchive $zip, string $dir, string $root, array $excluded): void {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath    = $dir . '/' . $item;
            $relativePath = ltrim(str_replace($root, '', $fullPath), '/\\');

            // Skip excluded top-level dirs
            $topLevel = explode('/', $relativePath)[0];
            if (in_array($topLevel, $excluded)) continue;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($relativePath);
                $this->addDirToZip($zip, $fullPath, $root, $excluded);
            } else {
                $zip->addFile($fullPath, $relativePath);
            }
        }
    }

    // ── Full backup (DB + files) ──────────────────────────────────────────────

    public function backupFull(string $createdBy = 'system'): array {
        $timestamp = date('Y-m-d_H-i-s');
        $filename  = "full_backup_{$timestamp}.zip";
        $filepath  = $this->backupDir . '/' . $filename;

        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'message' => 'ZipArchive extension is not available.'];
        }

        // Write SQL to temp file
        $sqlTmp = sys_get_temp_dir() . "/db_dump_{$timestamp}.sql";
        file_put_contents($sqlTmp, $this->dumpDatabase());

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            unlink($sqlTmp);
            return ['success' => false, 'message' => 'Failed to create ZIP archive.'];
        }

        // Add SQL dump
        $zip->addFile($sqlTmp, "database/db_dump_{$timestamp}.sql");

        // Add project files
        $root     = realpath(__DIR__ . '/..');
        $excluded = ['backups', 'logs', '.git', 'node_modules', '.kilo'];
        $this->addDirToZip($zip, $root, $root, $excluded);

        $zip->close();
        unlink($sqlTmp);

        $size = filesize($filepath);
        $this->logBackup($filename, 'full', $size, $createdBy);

        return ['success' => true, 'filename' => $filename, 'size' => $size];
    }

    // ── History & helpers ─────────────────────────────────────────────────────

    private function logBackup(string $filename, string $type, int $size, string $createdBy): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO backup_history (filename, type, size_bytes, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$filename, $type, $size, $createdBy]);
    }

    public function getHistory(int $limit = 30): array {
        return $this->pdo->query("
            SELECT * FROM backup_history ORDER BY created_at DESC LIMIT {$limit}
        ")->fetchAll();
    }

    public function deleteBackup(int $id): array {
        $stmt = $this->pdo->prepare("SELECT filename FROM backup_history WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['success' => false, 'message' => 'Backup record not found.'];
        }
        $filepath = $this->backupDir . '/' . $row->filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $this->pdo->prepare("DELETE FROM backup_history WHERE id = ?")->execute([$id]);
        return ['success' => true, 'message' => 'Backup deleted.'];
    }

    public function getFilePath(int $id): ?string {
        $stmt = $this->pdo->prepare("SELECT filename FROM backup_history WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $path = $this->backupDir . '/' . $row->filename;
        return file_exists($path) ? $path : null;
    }

    public function formatSize(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
