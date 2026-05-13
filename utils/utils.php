<?php
function getDbConfig(?string $dbName = null): array
{
    $configFile = __DIR__ . '/../config.php';
    if (!file_exists($configFile)) {
        throw new Exception("Configuration file not found: {$configFile}");
    }

    $config = require $configFile;
    $dbs = $config["databases"];

    if ($dbName === null || !isset($dbs[$dbName])) {
        return $config['default'];
    }

    return array_merge(
        $config['default'],
        ['database' => $dbName],
        $dbs[$dbName] ? $dbs[$dbName] : []
    );
}

function escapeIdentifier(string $name, string $type): string
{
    if ($type === "postgres") {
        return '"' . str_replace('"', '""', $name) . '"';
    }
    return "`" . str_replace("`", "``", $name) . "`";
}

function getRequestParams(): array
{
    $params = [];
    $dbName = null;

    if (!empty($_GET))
        $params = array_merge($params, $_GET);

    if (!empty($_POST))
        $params = array_merge($params, $_POST);

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        if (is_array($jsonData)) {
            if (isset($jsonData['db']))
                $dbName = $jsonData['db'];

            if (isset($jsonData['params']) && is_array($jsonData['params']) && isset($jsonData['params'][0]))
                $params = array_merge($params, $jsonData['params'][0]);
            elseif (isset($jsonData['params']) && is_array($jsonData['params']))
                $params = array_merge($params, $jsonData['params']);
            foreach ($jsonData as $key => $value) {
                if ($key !== 'db' && $key !== 'params' && $key !== 'keys') {
                    $params[$key] = $value;
                }
            }
        }
    }

    if ($dbName !== null)
        $params['db'] = $dbName;

    return $params;
}

function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['err' => false, 'result' => $data], JSON_PRETTY_PRINT);
    exit;
}

function jsonErrResponse($data, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['err' => true, 'msg' => $data], JSON_PRETTY_PRINT);
    exit;
}

function errorResponse(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['err' => true, 'msg' => $message], JSON_PRETTY_PRINT);
    exit;
}

function convertSqlAndParamsToString(string $sql, array $params): string
{
    return vsprintf($sql, $params);
}

function assignDataPush($data): array
{
    if (!$data || !is_array($data) || empty($data)) {
        return [];
    }

    $result = [];

    foreach ($data as $key => $value) {
        if (is_string($key) && str_starts_with($key, '$')) {
            if (is_array($value) && !isset($value[0])) {
                foreach ($value as $k => $v) {
                    $result[$k] = $v;
                }
            }
        } else {
            $result[$key] = $value;
        }
    }

    return $result;
}
