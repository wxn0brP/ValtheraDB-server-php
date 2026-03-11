<?php
$_DB_CONN = null;
$_DB_DRIVER = null; // pdo | mysqli

function db_init(array $config): void
{
    global $_DB_CONN;
    global $_DB_DRIVER;

    if ($_DB_CONN !== null)
        return;

    $driver = $config['driver'] ?? 'auto';

    if ($driver === 'auto') {
        $driver = class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers()) ? 'pdo' : 'mysqli';
    }

    $_DB_DRIVER = $driver;

    if ($driver === 'pdo') {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            $config['host'],
            $config['port'] ?? 3306,
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );
        $_DB_CONN = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $_DB_CONN = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port'] ?? 3306
        );
        $_DB_CONN->set_charset($config['charset'] ?? 'utf8mb4');
    }
}

function _db_get_types(array $params): string
{
    $types = '';
    foreach ($params as $param) {
        if (is_int($param))
            $types .= 'i';
        elseif (is_float($param))
            $types .= 'd';
        else
            $types .= 's';
    }
    return $types;
}

function db_prepare(string $sql, array $params = []): object
{
    global $_DB_CONN, $_DB_DRIVER;

    if ($_DB_CONN === null)
        throw new Exception("Database connection not initialized");

    if ($_DB_DRIVER === 'pdo') {
        $stmt = $_DB_CONN->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } else {
        $stmt = $_DB_CONN->prepare($sql);
        if (!empty($params)) {
            $types = _db_get_types($params);
            $refs = [];
            foreach ($params as $key => $value) {
                $refs[$key] = &$params[$key];
            }
            $stmt->bind_param($types, ...$refs);
        }
        $stmt->execute();
        return $stmt;
    }
}

function db_fetch_all(string $sql, array $params = []): array
{
    $stmt = db_prepare($sql, $params);
    global $_DB_DRIVER;

    if ($_DB_DRIVER === 'pdo') {
        return $stmt->fetchAll();
    } else {
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

function db_fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db_prepare($sql, $params);
    global $_DB_DRIVER;

    if ($_DB_DRIVER === 'pdo') {
        $row = $stmt->fetch();
        return $row ?: null;
    } else {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ?: null;
    }
}

function db_execute(string $sql, array $params = []): bool
{
    db_prepare($sql, $params);
    return true;
}

function db_close(): void
{
    global $_DB_CONN;
    global $_DB_DRIVER;
    if ($_DB_CONN !== null) {
        if ($_DB_DRIVER === 'pdo') {
            $_DB_CONN = null;
        } else {
            $_DB_CONN->close();
            $_DB_CONN = null;
        }
    }
}
