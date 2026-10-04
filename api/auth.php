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

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'msg' => 'طريقة الطلب غير صحيحة']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $login = trim($input['login'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        echo json_encode(['status' => 'error', 'msg' => 'يرجى إدخال البريد/الجوال وكلمة المرور']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            logAction('LOGIN_SUCCESS', $user['id'], $user['name'], ['email' => $user['email'], 'phone' => $user['phone'], 'role' => $user['role']]);
            
            echo json_encode([
                'status' => 'ok',
                'msg' => 'تم تسجيل الدخول بنجاح',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            logAction('LOGIN_FAILED', null, null, ['login' => $login, 'reason' => 'invalid_credentials']);
            echo json_encode(['status' => 'error', 'msg' => 'البريد/الجوال أو كلمة المرور غير صحيحة']);
        }
    } catch (PDOException $e) {
        logError('Login error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء تسجيل الدخول']);
    }
    
} elseif ($action === 'register') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'msg' => 'طريقة الطلب غير صحيحة']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    $password_confirm = $input['password_confirm'] ?? '';
    $role = $input['role'] ?? 'needy';
    
    if (empty($name) || strlen($name) < 3) {
        echo json_encode(['status' => 'error', 'msg' => 'الاسم يجب أن يكون 3 أحرف على الأقل']);
        exit;
    }
    
    if (empty($email) && empty($phone)) {
        echo json_encode(['status' => 'error', 'msg' => 'يجب إدخال البريد الإلكتروني أو رقم الجوال']);
        exit;
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'msg' => 'البريد الإلكتروني غير صحيح']);
        exit;
    }
    
    if (empty($password) || strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'msg' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
        exit;
    }
    
    if ($password !== $password_confirm) {
        echo json_encode(['status' => 'error', 'msg' => 'كلمات المرور غير متطابقة']);
        exit;
    }
    
    if (!in_array($role, ['donor', 'needy'])) {
        echo json_encode(['status' => 'error', 'msg' => 'الدور غير صحيح']);
        exit;
    }
    
    try {
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $check_stmt->execute([$email ?: null, $phone ?: null]);
        if ($check_stmt->fetch()) {
            echo json_encode(['status' => 'error', 'msg' => 'البريد الإلكتروني أو رقم الجوال مستخدم بالفعل']);
            exit;
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email ?: null, $phone ?: null, $hashed_password, $role]);
        
        $new_user_id = $pdo->lastInsertId();
        logAction('REGISTER_SUCCESS', $new_user_id, $name, ['email' => $email, 'phone' => $phone, 'role' => $role]);
        
        echo json_encode(['status' => 'ok', 'msg' => 'تم التسجيل بنجاح! يمكنك تسجيل الدخول الآن.']);
    } catch (PDOException $e) {
        logError('Register error: ' . $e->getMessage(), __FILE__, __LINE__);
        echo json_encode(['status' => 'error', 'msg' => 'حدث خطأ أثناء التسجيل']);
    }
    
} elseif ($action === 'logout') {
    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['user_name'] ?? null;
    
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    
    if ($user_id) {
        logAction('LOGOUT', $user_id, $user_name);
    }
    
    echo json_encode(['status' => 'ok', 'msg' => 'تم تسجيل الخروج بنجاح']);
    
} elseif ($action === 'check_session') {
    $is_logged_in = isset($_SESSION['user_id']);
    if ($is_logged_in) {
        echo json_encode([
            'status' => 'ok',
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? '',
                'role' => $_SESSION['user_role'] ?? ''
            ],
            'csrf_token' => $_SESSION['csrf_token'] ?? ''
        ]);
    } else {
        echo json_encode(['status' => 'ok', 'logged_in' => false]);
    }
    
} else {
    echo json_encode(['status' => 'error', 'msg' => 'إجراء غير معروف']);
}

