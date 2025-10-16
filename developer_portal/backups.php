<?php
/**
 * Backup Management Page
 * إدارة النسخ الاحتياطية
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$current_page = 'backups';
$page_title = 'إدارة النسخ الاحتياطية';

// الحصول على قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

$success_message = '';
$error_message = '';

// مجلد النسخ الاحتياطية
$backup_dir = '../backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// معالجة العمليات
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'create_backup':
                $backup_type = $_POST['backup_type'];
                $include_data = isset($_POST['include_data']) ? true : false;
                
                $timestamp = date('Y-m-d_H-i-s');
                $backup_filename = "backup_{$backup_type}_{$timestamp}.sql";
                $backup_path = $backup_dir . '/' . $backup_filename;
                
                if ($backup_type === 'main') {
                    createMainDatabaseBackup($backup_path, $include_data);
                } elseif ($backup_type === 'tenant') {
                    $tenant_id = $_POST['tenant_id'];
                    createTenantBackup($tenant_id, $backup_path, $include_data);
                }
                
                $success_message = "تم إنشاء النسخة الاحتياطية بنجاح: $backup_filename";
                break;
                
            case 'delete_backup':
                $filename = basename($_POST['filename']);
                $file_path = $backup_dir . '/' . $filename;
                
                if (file_exists($file_path)) {
                    unlink($file_path);
                    $success_message = "تم حذف النسخة الاحتياطية بنجاح";
                } else {
                    $error_message = "الملف غير موجود";
                }
                break;
                
            case 'restore_backup':
                $filename = basename($_POST['filename']);
                $file_path = $backup_dir . '/' . $filename;
                
                if (file_exists($file_path)) {
                    restoreBackup($file_path);
                    $success_message = "تم استعادة النسخة الاحتياطية بنجاح";
                } else {
                    $error_message = "ملف النسخة الاحتياطية غير موجود";
                }
                break;
        }
    } catch (Exception $e) {
        $error_message = "حدث خطأ: " . $e->getMessage();
    }
}

// جلب قائمة النسخ الاحتياطية
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = $backup_dir . '/' . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'created' => filemtime($file_path),
                'type' => strpos($file, 'main') !== false ? 'رئيسية' : 'مستأجر'
            ];
        }
    }
    
    // ترتيب حسب تاريخ الإنشاء (الأحدث أولاً)
    usort($backup_files, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}

// جلب قائمة المستأجرين
$tenants = [];
try {
    $stmt = $pdo->query("SELECT id, tenant_id, company_name FROM tenants WHERE is_active = 1 ORDER BY company_name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = "خطأ في جلب قائمة المستأجرين: " . $e->getMessage();
}

// دوال النسخ الاحتياطي
function createMainDatabaseBackup($backup_path, $include_data = true) {
    global $pdo;
    
    $output = "-- نسخة احتياطية لقاعدة البيانات الرئيسية\n";
    $output .= "-- تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "\n\n";
    
    $tables = ['tenants', 'system_settings', 'system_logs'];
    
    foreach ($tables as $table) {
        $output .= "-- \n";
        $output .= "-- بنية الجدول '$table'\n";
        $output .= "-- \n\n";
        
        $stmt = $pdo->query("SHOW CREATE TABLE '$table'");
        $row = $stmt->fetch();
        $output .= $row['Create Table'] . ";\n\n";
        
        if ($include_data) {
            $output .= "-- \n";
            $output .= "-- إدراج بيانات الجدول '$table'\n";
            $output .= "-- \n\n";
            
            $stmt = $pdo->query("SELECT * FROM '$table'");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $output .= "INSERT INTO '$table' VALUES (";
                $values = array_map(function($value) use ($pdo) {
                    return $value === null ? 'NULL' : $pdo->quote($value);
                }, array_values($row));
                $output .= implode(', ', $values);
                $output .= ");\n";
            }
            $output .= "\n";
        }
    }
    
    file_put_contents($backup_path, $output);
}

function createTenantBackup($tenant_id, $backup_path, $include_data = true) {
    // هذه دالة مبسطة - في التطبيق الحقيقي ستحتاج لتنفيذ أكثر تعقيداً
    $output = "-- نسخة احتياطية لقاعدة بيانات المستأجر: $tenant_id\n";
    $output .= "-- تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "\n\n";
    $output .= "-- ملاحظة: هذه نسخة تجريبية\n";
    
    file_put_contents($backup_path, $output);
}

function restoreBackup($backup_path) {
    global $pdo;
    
    $sql = file_get_contents($backup_path);
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !str_starts_with($statement, '--')) {
            $pdo->exec($statement);
        }
    }
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>إدارة النسخ الاحتياطية</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">النسخ الاحتياطية</li>
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

            <!-- إنشاء نسخة احتياطية جديدة -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">إنشاء نسخة احتياطية جديدة</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="create_backup">
                        
                        <div class="col-md-3">
                            <label for="backup_type" class="form-label">نوع النسخة الاحتياطية</label>
                            <select class="form-select" name="backup_type" id="backup_type" required>
                                <option value="main">قاعدة البيانات الرئيسية</option>
                                <option value="tenant">قاعدة بيانات مستأجر</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3" id="tenant_select_div" style="display: none;">
                            <label for="tenant_id" class="form-label">المستأجر</label>
                            <select class="form-select" name="tenant_id" id="tenant_id">
                                <option value="">اختر المستأجر</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['tenant_id']; ?>">
                                        <?php echo htmlspecialchars($tenant['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="include_data" id="include_data" checked>
                                <label class="form-check-label" for="include_data">
                                    تضمين البيانات
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-download"></i> إنشاء النسخة الاحتياطية
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- قائمة النسخ الاحتياطية -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">النسخ الاحتياطية المتاحة</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($backup_files)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>اسم الملف</th>
                                        <th>النوع</th>
                                        <th>الحجم</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backup_files as $file): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($file['name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $file['type']; ?></span>
                                            </td>
                                            <td><?php echo formatBytes($file['size']); ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', $file['created']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="../backups/<?php echo urlencode($file['name']); ?>" 
                                                       class="btn btn-sm btn-success" 
                                                       download
                                                       title="تحميل">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-warning" 
                                                            onclick="restoreBackup('<?php echo htmlspecialchars($file['name']); ?>')"
                                                            title="استعادة">
                                                        <i class="fas fa-upload"></i>
                                                    </button>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            onclick="deleteBackup('<?php echo htmlspecialchars($file['name']); ?>')"
                                                            title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد نسخ احتياطية</h5>
                            <p class="text-muted">قم بإنشاء أول نسخة احتياطية من الأعلى</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- معلومات مهمة -->
            <div class="card mt-4">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle"></i> معلومات مهمة
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>احتفظ بنسخ احتياطية منتظمة لضمان سلامة البيانات</li>
                        <li>اختبر النسخ الاحتياطية دورياً للتأكد من إمكانية استعادتها</li>
                        <li>احفظ النسخ الاحتياطية في مواقع متعددة (خارج الخادم)</li>
                        <li>عملية الاستعادة ستحل محل البيانات الحالية - تأكد من رغبتك في ذلك</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal تأكيد الحذف -->
<div class="modal fade" id="deleteBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من رغبتك في حذف هذه النسخة الاحتياطية؟</p>
                <p class="text-danger"><strong>تحذير:</strong> لا يمكن التراجع عن هذه العملية</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_backup">
                    <input type="hidden" name="filename" id="deleteFilename">
                    <button type="submit" class="btn btn-danger">حذف</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal تأكيد الاستعادة -->
<div class="modal fade" id="restoreBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الاستعادة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من رغبتك في استعادة هذه النسخة الاحتياطية؟</p>
                <p class="text-warning"><strong>تحذير:</strong> ستحل البيانات المُستعادة محل البيانات الحالية</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="restore_backup">
                    <input type="hidden" name="filename" id="restoreFilename">
                    <button type="submit" class="btn btn-warning">استعادة</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('backup_type').addEventListener('change', function() {
    const tenantDiv = document.getElementById('tenant_select_div');
    const tenantSelect = document.getElementById('tenant_id');
    
    if (this.value === 'tenant') {
        tenantDiv.style.display = 'block';
        tenantSelect.required = true;
    } else {
        tenantDiv.style.display = 'none';
        tenantSelect.required = false;
    }
});

function deleteBackup(filename) {
    document.getElementById('deleteFilename').value = filename;
    new bootstrap.Modal(document.getElementById('deleteBackupModal')).show();
}

function restoreBackup(filename) {
    document.getElementById('restoreFilename').value = filename;
    new bootstrap.Modal(document.getElementById('restoreBackupModal')).show();
}
</script>

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