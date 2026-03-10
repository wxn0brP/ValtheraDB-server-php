<?php
/**
 * Database connection utility
 * Supports both PDO and MySQLi drivers
 * Provides singleton database connections with support for multiple databases
 */

class Database {
    private static array $instances = [];
    private static ?array $config = null;
    private static ?string $lastSql = null;

    /**
     * Get the last executed SQL query
     */
    public static function getLastSql(): ?string {
        return self::$lastSql;
    }

    /**
     * Load configuration from config.php
     */
    private static function loadConfig(): array {
        if (self::$config === null) {
            $configFile = __DIR__ . '/../config.php';
            if (!file_exists($configFile)) {
                throw new Exception("Configuration file not found: {$configFile}");
            }
            self::$config = require $configFile;
        }
        return self::$config;
    }

    /**
     * Get configuration for a specific database
     */
    private static function getDbConfig(?string $dbName = null): array {
        $config = self::loadConfig();

        if ($dbName === null) {
            throw new Exception("Database name not specified");
        }

        if (!isset($config['databases'][$dbName])) {
            throw new Exception("Database configuration not found: {$dbName}");
        }

        return array_merge($config['default'], $config['databases'][$dbName]);
    }

    /**
     * Detect available database driver
     */
    private static function detectDriver(string $driver): string {
        if ($driver !== 'auto') {
            return $driver;
        }

        if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
            return 'pdo';
        } elseif (class_exists('mysqli')) {
            return 'mysqli';
        } else {
            throw new Exception("No database driver available (PDO or MySQLi required)");
        }
    }

    /**
     * Get singleton database connection for a specific database
     */
    public static function getConnection(?string $dbName = null): PDO|mysqli {
        $configKey = $dbName ?? 'default';
        
        if (!isset(self::$instances[$configKey])) {
            $dbConfig = self::getDbConfig($dbName);
            $driver = self::detectDriver($dbConfig['driver'] ?? 'auto');

            if ($driver === 'pdo') {
                self::$instances[$configKey] = self::createPDOConnection($dbConfig);
            } else {
                self::$instances[$configKey] = self::createMySQLiConnection($dbConfig);
            }
        }

        return self::$instances[$configKey];
    }

    /**
     * Create PDO connection
     */
    private static function createPDOConnection(array $config): PDO {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;
    }

    /**
     * Create MySQLi connection
     */
    private static function createMySQLiConnection(array $config): mysqli {
        $mysqli = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port']
        );

        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }

        $mysqli->set_charset($config['charset']);
        return $mysqli;
    }

    /**
     * Check if using PDO for a specific database
     */
    public static function isPDO(?string $dbName = null): bool {
        return self::getConnection($dbName) instanceof PDO;
    }

    /**
     * Execute a prepared statement with given parameters
     */
    public static function execute(string $sql, array $params = [], ?string $dbName = null): DatabaseResult {
        self::$lastSql = self::interpolateQuery($sql, $params);
        $conn = self::getConnection($dbName);

        if ($conn instanceof PDO) {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return new DatabaseResult($stmt, 'pdo');
        } else {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            if (!empty($params)) {
                $types = '';
                $values = [];
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                    $values[] = $param;
                }
                $stmt->bind_param($types, ...$values);
            }

            $stmt->execute();
            return new DatabaseResult($stmt, 'mysqli');
        }
    }

    /**
     * Fetch all results from a query
     */
    public static function fetchAll(string $sql, array $params = [], ?string $dbName = null): array {
        return self::execute($sql, $params, $dbName)->fetchAll();
    }

    /**
     * Fetch single row from a query
     */
    public static function fetchOne(string $sql, array $params = [], ?string $dbName = null): ?array {
        $result = self::execute($sql, $params, $dbName)->fetch();
        return $result ?: null;
    }

    /**
     * Get last inserted ID
     */
    public static function lastInsertId(?string $dbName = null): string {
        $conn = self::getConnection($dbName);

        if ($conn instanceof PDO) {
            return $conn->lastInsertId();
        } else {
            return $conn->insert_id;
        }
    }

    /**
     * Get affected rows count
     */
    public static function affectedRows(?string $dbName = null): int {
        $conn = self::getConnection($dbName);

        if ($conn instanceof PDO) {
            return $conn->rowCount();
        } else {
            return $conn->affected_rows;
        }
    }

    /**
     * Check if table/collection exists
     */
    public static function tableExists(string $table, ?string $dbName = null): bool {
        $sql = "SHOW TABLES LIKE ?";
        $result = self::fetchOne($sql, [$table], $dbName);
        return $result !== null;
    }

    /**
     * Interpolate query with parameters for debugging
     */
    private static function interpolateQuery(string $sql, array $params): string {
        if (empty($params)) {
            return $sql;
        }

        $keys = [];
        $values = [];

        foreach ($params as $param) {
            $keys[] = '/\?/';
            if (is_null($param)) {
                $values[] = 'NULL';
            } elseif (is_bool($param)) {
                $values[] = $param ? '1' : '0';
            } elseif (is_int($param) || is_float($param)) {
                $values[] = (string) $param;
            } else {
                $values[] = "'" . addslashes($param) . "'";
            }
        }

        $query = $sql;
        foreach ($keys as $index => $key) {
            $query = preg_replace($key, $values[$index], $query, 1);
        }

        return $query;
    }
}

/**
 * Wrapper for database results to provide consistent API
 */
class DatabaseResult {
    private PDOStatement|mysqli_stmt $stmt;
    private string $driver;
    private ?array $fetchedRows = null;

    public function __construct(PDOStatement|mysqli_stmt $stmt, string $driver) {
        $this->stmt = $stmt;
        $this->driver = $driver;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(): array {
        if ($this->fetchedRows !== null) {
            return $this->fetchedRows;
        }

        if ($this->driver === 'pdo') {
            $this->fetchedRows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $result = $this->stmt->get_result();
            if (!$result) {
                return [];
            }
            $this->fetchedRows = $result->fetch_all(MYSQLI_ASSOC);
        }

        return $this->fetchedRows;
    }

    /**
     * Fetch single row
     */
    public function fetch(): ?array {
        if ($this->driver === 'pdo') {
            $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } else {
            $result = $this->stmt->get_result();
            if (!$result) {
                return null;
            }
            $row = $result->fetch_assoc();
            return $row ?: null;
        }
    }

    /**
     * Get affected rows
     */
    public function rowCount(): int {
        if ($this->driver === 'pdo') {
            return $this->stmt->rowCount();
        } else {
            return $this->stmt->affected_rows;
        }
    }
}
