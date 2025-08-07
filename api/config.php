<?php
// Site and database configuration
define('SITE_URL', 'http://localhost/ubiaza/');
define('DB_HOST', 'localhost');
define('DB_NAME', 'ubiaza_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Nomba API credentials (replacing Mono)
define('NOMBA_API_TOKEN', 'your_nomba_api_token_here');
define('NOMBA_ACCOUNT_ID', 'your_nomba_account_id_here');
define('NOMBA_WEBHOOK_SECRET', 'your_nomba_webhook_secret_here');

// SMTP configuration for notifications
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'dbsc2008@gmail.com');
define('SMTP_PASS', 'ckhq efzr fdqp bvnu');

// Directory paths
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('LOG_DIR', __DIR__ . '/logs/');
define('RATE_LIMIT_STORAGE', __DIR__ . '/cache/rate_limits/');

// CSRF token validation
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate limiting function
function check_rate_limit($action, $user_id, $limit, $window_seconds) {
    $cache_dir = RATE_LIMIT_STORAGE;
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $file = $cache_dir . "/{$action}_{$user_id}.json";
    $current_time = time();
    $window_start = $current_time - $window_seconds;

    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(file_get_contents($file), true) ?: [];
        $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
            return $timestamp >= $window_start;
        });
    }

    if (count($attempts) >= $limit) {
        return false;
    }

    $attempts[] = $current_time;
    file_put_contents($file, json_encode($attempts));
    return true;
}

// Send email notification using PHPMailer
function send_email_notification($to, $subject, $message) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'Ubiaza');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        $logger = new Monolog\Logger('email');
        $logger->pushHandler(new Monolog\Handler\StreamHandler(LOG_DIR . 'email.log', Monolog\Logger::ERROR));
        $logger->error("Failed to send email to $to: " . $mail->ErrorInfo);
        return false;
    }
}
?>