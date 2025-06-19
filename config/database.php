<?php
// FICHERO MODIFICADO: config/database.php
declare(strict_types=1);

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        // Configuración debería venir de variables de entorno
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'u414388299_libv2';
        $pass = getenv('DB_PASS') ?: 'Spider2025@';
        $name = getenv('DB_NAME') ?: 'u414388299_libv2';
        
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $this->connection = new mysqli($host, $user, $pass, $name);
            $this->connection->set_charset("utf8mb4");
            $this->connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
        } catch (mysqli_sql_exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            header('HTTP/1.1 503 Service Unavailable');
            exit('Service temporarily unavailable');
        }
    }
    
    public static function getInstance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection(): mysqli {
        return $this->connection;
    }
    
    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

function getDBConnection(): mysqli {
    return Database::getInstance()->getConnection();
}