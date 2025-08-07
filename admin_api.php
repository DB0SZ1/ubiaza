<?php
require_once 'config.php';
require_once 'db.php';
require_once '../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

session_start();

// Initialize logger
$logger = new Logger('admin_api');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'admin_api.log', Logger::INFO));

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'stats':
        try {
            // Total users
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
            $total_users = $stmt->fetch_assoc()['count'];

            // Total transactions
            $stmt = $conn->query("SELECT COUNT(*) as count FROM transactions");
            $total_transactions = $stmt->fetch_assoc()['count'];

            // Total revenue (sum of fees)
            $stmt = $conn->query("SELECT SUM(fee) as total FROM transactions WHERE status = 'completed'");
            $total_revenue = $stmt->fetch_assoc()['total'] ?? 0;

            // Pending actions
            $stmt = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'");
            $pending_actions = $stmt->fetch_assoc()['count'];

            // Calculate changes from last month
            $last_month = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at < ?");
            $stmt->bind_param("s", $last_month);
            $stmt->execute();
            $prev_users = $stmt->get_result()->fetch_assoc()['count'];
            $users_change = $prev_users > 0 ? round((($total_users - $prev_users) / $prev_users) * 100, 1) : 0;

            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE created_at < ?");
            $stmt->bind_param("s", $last_month);
            $stmt->execute();
            $prev_transactions = $stmt->get_result()->fetch_assoc()['count'];
            $transactions_change = $prev_transactions > 0 ? round((($total_transactions - $prev_transactions) / $prev_transactions) * 100, 1) : 0;

            $stmt = $conn->prepare("SELECT SUM(fee) as total FROM transactions WHERE status = 'completed' AND created_at < ?");
            $stmt->bind_param("s", $last_month);
            $stmt->execute();
            $prev_revenue = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
            $revenue_change = $prev_revenue > 0 ? round((($total_revenue - $prev_revenue) / $prev_revenue) * 100, 1) : 0;

            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending' AND created_at < ?");
            $stmt->bind_param("s", $last_month);
            $stmt->execute();
            $prev_pending = $stmt->get_result()->fetch_assoc()['count'];
            $pending_change = $prev_pending > 0 ? round((($pending_actions - $prev_pending) / $prev_pending) * 100, 1) : 0;

            echo json_encode([
                'total_users' => $total_users,
                'total_transactions' => $total_transactions,
                'total_revenue' => $total_revenue,
                'pending_actions' => $pending_actions,
                'users_change' => $users_change,
                'transactions_change' => $transactions_change,
                'revenue_change' => $revenue_change,
                'pending_change' => $pending_change
            ]);
        } catch (Exception $e) {
            $logger->error("Error fetching stats: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch stats']);
        }
        break;

    case 'users':
        try {
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $verification = isset($_GET['verification']) ? $_GET['verification'] : '';
            $offset = ($page - 1) * 10;

            $query = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.bvn_verified, u.email_verified, 
                    COALESCE(a.balance, 0) as balance, u.created_at 
                    FROM users u 
                    LEFT JOIN accounts a ON u.id = a.user_id 
                    WHERE 1=1";
            $params = [];
            $types = '';

            if ($search) {
                $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
                $search_param = "%$search%";
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
                $types .= 'ssss';
            }

            if ($status) {
                $query .= " AND u.status = ?";
                $params[] = $status;
                $types .= 's';
            }

            if ($verification === 'verified') {
                $query .= " AND (u.bvn_verified = 1 OR u.email_verified = 1)";
            } elseif ($verification === 'unverified') {
                $query .= " AND u.bvn_verified = 0 AND u.email_verified = 0";
            }

            $query .= " ORDER BY u.created_at DESC LIMIT 10 OFFSET ?";
            $params[] = $offset;
            $types .= 'i';

            $stmt = $conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $users = $result->fetch_all(MYSQLI_ASSOC);

            // Get total count for pagination
            $count_query = "SELECT COUNT(*) as total FROM users u WHERE 1=1";
            $count_params = [];
            $count_types = '';

            if ($search) {
                $count_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
                $count_params = array_merge($count_params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
                $count_types .= 'ssss';
            }

            if ($status) {
                $count_query .= " AND u.status = ?";
                $count_params[] = $status;
                $count_types .= 's';
            }

            if ($verification === 'verified') {
                $count_query .= " AND (u.bvn_verified = 1 OR u.email_verified = 1)";
            } elseif ($verification === 'unverified') {
                $count_query .= " AND u.bvn_verified = 0 AND u.email_verified = 0";
            }

            $stmt = $conn->prepare($count_query);
            if ($count_params) {
                $stmt->bind_param($count_types, ...$count_params);
            }
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];

            echo json_encode([
                'users' => $users,
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'total_pages' => ceil($total / 10)
                ]
            ]);
        } catch (Exception $e) {
            $logger->error("Error fetching users: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch users']);
        }
        break;

    case 'get_user':
        try {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, status, bvn_verified, email_verified 
                                  FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if ($user) {
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
        } catch (Exception $e) {
            $logger->error("Error fetching user: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch user']);
        }
        break;

    case 'save_user':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['first_name'], $data['last_name'], $data['email'], $data['phone'], $data['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            if (isset($data['id']) && $data['id']) {
                // Update user
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $data['first_name'], $data['last_name'], $data['email'], $data['phone'], $data['status'], $data['id']);
            } else {
                // Create new user
                $password_hash = password_hash('default123', PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, status, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $data['first_name'], $data['last_name'], $data['email'], $data['phone'], $data['status'], $password_hash);
            }

            $stmt->execute();
            
            // Log admin activity
            $admin_id = $_SESSION['admin_id'];
            $action = isset($data['id']) ? 'update_user' : 'create_user';
            $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, description, ip_address, user_agent) VALUES (?, ?, 'user', ?, ?, ?, ?)");
            $description = isset($data['id']) ? "Updated user {$data['email']}" : "Created user {$data['email']}";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $target_id = $data['id'] ?? $conn->insert_id;
            $stmt->bind_param("isiss", $admin_id, $action, $target_id, $description, $ip_address, $user_agent);
            $stmt->execute();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $logger->error("Error saving user: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save user']);
        }
        break;

    case 'ban_user':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = $data['user_id'] ?? 0;
            $reason = $data['reason'] ?? '';

            if (!$user_id || !$reason) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET status = 'banned', banned_reason = ?, banned_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $reason, $user_id);
            $stmt->execute();

            // Log admin activity
            $admin_id = $_SESSION['admin_id'];
            $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, description, ip_address, user_agent) VALUES (?, 'ban_user', 'user', ?, ?, ?, ?)");
            $description = "Banned user ID $user_id: $reason";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $stmt->bind_param("isiss", $admin_id, $user_id, $description, $ip_address, $user_agent);
            $stmt->execute();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $logger->error("Error banning user: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to ban user']);
        }
        break;

    case 'suspend_user':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = $data['user_id'] ?? 0;
            $duration = $data['duration'] ?? 0;
            $reason = $data['reason'] ?? '';

            if (!$user_id || !$duration || !$reason) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            $suspend_until = date('Y-m-d H:i:s', strtotime("+{$duration} days"));

            $stmt = $conn->prepare("UPDATE users SET status = 'suspended', suspended_reason = ?, suspended_until = ? WHERE id = ?");
            $stmt->bind_param("ssi", $reason, $suspend_until, $user_id);
            $stmt->execute();

            // Log admin activity
            $admin_id = $_SESSION['admin_id'];
            $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, description, ip_address, user_agent) VALUES (?, 'suspend_user', 'user', ?, ?, ?, ?)");
            $description = "Suspended user ID $user_id for $duration days: $reason";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $stmt->bind_param("isiss", $admin_id, $user_id, $description, $ip_address, $user_agent);
            $stmt->execute();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $logger->error("Error suspending user: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to suspend user']);
        }
        break;

    case 'transactions':
        try {
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $type = isset($_GET['type']) ? $_GET['type'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $offset = ($page - 1) * 10;

            $query = "SELECT t.reference, t.external_reference, t.type, t.amount, t.fee, t.status, t.created_at,
                     CONCAT(u.first_name, ' ', u.last_name) as user_name
                     FROM transactions t
                     LEFT JOIN users u ON t.user_id = u.id
                     WHERE 1=1";
            $params = [];
            $types = '';

            if ($search) {
                $query .= " AND (t.reference LIKE ? OR t.external_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
                $search_param = "%$search%";
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
                $types .= 'ssss';
            }

            if ($type) {
                $query .= " AND t.type = ?";
                $params[] = $type;
                $types .= 's';
            }

            if ($status) {
                $query .= " AND t.status = ?";
                $params[] = $status;
                $types .= 's';
            }

            $query .= " ORDER BY t.created_at DESC LIMIT 10 OFFSET ?";
            $params[] = $offset;
            $types .= 'i';

            $stmt = $conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $transactions = $result->fetch_all(MYSQLI_ASSOC);

            // Get total count for pagination
            $count_query = "SELECT COUNT(*) as total FROM transactions t
                           LEFT JOIN users u ON t.user_id = u.id
                           WHERE 1=1";
            $count_params = [];
            $count_types = '';

            if ($search) {
                $count_query .= " AND (t.reference LIKE ? OR t.external_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
                $count_params = array_merge($count_params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
                $count_types .= 'ssss';
            }

            if ($type) {
                $count_query .= " AND t.type = ?";
                $count_params[] = $type;
                $count_types .= 's';
            }

            if ($status) {
                $count_query .= " AND t.status = ?";
                $count_params[] = $status;
                $count_types .= 's';
            }

            $stmt = $conn->prepare($count_query);
            if ($count_params) {
                $stmt->bind_param($count_types, ...$count_params);
            }
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];

            echo json_encode([
                'transactions' => $transactions,
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'total_pages' => ceil($total / 10)
                ]
            ]);
        } catch (Exception $e) {
            $logger->error("Error fetching transactions: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch transactions']);
        }
        break;

    case 'get_transaction':
        try {
            $reference = isset($_GET['reference']) ? $_GET['reference'] : '';
            $stmt = $conn->prepare("SELECT t.reference, t.external_reference, t.type, t.amount, t.fee, t.status, t.created_at,
                                  CONCAT(u.first_name, ' ', u.last_name) as user_name
                                  FROM transactions t
                                  LEFT JOIN users u ON t.user_id = u.id
                                  WHERE t.reference = ?");
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            $transaction = $stmt->get_result()->fetch_assoc();
            
            if ($transaction) {
                echo json_encode($transaction);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Transaction not found']);
            }
        } catch (Exception $e) {
            $logger->error("Error fetching transaction: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch transaction']);
        }
        break;

    case 'save_settings':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['app_name'], $data['maintenance_mode'], $data['daily_transfer_limit'], 
                     $data['single_transaction_limit'], $data['transfer_fee'], $data['bill_payment_fee'],
                     $data['airtime_cashback'], $data['data_cashback'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES 
                                  ('app_name', ?),
                                  ('maintenance_mode', ?),
                                  ('daily_transfer_limit', ?),
                                  ('single_transaction_limit', ?),
                                  ('transfer_fee', ?),
                                  ('bill_payment_fee', ?),
                                  ('airtime_cashback', ?),
                                  ('data_cashback', ?)");
            $stmt->bind_param("ssiddidd", 
                $data['app_name'],
                $data['maintenance_mode'],
                $data['daily_transfer_limit'],
                $data['single_transaction_limit'],
                $data['transfer_fee'],
                $data['bill_payment_fee'],
                $data['airtime_cashback'],
                $data['data_cashback']
            );
            $stmt->execute();

            // Log admin activity
            $admin_id = $_SESSION['admin_id'];
            $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_type, description, ip_address, user_agent) VALUES (?, 'update_settings', 'system', ?, ?, ?)");
            $description = "Updated system settings";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $stmt->bind_param("isss", $admin_id, $description, $ip_address, $user_agent);
            $stmt->execute();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $logger->error("Error saving settings: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save settings']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

$db->close();
?>