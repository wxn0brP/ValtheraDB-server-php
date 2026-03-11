<?php
/**
 * Utility functions for database operations
 */

/**
 * Get database configuration by name
 * Returns merged config (default + named config overrides)
 */
function getDbConfig(?string $dbName = null): array
{
    $configFile = __DIR__ . '/../config.php';
    if (!file_exists($configFile)) {
        throw new Exception("Configuration file not found: {$configFile}");
    }

    $config = require $configFile;

    if ($dbName === null || !isset($config[$dbName])) {
        return $config['default'];
    }

    return array_merge($config['default'], $config[$dbName]);
}

/**
 * Escape identifier (table/column name) based on database type
 */
function escapeIdentifier(string $name, string $type): string
{
    if ($type === "postgres") {
        return '"' . str_replace('"', '""', $name) . '"';
    }
    return "`" . str_replace("`", "``", $name) . "`";
}

/**
 * Generate a unique ID (similar to MongoDB ObjectId style)
 */
function genId(): string
{
    return bin2hex(random_bytes(12));
}

/**
 * Bind parameters to mysqli statement
 * Helper for MySQLi driver
 */
function bindParams(mysqli_stmt $stmt, array $params): void
{
    if (empty($params)) {
        return;
    }

    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    $stmt->bind_param($types, ...$params);
}

/**
 * Get request parameters from multiple sources (GET, POST body, POST params)
 * For POST with JSON: expects {"db": "dbName", "params": [{...}]}
 * For GET: uses query parameters directly
 */
function getRequestParams(): array
{
    $params = [];
    $dbName = null;

    // GET parameters
    if (!empty($_GET)) {
        $params = array_merge($params, $_GET);
    }

    // POST parameters
    if (!empty($_POST)) {
        $params = array_merge($params, $_POST);
    }

    // JSON body (for API requests)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        if (is_array($jsonData)) {
            // New API format: {"db": "dbName", "params": [{...}]}
            if (isset($jsonData['db'])) {
                $dbName = $jsonData['db'];
            }
            // params[0] contains the actual query parameters
            if (isset($jsonData['params']) && is_array($jsonData['params']) && isset($jsonData['params'][0])) {
                $params = array_merge($params, $jsonData['params'][0]);
            } elseif (isset($jsonData['params']) && is_array($jsonData['params'])) {
                $params = array_merge($params, $jsonData['params']);
            }
            // Also merge any other top-level keys (for backward compatibility)
            foreach ($jsonData as $key => $value) {
                if ($key !== 'db' && $key !== 'params' && $key !== 'keys') {
                    $params[$key] = $value;
                }
            }
        }
    }

    // Add db to params if specified
    if ($dbName !== null) {
        $params['db'] = $dbName;
    }

    return $params;
}

/**
 * Get a specific parameter from request
 */
function getParam(string $key, $default = null)
{
    $params = getRequestParams();
    return $params[$key] ?? $default;
}

/**
 * Build WHERE clause from search parameters (key == value only)
 * Returns array with [sql_clause, params_array]
 */
function buildWhereClause(array $search, string $tableAlias = ''): array
{
    $conditions = [];
    $params = [];

    foreach ($search as $key => $value) {
        // Skip special operators (starting with $)
        if (str_starts_with($key, '$')) {
            continue;
        }

        // Skip nested objects/arrays (advanced search not supported)
        if (is_array($value) || is_object($value)) {
            continue;
        }

        // Skip null values
        if ($value === null) {
            continue;
        }

        $column = $tableAlias ? "{$tableAlias}.`{$key}`" : "`{$key}`";
        $conditions[] = "{$column} = ?";
        $params[] = $value;
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    return [$whereClause, $params];
}

/**
 * Send JSON response in standardized format
 * Format: { err: boolean, result: any } - result contains the data on success
 */
function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['err' => false, 'result' => $data], JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response in standardized format
 * Format: { err: boolean, msg: string } - msg contains error message
 */
function errorResponse(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['err' => true, 'msg' => $message], JSON_PRETTY_PRINT);
    exit;
}

/**
 * Validate required fields in data
 */
function validateRequired(array $data, array $requiredFields): array
{
    $errors = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            $errors[] = "Missing required field: {$field}";
        }
    }
    return $errors;
}

function convertSqlAndParamsToString(string $sql, array $params): string
{
    return vsprintf($sql, $params);
}
