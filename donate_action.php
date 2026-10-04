<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/logger.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

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

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'msg' => 'خطأ في التحقق من الأمان']);
    exit;
}

$case_id = intval($data['case_id'] ?? 0);
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

