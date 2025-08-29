<?php
declare(strict_types=1);

use App\Infrastructure\DBManager;
use App\Infrastructure\LoadSql;
use App\Infrastructure\Result;
use App\Repos\UserRepo;
use App\Repos\ContactRepo;

header('Content-Type: application/json');

// CORS for local testing
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../src/config.php';

$db  = new DBManager($config['db']);
$sql = new LoadSql($config['sql_root']);

$userRepo = new UserRepo($db, $sql);

// Helper functions
function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function send($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function not_found(): void {
    send(['status' => 'error', 'code' => 'NOT_FOUND', 'message' => 'Route not found'], 404);
}

// These are our http status error codes. I'm not a big fan of hard coding it, but should be fine in this use case.
function http_status_for(string $code): int {
    static $map = [
        'OK' => 200, 'CREATED' => 201,
        'INVALID_INPUT' => 422,
        'INVALID_EMAIL' => 422,
        'INVALID_NAME' => 422,
        'INVALID_PHONE' => 422,
        'NOT_ENOUGH_ARGUMENTS' => 422,
        'DUPLICATE_EMAIL' => 409,
        'NOT_FOUND' => 404,
        'NOOP' => 200,
        'DB_ERROR' => 500,
        'INTERNAL' => 500,
    ];
    return $map[$code] ?? 400;
}

function send_result(Result $res, int $okStatus = 200): void {
    if ($res->ok) {
        send(['status' => 'ok', 'data' => $res->data, 'meta' => $res->meta], $okStatus);
    } else {
        $status = http_status_for($res->code ?? 'INTERNAL');
        send([
            'status'  => 'error',
            'code'    => $res->code,
            'message' => $res->message,
            'meta'    => $res->meta
        ], $status);
    }
}

// To fix the path
$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$path = $rawPath;
if ($scriptDir !== '' && str_starts_with($rawPath, $scriptDir)) {
    $path = substr($rawPath, strlen($scriptDir));
    if ($path === '') $path = '/';
}
$parts = array_values(array_filter(explode('/', $path)));

$scriptBase = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!empty($parts) && $parts[0] === $scriptBase) {
    array_shift($parts);
}
if (!empty($parts) && $parts[0] === 'api') {
    array_shift($parts);
}

// For testing health
if ($parts === ['health']) {
    send([
        'ok'=> true,
        'method'=> $method,
        'rawPath'=> $rawPath,
        'scriptDir' => $scriptDir,
        'path'=> $path,
        'parts'=> $parts
    ], 200);
}

// Routing
try {
    // /users
    if ($parts === ['users']) {
        if ($method === 'POST') {
            $b = read_json_body();
            if (!isset($b['name'], $b['email'], $b['password'])) {
                send([
                    'status'=> 'error',
                    'code'=> 'INVALID_INPUT',
                    'message' => 'Missing required fields: name, email, password'
                ], 422);
            }
            $res = $userRepo->createUser($b['name'], $b['email'], $b['password']);
            send_result($res, 201);
        }
        not_found();
    }

    // /users/{uid}
    if (count($parts) === 2 && $parts[0] === 'users') {
        $uid = (int) $parts[1];

        if ($method === 'GET')    { send_result($userRepo->readUser($uid), 200); }
        if ($method === 'PATCH')  { send_result($userRepo->updateUser($uid, read_json_body()), 200); }
        if ($method === 'DELETE') { send_result($userRepo->deleteUser($uid), 200); }

        not_found();
    }

    // /users/{uid}/contacts  (not implemented yet)
    if (count($parts) === 3 && $parts[0] === 'users' && $parts[2] === 'contacts') {
        send(['status' => 'error', 'code' => 'NOT_IMPLEMENTED', 'message' => 'Contact routes are not available yet'], 501);
    }

    // /contacts/{cid} (not implemented yet)
    if (count($parts) === 2 && $parts[0] === 'contacts') {
        send(['status' => 'error', 'code' => 'NOT_IMPLEMENTED', 'message' => 'Contact routes are not available yet'], 501);
    }

    // Fallback
    not_found();

} catch (Throwable $e) {
    send([
        'status'  => 'error',
        'code'    => 'INTERNAL',
        'message' => 'Server error',
        'detail'  => $e->getMessage()
    ], 500);
}
