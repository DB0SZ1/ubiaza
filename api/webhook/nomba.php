<?php
require_once '../db.php';
require_once '../config.php';
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

header('Content-Type: application/json');

$logger = new Logger('webhook');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'webhook.log', Logger::INFO));

// Verify webhook signature (Nomba-specific)
$signature = $_SERVER['HTTP_X_NOMBA_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');
$expected_signature = hash_hmac('sha256', $payload, NOMBA_WEBHOOK_SECRET);

if ($signature !== $expected_signature) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    $logger->warning("Invalid webhook signature");
    exit;
}

$data = json_decode($payload, true);
$event = $data['event'] ?? '';

$db = new Database();
$conn = $db->getConnection();
$conn->begin_transaction();

try {
    switch ($event) {
        case 'virtual_account.funded':
            $nomba_ref = $data['data']['accountRef'];
            $amount = $data['data']['amount'] / 100; // Convert kobo to NGN
            $reference = $data['data']['transactionReference'];

            $stmt = $conn->prepare("SELECT user_id FROM virtual_accounts WHERE nomba_reference = ?");
            $stmt->bind_param("s", $nomba_ref);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Virtual account not found']);
                $logger->warning("Virtual account not found: $nomba_ref");
                exit;
            }

            $stmt = $conn->prepare("UPDATE wallets SET available_balance = available_balance + ?, pending_balance = pending_balance - ? WHERE user_id = ?");
            $stmt->bind_param("ddi", $amount, $amount, $user['user_id']);
            $stmt->execute();

            $stmt = $conn->prepare("
                INSERT INTO transactions (user_id, type, amount, fee, balance_before, balance_after, reference, external_reference, status)
                VALUES (?, 'deposit', ?, 0, (SELECT available_balance FROM wallets WHERE user_id = ?), (SELECT available_balance FROM wallets WHERE user_id = ?), ?, ?, 'completed')
            ");
            $stmt->bind_param("idiiis", $user['user_id'], $amount, $user['user_id'], $user['user_id'], $reference, $reference);
            $stmt->execute();

            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, channel, priority)
                VALUES (?, 'virtual_account', 'Virtual Account Funded', ?, 'in_app', 'high')
            ");
            $message = "Your virtual account received ₦" . number_format($amount, 2) . ".";
            $stmt->bind_param("is", $user['user_id'], $message);
            $stmt->execute();

            $stmt = $conn->prepare("
                INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, user_agent)
                VALUES (?, 'virtual_account_funded', 'virtual_account', ?, ?, ?, ?)
            ");
            $details = json_encode(['amount' => $amount, 'reference' => $reference]);
            $stmt->bind_param("iisss", $user['user_id'], $user['user_id'], $details, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
            $stmt->execute();

            $logger->info("Virtual account funded: user_id={$user['user_id']}, amount=$amount, reference=$reference");
            break;

        case 'transfer.completed':
            $reference = $data['data']['merchantTxRef'];
            $status = $data['data']['status'] === 'SUCCESS' ? 'completed' : 'failed';

            $stmt = $conn->prepare("SELECT user_id, recipient_id FROM transactions WHERE reference = ? AND type IN ('transfer', 'external_bank')");
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            $txn = $stmt->get_result()->fetch_assoc();

            if (!$txn) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Transaction not found']);
                $logger->warning("Transaction not found for webhook: $reference");
                exit;
            }

            $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE reference = ?");
            $stmt->bind_param("ss", $status, $reference);
            $stmt->execute();

            if ($status === 'completed' && $txn['recipient_id']) {
                $stmt = $conn->prepare("UPDATE wallets SET available_balance = available_balance + (SELECT amount FROM transactions WHERE reference = ?), pending_balance = pending_balance - (SELECT amount FROM transactions WHERE reference = ?) WHERE user_id = ?");
                $stmt->bind_param("ssi", $reference, $reference, $txn['recipient_id']);
                $stmt->execute();
            }

            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, channel, priority)
                VALUES (?, 'transaction', 'Transfer Status', ?, 'in_app', 'high')
            ");
            $message = $status === 'completed' ? "Your transfer (Ref: $reference) was successful." : "Your transfer (Ref: $reference) failed.";
            $stmt->bind_param("is", $txn['user_id'], $message);
            $stmt->execute();

            $logger->info("Transfer $status: user_id={$txn['user_id']}, reference=$reference");
            break;

        default:
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid event']);
            $logger->warning("Invalid webhook event: $event");
            exit;
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed: ' . $e->getMessage()]);
    $logger->error("Webhook processing failed: " . $e->getMessage());
} finally {
    $db->close();
}
?>