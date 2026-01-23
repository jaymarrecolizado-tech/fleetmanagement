<?php
/**
 * LOKA - Soft Delete Notification
 */

requireAuth();

$notifId = (int) get('id');
$notif = db()->fetch("SELECT * FROM notifications WHERE id = ? AND user_id = ?", [$notifId, userId()]);

if ($notif) {
    db()->update('notifications', ['deleted_at' => date(DATETIME_FORMAT)], 'id = ?', [$notifId]);

    redirectWith('/?page=notifications' . (get('view') === 'archive' ? '&view=archive' : ''), 'success', 'Notification deleted.');
}

redirect('/?page=notifications');
