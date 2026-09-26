<?php
require_once __DIR__ . '/response_helper.php';

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        fail('Please log in to continue.', 401);
    }
}

function require_role(string $role): void
{
    require_login();
    if ($_SESSION['role'] !== $role) {
        fail('You are not authorized to perform this action.', 403);
    }
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function log_activity(PDO $pdo, ?int $userId, string $action, string $details = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $action, $details]);
}
