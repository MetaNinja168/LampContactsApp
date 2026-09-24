<?php
// ============================================================
//  api/index.php — Unified RESTful API Router
//
//  POST   /api/index.php (login + password)      — Login user
//  POST   /api/index.php (firstName + lastName)  — Register user
//  POST   /api/index.php?action=addContact       — Add contact
//  GET    /api/index.php?action=searchContacts&q=term — Search contacts
//  GET    /api/index.php?action=getContact&id=1 — Get single contact
//  PUT    /api/index.php?action=updateContact&id=1 — Update contact
//  DELETE /api/index.php?action=deleteContact&id=1 — Delete contact
//  POST   /api/index.php?action=adminSearchUsers — Admin search users
//  POST   /api/index.php?action=adminSearchContacts — Admin search all contacts
//  POST   /api/index.php?action=setUserStatus   — Admin disable/enable user
//  POST   /api/index.php?action=changePassword  — Admin change user password
//  GET    /api/index.php?action=health          — Health check
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$db     = getDB();
$body   = getRequestBody();

// ============================================================
// UNAUTHENTICATED ENDPOINTS
// ============================================================

// Health Check
if ($action === 'health' || (isset($_GET['ping']))) {
    respond(200, ['status' => 'ok', 'timestamp' => time()]);
}

// LOGIN (unauthenticated)
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

