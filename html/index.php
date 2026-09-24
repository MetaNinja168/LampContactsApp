<?php
// ============================================================
//  api/index.php — Unified Contacts Manager RESTful API
//
//  GET    /api/index.php?ping=1   — status ping health check
//  POST   /api/index.php (login)  — authenticate user
//  POST   /api/index.php (register) — register new user
//  GET    /api/index.php          — list all contacts for user
//  GET    /api/index.php?q=term   — partial search contacts
//  GET    /api/index.php?id=1     — get single contact by ID
//  POST   /api/index.php (contact)  — create new contact
//  PUT    /api/index.php?id=1     — update contact by ID
//  DELETE /api/index.php?id=1     — delete contact by ID
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// 1. Unauthenticated Health Check (Ping)
if ($method === 'GET' && (isset($_GET['ping']) || (isset($_GET['action']) && $_GET['action'] === 'ping'))) {
    respond(200, ['status' => 'OK', 'timestamp' => time()]);
}

// 2. Unauthenticated Login/Register (POST)
if ($method === 'POST') {
    $body = getRequestBody();

    // ── Register ───────────────────────────────────────────────
    if (isset($body['action']) && $body['action'] === 'register') {
        $firstName = clean($body['firstName'] ?? '');
        $lastName  = clean($body['lastName'] ?? '');
        $login     = clean($body['login'] ?? '');
        $password  = $body['password'] ?? '';

        if (!$firstName || !$lastName || !$login || !$password) {
            respond(400, ['error' => 'First name, last name, login, and password are required']);
        }

        // Check if the login already exists
        $stmt = $db->prepare('SELECT ID FROM Users WHERE Login = :login LIMIT 1');
        $stmt->execute([':login' => $login]);

        if ($stmt->fetch()) {
            respond(409, ['error' => 'Login already exists']);
        }

        // Hash the password before storing it
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare(
            'INSERT INTO Users (FirstName, LastName, Login, Password)
             VALUES (:firstName, :lastName, :login, :password)'
        );

        $stmt->execute([
            ':firstName' => $firstName,
            ':lastName'  => $lastName,
            ':login'     => $login,
            ':password'  => $passwordHash
        ]);

        respond(201, [
            'success' => true,
            'message' => 'User registered successfully',
            'userId'  => (int) $db->lastInsertId()
        ]);
    }

    // ── Login ──────────────────────────────────────────────────
    // ── Login ──────────────────────────────────────────────────
    if (isset($body['login']) &&
    	isset($body['password']) &&
    	!isset($body['action'])) {
        $login    = clean($body['login']);
        $password = $body['password'];

        if (!$login || !$password) {
            respond(400, ['error' => 'Login and password are required']);
        }

        // Retrieve the stored password hash
        $stmt = $db->prepare(
            'SELECT ID, FirstName, LastName, Password, Role, Status
             FROM Users
             WHERE Login = :login
             LIMIT 1'
        );

        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch();

        // Verify the supplied password against the stored hash
        if ($user && password_verify($password, $user['Password'])) {
    		if ($user['Status'] !== 'active') {
   		     respond(403, [
   		         'id'    => 0,
   		         'error' => 'Account is inactive'
   		     ]);
   		 }

            respond(200, [
                'id'        => (int) $user['ID'],
                'firstName' => $user['FirstName'],
                'lastName'  => $user['LastName'],
                'token'     => (string) $user['ID'],
                'error'     => ''
            ]);
        } else {
            respond(401, [
                'id'        => 0,
                'firstName' => '',
                'lastName'  => '',
                'error'     => 'No Records Found'
            ]);
        }
    }
}

// 3. All other routes require an authenticated user
$userId = requireAuth();

// ============================================================
// ADMIN ROUTES
// ============================================================

