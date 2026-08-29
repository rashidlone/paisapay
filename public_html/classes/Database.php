<?php
// /classes/Database.php

class Database {
    private static $instance = null;
    private $connection;
    private $debug_mode;
    
    private function __construct() {
        $this->debug_mode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
        
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            // Set timezone for connection
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                $options
            );
            
            // Set timezone to IST
            $this->connection->exec("SET time_zone = '+05:30'");
            
        } catch (PDOException $e) {
            if ($this->debug_mode) {
                error_log('Database connection failed: ' . $e->getMessage());
            }
            throw new Exception('Database connection failed');
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function quote($value) {
        return $this->connection->quote($value);
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function getDebugMode() {
        return $this->debug_mode;
    }
}
?>