<?php
/**
 * Security utilities - CORS and direct access protection
 * Include this file at the top of every db/ and utils/ script
 */

/**
 * Set CORS headers for API responses
 */
function setCorsHeaders(): void
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Check if script is being run directly (not included)
 * Exits with status code 1 if accessed directly
 */
function protectFromDirectAccess(): void
{
    $includedFile = __FILE__;
    $includedFiles = get_included_files();

    // Remove the first file (the main entry point)
    $mainScript = array_shift($includedFiles);

    // If this file is the main script, it was accessed directly
    if (realpath($includedFile) === realpath($mainScript)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Direct access forbidden']);
        exit(1);
    }
}

/**
 * Check authentication credentials
 * Returns token name if authenticated, null otherwise
 */
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

/**
 * Require authentication from request
 * Exits with 401 if not authenticated
 * Returns token name if authenticated
 */
function requireAuth(): string
{
    $authHeader =
        $_SERVER['HTTP_AUTHORIZATION']
        ?? getallheaders()['Authorization']
        ?? '';
    $token = null;

    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (!empty($authHeader)) {
        $token = $authHeader;
    }

    $tokenName = checkAuth($token);
    if ($tokenName === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['err' => true, 'msg' => 'Unauthorized']);
        exit(1);
    }

    return $tokenName;
}

setCorsHeaders();
protectFromDirectAccess();
requireAuth();
