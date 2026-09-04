<?php
// core/SecuritySession.php
/**
 * SecuritySession handles OWASP-compliant session lifecycle, 
 * cookie security attributes, session rotation, and inactivity timeout.
 */
class SecuritySession {
    const INACTIVITY_TIMEOUT = 900; // 15 minutes = 900 seconds

    /**
     * Start secure session with HttpOnly, SameSite, and optional Secure flag.
     */
    public static function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

            // Set secure cookie parameters
            session_set_cookie_params([
                'lifetime' => 0,              // Session cookie until browser close
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,       // True if HTTPS
                'httponly' => true,           // Prevent JavaScript document.cookie access (Mitigates XSS)
                'samesite' => 'Strict'        // Mitigate Cross-Site Request Forgery (CSRF)
            ]);

            session_start();
        }

        // Validate inactivity timeout
        self::checkInactivityTimeout();
    }

    /**
     * Regenerates Session ID upon successful login to prevent Session Fixation.
     * Also registers session authentication data and initial timestamp.
     */
    public static function loginSuccess($userData) {
        // Regenerate session ID and delete old session storage
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userData['user_id'];
        $_SESSION['role']    = $userData['role'];
        $_SESSION['user']    = $userData;
        $_SESSION['last_activity'] = time();

        // Generate CSRF Token for form and AJAX state changes
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Check if session has expired due to user inactivity.
     */
    private static function checkInactivityTimeout() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
            if ((time() - $_SESSION['last_activity']) > self::INACTIVITY_TIMEOUT) {
                self::destroySession();
                $loginUrl = '../Login/login.php?error=' . urlencode('Session expired due to inactivity. Please log in again.');
                header('Location: ' . $loginUrl);
                exit;
            }
        }
        // Update activity timestamp if user is active
        if (isset($_SESSION['user_id'])) {
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Completely destroys session data and cookie for safe logout.
     */
    public static function destroySession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        // Invalidate session cookie on client browser
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Guard to enforce Admin role authentication.
     */
    public static function checkAdminAuth() {
        self::startSecureSession();
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            header('HTTP/1.1 401 Unauthorized');
            header('Location: ../Login/login.php?error=' . urlencode('Unauthorized access: Admin privilege required.'));
            exit;
        }
    }

    /**
     * Validate CSRF Token
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}
