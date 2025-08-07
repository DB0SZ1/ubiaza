<?php
require_once '../../db.php';
require_once '../../config.php';
require_once '../../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

header('Content-Type: application/json');
session_start();

$logger = new Logger('wallet');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'wallet.log', Logger::INFO));

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    $logger->warning("Unauthorized wallet access attempt");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_balance':
        $db = new Database();
        $conn = $db->getConnection();
        try {
            $stmt = $conn->prepare("SELECT available_balance, pending_balance FROM wallets WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $wallet = $result->fetch_assoc();
            if (!$wallet) {
                $stmt = $conn->prepare("INSERT INTO wallets (user_id, available_balance, pending_balance, currency) VALUES (?, 0.00, 0.00, 'NGN')");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $wallet = ['available_balance' => 0.00, 'pending_balance' => 0.00];
            }
            echo json_encode(['success' => true, 'wallet' => $wallet]);
            $logger->info("Wallet balance fetched for user ID: " . $_SESSION['user_id']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch balance: ' . $e->getMessage()]);
            $logger->error("Failed to fetch wallet balance for user ID: " . $_SESSION['user_id'] . ": " . $e->getMessage());
        } finally {
            $db->close();
        }
        break;

    case 'deposit':
        if (!check_rate_limit('deposit', $_SESSION['user_id'], 5, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            $logger->warning("Rate limit exceeded for deposit: user ID " . $_SESSION['user_id']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $amount = floatval($data['amount'] ?? 0);
        $payment_method = $data['payment_method'] ?? 'card';
        $csrf_token = $data['csrf_token'] ?? '';

        if ($amount < 100 || !in_array($payment_method, ['card', 'bank']) || !validate_csrf_token($csrf_token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid amount, payment method, or CSRF token']);
            $logger->warning("Invalid deposit input for user ID: " . $_SESSION['user_id'] . ", amount: $amount, method: $payment_method");
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if (!$user) {
                $conn->rollback();
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                $logger->warning("User not found for deposit: ID " . $_SESSION['user_id']);
                exit;
            }

            $reference = 'DEP_' . time() . '_' . bin2hex(random_bytes(8));
            $payment_response = initiateNombaCheckout($amount, $user['email'], $reference, $user['first_name'] . ' ' . $user['last_name'], $payment_method);
            if (!$payment_response['success']) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Payment initiation failed: ' . ($payment_response['message'] ?? 'Unknown error')]);
                $logger->error("Payment initiation failed for deposit, user ID: " . $_SESSION['user_id'] . ": " . ($payment_response['message'] ?? 'Unknown error'));
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, total_amount, balance_before, balance_after, reference, status, currency) VALUES (?, 'deposit', ?, ?, (SELECT available_balance FROM wallets WHERE user_id = ?), (SELECT available_balance FROM wallets WHERE user_id = ?), ?, 'pending', 'NGN')");
            $total_amount = $amount;
            $stmt->bind_param("iddiiis", $_SESSION['user_id'], $amount, $total_amount, $_SESSION['user_id'], $_SESSION['user_id'], $reference);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?");
            $stmt->bind_param("di", $amount, $_SESSION['user_id']);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, channel, priority) VALUES (?, 'transaction', ?, ?, 'in_app', 'medium')");
            $message = "Your deposit of ₦" . number_format($amount, 2) . " is being processed.";
            $title = "Deposit Initiated";
            $stmt->bind_param("iss", $_SESSION['user_id'], $title, $message);
            $stmt->execute();

            if ($user['email_notifications']) {
                send_email_notification($user['email'], $title, "<p>$message</p><p>Reference: $reference</p>");
            }

            $conn->commit();
            $logger->info("Deposit initiated for user ID: " . $_SESSION['user_id'] . ", amount: $amount, reference: $reference");
            echo json_encode(['success' => true, 'message' => 'Deposit initiated', 'reference' => $reference, 'checkout_url' => $payment_response['checkout_url']]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Deposit failed: ' . $e->getMessage()]);
            $logger->error("Deposit failed for user ID: " . $_SESSION['user_id'] . ": " . $e->getMessage());
        } finally {
            $db->close();
        }
        break;

    case 'transfer':
        if (!check_rate_limit('transfer', $_SESSION['user_id'], 5, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            $logger->warning("Rate limit exceeded for transfer: user ID " . $_SESSION['user_id']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $amount = floatval($data['amount'] ?? 0);
        $recipient_type = $data['recipient_type'] ?? 'ubiaza';
        $note = $data['note'] ?? '';
        $csrf_token = $data['csrf_token'] ?? '';
        $beneficiary_id = $data['beneficiary_id'] ?? null;
        $fee = $recipient_type === 'ubiaza' ? 10.00 : 50.00;
        $cashback_rate = $recipient_type === 'ubiaza' ? 0.00 : 0.00; // No cashback for transfers
        $total_amount = $amount + $fee;

        if ($amount < 100 || !in_array($recipient_type, ['ubiaza', 'bank']) || !validate_csrf_token($csrf_token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid amount, recipient type, or CSRF token']);
            $logger->warning("Invalid transfer input for user ID: " . $_SESSION['user_id'] . ", amount: $amount, recipient_type: $recipient_type");
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $conn->begin_transaction();

        try {
            // Verify sender wallet
            $stmt = $conn->prepare("SELECT available_balance, daily_spent, monthly_spent, daily_limit, monthly_limit FROM wallets WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $sender_wallet = $stmt->get_result()->fetch_assoc();
            if (!$sender_wallet || $sender_wallet['available_balance'] < $total_amount) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient balance']);
                $logger->warning("Insufficient balance for transfer, user ID: " . $_SESSION['user_id'] . ", required: $total_amount");
                exit;
            }

            // Check spending limits
            if ($sender_wallet['daily_spent'] + $total_amount > $sender_wallet['daily_limit'] ||
                $sender_wallet['monthly_spent'] + $total_amount > $sender_wallet['monthly_limit']) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Transfer exceeds daily or monthly limit']);
                $logger->warning("Transfer exceeds limits for user ID: " . $_SESSION['user_id'] . ", daily_spent: {$sender_wallet['daily_spent']}, monthly_spent: {$sender_wallet['monthly_spent']}");
                exit;
            }

            $reference = 'TXN_' . time() . '_' . bin2hex(random_bytes(8));
            $sender_balance_before = $sender_wallet['available_balance'];
            $sender_balance_after = $sender_balance_before - $total_amount;

            if ($recipient_type === 'ubiaza') {
                $recipient_email = filter_var($data['recipient_email'] ?? '', FILTER_SANITIZE_EMAIL);
                if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                    $conn->rollback();
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid recipient email']);
                    $logger->warning("Invalid recipient email for transfer, user ID: " . $_SESSION['user_id']);
                    exit;
                }

                $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE email = ?");
                $stmt->bind_param("s", $recipient_email);
                $stmt->execute();
                $recipient = $stmt->get_result()->fetch_assoc();
                if (!$recipient) {
                    $conn->rollback();
                    http_response_code(400);
                    echo json_encode(['error' => 'Recipient not found']);
                    $logger->warning("Recipient not found for transfer, email: $recipient_email, user ID: " . $_SESSION['user_id']);
                    exit;
                }
                $recipient_id = $recipient['id'];
                $recipient_name = $recipient['first_name'] . ' ' . $recipient['last_name'];

                $stmt = $conn->prepare("SELECT available_balance FROM wallets WHERE user_id = ? FOR UPDATE");
                $stmt->bind_param("i", $recipient_id);
                $stmt->execute();
                $recipient_wallet = $stmt->get_result()->fetch_assoc();
                if (!$recipient_wallet) {
                    $stmt = $conn->prepare("INSERT INTO wallets (user_id, available_balance, pending_balance, currency) VALUES (?, 0.00, 0.00, 'NGN')");
                    $stmt->bind_param("i", $recipient_id);
                    $stmt->execute();
                    $recipient_wallet = ['available_balance' => 0.00];
                }

                $transfer_response = initiateNombaTransfer($amount, $_SESSION['user_id'], $recipient_id, $reference);
                if (!$transfer_response['success']) {
                    $conn->rollback();
                    http_response_code(400);
                    echo json_encode(['error' => 'Transfer initiation failed: ' . ($transfer_response['message'] ?? 'Unknown error')]);
                    $logger->error("Transfer initiation failed for user ID: " . $_SESSION['user_id'] . " to recipient ID: $recipient_id");
                    exit;
                }

                $stmt = $conn->prepare("UPDATE wallets SET available_balance = ?, daily_spent = daily_spent + ?, monthly_spent = monthly_spent + ? WHERE user_id = ?");
                $stmt->bind_param("dddi", $sender_balance_after, $total_amount, $total_amount, $_SESSION['user_id']);
                $stmt->execute();

                $recipient_balance_before = $recipient_wallet['available_balance'];
                $recipient_balance_after = $recipient_balance_before;
                $stmt = $conn->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?");
                $stmt->bind_param("di", $amount, $recipient_id);
                $stmt->execute();

                $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, fee, total_amount, balance_before, balance_after, reference, recipient_id, recipient_name, recipient_email, status, description, currency, cashback_amount, cashback_rate) VALUES (?, 'transfer', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'NGN', 0.00, ?)");
                $stmt->bind_param("iddiddissssd", $_SESSION['user_id'], $amount, $fee, $total_amount, $sender_balance_before, $sender_balance_after, $reference, $recipient_id, $recipient_name, $recipient_email, $note, $cashback_rate);
                $stmt->execute();

                $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, fee, total_amount, balance_before, balance_after, reference, sender_id, status, description, currency, cashback_amount, cashback_rate) VALUES (?, 'transfer', ?, 0, ?, ?, ?, ?, ?, 'pending', ?, 'NGN', 0.00, ?)");
                $stmt->bind_param("ididdisssd", $recipient_id, $amount, $amount, $recipient_balance_before, $recipient_balance_after, $reference, $_SESSION['user_id'], $note, $cashback_rate);
                $stmt->execute();

                if ($beneficiary_id) {
                    $stmt = $conn->prepare("UPDATE beneficiaries SET usage_count = usage_count + 1, last_used = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $beneficiary_id, $_SESSION['user_id']);
                    $stmt->execute();
                }

                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, channel, priority) VALUES (?, 'transaction', ?, ?, 'in_app', 'high')");
                $message = "You have initiated a transfer of ₦" . number_format($amount, 2) . " to $recipient_name ($recipient_email).";
                $title = "Transfer Initiated";
                $stmt->bind_param("iss", $_SESSION['user_id'], $title, $message);
                $stmt->execute();

                if ($recipient['email_notifications']) {
                    send_email_notification($recipient['email'], "Transfer Received", "<p>You have a pending transfer of ₦" . number_format($amount, 2) . " from a Ubiaza user.</p><p>Reference: $reference</p>");
                }

                $logger->info("Ubiaza transfer initiated from user ID: " . $_SESSION['user_id'] . " to recipient ID: $recipient_id, amount: $amount, fee: $fee, reference: $reference");
                echo json_encode(['success' => true, 'message' => 'Transfer initiated', 'reference' => $reference]);
            } else {
                // External bank transfer
                $account_number = $data['account_number'] ?? '';
                $bank_code = $data['bank_code'] ?? '';
                $account_name = $data['account_name'] ?? '';
                $bank_name = $data['bank_name'] ?? '';

                $lookup_response = lookupBankAccount($account_number, $bank_code);
                if (!$lookup_response['success'] || $lookup_response['account_name'] !== $account_name) {
                    $conn->rollback();
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bank details: ' . ($lookup_response['message'] ?? 'Unknown error')]);
                    $logger->warning("Bank account lookup failed for user ID: " . $_SESSION['user_id'] . ", account: $account_number, bank_code: $bank_code");
                    exit;
                }

                $transfer_response = initiateNombaBankTransfer($amount, $account_number, $bank_code, $account_name, $reference, $lookup_response['account_name']);
                if (!$transfer_response['success']) {
                    $conn->rollback();
                    http_response_code(400);
                    echo json_encode(['error' => 'Bank transfer initiation failed: ' . ($transfer_response['message'] ?? 'Unknown error')]);
                    $logger->error("Bank transfer initiation failed for user ID: " . $_SESSION['user_id'] . ", account: $account_number");
                    exit;
                }

                $stmt = $conn->prepare("UPDATE wallets SET available_balance = ?, daily_spent = daily_spent + ?, monthly_spent = monthly_spent + ? WHERE user_id = ?");
                $stmt->bind_param("dddi", $sender_balance_after, $total_amount, $total_amount, $_SESSION['user_id']);
                $stmt->execute();

                $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, fee, total_amount, balance_before, balance_after, reference, status, recipient_name, recipient_bank, recipient_account, description, currency, cashback_amount, cashback_rate) VALUES (?, 'transfer', ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, 'NGN', 0.00, ?)");
                $stmt->bind_param("iddiddissssd", $_SESSION['user_id'], $amount, $fee, $total_amount, $sender_balance_before, $sender_balance_after, $reference, $account_name, $bank_name, $account_number, $note, $cashback_rate);
                $stmt->execute();

                if ($beneficiary_id) {
                    $stmt = $conn->prepare("UPDATE beneficiaries SET usage_count = usage_count + 1, last_used = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $beneficiary_id, $_SESSION['user_id']);
                    $stmt->execute();
                }

                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, channel, priority) VALUES (?, 'transaction', ?, ?, 'in_app', 'high')");
                $message = "You have initiated a transfer of ₦" . number_format($amount, 2) . " to $account_name ($account_number).";
                $title = "Bank Transfer Initiated";
                $stmt->bind_param("iss", $_SESSION['user_id'], $title, $message);
                $stmt->execute();

                if ($user['email_notifications']) {
                    send_email_notification($user['email'], $title, "<p>$message</p><p>Reference: $reference</p>");
                }

                $logger->info("Bank transfer initiated from user ID: " . $_SESSION['user_id'] . " to account: $account_number, amount: $amount, fee: $fee, reference: $reference");
                echo json_encode(['success' => true, 'message' => 'Bank transfer initiated', 'reference' => $reference]);
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Transfer failed: ' . $e->getMessage()]);
            $logger->error("Transfer failed for user ID: " . $_SESSION['user_id'] . ": " . $e->getMessage());
        } finally {
            $db->close();
        }
        break;

    case 'save_beneficiary':
        if (!check_rate_limit('save_beneficiary', $_SESSION['user_id'], 10, 3600)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            $logger->warning("Rate limit exceeded for save_beneficiary: user ID " . $_SESSION['user_id']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? '';
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? null;
        $account_number = $data['account_number'] ?? null;
        $bank_code = $data['bank_code'] ?? null;
        $bank_name = $data['bank_name'] ?? null;
        $phone_number = $data['phone_number'] ?? null;
        $network_provider = $data['network_provider'] ?? null;
        $nickname = $data['nickname'] ?? null;
        $is_favorite = filter_var($data['is_favorite'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $is_verified = $data['is_verified'] ?? 1;
        $csrf_token = $data['csrf_token'] ?? '';

        if (!in_array($type, ['ubiaza', 'bank', 'mobile']) || !$name || !validate_csrf_token($csrf_token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid beneficiary details or CSRF token']);
            $logger->warning("Invalid beneficiary input for user ID: " . $_SESSION['user_id'] . ", type: $type");
            exit;
        }

        if ($type === 'ubiaza' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email address']);
            $logger->warning("Invalid email for beneficiary, user ID: " . $_SESSION['user_id']);
            exit;
        }

        if ($type === 'bank' && (!$account_number || !$bank_code || !$bank_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid bank details']);
            $logger->warning("Invalid bank details for beneficiary, user ID: " . $_SESSION['user_id']);
            exit;
        }

        if ($type === 'mobile' && (!$phone_number || !$network_provider)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid mobile details']);
            $logger->warning("Invalid mobile details for beneficiary, user ID: " . $_SESSION['user_id']);
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT id FROM beneficiaries WHERE user_id = ? AND (email = ? OR (account_number = ? AND bank_code = ?) OR (phone_number = ? AND network_provider = ?))");
            $stmt->bind_param("isssss", $_SESSION['user_id'], $email, $account_number, $bank_code, $phone_number, $network_provider);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Beneficiary already exists']);
                $logger->warning("Beneficiary already exists for user ID: " . $_SESSION['user_id']);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO beneficiaries (user_id, type, name, email, account_number, bank_code, bank_name, phone_number, network_provider, nickname, is_favorite, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssssssi", $_SESSION['user_id'], $type, $name, $email, $account_number, $bank_code, $bank_name, $phone_number, $network_provider, $nickname, $is_favorite, $is_verified);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, channel, priority) VALUES (?, 'beneficiary', ?, ?, 'in_app', 'medium')");
            $message = "New beneficiary added: " . ($nickname ?: $name) . ".";
            $title = "Beneficiary Added";
            $stmt->bind_param("iss", $_SESSION['user_id'], $title, $message);
            $stmt->execute();

            $stmt = $conn->prepare("SELECT email, email_notifications FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user['email_notifications']) {
                send_email_notification($user['email'], $title, "<p>$message</p>");
            }

            $conn->commit();
            $logger->info("Beneficiary saved for user ID: " . $_SESSION['user_id'] . ", type: $type, name: $name");
            echo json_encode(['success' => true, 'message' => 'Beneficiary saved']);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save beneficiary: ' . $e->getMessage()]);
            $logger->error("Failed to save beneficiary for user ID: " . $_SESSION['user_id'] . ": " . $e->getMessage());
        } finally {
            $db->close();
        }
        break;

    case 'lookup_bank_account':
        if (!check_rate_limit('lookup_bank_account', $_SESSION['user_id'], 10, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            $logger->warning("Rate limit exceeded for bank account lookup: user ID " . $_SESSION['user_id']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $account_number = $data['account_number'] ?? '';
        $bank_code = $data['bank_code'] ?? '';
        $csrf_token = $data['csrf_token'] ?? '';

        if (!$account_number || !$bank_code || !validate_csrf_token($csrf_token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid account number, bank code, or CSRF token']);
            $logger->warning("Invalid input for bank account lookup, user ID: " . $_SESSION['user_id']);
            exit;
        }

        try {
            $lookup_response = lookupBankAccount($account_number, $bank_code);
            if ($lookup_response['success']) {
                echo json_encode(['success' => true, 'account_name' => $lookup_response['account_name'], 'bank_name' => $lookup_response['bank_name']]);
                $logger->info("Bank account lookup successful for user ID: " . $_SESSION['user_id'] . ", account: $account_number");
            } else {
                http_response_code(400);
                echo json_encode(['error' => $lookup_response['message'] ?? 'Invalid bank details']);
                $logger->warning("Bank account lookup failed for user ID: " . $_SESSION['user_id'] . ", account: $account_number");
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Bank account lookup failed: ' . $e->getMessage()]);
            $logger->error("Bank account lookup failed for user ID: " . $_SESSION['user_id'] . ": " . $e->getMessage());
        }
        break;

    case 'create_virtual_account':
        if (!check_rate_limit('create_virtual_account', $_SESSION['user_id'], 2, 3600)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            $logger->warning("Rate limit exceeded for virtual account creation: user ID " . $_SESSION['user_id']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $account_name = $data['account_name'] ?? '';
        $use_phone = filter_var($data['use_phone'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $csrf_token = $data['csrf_token'] ?? '';

        if (!validate_csrf_token($csrf_token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid CSRF token']);
            $logger->warning("Invalid CSRF token for virtual account creation, user ID: " . $_SESSION['user_id']);
            exit;
        }

        $db = new Database();
        $conn = $db->getConnection();
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT first_name, last_name, phone, email, email_notifications FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if (!$user) {
                $conn->rollback();
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                $logger->warning("User not found for virtual account creation: ID " . $_SESSION['user_id']);
                exit;
            }

            $stmt = $conn->prepare("SELECT id FROM virtual_accounts WHERE user_id = ? AND status = 'active'");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Active virtual account already exists']);
                $logger->warning("Active virtual account already exists for user ID: " . $_SESSION['user_id']);
                exit;
            }

            $final_account_name = $account_name ?: ($user['first_name'] . ' ' . $user['last_name']);
            $phone_number = $use_phone ? $user['phone'] : null;
            $account_ref = 'VA_' . time() . '_' . bin2hex(random_bytes(8));

            $virtual_account_response = createNombaVirtualAccount($account_ref, $final_account_name);
            if (!$virtual_account_response['success']) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['error' => 'Virtual account creation failed: ' . ($virtual_account_response['message'] ?? 'Unknown error')]);
                $logger->error("Virtual account creation failed for user ID: " . $_SESSION['user_id'] . ": " . ($virtual_account_response['message'] ?? 'Unknown error'));
                exit;
            }

            $account_number = $virtual_account_response['account_number'];
            $stmt = $conn->prepare("INSERT INTO virtual_accounts (user_id, account_number, account_name, phone_number, status, nomba_reference) VALUES (?, ?, ?, ?, 'active', ?)");
            $stmt->bind_param("issss", $_SESSION['user_id'], $account_number, $final_account_name, $phone_number, $account_ref);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, channel, priority) VALUES (?, 'account', ?, ?, 'in_app', 'high')");
            $message = "Your virtual account ($account_number) has been created.";
            $title = "Virtual Account Created";
            $stmt->bind_param("iss", $_SESSION['user_id'], $title, $message);
            $stmt->execute();

            if ($user['email_notifications']) {
                send_email_notification($user['email'], $title, "<p>$message</p>");
            }

            $conn->commit();
            $logger->info("Virtual account created for user ID: " . $_SESSION['user_id'] . ", account_number: $account_number");
            echo json_encode(['success' => true, 'message' => 'Virtual account created', 'account_number' => $account_number, 'account_name' => $final_account_name]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Virtual account creation failed: ' . $e->getMessage()]);
            $logger->error("Virtual account creation failed for user ID: " . $_SESSION['user_id'] . ": " . $e->getMessage());
        } finally {
            $db->close();
        }
        break;

    case 'get_transactions':
        $db = new Database();
        $conn = $db->getConnection();
        try {
            $stmt = $conn->prepare("SELECT id, type, amount, fee, total_amount, balance_before, balance_after, reference, status, created_at, recipient_id, recipient_name, recipient_email, recipient_bank, recipient_account, description, cashback_amount 
                                    FROM transactions 
                                    WHERE user_id = ? 
                                    ORDER BY created_at DESC LIMIT 50");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'transactions' => $transactions]);
            $logger->info("Transactions fetched for user ID: " . $_SESSION['user_id']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch transactions: ' . $e->getMessage()]);
            $logger->error("Failed to fetch transactions for user ID: " . $_SESSION['user_id'] . ": " . $e->getMessage());
        } finally {
            $db->close();
        }
        break;

    case 'get_virtual_account':
        $db = new Database();
        $conn = $db->getConnection();
        try {
            $stmt = $conn->prepare("SELECT account_number, account_name, phone_number, status FROM virtual_accounts WHERE user_id = ? AND status = 'active'");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $virtual_account = $stmt->get_result()->fetch_assoc();
            if (!$virtual_account) {
                echo json_encode(['success' => true, 'virtual_account' => null]);
            } else {
                echo json_encode(['success' => true, 'virtual_account' => $virtual_account]);
            }
            $logger->info("Virtual account fetched for user ID: " . $_SESSION['user_id']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch virtual account: ' . $e->getMessage()]);
            $logger->error("Failed to fetch virtual account for user ID: " . $_SESSION['user_id'] . ": " . $e->getMessage());
        } finally {
            $db->close();
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        $logger->warning("Invalid wallet action: $action for user ID: " . $_SESSION['user_id']);
        exit;
}

// Helper function for Nomba Checkout
function initiateNombaCheckout($amount, $email, $reference, $name, $payment_method) {
    $url = 'https://api.nomba.com/v1/checkout/order';
    $data = [
        'order' => [
            'orderReference' => $reference,
            'customerEmail' => $email,
            'amount' => $amount * 100, // Convert to kobo
            'currency' => 'NGN',
            'customerId' => $email,
            'callbackUrl' => SITE_URL . 'callback'
        ]
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . NOMBA_API_TOKEN,
            'Content-Type: application/json',
            'accountId: ' . NOMBA_ACCOUNT_ID
        ]
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $logger = new Logger('wallet');
        $logger->pushHandler(new StreamHandler(LOG_DIR . 'wallet.log', Logger::ERROR));
        $logger->error("cURL error in initiateNombaCheckout: " . curl_error($ch));
        curl_close($ch);
        return ['success' => false, 'message' => 'Payment gateway error'];
    }
    curl_close($ch);
    $result = json_decode($response, true);
    return [
        'success' => isset($result['code']) && $result['code'] === '00',
        'message' => $result['description'] ?? null,
        'checkout_url' => $result['data']['checkoutLink'] ?? null
    ];
}

// Helper function for Nomba Wallet-to-Wallet Transfer
function initiateNombaTransfer($amount, $sender_id, $recipient_id, $reference) {
    $url = 'https://api.nomba.com/v1/transfers/wallet';
    $data = [
        'amount' => $amount * 100, // Convert to kobo
        'receiverAccountId' => "RECIPIENT_" . $recipient_id,
        'merchantTxRef' => $reference,
        'narration' => 'Ubiaza wallet transfer'
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . NOMBA_API_TOKEN,
            'Content-Type: application/json',
            'accountId: ' . NOMBA_ACCOUNT_ID
        ]
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $logger = new Logger('wallet');
        $logger->pushHandler(new StreamHandler(LOG_DIR . 'wallet.log', Logger::ERROR));
        $logger->error("cURL error in initiateNombaTransfer: " . curl_error($ch));
        curl_close($ch);
        return ['success' => false, 'message' => 'Transfer gateway error'];
    }
    curl_close($ch);
    $result = json_decode($response, true);
    return [
        'success' => isset($result['code']) && $result['code'] === '00',
        'message' => $result['description'] ?? null
    ];
}

// Helper function for Nomba Bank Transfer
function initiateNombaBankTransfer($amount, $account_number, $bank_code, $account_name, $reference, $verified_account_name) {
    $url = 'https://api.nomba.com/v1/transfers/bank';
    $data = [
        'amount' => $amount * 100, // Convert to kobo
        'accountNumber' => $account_number,
        'bankCode' => $bank_code,
        'accountName' => $verified_account_name,
        'merchantTxRef' => $reference,
        'senderName' => 'Ubiaza User'
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . NOMBA_API_TOKEN,
            'Content-Type: application/json',
            'accountId: ' . NOMBA_ACCOUNT_ID
        ]
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $logger = new Logger('wallet');
        $logger->pushHandler(new StreamHandler(LOG_DIR . 'wallet.log', Logger::ERROR));
        $logger->error("cURL error in initiateNombaBankTransfer: " . curl_error($ch));
        curl_close($ch);
        return ['success' => false, 'message' => 'Bank transfer gateway error'];
    }
    curl_close($ch);
    $result = json_decode($response, true);
    return [
        'success' => isset($result['code']) && $result['code'] === '00',
        'message' => $result['description'] ?? null
    ];
}

// Helper function for Nomba Bank Account Lookup
function lookupBankAccount($account_number, $bank_code) {
    $url = 'https://api.nomba.com/v1/transfers/bank/lookup';
    $data = [
        'accountNumber' => $account_number,
        'bankCode' => $bank_code
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . NOMBA_API_TOKEN,
            'Content-Type: application/json',
            'accountId: ' . NOMBA_ACCOUNT_ID
        ]
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $logger = new Logger('wallet');
        $logger->pushHandler(new StreamHandler(LOG_DIR . 'wallet.log', Logger::ERROR));
        $logger->error("cURL error in lookupBankAccount: " . curl_error($ch));
        curl_close($ch);
        return ['success' => false, 'message' => 'Bank lookup error'];
    }
    curl_close($ch);
    $result = json_decode($response, true);
    return [
        'success' => isset($result['code']) && $result['code'] === '00',
        'message' => $result['description'] ?? null,
        'account_name' => $result['data']['accountName'] ?? null,
        'bank_name' => $result['data']['bankName'] ?? null
    ];
}

// Helper function for Nomba Virtual Account Creation
function createNombaVirtualAccount($account_ref, $account_name) {
    $url = 'https://api.nomba.com/v1/accounts/virtual';
    $data = [
        'accountRef' => $account_ref,
        'accountName' => $account_name
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . NOMBA_API_TOKEN,
            'Content-Type: application/json',
            'accountId: ' . NOMBA_ACCOUNT_ID
        ]
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $logger = new Logger('wallet');
        $logger->pushHandler(new StreamHandler(LOG_DIR . 'wallet.log', Logger::ERROR));
        $logger->error("cURL error in createNombaVirtualAccount: " . curl_error($ch));
        curl_close($ch);
        return ['success' => false, 'message' => 'Virtual account creation error'];
    }
    curl_close($ch);
    $result = json_decode($response, true);
    return [
        'success' => isset($result['code']) && $result['code'] === '00',
        'message' => $result['description'] ?? null,
        'account_number' => $result['data']['accountNumber'] ?? null
    ];
}
?>