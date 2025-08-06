<?php
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('Error: Composer autoloader not found at ' . $autoloadPath . '. Run "composer install" in C:\xampp\htdocs\ubiaza');
}
require_once $autoloadPath;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Database {
    private $conn;
    private $logger;

    public function __construct() {
        $this->logger = new Logger('database');
        $this->logger->pushHandler(new StreamHandler(LOG_DIR . 'database.log', Logger::INFO));
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($this->conn->connect_error) {
                $this->logger->error("Connection failed: " . $this->conn->connect_error);
                throw new Exception("Database connection failed");
            }
            $this->logger->info("Database connected successfully");
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>