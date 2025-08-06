<?php
require_once '../config.php';
require_once '../db.php';
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

header('Content-Type: application/json');
session_start();

// Initialize logger
$logger = new Logger('cards');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'cards.log', Logger::INFO));

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    $logger->warning("Unauthorized card operation attempt");
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    // SECTION: Add Card
    case 'add':
        $data = json_decode(file_get_contents('php://input'), true);
        $card_number = filter_var($data['card_number'] ?? '', FILTER_SANITIZE_STRING);
        $card_type = filter_var($data['card_type'] ?? '', FILTER_SANITIZE_STRING);
        $expiry_date = $data['expiry_date'] ?? ''; // Format: YYYY-MM-DD
        $cvv = filter_var($data['cvv'] ?? '', FILTER_SANITIZE_STRING);

        if (empty($card_number) || !in_array($card_type, ['visa', 'mastercard', 'verve']) || !DateTime::createFromFormat('Y-m-d', $expiry_date) || !preg_match('/^\d{3,4}$/', $cvv)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            $logger->warning("Invalid card input for user ID: " . $_SESSION['user_id']);
            exit;
        }

        // Validate card with payment gateway (placeholder)
        $card_validation = validateCard($card_number, $cvv);
        if (!$card_validation['success']) {
            http_response_code(400);
            echo json_encode(['error' => 'Card validation failed']);
            $logger->error("Card validation failed for user ID: " . $_SESSION['user_id']);
            exit;
        }

        // Encrypt card number (simplified; use proper encryption in production)
        $encrypted_card = base64_encode($card_number); // Replace with OpenSSL encryption

        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("INSERT INTO cards (user_id, card_number, card_type, expiry_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $_SESSION['user_id'], $encrypted_card, $card_type, $expiry_date);
        $stmt->execute();
        $logger->info("Card added for user ID: " . $_SESSION['user_id']);
        echo json_encode(['message' => 'Card added successfully']);
        $db->close();
        break;

    // SECTION: View Cards
    case 'view':
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id, card_type, SUBSTRING(card_number, -4) AS last_four, expiry_date FROM cards WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $cards = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['cards' => $cards]);
        $db->close();
        break;

    // SECTION: Delete Card
    case 'delete':
        $data = json_decode(file_get_contents('php://input'), true);
        $card_id = intval($data['card_id'] ?? 0);

        if ($card_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid card ID']);
            $logger->warning("Invalid card ID for deletion by user ID: " . $_SESSION['user_id']);
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("DELETE FROM cards WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $card_id, $_SESSION['user_id']);
        $stmt->execute();
        $logger->info("Card ID $card_id deleted by user ID: " . $_SESSION['user_id']);
        echo json_encode(['message' => 'Card deleted successfully']);
        $db->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        $logger->warning("Invalid cards action: $action");
        exit;
}

// Helper function to validate card (e.g., via Flutterwave/Paystack)
function validateCard($card_number, $cvv) {
    // Replace with actual payment gateway integration
    return ['success' => true]; // Placeholder
}
?>