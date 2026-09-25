<?php
// ============================================================
//  api/auth.php — Authentication Endpoints
//  POST /api/auth.php (login + password)       — Login user
//  POST /api/auth.php (firstName + lastName)   — Register user
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$body   = getRequestBody();
$db     = getDB();

// LOGIN
if ($method === 'POST' && isset($body['login']) && isset($body['password']) && !isset($body['firstName'])) {
    $login    = clean($body['login']);
    $password = clean($body['password']);

    if (!$login || !$password) {
        respond(400, ['error' => 'Login and password are required']);
    }

    $stmt = $db->prepare('SELECT ID, firstName, lastName, Password, Enabled, Role FROM Users WHERE Login = :login LIMIT 1');
    $stmt->execute([':login' => $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['Password'])) {
        respond(401, ['error' => 'Invalid credentials']);
    }

    if (!$user['Enabled']) {
        respond(403, ['error' => 'Account is disabled']);
    }

    respond(200, [
        'id'        => (int) $user['ID'],
        'firstName' => $user['firstName'],
        'lastName'  => $user['lastName'],
        'role'      => $user['Role'],
        'token'     => (string) $user['ID'],
        'error'     => ''
    ]);
}

// REGISTER
if ($method === 'POST' && isset($body['firstName']) && isset($body['lastName']) && isset($body['login']) && isset($body['password'])) {
    $firstName = clean($body['firstName']);
    $lastName  = clean($body['lastName']);
    $login     = clean($body['login']);
    $password  = clean($body['password']);

    if (!$firstName || !$lastName || !$login || !$password) {
        respond(400, ['error' => 'All fields are required']);
    }

    // Check if user already exists
    $check = $db->prepare('SELECT ID FROM Users WHERE Login = :login');
    $check->execute([':login' => $login]);
    if ($check->fetch()) {
        respond(400, ['error' => 'User already exists']);
    }

    // Hash password and insert
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO Users (firstName, lastName, Login, Password, Role, Enabled) VALUES (:fn, :ln, :login, :pass, :role, :enabled)');
    $stmt->execute([
        ':fn' => $firstName,
        ':ln' => $lastName,
        ':login' => $login,
        ':pass' => $hashedPassword,
        ':role' => 'user',
        ':enabled' => 1
    ]);

    respond(201, [
        'success' => true,
        'message' => 'User registered successfully',
        'user' => [
            'id' => (int) $db->lastInsertId(),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'login' => $login
        ]
    ]);
}

// Invalid request
respond(400, ['error' => 'Invalid request']);
?>
