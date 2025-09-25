<?php

namespace App;

use App\Colors;
use PDO;
use PDOException;
use stdClass;

class TokenStorage {
    private string $dbPath;
    private PDO $pdo;
    private $stmtGetBy_token, $stmtGetBy_chat_id;

    public const TOKEN_LENGTH = 16;
    public const TOKEN_CHARACTERS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
       
    public function __construct(string $dbPath) {
        if (!file_exists($dbPath) || !is_dir($dbPath)) {
            throw new RuntimeException("Database dir '$dbPath' is not exists!");
        }
        $this->dbPath = $dbPath;
        $this->pdo = new PDO('sqlite:' . $this->dbPath . "/tokens.db");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initTable();
    }
    
    private function initTable(): void {
        cprintf(null, "[%s] Checking DB table", __METHOD__);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tokens (
                token CHAR(32) PRIMARY KEY,  
                chat_id INTEGER NOT NULL UNIQUE,
                created_at INTEGER NOT NULL,
                deleted_at INTEGER DEFAULT NULL
            )");     
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON tokens(created_at)");
    }

    public function newToken(int $chatId): string {
        cprintf(null, "[%s] Generate token", __METHOD__);
        $maxCharecterNum = strlen(self::TOKEN_CHARACTERS) - 1;
        do {
            $token = '';
            for ($i = 0; $i < self::TOKEN_LENGTH; $i++) {
                $token .= self::TOKEN_CHARACTERS[random_int(0, $maxCharecterNum)];
            }
        } while ($this->getToken($token)->chat_id ?? false);
           
        return $this->saveToken($token, $chatId);
    }

    protected function saveToken(string $token, int $chatId): string
    {
        cprintf(null, "[%s] Save token", __METHOD__);
        try {
            $this
                ->pdo
                ->prepare("INSERT INTO tokens (token, chat_id, created_at) VALUES (?, ?, ?)")
                ->execute([$token, $chatId, time()]);    
        } catch (PDOException $e) {
            cprintf(Colors::YELLOW, "Code: %d Exception: %s", $e->getCode(), $e->getMessage());
            if ($e->getCode() == 23000) { // SQLITE_CONSTRAINT
                cprintf(Colors::YELLOW, "[%s] Chat exists! Try to load token", __METHOD__);
                return $this->getToken($chatId)->token;
            }
            throw $e;
        } 

        return $token;
    }

    public function getToken(int|string $id): stdClass
    {
        $idField = is_string($id) ? "token" : "chat_id";
        $stmt = "stmtGetBy_$idField";
        cprintf(null, "[%s] Load data by %s=%s", __METHOD__, $idField, $id);
        $this->$stmt ??= $this->pdo->prepare("
            SELECT 
                token, 
                chat_id chatId, 
                datetime(created_at, 'unixepoch') as created_at, 
                datetime(deleted_at, 'unixepoch') as deleted_at 
            FROM tokens WHERE $idField = ? LIMIT 1
        ");
        $this->$stmt->execute([$id]);

        return (object) $this->$stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    public function deleteToken(?string $token): void {
        cprintf(null, "[%s] Remove token %s", __METHOD__, $token);
        if ($token) {
            $this
                ->pdo
                ->prepare("UPDATE tokens set deleted_at = ? WHERE token = ? LIMIT 1")
                ->execute([time(), $token])
            ;
        }
    }
}