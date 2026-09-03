<?php
$_DB_CONN = null;

function db_init(array $config): void
{
    global $_DB_CONN;

    if ($_DB_CONN !== null)
        return;

    if (!class_exists('PDO') || !in_array('mysql', PDO::getAvailableDrivers())) {
        throw new Exception("pdo_mysql extension is required but not available");
    }

    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $config['host'],
        $config['port'] ?? 3306,
        $config['database'],
        $config['charset'] ?? 'utf8mb4'
    );
    $_DB_CONN = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function db_prepare(string $sql, array $params = []): object
{
    global $_DB_CONN;

    if ($_DB_CONN === null)
        throw new Exception("Database connection not initialized");

    $stmt = $_DB_CONN->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch_all(string $sql, array $params = []): array
{
    $stmt = db_prepare($sql, $params);
    return $stmt->fetchAll();
}

function db_fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db_prepare($sql, $params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_execute(string $sql, array $params = []): bool
{
    db_prepare($sql, $params);
    return true;
}

function db_close(): void
{
    global $_DB_CONN;
    if ($_DB_CONN !== null) {
        $_DB_CONN = null;
    }
}
