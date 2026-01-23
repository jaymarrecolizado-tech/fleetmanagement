<?php
/**
 * LOKA - Mark Notification as Read
 */

$notifId = (int) get('id');
$notif = db()->fetch("SELECT * FROM notifications WHERE id = ? AND user_id = ?", [$notifId, userId()]);

if ($notif) {
    db()->update('notifications', ['is_read' => 1], 'id = ?', [$notifId]);
    
    // Redirect to link if exists
    if ($notif->link) {
        redirect($notif->link);
    }
}

redirect('/?page=notifications');
