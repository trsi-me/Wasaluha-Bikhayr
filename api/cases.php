<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/logger.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_cases') {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as user_name 
            FROM cases c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.status IN ('open', 'claimed')
            ORDER BY c.urgent DESC, c.created_at DESC
        ");
        $stmt->execute();
        $cases = $stmt->fetchAll();
        
        echo json_encode(['status' => 'ok', 'cases' => $cases]);
    } catch (PDOException $e) {
        logError('Get cases error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء جلب الحالات']);
    }
    
} elseif ($action === 'add_case') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'msg' => 'يجب تسجيل الدخول أولاً']);
        exit;
    }
    
    if (($_SESSION['user_role'] ?? '') !== 'needy') {
        echo json_encode(['status' => 'error', 'msg' => 'هذه الصفحة للمستفيدين فقط']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'msg' => 'طريقة الطلب غير صحيحة']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'msg' => 'خطأ في التحقق من الأمان']);
        exit;
    }
    
    $item_name = trim($input['item_name'] ?? '');
    $quantity = intval($input['quantity'] ?? 1);
    $type = trim($input['type'] ?? '');
    $urgent = isset($input['urgent']) && $input['urgent'] ? 1 : 0;
    $user_id = $_SESSION['user_id'];
    
    if (empty($item_name) || strlen($item_name) < 3) {
        echo json_encode(['status' => 'error', 'msg' => 'اسم الاحتياج يجب أن يكون 3 أحرف على الأقل']);
        exit;
    }
    
    if ($quantity < 1 || $quantity > 1000) {
        echo json_encode(['status' => 'error', 'msg' => 'العدد يجب أن يكون بين 1 و 1000']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO cases (user_id, item_name, quantity, type, urgent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $item_name, $quantity, $type ?: null, $urgent]);
        
        $case_id = $pdo->lastInsertId();
        $user_name = $_SESSION['user_name'] ?? '';
        logAction('ADD_CASE', $user_id, $user_name, ['case_id' => $case_id, 'item_name' => $item_name, 'quantity' => $quantity, 'type' => $type, 'urgent' => $urgent]);
        
        echo json_encode(['status' => 'ok', 'msg' => 'تم إضافة الحالة بنجاح!', 'case_id' => $case_id]);
    } catch (PDOException $e) {
        logError('Add case error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء إضافة الحالة']);
    }
    
} elseif ($action === 'delete_case') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'msg' => 'يجب تسجيل الدخول أولاً']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'msg' => 'طريقة الطلب غير صحيحة']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'msg' => 'خطأ في التحقق من الأمان']);
        exit;
    }
    
    $case_id = intval($input['case_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM cases WHERE id = ? AND user_id = ? AND status = 'open'");
        $stmt->execute([$case_id, $user_id]);
        
        if ($case_data = $stmt->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM cases WHERE id = ?");
            $stmt->execute([$case_id]);
            
            $user_name = $_SESSION['user_name'] ?? '';
            logAction('DELETE_CASE', $user_id, $user_name, ['case_id' => $case_id, 'item_name' => $case_data['item_name'] ?? '']);
            
            echo json_encode(['status' => 'ok', 'msg' => 'تم حذف الحالة بنجاح']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'الحالة غير موجودة أو لا يمكن حذفها']);
        }
    } catch (PDOException $e) {
        logError('Delete case error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء حذف الحالة']);
    }
    
} elseif ($action === 'get_stats') {
    try {
        $stats_stmt = $pdo->query("SELECT COUNT(*) as total_cases FROM cases WHERE status = 'open'");
        $total_open_cases = $stats_stmt->fetch()['total_cases'] ?? 0;
        
        $stats_stmt = $pdo->query("SELECT COUNT(*) as total_fulfilled FROM cases WHERE status IN ('claimed', 'fulfilled')");
        $total_fulfilled = $stats_stmt->fetch()['total_fulfilled'] ?? 0;
        
        $stats_stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
        $total_users = $stats_stmt->fetch()['total_users'] ?? 0;
        
        $stats_stmt = $pdo->query("SELECT COUNT(*) as urgent_cases FROM cases WHERE urgent = 1 AND status = 'open'");
        $urgent_cases = $stats_stmt->fetch()['urgent_cases'] ?? 0;
        
        $stats_stmt = $pdo->query("SELECT COUNT(*) as total_cases_all FROM cases");
        $total_cases_all = $stats_stmt->fetch()['total_cases_all'] ?? 0;
        
        $stats_stmt = $pdo->query("SELECT COUNT(*) as total_donors FROM users WHERE role = 'donor'");
        $total_donors = $stats_stmt->fetch()['total_donors'] ?? 0;
        
        $stats_stmt = $pdo->query("SELECT COUNT(*) as total_needy FROM users WHERE role = 'needy'");
        $total_needy = $stats_stmt->fetch()['total_needy'] ?? 0;
        
        $stats_stmt = $pdo->query("SELECT COUNT(*) as total_donations FROM cases WHERE status IN ('claimed', 'fulfilled')");
        $total_donations = $stats_stmt->fetch()['total_donations'] ?? 0;
        
        $stats_stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total_amount FROM cases WHERE amount IS NOT NULL AND status IN ('claimed', 'fulfilled')");
        $total_amount = $stats_stmt->fetch()['total_amount'] ?? 0;
        
        echo json_encode([
            'status' => 'ok',
            'stats' => [
                'total_open_cases' => (int)$total_open_cases,
                'total_fulfilled' => (int)$total_fulfilled,
                'total_users' => (int)$total_users,
                'urgent_cases' => (int)$urgent_cases,
                'total_cases_all' => (int)$total_cases_all,
                'total_donors' => (int)$total_donors,
                'total_needy' => (int)$total_needy,
                'total_donations' => (int)$total_donations,
                'total_amount' => (float)$total_amount
            ]
        ]);
    } catch (PDOException $e) {
        logError('Get stats error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء جلب الإحصائيات']);
    }
    
} elseif ($action === 'get_recent_cases') {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as user_name 
            FROM cases c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.status = 'open'
            ORDER BY c.urgent DESC, c.created_at DESC 
            LIMIT 6
        ");
        $stmt->execute();
        $cases = $stmt->fetchAll();
        
        echo json_encode(['status' => 'ok', 'cases' => $cases]);
    } catch (PDOException $e) {
        logError('Get recent cases error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء جلب الحالات']);
    }
    
} elseif ($action === 'donate') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'msg' => 'يجب تسجيل الدخول أولاً']);
        exit;
    }
    
    if (($_SESSION['user_role'] ?? '') !== 'donor') {
        echo json_encode(['status' => 'error', 'msg' => 'هذه الصفحة للمتبرعين فقط']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'msg' => 'طريقة الطلب غير صحيحة']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'msg' => 'خطأ في التحقق من الأمان']);
        exit;
    }
    
    $case_id = intval($input['case_id'] ?? 0);
    $donor_id = $_SESSION['user_id'] ?? 0;
    
    if ($case_id <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'معرف الحالة غير صحيح']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM cases WHERE id = ? AND status = 'open'");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch();
        
        if (!$case) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'msg' => 'الحالة غير موجودة أو تم توفيرها بالفعل']);
            exit;
        }
        
        $needy_id = $case['user_id'];
        
        $stmt = $pdo->prepare("UPDATE cases SET status = 'claimed' WHERE id = ?");
        $stmt->execute([$case_id]);
        
        $message = "تم توفير حالتك بنجاح";
        $stmt = $pdo->prepare("INSERT INTO notifications (from_user, to_user, case_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$donor_id, $needy_id, $case_id, $message]);
        
        $pdo->commit();
        
        $donor_name = $_SESSION['user_name'] ?? '';
        logAction('DONATE', $donor_id, $donor_name, ['case_id' => $case_id, 'case_item' => $case['item_name'], 'needy_id' => $needy_id, 'delivery_place' => 'جمعية يد الخير']);
        
        echo json_encode([
            'status' => 'ok',
            'msg' => 'تم توفير الحالة بنجاح',
            'delivery_place' => 'جمعية يد الخير'
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError('Donate action error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء معالجة الطلب']);
    }
    
} else {
    echo json_encode(['status' => 'error', 'msg' => 'إجراء غير معروف']);
}

