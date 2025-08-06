<?php
class SessionManager {
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_lifetime' => 86400, // 1 day
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_httponly' => true,
                'use_strict_mode' => true
            ]);
            
            // Regenerate session ID periodically to prevent fixation
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }

    public static function regenerateSession() {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    public static function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function refreshCsrfToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    public static function checkTimeout($timeout = 1800) {
        if (isset($_SESSION['registration_start_time']) && (time() - $_SESSION['registration_start_time']) > $timeout) {
            $_SESSION['registration_data'] = null;
            $_SESSION['registration_start_time'] = null;
            return false;
        }
        return true;
    }
}
?>