<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/logger.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_user_info') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['status' => 'error', 'msg' => 'المستخدم غير موجود']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM cases WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $user_cases = $stmt->fetchAll();
        
        $cases_count = count($user_cases);
        $donations_count = 0;
        
        if ($user_role === 'donor') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cases WHERE status = 'claimed' AND id IN (SELECT DISTINCT c.id FROM cases c JOIN notifications n ON c.id = n.from_user WHERE n.from_user = ?)");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            $donations_count = $result ? intval($result['count']) : 0;
        }
        
        echo json_encode([
            'status' => 'ok',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role']
            ],
            'stats' => [
                'cases_count' => $cases_count,
                'donations_count' => $donations_count
            ],
            'cases' => $user_cases
        ]);
    } catch (PDOException $e) {
        logError('Get user info error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء جلب معلومات المستخدم']);
    }
    
} elseif ($action === 'get_notifications') {
    try {
        $stmt = $pdo->prepare("SELECT n.*, c.item_name FROM notifications n LEFT JOIN cases c ON n.case_id = c.id WHERE n.to_user = ? ORDER BY n.created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll();
        
        echo json_encode(['status' => 'ok', 'notifications' => $notifications]);
    } catch (PDOException $e) {
        logError('Get notifications error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ']);
    }
    
} elseif ($action === 'mark_read') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'msg' => 'طريقة الطلب غير صحيحة']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'msg' => 'خطأ في التحقق من الأمان']);
        exit;
    }
    
    $notif_id = intval($input['notification_id'] ?? 0);
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET read_flag = 1 WHERE id = ? AND to_user = ?");
        $stmt->execute([$notif_id, $user_id]);
        echo json_encode(['status' => 'ok']);
    } catch (PDOException $e) {
        logError('Mark notification read error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ']);
    }
    
} elseif ($action === 'set_delivery_method') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'msg' => 'طريقة الطلب غير صحيحة']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'msg' => 'خطأ في التحقق من الأمان']);
        exit;
    }
    
    $notif_id = intval($input['notification_id'] ?? 0);
    $delivery_method = trim($input['delivery_method'] ?? '');
    
    if (!in_array($delivery_method, ['pickup', 'delivery'])) {
        echo json_encode(['status' => 'error', 'msg' => 'طريقة التوصيل غير صحيحة']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET delivery_method = ?, read_flag = 1 WHERE id = ? AND to_user = ?");
        $stmt->execute([$delivery_method, $notif_id, $user_id]);
        echo json_encode(['status' => 'ok', 'msg' => 'تم حفظ اختيارك بنجاح']);
    } catch (PDOException $e) {
        logError('Set delivery method error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ']);
    }
    
} else {
    echo json_encode(['status' => 'error', 'msg' => 'إجراء غير معروف']);
}