if (isset($_GET['admin']) && $_GET['admin'] === 'users') {

    // Verify that the authenticated user is actually an admin
    $adminCheck = $db->prepare(
        'SELECT ID, Role, Status
         FROM Users
         WHERE ID = :id
         LIMIT 1'
    );

    $adminCheck->execute([':id' => $userId]);
    $admin = $adminCheck->fetch();

    if (!$admin || $admin['Role'] !== 'admin') {
        respond(403, ['error' => 'Admin access required']);
    }

    if ($admin['Status'] !== 'active') {
        respond(403, ['error' => 'Admin account is inactive']);
    }

    // --------------------------------------------------------
    // GET - List/Search Users
    // --------------------------------------------------------
    if ($method === 'GET') {

        $search = isset($_GET['q']) ? trim($_GET['q']) : '';

        if ($search !== '') {

            $like = '%' . $search . '%';

            $stmt = $db->prepare(
                'SELECT
                    ID as id,
                    FirstName as first_name,
                    LastName as last_name,
                    Login as login,
                    Role as role,
                    Status as status,
                    DateCreated as date_created,
                    DateUpdated as date_updated
                 FROM Users
                 WHERE
                    FirstName LIKE :q1
                    OR LastName LIKE :q2
                    OR Login LIKE :q3
                 ORDER BY FirstName, LastName'
            );

            $stmt->execute([
                ':q1' => $like,
                ':q2' => $like,
                ':q3' => $like
            ]);

        } else {

            $stmt = $db->prepare(
                'SELECT
                    ID as id,
                    FirstName as first_name,
                    LastName as last_name,
                    Login as login,
                    Role as role,
                    Status as status,
                    DateCreated as date_created,
                    DateUpdated as date_updated
                 FROM Users
                 ORDER BY FirstName, LastName'
            );

            $stmt->execute();
        }

        $users = $stmt->fetchAll();

        respond(200, [
            'users' => $users,
            'error' => ''
        ]);
    }

    // --------------------------------------------------------
    // POST - Add User
    // --------------------------------------------------------
    if ($method === 'POST') {

        $body = getRequestBody();

        if (($body['action'] ?? '') !== 'admin_add_user') {
            respond(400, ['error' => 'Invalid admin action']);
        }

        $firstName = clean($body['firstName'] ?? '');
        $lastName  = clean($body['lastName'] ?? '');
        $login     = clean($body['login'] ?? '');
        $password  = $body['password'] ?? '';
        $role      = clean($body['role'] ?? 'user');

        if (!$firstName || !$lastName || !$login || !$password) {
            respond(400, [
                'error' => 'First name, last name, login, and password are required'
            ]);
        }

        if (!in_array($role, ['user', 'admin'], true)) {
            respond(400, ['error' => 'Invalid role']);
        }

        $check = $db->prepare(
            'SELECT ID
             FROM Users
             WHERE Login = :login
             LIMIT 1'
        );

        $check->execute([':login' => $login]);

        if ($check->fetch()) {
            respond(409, ['error' => 'Login already exists']);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare(
            'INSERT INTO Users
                (FirstName, LastName, Login, Password, Role, Status)
             VALUES
                (:firstName, :lastName, :login, :password, :role, :status)'
        );

        $stmt->execute([
            ':firstName' => $firstName,
            ':lastName'  => $lastName,
            ':login'     => $login,
            ':password'  => $passwordHash,
            ':role'      => $role,
            ':status'    => 'active'
        ]);

        respond(201, [
            'message' => 'User created',
            'id'      => (int) $db->lastInsertId(),
            'error'   => ''
        ]);
    }

    // --------------------------------------------------------
    // PUT - Change Status or Password
    // --------------------------------------------------------
    if ($method === 'PUT') {

        $body = getRequestBody();
        $action = $body['action'] ?? '';
        $targetId = isset($body['userId']) ? (int) $body['userId'] : 0;

        if (!$targetId) {
            respond(400, ['error' => 'User ID is required']);
        }

        $check = $db->prepare(
            'SELECT ID
             FROM Users
             WHERE ID = :id
             LIMIT 1'
        );

        $check->execute([':id' => $targetId]);

        if (!$check->fetch()) {
            respond(404, ['error' => 'User not found']);
        }

        // Change user status
        if ($action === 'admin_change_status') {

            $status = clean($body['status'] ?? '');

            if (!in_array($status, ['active', 'inactive'], true)) {
                respond(400, [
                    'error' => 'Status must be active or inactive'
                ]);
            }

            // Prevent admin from disabling their own account
            if ($targetId === (int) $userId) {
                respond(400, [
                    'error' => 'You cannot change your own admin status'
                ]);
            }

            $stmt = $db->prepare(
                'UPDATE Users
                 SET Status = :status
                 WHERE ID = :id'
            );

            $stmt->execute([
                ':status' => $status,
                ':id'     => $targetId
            ]);

            respond(200, [
                'message' => 'User status updated',
                'error'   => ''
            ]);
        }

        // Change user password
        if ($action === 'admin_change_password') {

            $newPassword = $body['password'] ?? '';

            if (!$newPassword) {
                respond(400, ['error' => 'New password is required']);
            }

            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $stmt = $db->prepare(
                'UPDATE Users
                 SET Password = :password
                 WHERE ID = :id'
            );

            $stmt->execute([
                ':password' => $passwordHash,
                ':id'       => $targetId
            ]);

            respond(200, [
                'message' => 'User password updated',
                'error'   => ''
            ]);
        }

        respond(400, ['error' => 'Invalid admin action']);
    }

    // --------------------------------------------------------
    // DELETE - Delete User
    // --------------------------------------------------------
    if ($method === 'DELETE') {

        $targetId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if (!$targetId) {
            respond(400, [
                'error' => 'User ID is required - use ?id='
            ]);
        }

        // Do not let an admin delete themselves
        if ($targetId === (int) $userId) {
            respond(400, [
                'error' => 'You cannot delete your own admin account'
            ]);
        }

        $check = $db->prepare(
            'SELECT ID
             FROM Users
             WHERE ID = :id
             LIMIT 1'
        );

        $check->execute([':id' => $targetId]);

        if (!$check->fetch()) {
            respond(404, ['error' => 'User not found']);
        }

        // Delete the user's contacts first
        $stmt = $db->prepare(
            'DELETE FROM Contacts
             WHERE UserID = :id'
        );

        $stmt->execute([':id' => $targetId]);

        // Then delete the user
        $stmt = $db->prepare(
            'DELETE FROM Users
             WHERE ID = :id'
        );

        $stmt->execute([':id' => $targetId]);

        respond(200, [
            'message' => 'User deleted',
            'error'   => ''
        ]);
    }

    respond(405, ['error' => 'Method not allowed']);
}

switch ($method) {

    // ── GET: search, list, or single contact ──────────────────
    case 'GET':
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $search = isset($_GET['q'])  ? trim($_GET['q'])  : (isset($_GET['search']) ? trim($_GET['search']) : null);

        // Single contact by ID
        if ($id) {
            $stmt = $db->prepare(
                'SELECT
                    ID as id,
                    FirstName as first_name,
                    LastName as last_name,
                    Email as email,
                    PhoneNumber as phone_number,
                    UserID as user_id
                 FROM Contacts
                 WHERE ID = :id AND UserID = :uid
                 LIMIT 1'
            );

            $stmt->execute([':id' => $id, ':uid' => $userId]);
            $contact = $stmt->fetch();

            if (!$contact) {
                respond(404, ['error' => 'Contact not found']);
            }

            respond(200, $contact);
        }

        // Search contacts (partial match)
        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';

            $stmt = $db->prepare(
                'SELECT
                    ID as id,
                    FirstName as first_name,
                    LastName as last_name,
                    Email as email,
                    PhoneNumber as phone_number
                 FROM Contacts
                 WHERE UserID = :uid
                 AND (
                    FirstName LIKE :q1
                    OR LastName LIKE :q2
                    OR Email LIKE :q3
                    OR PhoneNumber LIKE :q4
                 )
                 ORDER BY FirstName, LastName'
            );

            $stmt->execute([
                ':uid' => $userId,
                ':q1'  => $like,
		':q2'  => $like,
		':q3'  => $like,
		':q4'  => $like
            ]);

            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                respond(200, ['contacts' => [], 'error' => 'No Records Found']);
            }

            respond(200, ['contacts' => $rows, 'error' => '']);
        }

        // List all contacts
        $stmt = $db->prepare(
            'SELECT
                ID as id,
                FirstName as first_name,
                LastName as last_name,
                Email as email,
                PhoneNumber as phone_number
             FROM Contacts
             WHERE UserID = :uid
             ORDER BY FirstName, LastName'
        );

        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            respond(200, ['contacts' => [], 'error' => 'No Records Found']);
        }

        respond(200, ['contacts' => $rows, 'error' => '']);
        break;

    // ── POST: create contact ───────────────────────────────────
    case 'POST':
        $body        = getRequestBody();
        $firstName   = clean($body['firstName'] ?? '');
        $lastName    = clean($body['lastName'] ?? '');
        $email       = clean($body['email'] ?? '');
        $phoneNumber = clean($body['phoneNumber'] ?? '');

        if (!$firstName || !$lastName || !$email || !$phoneNumber) {
            respond(400, ['error' => 'First name, last name, email, and phone number are required']);
        }

        $stmt = $db->prepare(
            'INSERT INTO Contacts
                (FirstName, LastName, Email, PhoneNumber, UserID)
             VALUES
                (:firstName, :lastName, :email, :phoneNumber, :uid)'
        );

        $stmt->execute([
            ':firstName'   => $firstName,
            ':lastName'    => $lastName,
            ':email'       => $email,
            ':phoneNumber' => $phoneNumber,
            ':uid'         => $userId
        ]);

        respond(201, [
            'message' => 'Contact created',
            'id'      => (int) $db->lastInsertId(),
            'error'   => ''
        ]);
        break;

    // ── PUT: update contact ─────────────────────────────────────
    case 'PUT':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if (!$id) {
            respond(400, ['error' => 'Contact ID is required — use ?id=']);
        }

        $check = $db->prepare(
            'SELECT ID
             FROM Contacts
             WHERE ID = :id AND UserID = :uid
             LIMIT 1'
        );

        $check->execute([
            ':id'  => $id,
            ':uid' => $userId
        ]);

        if (!$check->fetch()) {
            respond(404, ['error' => 'Contact not found']);
        }

        $body        = getRequestBody();
        $firstName   = clean($body['firstName'] ?? '');
        $lastName    = clean($body['lastName'] ?? '');
        $email       = clean($body['email'] ?? '');
        $phoneNumber = clean($body['phoneNumber'] ?? '');

        if (!$firstName || !$lastName || !$email || !$phoneNumber) {
            respond(400, ['error' => 'First name, last name, email, and phone number are required']);
        }

        $stmt = $db->prepare(
            'UPDATE Contacts
             SET FirstName = :firstName,
                 LastName = :lastName,
                 Email = :email,
                 PhoneNumber = :phoneNumber
             WHERE ID = :id AND UserID = :uid'
        );

        $stmt->execute([
            ':firstName'   => $firstName,
            ':lastName'    => $lastName,
            ':email'       => $email,
            ':phoneNumber' => $phoneNumber,
            ':id'          => $id,
            ':uid'         => $userId
        ]);

        respond(200, ['message' => 'Contact updated', 'error' => '']);
        break;

    // ── DELETE: delete contact ──────────────────────────────────
    case 'DELETE':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $stmt = $db->prepare(
                'DELETE FROM Contacts
                 WHERE ID = :id AND UserID = :uid'
            );

            $stmt->execute([
                ':id'  => $id,
                ':uid' => $userId
            ]);
        } else {
            respond(400, ['error' => 'Contact ID is required — use ?id=']);
        }

        if ($stmt->rowCount() === 0) {
            respond(404, ['error' => 'Contact not found']);
        }

        respond(200, ['message' => 'Contact deleted', 'error' => '']);
        break;

    default:
        respond(405, ['error' => 'Method not allowed']);
}
?>
