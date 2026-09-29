<?php
// ============================================================
//  api/admin.php — Admin Operations (Authenticated + Admin Role Required)
//  POST admins.php?action=searchUsers      — Search users
//  POST admins.php?action=searchContacts   — Search all contacts
//  POST admins.php?action=setStatus        — Enable/disable user
//  POST admins.php?action=changePassword   — Change user password
//  POST admins.php?action=showAll          — Show all users
//  GET  admins.php                         — Add Admin
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$db     = getDB();
$body   = getRequestBody();

// Require authentication
$userId = requireAuth();

// Verify admin role
$userStmt = $db->prepare('SELECT Role, Enabled FROM Users WHERE ID = :id');
$userStmt->execute([':id' => $userId]);
$authUser = $userStmt->fetch();

if (!$authUser || !$authUser['Enabled']) {
    respond(401, ['error' => 'User not found or disabled']);
}

if ($authUser['Role'] !== 'admin') {
    respond(403, ['error' => 'Admin access required']);
}

// ============================================================
// ADMIN SEARCH USERS
// ============================================================
if ($method === 'POST' && $action === 'searchUsers') {
    $search = $body['search'] ?? '';
    if (!$search) {
        respond(400, ['error' => 'Search term is required']);
    }

    $like = '%' . $search . '%';
    $stmt = $db->prepare('SELECT ID as id, FirstName as firstName, LastName as lastName, Login as login, Role as role, Enabled as enabled FROM Users WHERE FirstName LIKE :q1 OR LastName LIKE :q2 OR Login LIKE :q3 ORDER BY LastName, FirstName');
    $stmt->execute([
        ':q1' => $like,
        ':q2' => $like,
        ':q3' => $like
    ]);

    $users = $stmt->fetchAll();
    if (empty($users)) {
        respond(200, ['users' => [], 'error' => 'No Records Found']);
    }

    respond(200, [
        'users' => $users,
        'error' => ''
    ]);
}

// ============================================================
// ADMIN SHOW ALL USERS
// ============================================================
if ($method === 'GET') {
    $stmt = $db->prepare('SELECT ID as id, FirstName as firstName, LastName as lastName, Login as login, Role as role, Enabled as enabled FROM Users ORDER BY LastName, FirstName');
    $stmt->execute();

    $users = $stmt->fetchAll();
    if (empty($users)) {
        respond(200, ['users' => [], 'error' => 'No Records Found']);
    }

    respond(200, [
        'users' => $users,
        'error' => ''
    ]);
}

// ============================================================
// ADMIN SEARCH CONTACTS (all users' contacts)
// ============================================================
if ($method === 'POST' && $action === 'searchContacts') {

    $search = $body['search'] ?? '';
    if (!$search) {
        respond(400, ['error' => 'Search term is required']);
    }

    $input = $body['userID'] ?? '';
    if (!$input) {
        respond(400, ['error' => 'User ID is required']);
    }

    $like = '%' . $search . '%';
    $stmt = $db->prepare('SELECT ID as id, UserID as userId, FirstName as firstName, LastName as lastName, Email as email, PhoneNumber as phoneNumber FROM Contacts WHERE UserID = :id AND (firstName LIKE :q1 OR lastName LIKE :q2 OR email LIKE :q3) ORDER BY LastName, FirstName');
    $stmt->execute([
        ':id' => $input,
        ':q1' => $like,
        ':q2' => $like,
        ':q3' => $like
    ]);

    $contacts = $stmt->fetchAll();
    if (empty($contacts)) {
        respond(200, ['contacts' => [], 'error' => 'No Records Found']);
    }

    respond(200, [
        'contacts' => $contacts,
        'error' => ''
    ]);
}

// ============================================================
// ADMIN SHOW ALL CONTACTS
// ============================================================
if ($method === 'POST' && $action === 'showCon') {

    $input = $body['userID'] ?? '';
    if (!$input) {
        respond(400, ['error' => 'User ID is required']);
    }

    $stmt = $db->prepare('SELECT ID as id, UserID as userId, FirstName as firstName, LastName as lastName, Email as email, PhoneNumber as phoneNumber FROM Contacts WHERE UserID = :id ORDER BY LastName, FirstName');
    $stmt->execute([
        ':id' => $input
    ]);

    $contacts = $stmt->fetchAll();
    if (empty($contacts)) {
        respond(200, ['contacts' => [], 'error' => 'No Records Found']);
    }

    respond(200, [
        'contacts' => $contacts,
        'error' => ''
    ]);
}

// ============================================================
// ADMIN SET USER STATUS (enable/disable)
// ============================================================
if ($method === 'POST' && $action === 'setStatus') {
    $targetUserId = $body['userId'] ?? 0;
    $isActive = $body['isActive'] ?? true;

    if (!$targetUserId) {
        respond(400, ['error' => 'userId is required']);
    }

    $check = $db->prepare('SELECT ID FROM Users WHERE ID = :id');
    $check->execute([':id' => $targetUserId]);
    if (!$check->fetch()) {
        respond(404, ['error' => 'User not found']);
    }

    $stmt = $db->prepare('UPDATE Users SET Enabled = :enabled WHERE ID = :id');
    $stmt->execute([
        ':enabled' => $isActive ? 0 : 1,
        ':id' => $targetUserId
    ]);

    respond(200, [
        'message' => 'User status updated',
        'userId' => (int) $targetUserId,
        'enabled' => !((bool) $isActive),
        'error' => ''
    ]);
}

// ============================================================
// ADMIN CHANGE USER PASSWORD
// ============================================================
if ($method === 'POST' && $action === 'changePassword') {
    $targetUserId = $body['userId'] ?? 0;
    $newPassword = $body['newPassword'] ?? '';

    if (!$targetUserId || !$newPassword) {
        respond(400, ['error' => 'userId and newPassword are required']);
    }

    $check = $db->prepare('SELECT ID FROM Users WHERE ID = :id');
    $check->execute([':id' => $targetUserId]);
    if (!$check->fetch()) {
        respond(404, ['error' => 'User not found']);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $db->prepare('UPDATE Users SET Password = :pass WHERE ID = :id');
    $stmt->execute([
        ':pass' => $hashedPassword,
        ':id' => $targetUserId
    ]);

    respond(200, [
        'message' => 'Password changed successfully',
        'userId' => (int) $targetUserId,
        'error' => ''
    ]);
}

// ============================================================
// ADMIN ADD ADMIN
// ============================================================
if ($method === 'POST' && $action === 'addAdmin') {
    $firstName = clean($body['firstName']);
    $lastName = clean($body['lastName']);
    $login = clean($body['login']);
    $password = clean($body['password']);

    if (!$firstName || !$lastName || !$login || !$password) respond(400, ['error' => 'Please fill ALL fields']);

    $check = $db->prepare('SELECT ID FROM Users WHERE Login = :login');
    $check->execute([':login' => $login]);
    if ($check->fetch()) respond(400, 'User already exists');

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO Users (firstName, lastName, Login, Password, Role, Enabled) VALUES (:fn, :ln, :login, :pass, :role, :enabled)');
    $stmt->execute([
        ':fn' => $firstName,
        ':ln' => $lastName,
        ':login' => $login,
        ':pass' => $hashedPassword,
        ':role' => 'admin',
        ':enabled' => 1
    ]);

    respond(201, [
        'success' => true,
        'message' => 'Admin created successfully',
        'user' => [
            'id' => (int) $db->lastInsertId(),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'login' => $login
        ]
    ]);
}

// Invalid action
respond(400, ['error' => 'Invalid action']);
?>
