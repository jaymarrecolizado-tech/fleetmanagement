<?php
/**
 * LOKA - Email Queue Class
 * 
 * Handles queueing and processing of emails for background sending
 */

class EmailQueue
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Add email to queue
     */
    public function queue(
        string $toEmail,
        string $subject,
        string $body,
        ?string $toName = null,
        ?string $template = null,
        int $priority = 5,
        ?string $scheduledAt = null
    ): int {
        return $this->db->insert('email_queue', [
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $subject,
            'body' => $body,
            'template' => $template,
            'priority' => $priority,
            'scheduled_at' => $scheduledAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Queue email using template
     */
    public function queueTemplate(
        string $toEmail,
        string $templateKey,
        array $data = [],
        ?string $toName = null,
        int $priority = 5
    ): int {
        // Get template
        $templates = MAIL_TEMPLATES;
        if (!isset($templates[$templateKey])) {
            throw new Exception("Email template '$templateKey' not found");
        }
        
        $template = $templates[$templateKey];
        $subject = $template['subject'];
        
        // Build email body
        $body = $this->buildEmailBody($templateKey, $template, $data);
        
        return $this->queue($toEmail, $subject, $body, $toName, $templateKey, $priority);
    }
    
    /**
     * Build HTML email body from template
     */
    private function buildEmailBody(string $templateKey, array $template, array $data): string
    {
        $message = $data['message'] ?? $template['template'];
        $link = $data['link'] ?? null;
        $linkText = $data['link_text'] ?? 'View Details';
        
        // Build full URL - link already starts with /, so just append to SITE_URL
        $fullLink = $link ? (SITE_URL . $link) : null;
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($template['subject']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: #0d6efd; color: #fff; padding: 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .message { margin-bottom: 20px; }
                .btn { display: inline-block; padding: 12px 24px; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .btn:hover { background: #0b5ed7; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . APP_NAME . '</h1>
                </div>
                <div class="content">
                    <div class="message">' . nl2br(htmlspecialchars($message)) . '</div>';
        
        if ($fullLink) {
            $html .= '<p><a href="' . htmlspecialchars($fullLink) . '" class="btn">' . htmlspecialchars($linkText) . '</a></p>';
        }
        
        $html .= '
                </div>
                <div class="footer">
                    <p>This is an automated message from ' . APP_NAME . '</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Get pending emails for processing
     * Prioritizes recently created emails (within last 30 seconds) for faster delivery
     */
    public function getPending(int $limit = 10): array
    {
        // First, try to get emails created in the last 30 seconds (recent emails)
        $recentEmails = $this->db->fetchAll(
            "SELECT * FROM email_queue 
             WHERE status = 'pending' 
             AND attempts < max_attempts
             AND (scheduled_at IS NULL OR scheduled_at <= NOW())
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
             ORDER BY priority ASC, created_at ASC
             LIMIT ?",
            [$limit]
        );
        
        // If we got some recent emails, return them
        if (!empty($recentEmails)) {
            return $recentEmails;
        }
        
        // Otherwise, get any pending emails
        return $this->db->fetchAll(
            "SELECT * FROM email_queue 
             WHERE status = 'pending' 
             AND attempts < max_attempts
             AND (scheduled_at IS NULL OR scheduled_at <= NOW())
             ORDER BY priority ASC, created_at ASC
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * Mark email as processing
     */
    public function markProcessing(int $id): void
    {
        $this->db->update('email_queue', [
            'status' => 'processing',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
    }
    
    /**
     * Mark email as sent
     */
    public function markSent(int $id): void
    {
        $this->db->update('email_queue', [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
    }
    
    /**
     * Mark email as failed
     */
    public function markFailed(int $id, string $error): void
    {
        $email = $this->db->fetch("SELECT attempts, max_attempts FROM email_queue WHERE id = ?", [$id]);
        
        $newAttempts = ($email->attempts ?? 0) + 1;
        $status = $newAttempts >= ($email->max_attempts ?? 3) ? 'failed' : 'pending';
        
        $this->db->update('email_queue', [
            'status' => $status,
            'attempts' => $newAttempts,
            'error_message' => $error,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
    }
    
    /**
     * Process the email queue
     * Returns array with counts of sent, failed, skipped
     */
    public function process(int $batchSize = 10): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        
        try {
            $emails = $this->getPending($batchSize);
            
            if (empty($emails)) {
                return $results;
            }
            
            $mailer = new Mailer();
            
            foreach ($emails as $email) {
                $this->markProcessing($email->id);
                
                try {
                    $mailer = new Mailer();
                    
                    $sent = $mailer->send(
                        $email->to_email,
                        $email->subject,
                        $email->body,
                        $email->to_name
                    );
                    
                    if ($sent) {
                        $this->markSent($email->id);
                        $results['sent']++;
                    } else {
                        $errors = $mailer->getErrors();
                        $errorMsg = !empty($errors) ? implode(', ', $errors) : 'Send returned false';
                        $this->markFailed($email->id, $errorMsg);
                        $results['failed']++;
                        error_log("Email #{$email->id} failed to {$email->to_email}: {$errorMsg}");
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $this->markFailed($email->id, $errorMsg);
                    $results['failed']++;
                    error_log("Email #{$email->id} exception: {$errorMsg}");
                }
            }
            
            if ($results['sent'] > 0 || $results['failed'] > 0) {
                error_log("EmailQueue processed: {$results['sent']} sent, {$results['failed']} failed");
            }
        } catch (Exception $e) {
            error_log("EmailQueue::process() exception: " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        return [
            'pending' => $this->db->fetchColumn("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'"),
            'processing' => $this->db->fetchColumn("SELECT COUNT(*) FROM email_queue WHERE status = 'processing'"),
            'sent' => $this->db->fetchColumn("SELECT COUNT(*) FROM email_queue WHERE status = 'sent'"),
            'failed' => $this->db->fetchColumn("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'"),
        ];
    }
    
    /**
     * Clean old sent emails (older than X days)
     */
    public function cleanup(int $daysOld = 30): int
    {
        $result = $this->db->query(
            "DELETE FROM email_queue WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$daysOld]
        );
        return $result->rowCount();
    }
}
