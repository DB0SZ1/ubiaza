<?php
require_once '../config.php';
require_once '../db.php';
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

header('Content-Type: application/json');
session_start();

// Initialize logger
$logger = new Logger('loans');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'loans.log', Logger::INFO));

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    $logger->warning("Unauthorized loan operation attempt");
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    // SECTION: Apply for Loan
    case 'apply':
        $data = json_decode(file_get_contents('php://input'), true);
        $amount = floatval($data['amount'] ?? 0);

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid amount']);
            $logger->warning("Invalid loan amount for user ID: " . $_SESSION['user_id']);
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT bvn_verified, nin_verified FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user['bvn_verified'] || !$user['nin_verified']) {
            http_response_code(403);
            echo json_encode(['error' => 'BVN and NIN verification required']);
            $logger->warning("Loan application denied due to unverified identity for user ID: " . $_SESSION['user_id']);
            $db->close();
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO loans (user_id, amount, interest_rate, status) VALUES (?, ?, 5.00, 'pending')");
        $stmt->bind_param("id", $_SESSION['user_id'], $amount);
        $stmt->execute();
        $logger->info("Loan application of $amount submitted by user ID: " . $_SESSION['user_id']);
        echo json_encode(['message' => 'Loan application submitted']);
        $db->close();
        break;

    // SECTION: View Loans
    case 'view':
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id, amount, interest_rate, status, created_at FROM loans WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $loans = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['loans' => $loans]);
        $db->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        $logger->warning("Invalid loans action: $action");
        exit;
}
?>