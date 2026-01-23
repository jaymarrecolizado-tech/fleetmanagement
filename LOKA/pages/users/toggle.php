<?php
/**
 * LOKA - Toggle User Status
 */

requireRole(ROLE_ADMIN);

$userId = (int) get('id');
$user = db()->fetch("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);

if (!$user) redirectWith('/?page=users', 'danger', 'User not found.');

// Prevent self-deactivation
if ($user->id === userId()) {
    redirectWith('/?page=users', 'danger', 'You cannot deactivate your own account.');
}

$newStatus = $user->status === USER_ACTIVE ? USER_INACTIVE : USER_ACTIVE;
db()->update('users', ['status' => $newStatus, 'updated_at' => date(DATETIME_FORMAT)], 'id = ?', [$userId]);
auditLog('user_status_changed', 'user', $userId, ['status' => $user->status], ['status' => $newStatus]);

$message = $newStatus === USER_ACTIVE ? 'User activated successfully.' : 'User deactivated successfully.';
redirectWith('/?page=users', 'success', $message);
