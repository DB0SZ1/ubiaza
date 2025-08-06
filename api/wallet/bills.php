<?php
require_once '../config.php';
require_once '../db.php';
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

header('Content-Type: application/json');
session_start();

// Initialize logger
$logger = new Logger('bills');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'bills.log', Logger::INFO));

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    $logger->warning("Unauthorized bill payment attempt");
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    // SECTION: Pay Bill
    case 'pay':
        $data = json_decode(file_get_contents('php://input'), true);
        $type = filter_var($data['type'] ?? '', FILTER_SANITIZE_STRING);
        $amount = floatval($data['amount'] ?? 0);
        $reference = filter_var($data['reference'] ?? '', FILTER_SANITIZE_STRING);

        if (empty($type) || $amount <= 0 || empty($reference)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            $logger->warning("Invalid bill payment input for user ID: " . $_SESSION['user_id']);
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $wallet = $stmt->get_result()->fetch_assoc();

        if ($wallet['balance'] < $amount) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['error' => 'Insufficient balance']);
            $logger->warning("Insufficient balance for bill payment by user ID: " . $_SESSION['user_id']);
            $db->close();
            exit;
        }

        $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
        $stmt->bind_param("di", $amount, $_SESSION['user_id']);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO bills (user_id, type, amount, reference, status) VALUES (?, ?, ?, ?, 'paid')");
        $stmt->bind_param("isds", $_SESSION['user_id'], $type, $amount, $reference);
        $stmt->execute();

        $txn_reference = 'BIL_' . time() . '_' . bin2hex(random_bytes(8));
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, status, reference) VALUES (?, 'bill_payment', ?, 'completed', ?)");
        $stmt->bind_param("ids", $_SESSION['user_id'], $amount, $txn_reference);
        $stmt->execute();

        $conn->commit();
        $logger->info("Bill payment of $amount ($type) completed by user ID: " . $_SESSION['user_id']);
        echo json_encode(['message' => 'Bill paid successfully', 'reference' => $txn_reference]);
        $db->close();
        break;

    // SECTION: View Bills
    case 'view':
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id, type, amount, reference, status, created_at FROM bills WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $bills = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['bills' => $bills]);
        $db->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        $logger->warning("Invalid bills action: $action");
        exit;
}
?>