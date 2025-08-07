<?php
require_once 'api/config.php';
require_once 'db.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('webhook');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'webhook.log', Logger::INFO));

// Verify webhook secret
$signature = $_SERVER['HTTP_X_NOMBA_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');
$expected_signature = hash_hmac('sha256', $payload, NOMBA_WEBHOOK_SECRET);
if (!hash_equals($expected_signature, $signature)) {
    $logger->error("Invalid webhook signature");
    http_response_code(401);
    exit;
}

$data = json_decode($payload, true);
if ($data['event'] === 'payment_success') {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Update account balance
    $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ?, available_balance = available_balance + ? WHERE account_number = ?");
    $stmt->bind_param("dds", $data['amount'], $data['amount'], $data['accountNumber']);
    if ($stmt->execute()) {
        $logger->info("Balance updated for account: " . $data['accountNumber'] . ", Amount: " . $data['amount']);
    } else {
        $logger->error("Failed to update balance for account: " . $data['accountNumber'] . ": " . $conn->error);
    }

    // Log transaction
    $stmt = $conn->prepare("SELECT user_id FROM accounts WHERE account_number = ?");
    $stmt->bind_param("s", $data['accountNumber']);
    $stmt->execute();
    $user_id = $stmt->get_result()->fetch_assoc()['user_id'];

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, recipient_account, status, created_at) VALUES (?, 'deposit', ?, ?, 'completed', NOW())");
    $stmt->bind_param("ids", $user_id, $data['amount'], $data['accountNumber']);
    if ($stmt->execute()) {
        $logger->info("Transaction logged for user ID: $user_id, Amount: " . $data['amount']);
    } else {
        $logger->error("Failed to log transaction for user ID: $user_id: " . $conn->error);
    }

    // Notify user
    $stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $subject = "Ubiaza Account Funded";
    $message = "Dear {$user['first_name']},<br>Your account ({$data['accountNumber']}) has been credited with ₦" . number_format($data['amount'], 2) . ".";
    send_email_notification($user['email'], $subject, $message);

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Account Funded', ?)");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();

    $db->close();
    http_response_code(200);
}
?>