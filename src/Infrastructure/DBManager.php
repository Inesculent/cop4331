<?php

namespace App\Infrastructure;
use PDO;

// Creates helper functions for our Repos
class DBManager {
    private PDO $pdo;

    public function __construct(array $cfg) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
            $cfg['host'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4'
        );
        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function pdo(): PDO { return $this->pdo; }

    public function rows(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function exec(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $sql, array $params = []): string {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }
}