<?php
declare(strict_types=1);

use App\Infrastructure\DBManager;
use App\Infrastructure\LoadSql;
use App\Infrastructure\Result;
use App\Repos\UserRepo;
use App\Infrastructure\AuthService;
use App\Infrastructure\AuthMiddleware;
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

// Check for composer autoload
require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../src/config.php';

$db  = new DBManager($config['db']);
$sql = new LoadSql($config['sql_root']);
$userRepo = new UserRepo($db, $sql);
$contactRepo = new ContactRepo($db, $sql);

// --- Auth wiring (JWT) ---
$authService = new AuthService($config);
$authMw = new AuthMiddleware($config);
$AUTH_UID = $authMw->authenticate();
$GLOBALS['AUTH_UID'] = $AUTH_UID;

// Helpers
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

function not_allowed(): void {
    send(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed'], 405);
}

// HTTP code map
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
        'PAYLOAD_TOO_LARGE' => 413,
        'INVALID_JSON' => 400,
        'METHOD_NOT_ALLOWED' => 405,
        'UNAUTHENTICATED' => 401,
        'FORBIDDEN' => 403,
    ];
    return $map[$code] ?? 400;
}

// Jsonify and standardized send function
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

// Require auth (optionally assert a specific uid)
function require_auth(?int $mustMatchUid = null): int {
    $uid = (int)($GLOBALS['AUTH_UID'] ?? 0);
    if ($uid <= 0) {
        send(['status'=>'error','code'=>'UNAUTHENTICATED','message'=>'Login required'], 401);
    }
    if ($mustMatchUid !== null && $mustMatchUid !== $uid) {
        send(['status'=>'error','code'=>'FORBIDDEN','message'=>'Forbidden'], 403);
    }
    return $uid;
}

// Disgusting routing path parse
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

// Health check
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

// Dev tooling to show auth info for testing
if ($parts === ['dev', 'auth'] && $config['dev']['show_cookies']) {
    $authCookie = $_COOKIE['auth'] ?? null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    $currentUid = $GLOBALS['AUTH_UID'] ?? null;
    
    send([
        'auth_cookie' => $authCookie,
        'auth_header' => $authHeader,
        'current_uid' => $currentUid,
        'cookie_visible' => $config['dev']['show_cookies'],
        'secure_cookies' => $config['dev']['secure_cookies'],
        'all_cookies' => $_COOKIE,
        'request_headers' => getallheaders(),
        'server_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    ], 200);
}

// Route management
try {
    // /users  (create user) - PUBLIC
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
        } else {
            not_allowed();
        }
    }

    // /auth/login - PUBLIC: verify credentials, issue JWT cookie
    if ($parts === ['auth', 'login']) {
        if ($method === 'POST') {
            $body = read_json_body();
            $email = (string)($body['email'] ?? '');
            $password = (string)($body['password'] ?? '');

            $res = $userRepo->verifyUser($email, $password);
            if ($res->ok) {
                $uid = (int)($res->data['user']['id'] ?? 0);
                if ($uid > 0) {
                    $token = $authService->issueAccessToken($uid);
                    $exp = time() + (int)$config['auth']['access_ttl'];
                    
                    // Set cookie with explicit domain for local testing
                    $cookieOptions = [
                        'expires'  => $exp,
                        'path'     => '/',
                        'secure'   => $config['dev']['secure_cookies'],
                        'httponly' => !$config['dev']['show_cookies'], // false for testing = visible in devtools
                        'samesite' => 'Lax',
                    ];
                    
                    // Don't set domain for localhost to ensure Postman compatibility
                    $cookieSet = setcookie('auth', $token, $cookieOptions);
                    
                    // For development: also return token in response body for easy access
                    if ($config['dev']['show_cookies']) {
                        $res->data['auth_token'] = $token;
                        $res->data['cookie_set'] = $cookieSet;
                        $res->data['expires'] = date('Y-m-d H:i:s', $exp);
                    }
                    
                    // Add debug headers
                    header('X-Auth-Token: ' . $token);
                    header('X-Cookie-Set: ' . ($cookieSet ? 'true' : 'false'));
                }
            }
            send_result($res, 200);
        } else {
            not_allowed();
        }
    }

    // /users/{uid} - PROTECTED: uid must match token subject
    if (count($parts) === 2 && $parts[0] === 'users') {
        $uidPath = (int) $parts[1];
        require_auth($uidPath); // blocks if not logged in or uid mismatch

        if ($method === 'GET')   { send_result($userRepo->readUser($uidPath), 200); }
        if ($method === 'PATCH') { send_result($userRepo->updateUser($uidPath, read_json_body()), 200); }
        if ($method === 'DELETE'){ send_result($userRepo->deleteUser($uidPath), 200); }

        not_found();
    }

    // /users/{uid}/contacts - PROTECTED
    if (count($parts) === 3 && $parts[0] === 'users' && $parts[2] === 'contacts') {
        $uidPath = (int)$parts[1];
        require_auth($uidPath);

        if ($method === 'POST') {
            // Create a new contact
            $body = read_json_body();
            $name = (string)($body['name'] ?? '');
            $phone = (string)($body['phone'] ?? '');
            $email = (string)($body['email'] ?? '');

            $res = $contactRepo->createContact($uidPath, $name, $phone, $email);
            send_result($res, 201);
        }

        if ($method === 'GET') {
            // List all contacts for this user
            $res = $contactRepo->listContacts($uidPath);
            send_result($res, 200);
        }

        not_allowed();
    }

    // /contacts/{cid} - PROTECTED
    if (count($parts) === 2 && $parts[0] === 'contacts') {
        $cid = (int)$parts[1];
        $uid = require_auth(); // Get the authenticated user ID

        if ($method === 'GET') {
            // Read a specific contact
            $res = $contactRepo->readContact($cid, $uid);
            send_result($res, 200);
        }

        if ($method === 'PATCH') {
            // Update a contact
            $body = read_json_body();
            $res = $contactRepo->updateContact($cid, $uid, $body);
            send_result($res, 200);
        }

        if ($method === 'DELETE') {
            // Delete a contact
            $res = $contactRepo->deleteContact($cid, $uid);
            send_result($res, 200);
        }

        not_allowed();
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
