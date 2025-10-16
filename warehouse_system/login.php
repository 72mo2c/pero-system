<?php
/**
 * Warehouse System Login Page
 * Tenant user authentication
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Redirect if already logged in
if (Session::isUserLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
$tenant_id = $_GET['tenant'] ?? '';
$show_tenant_form = empty($tenant_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    
    if ($action === 'verify_tenant') {
        // Verify tenant exists and is active
        $tenant_id = Security::sanitizeInput($_POST['tenant_id'] ?? '');
        
        if (empty($tenant_id)) {
            $error_message = 'يرجى إدخال معرف الشركة';
        } else {
            try {
                $pdo = DatabaseConfig::getMainConnection();
                $stmt = $pdo->prepare("
                    SELECT id, tenant_id, company_name, is_active, is_approved 
                    FROM tenants 
                    WHERE tenant_id = ? AND is_approved = 1
                ");
                $stmt->execute([$tenant_id]);
                $tenant = $stmt->fetch();
                
                if ($tenant) {
                    if ($tenant['is_active']) {
                        $show_tenant_form = false;
                    } else {
                        $error_message = 'هذه الشركة معطلة حالياً. يرجى التواصل مع الإدارة';
                    }
                } else {
                    $error_message = 'معرف الشركة غير موجود أو لم تتم الموافقة عليه';
                }
            } catch (Exception $e) {
                error_log('Tenant verification error: ' . $e->getMessage());
                $error_message = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً';
            }
        }
    } elseif ($action === 'login') {
        // Login process
        $tenant_id = Security::sanitizeInput($_POST['tenant_id'] ?? '');
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        // Rate limiting
        if (!Security::checkRateLimit('user_login_' . $tenant_id, MAX_LOGIN_ATTEMPTS, 3600)) {
            $error_message = 'تم تجاوز عدد محاولات تسجيل الدخول المسموح به. يرجى المحاولة لاحقاً';
        } else {
            // Basic validation
            if (empty($tenant_id) || empty($username) || empty($password)) {
                $error_message = 'يرجى إدخال جميع البيانات المطلوبة';
            } else {
                try {
                    // Verify tenant first
                    $main_pdo = DatabaseConfig::getMainConnection();
                    $stmt = $main_pdo->prepare("
                        SELECT id, tenant_id, company_name, is_active, is_approved 
                        FROM tenants 
                        WHERE tenant_id = ? AND is_approved = 1 AND is_active = 1
                    ");
                    $stmt->execute([$tenant_id]);
                    $tenant_data = $stmt->fetch();
                    
                    if (!$tenant_data) {
                        $error_message = 'معرف الشركة غير صحيح أو الشركة غير نشطة';
                    } else {
                        // Get tenant database connection
                        $tenant_pdo = DatabaseConfig::getTenantConnection($tenant_id);
                        
                        $stmt = $tenant_pdo->prepare("
                            SELECT id, username, email, password_hash, full_name, role, permissions, is_active,
                                   login_attempts, locked_until
                            FROM users 
                            WHERE (username = ? OR email = ?) AND is_active = 1
                        ");
                        $stmt->execute([$username, $username]);
                        $user = $stmt->fetch();
                        
                        if ($user) {
                            // Check if account is locked
                            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                                $error_message = 'الحساب مؤقت مؤقتاً. يرجى المحاولة لاحقاً';
                            } elseif (Security::verifyPassword($password, $user['password_hash'])) {
                                // Login successful
                                Session::loginTenantUser($user, $tenant_data);
                                
                                // Reset login attempts
                                $stmt = $tenant_pdo->prepare("
                                    UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?
                                ");
                                $stmt->execute([$user['id']]);
                                
                                // Log the login
                                Utils::logActivity(
                                    $tenant_pdo,
                                    $user['id'],
                                    'login',
                                    null,
                                    null,
                                    null,
                                    null,
                                    'تسجيل دخول ناجح للنظام'
                                );
                                
                                // Set remember me cookie if requested
                                if ($remember_me) {
                                    $token = Security::generateRandomString(64);
                                    // You would store this token in database and check it on future visits
                                    setcookie('remember_user', $token, time() + (30 * 24 * 3600), '/', '', true, true);
                                }
                                
                                header('Location: dashboard.php');
                                exit;
                            } else {
                                // Wrong password - increment login attempts
                                $new_attempts = $user['login_attempts'] + 1;
                                $locked_until = null;
                                
                                if ($new_attempts >= MAX_LOGIN_ATTEMPTS) {
                                    $locked_until = date('Y-m-d H:i:s', time() + 1800); // Lock for 30 minutes
                                    $error_message = 'تم قفل الحساب لمدة 30 دقيقة بسبب تجاوز عدد المحاولات المسموح به';
                                } else {
                                    $remaining = MAX_LOGIN_ATTEMPTS - $new_attempts;
                                    $error_message = "كلمة المرور غير صحيحة. المحاولات المتبقية: {$remaining}";
                                }
                                
                                $stmt = $tenant_pdo->prepare("
                                    UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?
                                ");
                                $stmt->execute([$new_attempts, $locked_until, $user['id']]);
                                
                                // Log failed attempt
                                Utils::logActivity(
                                    $tenant_pdo,
                                    $user['id'],
                                    'login_failed',
                                    null,
                                    null,
                                    null,
                                    null,
                                    'محاولة تسجيل دخول فاشلة - كلمة مرور خاطئة'
                                );
                            }
                        } else {
                            $error_message = 'اسم المستخدم غير موجود';
                        }
                    }
                } catch (Exception $e) {
                    error_log('User login error: ' . $e->getMessage());
                    $error_message = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً';
                }
            }
        }
    }
}

// If tenant_id is provided in URL, verify it
if (!empty($tenant_id) && $show_tenant_form) {
    try {
        $pdo = DatabaseConfig::getMainConnection();
        $stmt = $pdo->prepare("
            SELECT id, tenant_id, company_name, is_active, is_approved 
            FROM tenants 
            WHERE tenant_id = ? AND is_approved = 1
        ");
        $stmt->execute([$tenant_id]);
        $tenant = $stmt->fetch();
        
        if ($tenant && $tenant['is_active']) {
            $show_tenant_form = false;
        } else {
            $tenant_id = '';
            $show_tenant_form = true;
        }
    } catch (Exception $e) {
        $tenant_id = '';
        $show_tenant_form = true;
    }
}

$page_title = 'تسجيل الدخول';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SYSTEM_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo SYSTEM_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #008040ff 0%, #2d8d55ff 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></svg>') repeat;
            animation: float 20s linear infinite;
        }
        
        @keyframes float {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .login-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2.5rem;
            position: relative;
            z-index: 1;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        .login-body {
            padding: 3rem 2rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 1rem;
            color: #648b83ff;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        
        .step.completed {
            background: var(--success-color);
            color: white;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.875rem 1rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
            transform: translateY(-2px);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #008040ff 0%, #0c8154ff 100%);
            border: none;
            border-radius: 10px;
            padding: 0.875rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 235, 186, 0.3);
            color: white;
        }
        
        .btn-secondary {
            background: #648b75ff;
            border: none;
            border-radius: 10px;
            padding: 0.875rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #476952ff;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 2rem;
        }
        
        .company-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .company-info h5 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="login-container">
                    <div class="login-header">
                        <i class="fas fa-warehouse mb-3" style="font-size: 3rem;"></i>
                        <h1>نظام إدارة المخازن</h1>
                        <p>نظام شامل لإدارة المخازن والمخزون</p>
                    </div>
                    
                    <div class="login-body">
                        
                        
                        <!-- Error Message -->
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['message'])): ?>
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo htmlspecialchars($_GET['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($show_tenant_form): ?>
                            <!-- Step 1: Tenant Verification -->
                            <div class="fade-in">
                                <h4 class="text-center mb-4">
                                    <i class="fas fa-building me-2" style="color:'#008040ff' ;"></i>
                                    تحديد الشركة
                                </h4>
                                
                                <form method="POST" action="" id="tenantForm">
                                    <input type="hidden" name="action" value="verify_tenant">
                                    
                                    <div class="mb-3">
                                        <label for="tenant_id" class="form-label">
                                            <i class="fas fa-id-card me-2"></i>معرف الشركة
                                        </label>
                                        <input type="text" class="form-control" id="tenant_id" name="tenant_id" 
                                               value="<?php echo htmlspecialchars($_POST['tenant_id'] ?? $tenant_id); ?>" 
                                               placeholder="أدخل معرف الشركة" required autofocus>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            معرف الشركة المقدم من إدارة النظام
                                        </small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-login">
                                        <i class="fas fa-arrow-left me-2"></i>متابعة
                                    </button>
                                </form>
                            </div>
                            
                        <?php else: ?>
                            <!-- Step 2: User Login -->
                            <div class="fade-in">
                                <!-- Company Info -->
                                <div class="company-info">
                                    <h5>
                                        <i class="fas fa-building me-2"></i>
                                        <?php echo htmlspecialchars($tenant['company_name'] ?? 'الشركة'); ?>
                                    </h5>
                                    <p class="mb-0 text-muted">
                                        <small>معرف الشركة: <?php echo htmlspecialchars($tenant_id); ?></small>
                                    </p>
                                </div>
                                
                                <h4 class="text-center mb-4">
                                    <i class="fas fa-user me-2 text-primary"></i>
                                    تسجيل الدخول
                                </h4>
                                
                                <form method="POST" action="" id="loginForm">
                                    <input type="hidden" name="action" value="login">
                                    <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($tenant_id); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user me-2"></i>اسم المستخدم أو البريد الإلكتروني
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                               placeholder="أدخل اسم المستخدم" required autofocus>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>كلمة المرور
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="أدخل كلمة المرور" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                                        <label class="form-check-label" for="remember_me">
                                            تذكرني
                                        </label>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <a href="?" class="btn btn-secondary w-100">
                                                <i class="fas fa-arrow-right me-2"></i>تغيير الشركة
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <button type="submit" class="btn btn-login w-100">
                                                <i class="fas fa-sign-in-alt me-2"></i>تسجيل الدخول
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <div class="text-muted small">
                                الإصدار <?php echo SYSTEM_VERSION; ?> | طور بواسطة <?php echo SYSTEM_AUTHOR; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Form validation and loading states
        document.getElementById('tenantForm')?.addEventListener('submit', function(e) {
            const tenantId = document.getElementById('tenant_id').value.trim();
            
            if (!tenantId) {
                e.preventDefault();
                alert('يرجى إدخال معرف الشركة');
                return;
            }
            
            // Show loading state
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري التحقق...';
            button.disabled = true;
        });
        
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('يرجى إدخال جميع البيانات المطلوبة');
                return;
            }
            
            // Show loading state
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري تسجيل الدخول...';
            button.disabled = true;
        });
        
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                // Auto-dismiss after 5 seconds, but only if it's not an error
                if (!alert.classList.contains('alert-danger')) {
                    setTimeout(function() {
                        bsAlert.close();
                    }, 5000);
                }
            });
        }, 100);
    </script>
</body>
</html>