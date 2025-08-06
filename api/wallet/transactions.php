<?php
require_once '../config.php';
require_once '../db.php';
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

header('Content-Type: application/json');
session_start();

$logger = new Logger('transactions');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'transactions.log', Logger::INFO));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    $logger->warning("Unauthorized transaction attempt");
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'transfer':
        $data = json_decode(file_get_contents('php://input'), true);
        $recipient_id = $data['recipient_id'] ?? null;
        $recipient_account = $data['recipient_account'] ?? null;
        $recipient_bank = $data['recipient_bank'] ?? null;
        $amount = floatval($data['amount'] ?? 0);

        if ($amount <= 0 || (!$recipient_id && (!$recipient_account || !$recipient_bank))) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            $logger->warning("Invalid transfer input for user ID: " . $_SESSION['user_id']);
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $sender = $stmt->get_result()->fetch_assoc();

        if ($sender['balance'] < $amount) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['error' => 'Insufficient balance']);
            $logger->warning("Insufficient balance for user ID: " . $_SESSION['user_id']);
            $db->close();
            exit;
        }

        // Verify recipient account with Flutterwave
        if (!$recipient_id && $recipient_account && $recipient_bank) {
            $account_verification = verifyAccount($recipient_account, $recipient_bank);
            if (!$account_verification['success']) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Account verification failed']);
                $logger->error("Account verification failed for $recipient_account at $recipient_bank");
                $db->close();
                exit;
            }
        }

        $reference = 'TXN_' . time() . '_' . bin2hex(random_bytes(8));
        if ($recipient_id) {
            $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
            $stmt->bind_param("di", $amount, $recipient_id);
            $stmt->execute();
        } else {
            // Initiate external transfer via Flutterwave
            $transfer_response = initiateTransfer($recipient_account, $recipient_bank, $amount, $reference);
            if (!$transfer_response['success']) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Transfer failed']);
                $logger->error("Transfer failed for $recipient_account at $recipient_bank");
                $db->close();
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
        $stmt->bind_param("di", $amount, $_SESSION['user_id']);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, recipient_id, recipient_account, recipient_bank, status, reference) VALUES (?, 'transfer', ?, ?, ?, ?, 'completed', ?)");
        $stmt->bind_param("idissis", $_SESSION['user_id'], $amount, $recipient_id, $recipient_account, $recipient_bank, $reference);
        $stmt->execute();

        $conn->commit();
        $logger->info("Transfer of $amount completed by user ID: " . $_SESSION['user_id']);
        echo json_encode(['message' => 'Transfer successful', 'reference' => $reference]);
        $db->close();
        break;

    case 'deposit':
        // ... (unchanged from your version)
        break;

    case 'withdraw':
        // ... (unchanged from your version)
        break;

    case 'get_transactions':
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? OR recipient_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['transactions' => $transactions]);
        $db->close();
        break;

    case 'get_user_data':
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT u.first_name, u.last_name, u.email, u.phone, w.balance FROM users u JOIN wallets w ON u.id = w.user_id WHERE u.id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            echo json_encode(['user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            $logger->warning("User data not found for ID: " . $_SESSION['user_id']);
        }
        $db->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        $logger->warning("Invalid transaction action: $action");
        exit;
}

function verifyAccount($account_number, $bank_code) {
    $url = 'https://api.flutterwave.com/v3/accounts/resolve';
    $data = [
        'account_number' => $account_number,
        'bank_code' => $bank_code
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . PAYMENT_GATEWAY_KEY,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return [
        'success' => isset($result['status']) && $result['status'] === 'success',
        'account_name' => $result['data']['account_name'] ?? null
    ];
}

function initiateTransfer($account_number, $bank_code, $amount, $reference) {
    $url = 'https://api.flutterwave.com/v3/transfers';
    $data = [
        'account_number' => $account_number,
        'bank_code' => $bank_code,
        'amount' => $amount,
        'currency' => 'NGN',
        'reference' => $reference
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . PAYMENT_GATEWAY_KEY,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return [
        'success' => isset($result['status']) && $result['status'] === 'success'
    ];
}
?>