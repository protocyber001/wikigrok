<?php
require_once _WIKIDIR_ . 'config/config.php';

class Database {
    private $pdo;
    private $isMysqlAvailable;

    public function __construct() {
        $this->isMysqlAvailable = $this->connectMysql();
        if (!$this->isMysqlAvailable) {
            $this->ensureJsonFile();
        }
    }

    private function connectMysql() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function ensureJsonFile() {
        if (!file_exists(JSON_FILE)) {
            file_put_contents(JSON_FILE, json_encode(['articles' => [], 'revisions' => []]));
        }
    }

    public function isMysqlAvailable() {
        return $this->isMysqlAvailable;
    }

    public function query($sql, $params = []) {
        if ($this->isMysqlAvailable) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
        return null;
    }

    public function saveToJson($data) {
        file_put_contents(JSON_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function getFromJson() {
        return json_decode(file_get_contents(JSON_FILE), true) ?: ['articles' => [], 'revisions' => []];
    }
}
?>