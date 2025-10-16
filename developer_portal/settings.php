<?php
/**
 * System Settings Page
 * إعدادات النظام
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$current_page = 'settings';
$page_title = 'إعدادات النظام';

// الحصول على قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

$success_message = '';
$error_message = '';

// إنشاء جدول الإعدادات إذا لم يكن موجوداً
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
        description TEXT,
        category VARCHAR(50) DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    $error_message = "خطأ في إنشاء جدول الإعدادات: " . $e->getMessage();
}

// الإعدادات الافتراضية
$default_settings = [
    // إعدادات عامة
    'site_name' => ['value' => 'نظام إدارة المخازن SaaS', 'type' => 'string', 'category' => 'general', 'description' => 'اسم الموقع'],
    'site_description' => ['value' => 'نظام إدارة المخازن متعدد المستأجرين', 'type' => 'string', 'category' => 'general', 'description' => 'وصف الموقع'],
    'site_email' => ['value' => 'admin@example.com', 'type' => 'string', 'category' => 'general', 'description' => 'البريد الإلكتروني للموقع'],
    'site_phone' => ['value' => '+966-XX-XXXX-XXX', 'type' => 'string', 'category' => 'general', 'description' => 'رقم الهاتف'],
    'timezone' => ['value' => 'Asia/Riyadh', 'type' => 'string', 'category' => 'general', 'description' => 'المنطقة الزمنية'],
    'default_language' => ['value' => 'ar', 'type' => 'string', 'category' => 'general', 'description' => 'اللغة الافتراضية'],
    
    // إعدادات الأمان
    'session_timeout' => ['value' => '3600', 'type' => 'number', 'category' => 'security', 'description' => 'مدة انتهاء الجلسة (بالثواني)'],
    'password_min_length' => ['value' => '8', 'type' => 'number', 'category' => 'security', 'description' => 'الحد الأدنى لطول كلمة المرور'],
    'enable_2fa' => ['value' => '0', 'type' => 'boolean', 'category' => 'security', 'description' => 'تفعيل التحقق بخطوتين'],
    'max_login_attempts' => ['value' => '5', 'type' => 'number', 'category' => 'security', 'description' => 'عدد محاولات تسجيل الدخول المسموحة'],
    'lockout_duration' => ['value' => '1800', 'type' => 'number', 'category' => 'security', 'description' => 'مدة الحظر بعد المحاولات الفاشلة (بالثواني)'],
    
    // إعدادات قاعدة البيانات
    'backup_interval' => ['value' => '24', 'type' => 'number', 'category' => 'database', 'description' => 'فترة النسخ الاحتياطي التلقائي (بالساعات)'],
    'keep_backups' => ['value' => '30', 'type' => 'number', 'category' => 'database', 'description' => 'عدد النسخ الاحتياطية المحفوظة'],
    'enable_auto_backup' => ['value' => '1', 'type' => 'boolean', 'category' => 'database', 'description' => 'تفعيل النسخ الاحتياطي التلقائي'],
    
    // إعدادات الاشتراكات
    'trial_period_days' => ['value' => '30', 'type' => 'number', 'category' => 'subscription', 'description' => 'مدة الفترة التجريبية (بالأيام)'],
    'max_tenants' => ['value' => '100', 'type' => 'number', 'category' => 'subscription', 'description' => 'الحد الأقصى للمستأجرين'],
    'auto_suspend_expired' => ['value' => '1', 'type' => 'boolean', 'category' => 'subscription', 'description' => 'تعليق الاشتراكات المنتهية تلقائياً'],
    
    // إعدادات الإشعارات
    'enable_email_notifications' => ['value' => '1', 'type' => 'boolean', 'category' => 'notifications', 'description' => 'تفعيل إشعارات البريد الإلكتروني'],
    'smtp_host' => ['value' => 'smtp.gmail.com', 'type' => 'string', 'category' => 'notifications', 'description' => 'خادم SMTP'],
    'smtp_port' => ['value' => '587', 'type' => 'number', 'category' => 'notifications', 'description' => 'منفذ SMTP'],
    'smtp_username' => ['value' => '', 'type' => 'string', 'category' => 'notifications', 'description' => 'اسم مستخدم SMTP'],
    'smtp_password' => ['value' => '', 'type' => 'string', 'category' => 'notifications', 'description' => 'كلمة مرور SMTP'],
    
    // إعدادات النظام
    'maintenance_mode' => ['value' => '0', 'type' => 'boolean', 'category' => 'system', 'description' => 'وضع الصيانة'],
    'debug_mode' => ['value' => '0', 'type' => 'boolean', 'category' => 'system', 'description' => 'وضع التطوير'],
    'log_level' => ['value' => 'info', 'type' => 'string', 'category' => 'system', 'description' => 'مستوى السجلات'],
    'enable_caching' => ['value' => '1', 'type' => 'boolean', 'category' => 'system', 'description' => 'تفعيل التخزين المؤقت']
];

// إدراج الإعدادات الافتراضية
try {
    foreach ($default_settings as $key => $setting) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$key, $setting['value'], $setting['type'], $setting['category'], $setting['description']]);
    }
} catch (Exception $e) {
    $error_message = "خطأ في إدراج الإعدادات الافتراضية: " . $e->getMessage();
}

// معالجة تحديث الإعدادات
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    try {
        foreach ($_POST as $key => $value) {
            if ($key !== 'action') {
                // تنظيف القيمة حسب النوع
                $stmt = $pdo->prepare("SELECT setting_type FROM system_settings WHERE setting_key = ?");
                $stmt->execute([$key]);
                $setting = $stmt->fetch();
                
                if ($setting) {
                    $clean_value = $value;
                    
                    if ($setting['setting_type'] === 'boolean') {
                        $clean_value = isset($_POST[$key]) ? '1' : '0';
                    } elseif ($setting['setting_type'] === 'number') {
                        $clean_value = is_numeric($value) ? $value : '0';
                    }
                    
                    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$clean_value, $key]);
                }
            }
        }
        $success_message = "تم حفظ الإعدادات بنجاح";
    } catch (Exception $e) {
        $error_message = "خطأ في حفظ الإعدادات: " . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY category, setting_key");
    while ($row = $stmt->fetch()) {
        $settings[$row['category']][$row['setting_key']] = $row;
    }
} catch (Exception $e) {
    $error_message = "خطأ في جلب الإعدادات: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>إعدادات النظام</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">الإعدادات</li>
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

            <form method="POST" id="settingsForm">
                <input type="hidden" name="action" value="update_settings">
                
                <!-- تبويبات الإعدادات -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">أقسام الإعدادات</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="nav nav-pills flex-column" id="settings-tabs" role="tablist">
                                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#general-tab" type="button">
                                        <i class="fas fa-cog"></i> الإعدادات العامة
                                    </button>
                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#security-tab" type="button">
                                        <i class="fas fa-shield-alt"></i> الأمان
                                    </button>
                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#database-tab" type="button">
                                        <i class="fas fa-database"></i> قاعدة البيانات
                                    </button>
                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#subscription-tab" type="button">
                                        <i class="fas fa-credit-card"></i> الاشتراكات
                                    </button>
                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#notifications-tab" type="button">
                                        <i class="fas fa-bell"></i> الإشعارات
                                    </button>
                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#system-tab" type="button">
                                        <i class="fas fa-server"></i> النظام
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-9">
                        <div class="tab-content" id="settings-tab-content">
                            
                            <!-- الإعدادات العامة -->
                            <div class="tab-pane fade show active" id="general-tab">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">الإعدادات العامة</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($settings['general'])): ?>
                                            <?php foreach ($settings['general'] as $key => $setting): ?>
                                                <div class="mb-3">
                                                    <label for="<?php echo $key; ?>" class="form-label">
                                                        <?php echo $setting['description']; ?>
                                                    </label>
                                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                                                   <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    <?php elseif ($key === 'timezone'): ?>
                                                        <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                                            <option value="Asia/Riyadh" <?php echo $setting['setting_value'] === 'Asia/Riyadh' ? 'selected' : ''; ?>>الرياض</option>
                                                            <option value="Asia/Dubai" <?php echo $setting['setting_value'] === 'Asia/Dubai' ? 'selected' : ''; ?>>دبي</option>
                                                            <option value="Europe/London" <?php echo $setting['setting_value'] === 'Europe/London' ? 'selected' : ''; ?>>لندن</option>
                                                            <option value="America/New_York" <?php echo $setting['setting_value'] === 'America/New_York' ? 'selected' : ''; ?>>نيويورك</option>
                                                        </select>
                                                    <?php elseif ($key === 'default_language'): ?>
                                                        <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                                            <option value="ar" <?php echo $setting['setting_value'] === 'ar' ? 'selected' : ''; ?>>العربية</option>
                                                            <option value="en" <?php echo $setting['setting_value'] === 'en' ? 'selected' : ''; ?>>English</option>
                                                        </select>
                                                    <?php else: ?>
                                                        <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>" 
                                                               class="form-control" 
                                                               id="<?php echo $key; ?>" 
                                                               name="<?php echo $key; ?>"
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- إعدادات الأمان -->
                            <div class="tab-pane fade" id="security-tab">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">إعدادات الأمان</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($settings['security'])): ?>
                                            <?php foreach ($settings['security'] as $key => $setting): ?>
                                                <div class="mb-3">
                                                    <label for="<?php echo $key; ?>" class="form-label">
                                                        <?php echo $setting['description']; ?>
                                                    </label>
                                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                                                   <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    <?php else: ?>
                                                        <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>" 
                                                               class="form-control" 
                                                               id="<?php echo $key; ?>" 
                                                               name="<?php echo $key; ?>"
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- إعدادات قاعدة البيانات -->
                            <div class="tab-pane fade" id="database-tab">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">إعدادات قاعدة البيانات</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($settings['database'])): ?>
                                            <?php foreach ($settings['database'] as $key => $setting): ?>
                                                <div class="mb-3">
                                                    <label for="<?php echo $key; ?>" class="form-label">
                                                        <?php echo $setting['description']; ?>
                                                    </label>
                                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                                                   <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    <?php else: ?>
                                                        <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>" 
                                                               class="form-control" 
                                                               id="<?php echo $key; ?>" 
                                                               name="<?php echo $key; ?>"
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- إعدادات الاشتراكات -->
                            <div class="tab-pane fade" id="subscription-tab">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">إعدادات الاشتراكات</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($settings['subscription'])): ?>
                                            <?php foreach ($settings['subscription'] as $key => $setting): ?>
                                                <div class="mb-3">
                                                    <label for="<?php echo $key; ?>" class="form-label">
                                                        <?php echo $setting['description']; ?>
                                                    </label>
                                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                                                   <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    <?php else: ?>
                                                        <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>" 
                                                               class="form-control" 
                                                               id="<?php echo $key; ?>" 
                                                               name="<?php echo $key; ?>"
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- إعدادات الإشعارات -->
                            <div class="tab-pane fade" id="notifications-tab">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">إعدادات الإشعارات</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($settings['notifications'])): ?>
                                            <?php foreach ($settings['notifications'] as $key => $setting): ?>
                                                <div class="mb-3">
                                                    <label for="<?php echo $key; ?>" class="form-label">
                                                        <?php echo $setting['description']; ?>
                                                    </label>
                                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                                                   <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    <?php elseif (strpos($key, 'password') !== false): ?>
                                                        <input type="password" class="form-control" 
                                                               id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                               placeholder="اتركه فارغاً لعدم التغيير">
                                                    <?php else: ?>
                                                        <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>" 
                                                               class="form-control" 
                                                               id="<?php echo $key; ?>" 
                                                               name="<?php echo $key; ?>"
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- إعدادات النظام -->
                            <div class="tab-pane fade" id="system-tab">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">إعدادات النظام</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($settings['system'])): ?>
                                            <?php foreach ($settings['system'] as $key => $setting): ?>
                                                <div class="mb-3">
                                                    <label for="<?php echo $key; ?>" class="form-label">
                                                        <?php echo $setting['description']; ?>
                                                    </label>
                                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                                                                   <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    <?php elseif ($key === 'log_level'): ?>
                                                        <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                                            <option value="debug" <?php echo $setting['setting_value'] === 'debug' ? 'selected' : ''; ?>>تصحيح</option>
                                                            <option value="info" <?php echo $setting['setting_value'] === 'info' ? 'selected' : ''; ?>>معلومات</option>
                                                            <option value="warning" <?php echo $setting['setting_value'] === 'warning' ? 'selected' : ''; ?>>تحذير</option>
                                                            <option value="error" <?php echo $setting['setting_value'] === 'error' ? 'selected' : ''; ?>>خطأ</option>
                                                        </select>
                                                    <?php else: ?>
                                                        <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>" 
                                                               class="form-control" 
                                                               id="<?php echo $key; ?>" 
                                                               name="<?php echo $key; ?>"
                                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- أزرار الحفظ -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                        <i class="fas fa-undo"></i> إعادة تعيين
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> حفظ الإعدادات
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    if (confirm('هل أنت متأكد من رغبتك في إعادة تعيين جميع الإعدادات؟')) {
        document.getElementById('settingsForm').reset();
    }
}

// تأكيد قبل الحفظ
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    if (!confirm('هل أنت متأكد من رغبتك في حفظ هذه الإعدادات؟')) {
        e.preventDefault();
    }
});
</script>

<?php include 'includes/footer.php'; ?>