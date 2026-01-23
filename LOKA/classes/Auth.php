<?php
/**
 * LOKA - Authentication Class
 * 
 * Handles user authentication with rate limiting and security logging
 */

class Auth
{
    private Database $db;
    private Security $security;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->security = Security::getInstance();
    }

    /**
     * Check if login is rate limited
     */
    public function isLoginRateLimited(string $email): bool
    {
        // Check by email
        if ($this->security->isRateLimited('login', $email, RATE_LIMIT_LOGIN_ATTEMPTS, RATE_LIMIT_LOGIN_WINDOW)) {
            return true;
        }
        
        // Also check by IP
        $ip = $this->security->getClientIp();
        if ($this->security->isRateLimited('login_ip', $ip, RATE_LIMIT_LOGIN_ATTEMPTS * 2, RATE_LIMIT_LOGIN_WINDOW)) {
            return true;
        }
        
        return false;
    }

    /**
     * Get remaining lockout time
     */
    public function getLockoutTime(string $email): int
    {
        $emailLockout = $this->security->getLockoutRemaining('login', $email, RATE_LIMIT_LOGIN_WINDOW);
        $ipLockout = $this->security->getLockoutRemaining('login_ip', $this->security->getClientIp(), RATE_LIMIT_LOGIN_WINDOW);
        
        return max($emailLockout, $ipLockout);
    }

    /**
     * Attempt login with email and password
     */
    public function attempt(string $email, string $password, bool $remember = false): array
    {
        // Check rate limiting
        if ($this->isLoginRateLimited($email)) {
            $remaining = $this->getLockoutTime($email);
            $minutes = ceil($remaining / 60);
            
            if (LOG_RATE_LIMIT_HITS) {
                $this->security->logSecurityEvent('login_rate_limited', "Email: $email", null);
            }
            
            return [
                'success' => false,
                'error' => "Too many failed attempts. Please try again in $minutes minute(s).",
                'locked' => true
            ];
        }
        
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL",
            [$email]
        );

        // Check if user exists
        if (!$user) {
            $this->recordFailedLogin($email);
            return ['success' => false, 'error' => 'Invalid email or password'];
        }
        
        // Check if account is locked
        if ($user->locked_until && strtotime($user->locked_until) > time()) {
            $remaining = ceil((strtotime($user->locked_until) - time()) / 60);
            return [
                'success' => false,
                'error' => "Account locked. Try again in $remaining minute(s).",
                'locked' => true
            ];
        }
        
        // Check if account is active
        if ($user->status !== USER_ACTIVE) {
            return ['success' => false, 'error' => 'Your account is not active. Contact administrator.'];
        }
        
        // Verify password
        if (!password_verify($password, $user->password)) {
            $this->recordFailedLogin($email, $user->id);
            return ['success' => false, 'error' => 'Invalid email or password'];
        }

        // Success - clear rate limits and login
        $this->security->clearRateLimits('login', $email);
        $this->clearFailedAttempts($user->id);
        $this->login($user, $remember);
        
        if (LOG_SUCCESSFUL_LOGINS) {
            $this->security->logSecurityEvent('login_success', "Email: $email", $user->id);
        }
        
        return ['success' => true, 'user' => $user];
    }

    /**
     * Record failed login attempt
     */
    private function recordFailedLogin(string $email, ?int $userId = null): void
    {
        // Record in rate limits
        $this->security->recordAttempt('login', $email);
        $this->security->recordAttempt('login_ip', $this->security->getClientIp());
        
        // Log the event
        if (LOG_FAILED_LOGINS) {
            $this->security->logSecurityEvent('login_failed', "Email: $email", $userId);
        }
        
        // Update user's failed attempt counter if user exists
        if ($userId) {
            $this->db->query(
                "UPDATE users SET 
                    failed_login_attempts = failed_login_attempts + 1,
                    last_failed_login = NOW()
                 WHERE id = ?",
                [$userId]
            );
            
            // Check if account should be locked
            $user = $this->db->fetch("SELECT failed_login_attempts FROM users WHERE id = ?", [$userId]);
            if ($user && $user->failed_login_attempts >= RATE_LIMIT_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + RATE_LIMIT_LOGIN_LOCKOUT);
                $this->db->update('users', ['locked_until' => $lockUntil], 'id = ?', [$userId]);
                
                $this->security->logSecurityEvent('account_locked', "Email: $email, locked until: $lockUntil", $userId);
            }
        }
    }

    /**
     * Clear failed login attempts
     */
    private function clearFailedAttempts(int $userId): void
    {
        $this->db->update('users', [
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_failed_login' => null
        ], 'id = ?', [$userId]);
    }

    /**
     * Login user (create session)
     */
    public function login(object $user, bool $remember = false): void
    {
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        // Set session data
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_department_id'] = $user->department_id;
        $_SESSION['user'] = $user;
        $_SESSION['logged_in_at'] = time();
        
        // Store fingerprint immediately after login to prevent validation issues
        $this->security->storeFingerprint();
        $_SESSION['_created'] = time();
        $_SESSION['_absolute_start'] = time();
        $_SESSION['_last_activity'] = time();

        // Update last login
        $this->db->update('users', ['last_login_at' => date(DATETIME_FORMAT)], 'id = ?', [$user->id]);

        // Handle remember me
        if ($remember) {
            $this->setRememberToken($user->id);
        }

        // Audit log
        auditLog('login', 'user', $user->id);
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $userId = userId();

        // Clear remember token
        $this->clearRememberToken();

        // Audit log before destroying session
        if ($userId) {
            auditLog('logout', 'user', $userId);
        }

        // Destroy session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Set remember me token
     */
    private function setRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expires = date(DATETIME_FORMAT, strtotime('+' . REMEMBER_ME_DAYS . ' days'));

        // Delete old tokens for this user
        $this->db->delete('remember_tokens', 'user_id = ?', [$userId]);

        // Insert new token
        $this->db->insert('remember_tokens', [
            'user_id' => $userId,
            'selector' => $selector,
            'hashed_token' => $hashedToken,
            'expires' => $expires,
            'created_at' => date(DATETIME_FORMAT)
        ]);

        // Set secure cookie with proper options
        $cookieValue = $selector . ':' . $token;
        setcookie(
            'remember_token',
            $cookieValue,
            [
                'expires' => time() + (REMEMBER_ME_DAYS * 24 * 60 * 60),
                'path' => COOKIE_PATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => COOKIE_SECURE,
                'httponly' => COOKIE_HTTPONLY,
                'samesite' => COOKIE_SAMESITE
            ]
        );
    }

    /**
     * Check and process remember me token
     */
    public function checkRememberMe(): bool
    {
        if (isLoggedIn()) {
            return true;
        }

        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }

        $parts = explode(':', $_COOKIE['remember_token']);
        if (count($parts) !== 2) {
            $this->clearRememberToken();
            return false;
        }

        [$selector, $token] = $parts;

        $record = $this->db->fetch(
            "SELECT * FROM remember_tokens WHERE selector = ? AND expires > NOW()",
            [$selector]
        );

        if (!$record) {
            $this->clearRememberToken();
            return false;
        }

        if (!hash_equals($record->hashed_token, hash('sha256', $token))) {
            $this->clearRememberToken();
            return false;
        }

        // Get user
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE id = ? AND status = ? AND deleted_at IS NULL",
            [$record->user_id, USER_ACTIVE]
        );

        if (!$user) {
            $this->clearRememberToken();
            return false;
        }

        // Login user
        $this->login($user, true);
        return true;
    }

    /**
     * Clear remember me token
     */
    private function clearRememberToken(): void
    {
        if (isset($_COOKIE['remember_token'])) {
            $parts = explode(':', $_COOKIE['remember_token']);
            if (count($parts) === 2) {
                $this->db->delete('remember_tokens', 'selector = ?', [$parts[0]]);
            }
        }

        // Clear cookie with proper options
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => COOKIE_PATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => COOKIE_SECURE,
            'httponly' => COOKIE_HTTPONLY,
            'samesite' => COOKIE_SAMESITE
        ]);
    }

    /**
     * Get user by ID
     */
    public function getUser(int $id): ?object
    {
        return $this->db->fetch(
            "SELECT u.*, d.name as department_name 
             FROM users u 
             LEFT JOIN departments d ON u.department_id = d.id 
             WHERE u.id = ? AND u.deleted_at IS NULL",
            [$id]
        );
    }

    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?object
    {
        return $this->db->fetch(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL",
            [$email]
        );
    }

    /**
     * Hash password
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Update password
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $hash = $this->hashPassword($newPassword);
        $result = $this->db->update('users', ['password' => $hash], 'id = ?', [$userId]);
        
        if ($result) {
            auditLog('password_changed', 'user', $userId);
        }
        
        return $result > 0;
    }
}
