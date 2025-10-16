<?php
/**
 * System Updates Page
 * صفحة تحديثات النظام
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$current_page = 'updates';
$page_title = 'تحديثات النظام';

// الحصول على قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

$success_message = '';
$error_message = '';

// إنشاء جدول التحديثات إذا لم يكن موجوداً
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        version VARCHAR(20) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        changes TEXT,
        update_type ENUM('major', 'minor', 'patch', 'security') DEFAULT 'minor',
        status ENUM('available', 'downloading', 'installing', 'installed', 'failed') DEFAULT 'available',
        release_date DATE,
        install_date TIMESTAMP NULL,
        file_path VARCHAR(500),
        file_size BIGINT DEFAULT 0,
        checksum VARCHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    $error_message = "خطأ في إنشاء جدول التحديثات: " . $e->getMessage();
}

// إضافة بعض التحديثات التجريبية
$sample_updates = [
    [
        'version' => '2.1.0',
        'title' => 'تحديث كبير - تحسينات الأمان والأداء',
        'description' => 'تحديث شامل يتضمن تحسينات أمنية مهمة وتحسين الأداء العام للنظام',
        'changes' => '• تحسين أمان تسجيل الدخول\n• إضافة التحقق بخطوتين\n• تحسين سرعة قاعدة البيانات\n• إصلاح مشاكل التصدير\n• واجهة مستخدم محدثة',
        'update_type' => 'major',
        'release_date' => '2024-12-15',
        'file_size' => 15728640 // 15 MB
    ],
    [
        'version' => '2.0.3',
        'title' => 'إصلاحات أمنية مهمة',
        'description' => 'تحديث أمني عاجل لإصلاح ثغرات محتملة في النظام',
        'changes' => '• إصلاح ثغرة في نظام المصادقة\n• تحديث مكتبات الأمان\n• تحسين حماية البيانات\n• إصلاح مشاكل الجلسات',
        'update_type' => 'security',
        'release_date' => '2024-11-20',
        'status' => 'installed',
        'install_date' => '2024-11-21 10:30:00',
        'file_size' => 5242880 // 5 MB
    ],
    [
        'version' => '2.0.2',
        'title' => 'تحسينات وإصلاحات صغيرة',
        'description' => 'إصلاحات للمشاكل المكتشفة وتحسينات صغيرة على الواجهة',
        'changes' => '• إصلاح مشكلة في التقارير\n• تحسين ترجمة النصوص\n• إصلاح أخطاء في النماذج\n• تحسين التوافق مع المتصفحات',
        'update_type' => 'patch',
        'release_date' => '2024-10-15',
        'status' => 'installed',
        'install_date' => '2024-10-16 14:20:00',
        'file_size' => 2097152 // 2 MB
    ]
];

// إدراج التحديثات التجريبية
try {
    foreach ($sample_updates as $update) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_updates (version, title, description, changes, update_type, status, release_date, install_date, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $update['version'],
            $update['title'],
            $update['description'],
            $update['changes'],
            $update['update_type'],
            $update['status'] ?? 'available',
            $update['release_date'],
            $update['install_date'] ?? null,
            $update['file_size']
        ]);
    }
} catch (Exception $e) {
    // تجاهل أخطاء الإدراج المُكررة
}

// معالجة العمليات
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'install_update':
                $update_id = $_POST['update_id'];
                
                // تحديث حالة التحديث إلى "قيد التثبيت"
                $stmt = $pdo->prepare("UPDATE system_updates SET status = 'installing' WHERE id = ?");
                $stmt->execute([$update_id]);
                
                // محاكاة عملية التثبيت
                sleep(2);
                
                // تحديث الحالة إلى "مثبت"
                $stmt = $pdo->prepare("UPDATE system_updates SET status = 'installed', install_date = NOW() WHERE id = ?");
                $stmt->execute([$update_id]);
                
                $success_message = "تم تثبيت التحديث بنجاح";
                break;
                
            case 'check_updates':
                // محاكاة فحص التحديثات
                $success_message = "تم فحص التحديثات - لا توجد تحديثات جديدة متاحة";
                break;
                
            case 'rollback_update':
                $update_id = $_POST['update_id'];
                
                $stmt = $pdo->prepare("UPDATE system_updates SET status = 'available', install_date = NULL WHERE id = ?");
                $stmt->execute([$update_id]);
                
                $success_message = "تم التراجع عن التحديث بنجاح";
                break;
        }
    } catch (Exception $e) {
        $error_message = "حدث خطأ: " . $e->getMessage();
    }
}

// جلب قائمة التحديثات
$updates = [];
try {
    $stmt = $pdo->query("SELECT * FROM system_updates ORDER BY release_date DESC, version DESC");
    $updates = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = "خطأ في جلب التحديثات: " . $e->getMessage();
}

// معلومات النظام الحالي
$system_info = [
    'current_version' => '2.0.3',
    'php_version' => PHP_VERSION,
    'mysql_version' => $pdo->query("SELECT VERSION() as version")->fetch()['version'] ?? 'غير معروف',
    'server_os' => php_uname('s') . ' ' . php_uname('r'),
    'last_update' => '2024-11-21',
    'update_channel' => 'مستقر'
];

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>تحديثات النظام</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">التحديثات</li>
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

            <div class="row mb-4">
                <!-- معلومات النظام الحالي -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">معلومات النظام الحالي</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>الإصدار الحالي:</strong></td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $system_info['current_version']; ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>إصدار PHP:</strong></td>
                                    <td><?php echo $system_info['php_version']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>إصدار MySQL:</strong></td>
                                    <td><?php echo $system_info['mysql_version']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>نظام التشغيل:</strong></td>
                                    <td><?php echo $system_info['server_os']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>آخر تحديث:</strong></td>
                                    <td><?php echo $system_info['last_update']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>قناة التحديثات:</strong></td>
                                    <td><?php echo $system_info['update_channel']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- أدوات التحديث -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">أدوات التحديث</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="d-grid gap-3">
                                <button type="submit" name="action" value="check_updates" class="btn btn-primary">
                                    <i class="fas fa-sync"></i> فحص التحديثات الجديدة
                                </button>
                                
                                <div class="form-group">
                                    <label for="update_channel" class="form-label">قناة التحديثات</label>
                                    <select class="form-select" id="update_channel" name="update_channel">
                                        <option value="stable">مستقر</option>
                                        <option value="beta">تجريبي</option>
                                        <option value="dev">تطوير</option>
                                    </select>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="auto_updates" name="auto_updates">
                                    <label class="form-check-label" for="auto_updates">
                                        تفعيل التحديثات التلقائية
                                    </label>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="backup_before_update" name="backup_before_update" checked>
                                    <label class="form-check-label" for="backup_before_update">
                                        إنشاء نسخة احتياطية قبل التحديث
                                    </label>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة التحديثات -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">التحديثات المتاحة والمثبتة</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($updates)): ?>
                        <div class="row">
                            <?php foreach ($updates as $update): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card h-100 <?php echo $update['status'] === 'installed' ? 'border-success' : 'border-primary'; ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($update['title']); ?></h6>
                                                <small class="text-muted">الإصدار <?php echo $update['version']; ?></small>
                                            </div>
                                            <div>
                                                <?php 
                                                $type_badges = [
                                                    'major' => 'danger',
                                                    'minor' => 'info',
                                                    'patch' => 'warning',
                                                    'security' => 'danger'
                                                ];
                                                $type_labels = [
                                                    'major' => 'كبير',
                                                    'minor' => 'صغير',
                                                    'patch' => 'إصلاح',
                                                    'security' => 'أمني'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $type_badges[$update['update_type']]; ?>">
                                                    <?php echo $type_labels[$update['update_type']]; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text"><?php echo htmlspecialchars($update['description']); ?></p>
                                            
                                            <div class="mb-3">
                                                <strong>التغييرات:</strong>
                                                <div class="mt-1">
                                                    <?php echo nl2br(htmlspecialchars($update['changes'])); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="row text-center small text-muted">
                                                <div class="col-6">
                                                    <strong>تاريخ الإصدار:</strong><br>
                                                    <?php echo date('Y-m-d', strtotime($update['release_date'])); ?>
                                                </div>
                                                <div class="col-6">
                                                    <strong>حجم الملف:</strong><br>
                                                    <?php echo formatBytes($update['file_size']); ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($update['install_date']): ?>
                                                <div class="mt-2 text-center small text-muted">
                                                    <strong>تاريخ التثبيت:</strong>
                                                    <?php echo date('Y-m-d H:i', strtotime($update['install_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer">
                                            <?php if ($update['status'] === 'available'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="install_update">
                                                    <input type="hidden" name="update_id" value="<?php echo $update['id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm w-100" 
                                                            onclick="return confirm('هل أنت متأكد من رغبتك في تثبيت هذا التحديث؟')">
                                                        <i class="fas fa-download"></i> تثبيت التحديث
                                                    </button>
                                                </form>
                                            <?php elseif ($update['status'] === 'installing'): ?>
                                                <button class="btn btn-warning btn-sm w-100" disabled>
                                                    <i class="fas fa-spinner fa-spin"></i> جاري التثبيت...
                                                </button>
                                            <?php elseif ($update['status'] === 'installed'): ?>
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-success btn-sm" disabled>
                                                        <i class="fas fa-check"></i> مثبت
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="rollback_update">
                                                        <input type="hidden" name="update_id" value="<?php echo $update['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100" 
                                                                onclick="return confirm('هل أنت متأكد من رغبتك في التراجع عن هذا التحديث؟')">
                                                            <i class="fas fa-undo"></i> التراجع
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php elseif ($update['status'] === 'failed'): ?>
                                                <button class="btn btn-danger btn-sm w-100" disabled>
                                                    <i class="fas fa-times"></i> فشل التثبيت
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-download fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد تحديثات</h5>
                            <p class="text-muted">لا توجد تحديثات متاحة في الوقت الحالي</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- معلومات مهمة -->
            <div class="card mt-4">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle"></i> ملاحظات مهمة
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>قم بعمل نسخة احتياطية قبل تثبيت أي تحديث</li>
                        <li>تأكد من عدم وجود مستخدمين متصلين أثناء التحديث</li>
                        <li>التحديثات الأمنية يُنصح بتثبيتها فوراً</li>
                        <li>في حالة فشل التحديث، يمكن التراجع للإصدار السابق</li>
                        <li>بعض التحديثات قد تتطلب إعادة تشغيل الخادم</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// دالة لتنسيق حجم الملف
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

include 'includes/footer.php'; 
?>