// REGISTER (unauthenticated)
if ($method === 'POST' && isset($body['firstName']) && isset($body['lastName']) && isset($body['login']) && isset($body['password'])) {
    if (!isset($body['password'])) {
        respond(400, ['error' => 'Missing required fields']);
    }

    $firstName = clean($body['firstName']);
    $lastName  = clean($body['lastName']);
    $login     = clean($body['login']);
    $password  = clean($body['password']);

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

// ============================================================
// AUTHENTICATED ENDPOINTS (all below require auth)
// ============================================================

$userId = requireAuth();

// Get authenticated user's role
$userStmt = $db->prepare('SELECT Role, Enabled FROM Users WHERE ID = :id');
$userStmt->execute([':id' => $userId]);
$authUser = $userStmt->fetch();

if (!$authUser || !$authUser['Enabled']) {
    respond(401, ['error' => 'User not found or disabled']);
}

$userRole = $authUser['Role'];
$isAdmin = ($userRole === 'admin');

// ============================================================
// CONTACT ENDPOINTS
// ============================================================

// ADD CONTACT
if ($method === 'POST' && $action === 'addContact') {
    if (!isset($body['firstName']) || !isset($body['lastName']) || 
        !isset($body['email']) || !isset($body['phoneNumber'])) {
        respond(400, ['error' => 'Missing required fields']);
    }

    $stmt = $db->prepare('INSERT INTO Contacts (UserID, firstName, lastName, email, phoneNumber) VALUES (:uid, :fn, :ln, :email, :phone)');
    $stmt->execute([
        ':uid' => $userId,
        ':fn' => clean($body['firstName']),
        ':ln' => clean($body['lastName']),
        ':email' => clean($body['email']),
        ':phone' => clean($body['phoneNumber'])
    ]);

    respond(201, [
        'success' => true,
        'message' => 'Contact added successfully',
        'id' => (int) $db->lastInsertId()
    ]);
}

// SEARCH CONTACTS (user's own or all if admin)
if ($method === 'GET' && $action === 'searchContacts') {
    $q = $_GET['q'] ?? '';
    if (!$q) {
        respond(400, ['error' => 'Search term (q parameter) is required']);
    }

    $like = '%' . $q . '%';

    if ($isAdmin) {
        // Admin searches all contacts
        $stmt = $db->prepare('SELECT ID as id, UserID as userId, firstName, lastName, email, phoneNumber FROM Contacts WHERE firstName LIKE :q OR lastName LIKE :q OR email LIKE :q ORDER BY lastName, firstName');
        $stmt->execute([':q' => $like]);
    } else {
        // User searches own contacts
        $stmt = $db->prepare('SELECT ID as id, firstName, lastName, email, phoneNumber FROM Contacts WHERE UserID = :uid AND (firstName LIKE :q OR lastName LIKE :q OR email LIKE :q) ORDER BY lastName, firstName');
        $stmt->execute([':uid' => $userId, ':q' => $like]);
    }

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

// GET SINGLE CONTACT
if ($method === 'GET' && $action === 'getContact') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if (!$id) {
        respond(400, ['error' => 'Contact ID (id parameter) is required']);
    }

    if ($isAdmin) {
        $stmt = $db->prepare('SELECT ID as id, UserID as userId, firstName, lastName, email, phoneNumber FROM Contacts WHERE ID = :id');
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $db->prepare('SELECT ID as id, firstName, lastName, email, phoneNumber FROM Contacts WHERE ID = :id AND UserID = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    $contact = $stmt->fetch();
    if (!$contact) {
        respond(404, ['error' => 'Contact not found']);
    }

    respond(200, $contact);
}

// UPDATE CONTACT
if ($method === 'PUT' && $action === 'updateContact') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if (!$id) {
        respond(400, ['error' => 'Contact ID (id parameter) is required']);
    }

    // Check ownership (unless admin)
    if (!$isAdmin) {
        $check = $db->prepare('SELECT ID FROM Contacts WHERE ID = :id AND UserID = :uid');
        $check->execute([':id' => $id, ':uid' => $userId]);
        if (!$check->fetch()) {
            respond(404, ['error' => 'Contact not found']);
        }
    } else {
        $check = $db->prepare('SELECT ID FROM Contacts WHERE ID = :id');
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            respond(404, ['error' => 'Contact not found']);
        }
    }

    if (!isset($body['firstName']) || !isset($body['lastName']) || 
        !isset($body['email']) || !isset($body['phoneNumber'])) {
        respond(400, ['error' => 'Missing required fields']);
    }

    $stmt = $db->prepare('UPDATE Contacts SET firstName = :fn, lastName = :ln, email = :email, phoneNumber = :phone WHERE ID = :id');
    $stmt->execute([
        ':fn' => clean($body['firstName']),
        ':ln' => clean($body['lastName']),
        ':email' => clean($body['email']),
        ':phone' => clean($body['phoneNumber']),
        ':id' => $id
    ]);

    respond(200, ['message' => 'Contact updated successfully', 'error' => '']);
}

// DELETE CONTACT
if ($method === 'DELETE' && $action === 'deleteContact') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if (!$id) {
        respond(400, ['error' => 'Contact ID (id parameter) is required']);
    }

    // Check ownership (unless admin)
    if (!$isAdmin) {
        $stmt = $db->prepare('DELETE FROM Contacts WHERE ID = :id AND UserID = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    } else {
        $stmt = $db->prepare('DELETE FROM Contacts WHERE ID = :id');
        $stmt->execute([':id' => $id]);
    }

    if ($stmt->rowCount() === 0) {
        respond(404, ['error' => 'Contact not found']);
    }

    respond(200, ['message' => 'Contact deleted successfully', 'error' => '']);
}

// ============================================================
// ADMIN ENDPOINTS
// ============================================================

// ADMIN SEARCH USERS
if ($method === 'POST' && $action === 'adminSearchUsers') {
    if (!$isAdmin) {
        respond(403, ['error' => 'Admin access required']);
    }

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

// ADMIN SEARCH CONTACTS (all users' contacts)
if ($method === 'POST' && $action === 'adminSearchContacts') {
    if (!$isAdmin) {
        respond(403, ['error' => 'Admin access required']);
    }

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

// ADMIN SET USER STATUS (enable/disable)
if ($method === 'POST' && $action === 'setUserStatus') {
    if (!$isAdmin) {
        respond(403, ['error' => 'Admin access required']);
    }

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

// ADMIN CHANGE USER PASSWORD
if ($method === 'POST' && $action === 'changePassword') {
    if (!$isAdmin) {
        respond(403, ['error' => 'Admin access required']);
    }

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
// FALLBACK - No action matched
// ============================================================

respond(405, ['error' => 'Method not allowed or invalid action']);
?>
