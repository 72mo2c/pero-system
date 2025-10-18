<?php
/**
 * Simple Database singleton wrapper
 * Provides a unified PDO connection for the main system database
 */
class Database {
    private static $instance = null;
    /** @var PDO */
    private $pdo;

    private function __construct() {
        // DatabaseConfig is loaded via config/config.php before including this class
        $this->pdo = DatabaseConfig::getMainConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the underlying PDO connection
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }
}
