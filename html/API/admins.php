<?php
// ============================================================
//  api/admin.php — Admin Operations (Authenticated + Admin Role Required)
//  POST /api/admin.php?action=searchUsers      — Search users
//  POST /api/admin.php?action=searchContacts   — Search all contacts
//  POST /api/admin.php?action=setStatus        — Enable/disable user
//  POST /api/admin.php?action=changePassword   — Change user password
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

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
    $stmt = $db->prepare('SELECT ID as id, firstName, lastName, Login as login, Role as role, Enabled as enabled FROM Users WHERE firstName LIKE :q OR lastName LIKE :q OR Login LIKE :q ORDER BY lastName, firstName');
    $stmt->execute([':q' => $like]);

    $users = $stmt->fetchAll();
    if (empty($users)) {
        respond(200, ['results' => [], 'users' => [], 'error' => 'No Records Found']);
    }

    respond(200, [
        'results' => array_column($users, 'firstName'),
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

    $like = '%' . $search . '%';
    $stmt = $db->prepare('SELECT ID as id, UserID as userId, firstName, lastName, email, phoneNumber FROM Contacts WHERE firstName LIKE :q OR lastName LIKE :q OR email LIKE :q ORDER BY lastName, firstName');
    $stmt->execute([':q' => $like]);

    $contacts = $stmt->fetchAll();
    if (empty($contacts)) {
        respond(200, ['results' => [], 'contacts' => [], 'error' => 'No Records Found']);
    }

    respond(200, [
        'results' => array_column($contacts, 'firstName'),
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
        ':enabled' => $isActive ? 1 : 0,
        ':id' => $targetUserId
    ]);

    respond(200, [
        'message' => 'User status updated',
        'userId' => (int) $targetUserId,
        'enabled' => (bool) $isActive,
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

// Invalid action
respond(400, ['error' => 'Invalid action']);
?>
