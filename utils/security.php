<?php
function setCorsHeaders(): void
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function protectFromDirectAccess(): void
{
    $includedFile = __FILE__;
    $includedFiles = get_included_files();
    $mainScript = array_shift($includedFiles);
    if (realpath($includedFile) === realpath($mainScript)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Direct access forbidden']);
        exit(1);
    }
}

function checkAuth(?string $token = null): ?string
{
    $configFile = __DIR__ . '/../config.php';
    if (!file_exists($configFile)) {
        return null;
    }

    $config = require $configFile;
    $auth = $config['auth'] ?? [];

    foreach ($auth as $name => $validToken) {
        if ($token === $validToken) {
            return $name;
        }
    }

    return null;
}

function set_unauthorized(): void
{
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['err' => true, 'msg' => 'Unauthorized']);
    exit(1);
}

function requireAuth(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];

    $authHeader =
        $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $headers['Authorization']
        ?? $headers['authorization']
        ?? null;

    if (empty($authHeader)) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true);
            if (is_array($jsonData))
                $authHeader = $jsonData['auth'] ?? null;
        }
    }

    if (empty($authHeader))
        set_unauthorized();

    $token = null;

    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (!empty($authHeader)) {
        $token = $authHeader;
    }

    $tokenName = checkAuth($token);
    if ($tokenName === null)
        set_unauthorized();

    return $tokenName;
}

setCorsHeaders();
protectFromDirectAccess();
requireAuth();
