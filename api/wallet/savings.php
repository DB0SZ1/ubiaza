<?php
require_once '../config.php';
require_once '../db.php';
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

header('Content-Type: application/json');
session_start();

// Initialize logger
$logger = new Logger('savings');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'savings.log', Logger::INFO));

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    $logger->warning("Unauthorized savings attempt");
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    // SECTION: Create Savings Plan
    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);
        $amount = floatval($data['amount'] ?? 0);
        $target_amount = floatval($data['target_amount'] ?? 0);
        $frequency = $data['frequency'] ?? '';

        if ($amount <= 0 || $target_amount <= 0 || !in_array($frequency, ['daily', 'weekly', 'monthly'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            $logger->warning("Invalid savings input for user ID: " . $_SESSION['user_id']);
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("INSERT INTO savings (user_id, amount, target_amount, frequency, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("idds", $_SESSION['user_id'], $amount, $target_amount, $frequency);
        $stmt->execute();
        $logger->info("Savings plan created by user ID: " . $_SESSION['user_id']);
        echo json_encode(['message' => 'Savings plan created']);
        $db->close();
        break;

    // SECTION: View Savings Plans
    case 'view':
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id, amount, target_amount, frequency, status, created_at FROM savings WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $savings = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['savings' => $savings]);
        $db->close();
        break;

    // SECTION: Contribute to Savings
    case 'contribute':
        $data = json_decode(file_get_contents('php://input'), true);
        $savings_id = intval($data['savings_id'] ?? 0);
        $amount = floatval($data['amount'] ?? 0);

        if ($savings_id <= 0 || $amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            $logger->warning("Invalid contribution input for user ID: " . $_SESSION['user_id']);
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
            $logger->warning("Insufficient balance for contribution by user ID: " . $_SESSION['user_id']);
            $db->close();
            exit;
        }

        $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
        $stmt->bind_param("di", $amount, $_SESSION['user_id']);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE savings SET amount = amount + ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("dii", $amount, $savings_id, $_SESSION['user_id']);
        $stmt->execute();

        $stmt = $conn->prepare("SELECT amount, target_amount FROM savings WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $savings_id, $_SESSION['user_id']);
        $stmt->execute();
        $savings = $stmt->get_result()->fetch_assoc();

        if ($savings['amount'] >= $savings['target_amount']) {
            $stmt = $conn->prepare("UPDATE savings SET status = 'completed' WHERE id = ?");
            $stmt->bind_param("i", $savings_id);
            $stmt->execute();
        }

        $reference = 'SAV_' . time() . '_' . bin2hex(random_bytes(8));
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, status, reference) VALUES (?, 'deposit', ?, 'completed', ?)");
        $stmt->bind_param("ids", $_SESSION['user_id'], $amount, $reference);
        $stmt->execute();

        $conn->commit();
        $logger->info("Contribution of $amount to savings ID $savings_id by user ID: " . $_SESSION['user_id']);
        echo json_encode(['message' => 'Contribution successful']);
        $db->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        $logger->warning("Invalid savings action: $action");
        exit;
}
?>