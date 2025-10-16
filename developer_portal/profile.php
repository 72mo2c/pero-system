<?php
/**
 * Admin Profile Page
 * صفحة الملف الشخصي للمدير
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$current_page = 'profile';
$page_title = 'الملف الشخصي';

// الحصول على قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

$success_message = '';
$error_message = '';

// إنشاء جدول المديرين إذا لم يكن موجوداً
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        phone VARCHAR(20),
        role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
        avatar VARCHAR(255),
        last_login TIMESTAMP NULL,
        is_active BOOLEAN DEFAULT TRUE,
        two_factor_enabled BOOLEAN DEFAULT FALSE,
        two_factor_secret VARCHAR(32),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // إضافة مدير افتراضي إذا لم يكن موجوداً
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        'admin',
        'admin@example.com',
        password_hash('admin123', PASSWORD_DEFAULT),
        'مدير النظام',
        'super_admin'
    ]);
} catch (Exception $e) {
    $error_message = "خطأ في إنشاء جدول المديرين: " . $e->getMessage();
}

// الحصول على بيانات المدير الحالي
$current_admin = null;
$admin_id = $_SESSION['admin_id'] ?? 1; // افتراضياً المدير الأول

try {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $current_admin = $stmt->fetch();
} catch (Exception $e) {
    $error_message = "خطأ في جلب بيانات المدير: " . $e->getMessage();
}

// معالجة العمليات
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'update_profile':
                $full_name = Security::sanitizeInput($_POST['full_name']);
                $email = Security::sanitizeInput($_POST['email'], 'email');
                $phone = Security::sanitizeInput($_POST['phone']);
                
                // التحقق من عدم وجود بريد إلكتروني مُكرر
                $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $admin_id]);
                
                if ($stmt->fetch()) {
                    throw new Exception("البريد الإلكتروني مُستخدم من قبل مدير آخر");
                }
                
                $stmt = $pdo->prepare("UPDATE admin_users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $admin_id]);
                
                $success_message = "تم تحديث البيانات الشخصية بنجاح";
                
                // تحديث البيانات المحلية
                $current_admin['full_name'] = $full_name;
                $current_admin['email'] = $email;
                $current_admin['phone'] = $phone;
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // التحقق من كلمة المرور الحالية
                if (!password_verify($current_password, $current_admin['password_hash'])) {
                    throw new Exception("كلمة المرور الحالية غير صحيحة");
                }
                
                // التحقق من تطابق كلمة المرور الجديدة
                if ($new_password !== $confirm_password) {
                    throw new Exception("كلمة المرور الجديدة غير متطابقة");
                }
                
                // التحقق من قوة كلمة المرور
                if (strlen($new_password) < 8) {
                    throw new Exception("كلمة المرور يجب أن تكون 8 أحرف على الأقل");
                }
                
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_password_hash, $admin_id]);
                
                $success_message = "تم تغيير كلمة المرور بنجاح";
                break;
                
            case 'toggle_2fa':
                $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE admin_users SET two_factor_enabled = ? WHERE id = ?");
                $stmt->execute([$two_factor_enabled, $admin_id]);
                
                $current_admin['two_factor_enabled'] = $two_factor_enabled;
                
                $message = $two_factor_enabled ? "تم تفعيل التحقق بخطوتين" : "تم إلغاء التحقق بخطوتين";
                $success_message = $message;
                break;
                
            case 'upload_avatar':
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                    $upload_dir = 'uploads/avatars/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception("صيغة الملف غير مدعومة. الصيغ المدعومة: " . implode(', ', $allowed_extensions));
                    }
                    
                    if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) { // 2MB
                        throw new Exception("حجم الملف كبير جداً. الحد الأقصى 2MB");
                    }
                    
                    $new_filename = 'avatar_' . $admin_id . '_' . time() . '.' . $file_extension;
                    $target_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
                        // حذف الصورة القديمة
                        if ($current_admin['avatar'] && file_exists($current_admin['avatar'])) {
                            unlink($current_admin['avatar']);
                        }
                        
                        $stmt = $pdo->prepare("UPDATE admin_users SET avatar = ? WHERE id = ?");
                        $stmt->execute([$target_path, $admin_id]);
                        
                        $current_admin['avatar'] = $target_path;
                        $success_message = "تم رفع الصورة الشخصية بنجاح";
                    } else {
                        throw new Exception("حدث خطأ أثناء رفع الصورة");
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// إحصائيات نشاط المدير
$admin_stats = [];

try {
    // آخر تسجيل دخول
    $admin_stats['last_login'] = $current_admin['last_login'] ?? 'لم يتم تسجيل الدخول مسبقاً';
    
    // عدد الإجراءات في آخر 30 يوم (من جدول السجلات)
    $stmt = $pdo->prepare("SELECT COUNT(*) as actions FROM system_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$admin_id]);
    $admin_stats['recent_actions'] = $stmt->fetch()['actions'] ?? 0;
    
    // عدد المستأجرين المُدارين
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenants");
    $admin_stats['managed_tenants'] = $stmt->fetch()['total'] ?? 0;
    
    // معدل النشاط
    $admin_stats['activity_rate'] = $admin_stats['recent_actions'] > 0 ? 'نشط' : 'غير نشط';
    
} catch (Exception $e) {
    // تجاهل الأخطاء في الإحصائيات
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>الملف الشخصي</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">الملف الشخصي</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- معلومات الملف الشخصي -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php if ($current_admin && $current_admin['avatar'] && file_exists($current_admin['avatar'])): ?>
                                    <img src="<?php echo $current_admin['avatar']; ?>" 
                                         alt="الصورة الشخصية" 
                                         class="rounded-circle" 
                                         style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" 
                                         style="width: 120px; height: 120px;">
                                        <i class="fas fa-user fa-3x text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <h4><?php echo htmlspecialchars($current_admin['full_name'] ?? 'غير محدد'); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($current_admin['username'] ?? ''); ?></p>
                            
                            <?php 
                            $role_labels = [
                                'super_admin' => 'مدير عام',
                                'admin' => 'مدير',
                                'moderator' => 'مشرف'
                            ];
                            $role_colors = [
                                'super_admin' => 'danger',
                                'admin' => 'primary',
                                'moderator' => 'info'
                            ];
                            $role = $current_admin['role'] ?? 'admin';
                            ?>
                            <span class="badge bg-<?php echo $role_colors[$role]; ?> mb-3">
                                <?php echo $role_labels[$role]; ?>
                            </span>
                            
                            <!-- رفع صورة شخصية -->
                            <form method="POST" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="action" value="upload_avatar">
                                <div class="mb-2">
                                    <input type="file" class="form-control form-control-sm" 
                                           name="avatar" accept="image/*" required>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-upload"></i> رفع صورة جديدة
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- إحصائيات سريعة -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">إحصائيات سريعة</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-12 mb-3">
                                    <div class="border-bottom pb-2">
                                        <h5 class="text-primary"><?php echo $admin_stats['managed_tenants']; ?></h5>
                                        <small class="text-muted">المستأجرين المُدارين</small>
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <div class="border-bottom pb-2">
                                        <h5 class="text-success"><?php echo $admin_stats['recent_actions']; ?></h5>
                                        <small class="text-muted">الإجراءات (30 يوم)</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <h5 class="text-info"><?php echo $admin_stats['activity_rate']; ?></h5>
                                    <small class="text-muted">معدل النشاط</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- تبويبات الإعدادات -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="info-tab" data-bs-toggle="tab" 
                                            data-bs-target="#info" type="button" role="tab">
                                        المعلومات الشخصية
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" 
                                            data-bs-target="#password" type="button" role="tab">
                                        كلمة المرور
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" 
                                            data-bs-target="#security" type="button" role="tab">
                                        الأمان
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="profileTabsContent">
                                
                                <!-- المعلومات الشخصية -->
                                <div class="tab-pane fade show active" id="info" role="tabpanel">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="full_name" class="form-label">الاسم الكامل</label>
                                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                                           value="<?php echo htmlspecialchars($current_admin['full_name'] ?? ''); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="username" class="form-label">اسم المستخدم</label>
                                                    <input type="text" class="form-control" id="username" 
                                                           value="<?php echo htmlspecialchars($current_admin['username'] ?? ''); ?>" 
                                                           disabled>
                                                    <small class="text-muted">لا يمكن تغيير اسم المستخدم</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">البريد الإلكتروني</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?php echo htmlspecialchars($current_admin['email'] ?? ''); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="phone" class="form-label">رقم الهاتف</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                                           value="<?php echo htmlspecialchars($current_admin['phone'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="role" class="form-label">الدور</label>
                                                    <input type="text" class="form-control" id="role" 
                                                           value="<?php echo $role_labels[$current_admin['role'] ?? 'admin']; ?>" 
                                                           disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="last_login" class="form-label">آخر تسجيل دخول</label>
                                                    <input type="text" class="form-control" id="last_login" 
                                                           value="<?php echo $current_admin['last_login'] ? date('Y-m-d H:i', strtotime($current_admin['last_login'])) : 'لم يتم التسجيل'; ?>" 
                                                           disabled>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> حفظ التغييرات
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- كلمة المرور -->
                                <div class="tab-pane fade" id="password" role="tabpanel">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                                            <input type="password" class="form-control" id="current_password" 
                                                   name="current_password" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                                            <input type="password" class="form-control" id="new_password" 
                                                   name="new_password" required minlength="8">
                                            <small class="text-muted">يجب أن تكون 8 أحرف على الأقل</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                            <input type="password" class="form-control" id="confirm_password" 
                                                   name="confirm_password" required minlength="8">
                                        </div>
                                        
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-key"></i> تغيير كلمة المرور
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- الأمان -->
                                <div class="tab-pane fade" id="security" role="tabpanel">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_2fa">
                                        
                                        <div class="mb-4">
                                            <h6>التحقق بخطوتين</h6>
                                            <p class="text-muted">يضيف طبقة حماية إضافية لحسابك</p>
                                            
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="two_factor_enabled" 
                                                       name="two_factor_enabled" 
                                                       <?php echo $current_admin['two_factor_enabled'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="two_factor_enabled">
                                                    تفعيل التحقق بخطوتين
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <h6>معلومات الجلسة</h6>
                                            <table class="table table-sm">
                                                <tr>
                                                    <td><strong>عنوان IP:</strong></td>
                                                    <td><?php echo $_SERVER['REMOTE_ADDR'] ?? 'غير معروف'; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>المتصفح:</strong></td>
                                                    <td><?php echo $_SERVER['HTTP_USER_AGENT'] ?? 'غير معروف'; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>وقت الجلسة:</strong></td>
                                                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-shield-alt"></i> حفظ إعدادات الأمان
                                        </button>
                                        
                                        <a href="logout.php" class="btn btn-danger ms-2" 
                                           onclick="return confirm('هل أنت متأكد من رغبتك في تسجيل الخروج؟')">
                                            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج من جميع الأجهزة
                                        </a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// التحقق من تطابق كلمة المرور
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('كلمة المرور غير متطابقة');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include 'includes/footer.php'; ?>