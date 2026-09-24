<?php
// ============================================================
//  api/contacts.php — Contact Operations (Authenticated)
//  POST   /api/contacts.php?action=add         — Add contact
//  GET    /api/contacts.php?action=search&q=term — Search contacts
//  GET    /api/contacts.php?action=get&id=1   — Get single contact
//  PUT    /api/contacts.php?action=update&id=1 — Update contact
//  DELETE /api/contacts.php?action=delete&id=1 — Delete contact
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

// Get authenticated user's role (for admin access to other users' contacts)
$userStmt = $db->prepare('SELECT Role, Enabled FROM Users WHERE ID = :id');
$userStmt->execute([':id' => $userId]);
$authUser = $userStmt->fetch();

if (!$authUser || !$authUser['Enabled']) {
    respond(401, ['error' => 'User not found or disabled']);
}

$isAdmin = ($authUser['Role'] === 'admin');

// ============================================================
// ADD CONTACT
// ============================================================
if ($method === 'POST' && $action === 'add') {
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

// ============================================================
// SEARCH CONTACTS
// ============================================================
if ($method === 'GET' && $action === 'search') {
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

// ============================================================
// GET SINGLE CONTACT
// ============================================================
if ($method === 'GET' && $action === 'get') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if (!$id) {
        respond(400, ['error' => 'Contact ID (id parameter) is required']);
    }

    if ($isAdmin) {
        // Admin can get any contact
        $stmt = $db->prepare('SELECT ID as id, UserID as userId, firstName, lastName, email, phoneNumber FROM Contacts WHERE ID = :id');
        $stmt->execute([':id' => $id]);
    } else {
        // User can only get their own contacts
        $stmt = $db->prepare('SELECT ID as id, firstName, lastName, email, phoneNumber FROM Contacts WHERE ID = :id AND UserID = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    $contact = $stmt->fetch();
    if (!$contact) {
        respond(404, ['error' => 'Contact not found']);
    }

    respond(200, $contact);
}

// ============================================================
// UPDATE CONTACT
// ============================================================
if ($method === 'PUT' && $action === 'update') {
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

// ============================================================
// DELETE CONTACT
// ============================================================
if ($method === 'DELETE' && $action === 'delete') {
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

// Invalid action
respond(400, ['error' => 'Invalid action']);
?>
