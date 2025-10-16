<?php
/**
 * Developer Portal Login Page
 * Authentication for developers
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Redirect if already logged in
if (Session::isDeveloperLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
$login_attempts = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = Security::sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Rate limiting
    if (!Security::checkRateLimit('developer_login', MAX_LOGIN_ATTEMPTS, 3600)) {
        $error_message = 'تم تجاوز عدد محاولات تسجيل الدخول المسموح به. يرجى المحاولة لاحقاً.';
    } else {
        // Basic validation
        if (empty($username) || empty($password)) {
            $error_message = 'يرجى إدخال اسم المستخدم وكلمة المرور';
        } else {
            try {
                $pdo = DatabaseConfig::getMainConnection();
                $stmt = $pdo->prepare("
                    SELECT id, username, email, password_hash, full_name, phone, is_active 
                    FROM developer_accounts 
                    WHERE (username = ? OR email = ?) AND is_active = 1
                ");
                $stmt->execute([$username, $username]);
                $developer = $stmt->fetch();
                
                if ($developer && Security::verifyPassword($password, $developer['password_hash'])) {
                    // Login successful
                    Session::loginDeveloper($developer);
                    
                    // Log the login
                    $stmt = $pdo->prepare("
                        INSERT INTO system_activity_logs (user_type, user_id, action, description, ip_address, user_agent) 
                        VALUES ('developer', ?, 'login', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $developer['id'],
                        'تسجيل دخول مطور ناجح',
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                    
                    // Set remember me cookie if requested
                    if ($remember_me) {
                        $token = Security::generateRandomString(64);
                        // You would store this token in database and check it on future visits
                        setcookie('remember_developer', $token, time() + (30 * 24 * 3600), '/', '', true, true);
                    }
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة';
                    
                    // Log failed attempt
                    if ($developer) {
                        $stmt = $pdo->prepare("
                            INSERT INTO system_activity_logs (user_type, user_id, action, description, ip_address, user_agent) 
                            VALUES ('developer', ?, 'login_failed', ?, ?, ?)
                        ");
                        $stmt->execute([
                            $developer['id'],
                            'محاولة تسجيل دخول فاشلة - كلمة مرور خاطئة',
                            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                        ]);
                    }
                }
            } catch (Exception $e) {
                error_log('Developer login error: ' . $e->getMessage());
                $error_message = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً';
            }
        }
    }
}

$page_title = 'تسجيل دخول المطور';
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
    
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #008040ff 0%, #3e8541ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #008040ff 0%, #008040 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: 700;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        
        .form-control:focus {
            border-color: #25eb9fff;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
            color: white;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .developer-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="developer-badge">
        <i class="fas fa-code me-2"></i>لوحة المطور
    </div>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-cogs mb-3" style="font-size: 2rem;"></i>
                        <h2>لوحة المطور</h2>
                        <p>نظام إدارة المخازن</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="loginForm">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>اسم المستخدم أو البريد الإلكتروني
                                </label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>كلمة المرور
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
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
                            
                            <button type="submit" class="btn btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>تسجيل الدخول
                            </button>
                        </form>
                        
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
        document.getElementById('togglePassword').addEventListener('click', function() {
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
        
        // Focus on username field
        document.getElementById('username').focus();
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
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
    </script>
</body>
</html>