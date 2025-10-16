<?php
/**
 * System Settings
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Initialize security and session
Security::initialize();
Session::initialize();

// Check authentication and admin privileges
Session::requireTenantLogin();
if (Session::getUserRole() !== 'admin') {
    Session::setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه الصفحة');
    header('Location: dashboard.php');
    exit;
}

// Get database connection
$pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_company_info') {
        // Validate CSRF token
        if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
            header('Location: settings.php');
            exit;
        }
        
        $company_data = [
            'company_name' => Security::sanitizeInput($_POST['company_name'] ?? ''),
            'company_name_en' => Security::sanitizeInput($_POST['company_name_en'] ?? ''),
            'commercial_registration' => Security::sanitizeInput($_POST['commercial_registration'] ?? ''),
            'tax_number' => Security::sanitizeInput($_POST['tax_number'] ?? ''),
            'address' => Security::sanitizeInput($_POST['address'] ?? ''),
            'city' => Security::sanitizeInput($_POST['city'] ?? ''),
            'phone' => Security::sanitizeInput($_POST['phone'] ?? ''),
            'email' => Security::sanitizeInput($_POST['email'] ?? ''),
            'website' => Security::sanitizeInput($_POST['website'] ?? '')
        ];
        
        $errors = [];
        
        if (empty($company_data['company_name'])) {
            $errors[] = 'اسم الشركة مطلوب';
        }
        
        if (empty($errors)) {
            try {
                // Check if settings exist
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'company_info'");
                $exists = $stmt->fetch()['count'] > 0;
                
                $json_data = json_encode($company_data);
                
                if ($exists) {
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_info'");
                    $stmt->execute([$json_data]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('company_info', ?)");
                    $stmt->execute([$json_data]);
                }
                
                Utils::logActivity($pdo, Session::getUserId(), 'update_company_info', 'settings', null, null, $company_data);
                Session::setFlash('success', 'تم تحديث معلومات الشركة بنجاح');
            } catch (Exception $e) {
                error_log('Settings update error: ' . $e->getMessage());
                Session::setFlash('error', 'حدث خطأ أثناء تحديث الإعدادات');
            }
        } else {
            Session::setFlash('error', implode('<br>', $errors));
        }
        
        header('Location: settings.php');
        exit;
    }
    
    if ($action === 'update_system_settings') {
        // Validate CSRF token
        if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
            header('Location: settings.php');
            exit;
        }
        
        $system_data = [
            'currency' => Security::sanitizeInput($_POST['currency'] ?? 'SAR'),
            'timezone' => Security::sanitizeInput($_POST['timezone'] ?? 'Asia/Riyadh'),
            'date_format' => Security::sanitizeInput($_POST['date_format'] ?? 'd/m/Y'),
            'default_language' => Security::sanitizeInput($_POST['default_language'] ?? 'ar'),
            'tax_rate' => (float)($_POST['tax_rate'] ?? 15),
            'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 10),
            'auto_backup' => isset($_POST['auto_backup']) ? 1 : 0,
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0
        ];
        
        try {
            // Update or insert system settings
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'system_settings'");
            $exists = $stmt->fetch()['count'] > 0;
            
            $json_data = json_encode($system_data);
            
            if ($exists) {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'system_settings'");
                $stmt->execute([$json_data]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('system_settings', ?)");
                $stmt->execute([$json_data]);
            }
            
            Utils::logActivity($pdo, Session::getUserId(), 'update_system_settings', 'settings', null, null, $system_data);
            Session::setFlash('success', 'تم تحديث إعدادات النظام بنجاح');
        } catch (Exception $e) {
            error_log('System settings update error: ' . $e->getMessage());
            Session::setFlash('error', 'حدث خطأ أثناء تحديث الإعدادات');
        }
        
        header('Location: settings.php');
        exit;
    }
}

// Get current settings
$company_info = [];
$system_settings = [];

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_info'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $company_info = json_decode($result['setting_value'], true) ?: [];
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'system_settings'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $system_settings = json_decode($result['setting_value'], true) ?: [];
    }
} catch (Exception $e) {
    error_log('Settings fetch error: ' . $e->getMessage());
}

// Default values
$company_info = array_merge([
    'company_name' => '',
    'company_name_en' => '',
    'commercial_registration' => '',
    'tax_number' => '',
    'address' => '',
    'city' => '',
    'phone' => '',
    'email' => '',
    'website' => ''
], $company_info);

$system_settings = array_merge([
    'currency' => 'SAR',
    'timezone' => 'Asia/Riyadh',
    'date_format' => 'd/m/Y',
    'default_language' => 'ar',
    'tax_rate' => 15,
    'low_stock_threshold' => 10,
    'auto_backup' => 0,
    'email_notifications' => 1
], $system_settings);

$page_title = 'إعدادات النظام';
$current_page = 'settings';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo Session::getTenantName(); ?></title>
    
    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .settings-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
        }
        
        .nav-pills .nav-link {
            border-radius: 10px;
            margin: 2px;
            color: #6c757d;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #dee2e6;
        }
    </style>
</head>
<body>
    <?php include '../shared/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4">
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="page-title">
                            <i class="fas fa-cog me-2"></i>
                            إعدادات النظام
                        </h2>
                    </div>
                    
                    <!-- Flash Messages -->
                    <?php if (Session::hasFlash('success')): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo Session::getFlash('success'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (Session::hasFlash('error')): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo Session::getFlash('error'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Settings Navigation -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="nav flex-column nav-pills" id="settings-tabs" role="tablist">
                                <button class="nav-link active" id="company-tab" data-bs-toggle="pill" data-bs-target="#company" type="button" role="tab">
                                    <i class="fas fa-building me-2"></i>
                                    معلومات الشركة
                                </button>
                                <button class="nav-link" id="system-tab" data-bs-toggle="pill" data-bs-target="#system" type="button" role="tab">
                                    <i class="fas fa-cogs me-2"></i>
                                    إعدادات النظام
                                </button>
                                <button class="nav-link" id="users-tab" data-bs-toggle="pill" data-bs-target="#users" type="button" role="tab">
                                    <i class="fas fa-users me-2"></i>
                                    إدارة المستخدمين
                                </button>
                                <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    إعدادات الأمان
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-9">
                            <div class="tab-content" id="settings-content">
                                <!-- Company Information -->
                                <div class="tab-pane fade show active" id="company" role="tabpanel">
                                    <div class="settings-card">
                                        <div class="settings-header">
                                            <h5 class="mb-0">
                                                <i class="fas fa-building me-2"></i>
                                                معلومات الشركة
                                            </h5>
                                        </div>
                                        
                                        <div class="card-body">
                                            <form method="POST">
                                                <?php echo Security::getCSRFInput(); ?>
                                                <input type="hidden" name="action" value="update_company_info">
                                                
                                                <div class="form-section">
                                                    <h6 class="section-title">المعلومات الأساسية</h6>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="company_name" class="form-label">اسم الشركة <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company_info['company_name']); ?>" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="company_name_en" class="form-label">اسم الشركة (بالإنجليزية)</label>
                                                                <input type="text" class="form-control" id="company_name_en" name="company_name_en" value="<?php echo htmlspecialchars($company_info['company_name_en']); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="commercial_registration" class="form-label">السجل التجاري</label>
                                                                <input type="text" class="form-control" id="commercial_registration" name="commercial_registration" value="<?php echo htmlspecialchars($company_info['commercial_registration']); ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="tax_number" class="form-label">الرقم الضريبي</label>
                                                                <input type="text" class="form-control" id="tax_number" name="tax_number" value="<?php echo htmlspecialchars($company_info['tax_number']); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-section">
                                                    <h6 class="section-title">معلومات الاتصال</h6>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="phone" class="form-label">رقم الهاتف</label>
                                                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($company_info['phone']); ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($company_info['email']); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="city" class="form-label">المدينة</label>
                                                                <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($company_info['city']); ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="website" class="form-label">الموقع الإلكتروني</label>
                                                                <input type="url" class="form-control" id="website" name="website" value="<?php echo htmlspecialchars($company_info['website']); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="address" class="form-label">العنوان</label>
                                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($company_info['address']); ?></textarea>
                                                    </div>
                                                </div>
                                                
                                                <div class="text-end">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>
                                                        حفظ معلومات الشركة
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- System Settings -->
                                <div class="tab-pane fade" id="system" role="tabpanel">
                                    <div class="settings-card">
                                        <div class="settings-header">
                                            <h5 class="mb-0">
                                                <i class="fas fa-cogs me-2"></i>
                                                إعدادات النظام
                                            </h5>
                                        </div>
                                        
                                        <div class="card-body">
                                            <form method="POST">
                                                <?php echo Security::getCSRFInput(); ?>
                                                <input type="hidden" name="action" value="update_system_settings">
                                                
                                                <div class="form-section">
                                                    <h6 class="section-title">الإعدادات العامة</h6>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="currency" class="form-label">العملة الافتراضية</label>
                                                                <select class="form-select" id="currency" name="currency">
                                                                    <option value="SAR" <?php echo $system_settings['currency'] === 'SAR' ? 'selected' : ''; ?>>ريال سعودي (SAR)</option>
                                                                    <option value="USD" <?php echo $system_settings['currency'] === 'USD' ? 'selected' : ''; ?>>دولار أمريكي (USD)</option>
                                                                    <option value="EUR" <?php echo $system_settings['currency'] === 'EUR' ? 'selected' : ''; ?>>يورو (EUR)</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="timezone" class="form-label">المنطقة الزمنية</label>
                                                                <select class="form-select" id="timezone" name="timezone">
                                                                    <option value="Asia/Riyadh" <?php echo $system_settings['timezone'] === 'Asia/Riyadh' ? 'selected' : ''; ?>>الرياض (Asia/Riyadh)</option>
                                                                    <option value="Asia/Dubai" <?php echo $system_settings['timezone'] === 'Asia/Dubai' ? 'selected' : ''; ?>>دبي (Asia/Dubai)</option>
                                                                    <option value="Africa/Cairo" <?php echo $system_settings['timezone'] === 'Africa/Cairo' ? 'selected' : ''; ?>>القاهرة (Africa/Cairo)</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="tax_rate" class="form-label">معدل الضريبة (%)</label>
                                                                <input type="number" class="form-control" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100" value="<?php echo $system_settings['tax_rate']; ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="low_stock_threshold" class="form-label">حد تنبيه المخزون المنخفض</label>
                                                                <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold" min="0" value="<?php echo $system_settings['low_stock_threshold']; ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-section">
                                                    <h6 class="section-title">الخيارات المتقدمة</h6>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-check mb-3">
                                                                <input class="form-check-input" type="checkbox" id="auto_backup" name="auto_backup" <?php echo $system_settings['auto_backup'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="auto_backup">
                                                                    النسخ الاحتياطي التلقائي
                                                                </label>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <div class="form-check mb-3">
                                                                <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $system_settings['email_notifications'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="email_notifications">
                                                                    إشعارات البريد الإلكتروني
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="text-end">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>
                                                        حفظ إعدادات النظام
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Users Management -->
                                <div class="tab-pane fade" id="users" role="tabpanel">
                                    <div class="settings-card">
                                        <div class="settings-header">
                                            <h5 class="mb-0">
                                                <i class="fas fa-users me-2"></i>
                                                إدارة المستخدمين
                                            </h5>
                                        </div>
                                        
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6>قائمة المستخدمين</h6>
                                                <a href="users.php" class="btn btn-primary">
                                                    <i class="fas fa-plus me-2"></i>
                                                    إضافة مستخدم جديد
                                                </a>
                                            </div>
                                            
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                لإدارة المستخدمين بشكل مفصل، يرجى زيارة صفحة إدارة المستخدمين المخصصة.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Security Settings -->
                                <div class="tab-pane fade" id="security" role="tabpanel">
                                    <div class="settings-card">
                                        <div class="settings-header">
                                            <h5 class="mb-0">
                                                <i class="fas fa-shield-alt me-2"></i>
                                                إعدادات الأمان
                                            </h5>
                                        </div>
                                        
                                        <div class="card-body">
                                            <div class="form-section">
                                                <h6 class="section-title">سياسة كلمات المرور</h6>
                                                
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    إعدادات الأمان متقدمة سيتم إضافتها في التحديثات القادمة.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>