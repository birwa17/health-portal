<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/response_helper.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo    = getDB();
$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($action) {

    // ---------------------------------------------------------------
    case 'register':
        $name     = required($input, 'name');
        $email    = strtolower(required($input, 'email'));
        $password = required($input, 'password');
        $phone    = trim((string)($input['phone'] ?? ''));
        $dob      = trim((string)($input['dob'] ?? ''));
        $gender   = trim((string)($input['gender'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Please enter a valid email address.', 422);
        }
        if (strlen($password) < 6) {
            fail('Password must be at least 6 characters long.', 422);
        }

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            fail('An account with this email already exists.', 409);
        }

        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, "patient", ?)');
            $stmt->execute([$name, $email, $hash, $phone]);
            $userId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO patient_profiles (user_id, dob, gender) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $dob ?: null, $gender ?: null]);

            log_activity($pdo, $userId, 'register', 'New patient account created');
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            fail('Registration failed. Please try again.', 500);
        }

        success('Registration successful. You can now log in.');
        break;

    // ---------------------------------------------------------------
    case 'login':
        $email    = strtolower(required($input, 'email'));
        $password = required($input, 'password');

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            fail('Invalid email or password.', 401);
        }
        if ($user['status'] === 'suspended') {
            fail('Your account has been suspended. Contact the admin.', 403);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];

        log_activity($pdo, $user['id'], 'login', 'User logged in');

        success('Login successful.', [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);
        break;

    // ---------------------------------------------------------------
    case 'logout':
        if (!empty($_SESSION['user_id'])) {
            log_activity($pdo, $_SESSION['user_id'], 'logout', 'User logged out');
        }
        $_SESSION = [];
        session_destroy();
        success('Logged out successfully.');
        break;

    // ---------------------------------------------------------------
    case 'me':
        require_login();
        success('Session active.', [
            'id'   => current_user_id(),
            'name' => $_SESSION['name'],
            'role' => current_role(),
        ]);
        break;

    // ---------------------------------------------------------------
    case 'change_password':
        require_login();
        $current = required($input, 'current_password');
        $new     = required($input, 'new_password');

        if (strlen($new) < 6) {
            fail('New password must be at least 6 characters long.', 422);
        }

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([current_user_id()]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password'])) {
            fail('Current password is incorrect.', 401);
        }

        $hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, current_user_id()]);

        log_activity($pdo, current_user_id(), 'change_password', 'Password changed');
        success('Password updated successfully.');
        break;

    // ---------------------------------------------------------------
    default:
        fail('Unknown action.', 404);
}
