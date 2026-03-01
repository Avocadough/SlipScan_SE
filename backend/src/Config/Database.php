<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    /**
     * Get singleton PDO connection to MySQL slipscan database
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $name = $_ENV['DB_NAME'] ?? 'slipscan';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed',
                    'error'   => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Internal server error',
                ]);
                exit;
            }
        }

        return self::$connection;
    }
}
