<?php
/**
 * LOKA - Toggle Notification Archive Status
 */

requireAuth();

$notifId = (int) get('id');
$notif = db()->fetch("SELECT * FROM notifications WHERE id = ? AND user_id = ?", [$notifId, userId()]);

if ($notif) {
    $newStatus = $notif->is_archived ? 0 : 1;
    db()->update('notifications', ['is_archived' => $newStatus], 'id = ?', [$notifId]);
    
    $message = $newStatus ? 'Notification archived.' : 'Notification moved to inbox.';
    redirectWith('/?page=notifications' . (get('view') === 'archive' ? '&view=archive' : ''), 'success', $message);
}

redirect('/?page=notifications');
