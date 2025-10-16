<?php
/**
 * System Logs Viewer
 * عارض سجلات النظام
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$current_page = 'logs';
$page_title = 'سجلات النظام';

// الحصول على قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

$success_message = '';
$error_message = '';

// معالجة العمليات
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'clear_logs':
                $log_type = $_POST['log_type'];
                if ($log_type === 'all') {
                    $stmt = $pdo->prepare("DELETE FROM system_logs");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM system_logs WHERE log_level = ?");
                    $stmt->execute([$log_type]);
                }
                $success_message = "تم مسح السجلات بنجاح";
                break;
                
            case 'export_logs':
                $log_level = $_POST['log_level'] ?? 'all';
                $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $end_date = $_POST['end_date'] ?? date('Y-m-d');
                
                exportLogs($log_level, $start_date, $end_date);
                break;
        }
    } catch (Exception $e) {
        $error_message = "حدث خطأ: " . $e->getMessage();
    }
}

// إحصائيات السجلات
$log_stats = [];
try {
    $stmt = $pdo->query("SELECT log_level, COUNT(*) as count FROM system_logs GROUP BY log_level");
    while ($row = $stmt->fetch()) {
        $log_stats[$row['log_level']] = $row['count'];
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_logs");
    $log_stats['total'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as today FROM system_logs WHERE DATE(created_at) = CURDATE()");
    $log_stats['today'] = $stmt->fetch()['today'];
} catch (Exception $e) {
    $error_message = "خطأ في جلب إحصائيات السجلات: " . $e->getMessage();
}

// معاملات البحث والتصفية
$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 50;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];

if (!empty($_GET['log_level'])) {
    $where_conditions[] = "log_level = ?";
    $params[] = $_GET['log_level'];
}

if (!empty($_GET['search'])) {
    $where_conditions[] = "(log_message LIKE ? OR user_action LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

if (!empty($_GET['start_date'])) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $_GET['start_date'];
}

if (!empty($_GET['end_date'])) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $_GET['end_date'];
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// جلب السجلات
$logs = [];
$total_logs = 0;
try {
    // عدد السجلات الكلي
    $count_sql = "SELECT COUNT(*) as total FROM system_logs $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetch()['total'];
    $total_pages = ceil($total_logs / $limit);
    
    // جلب السجلات
    $sql = "SELECT * FROM system_logs $where_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = "خطأ في جلب السجلات: " . $e->getMessage();
}

// دالة تصدير السجلات
function exportLogs($log_level, $start_date, $end_date) {
    global $pdo;
    
    $where_conditions = [];
    $params = [];
    
    if ($log_level !== 'all') {
        $where_conditions[] = "log_level = ?";
        $params[] = $log_level;
    }
    
    $where_conditions[] = "DATE(created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("SELECT * FROM system_logs $where_sql ORDER BY created_at DESC");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    $filename = "system_logs_{$start_date}_{$end_date}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // إضافة BOM للدعم العربي
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // رؤوس الأعمدة
    fputcsv($output, ['التاريخ', 'المستوى', 'الرسالة', 'العملية', 'المستخدم', 'IP']);
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['log_level'],
            $log['log_message'],
            $log['user_action'] ?? '',
            $log['user_id'] ?? '',
            $log['ip_address'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>سجلات النظام</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">السجلات</li>
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

            <!-- إحصائيات السجلات -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($log_stats['total'] ?? 0); ?></h3>
                                    <p class="mb-0">إجمالي السجلات</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-list fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($log_stats['today'] ?? 0); ?></h3>
                                    <p class="mb-0">سجلات اليوم</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-day fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($log_stats['warning'] ?? 0); ?></h3>
                                    <p class="mb-0">تحذيرات</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($log_stats['error'] ?? 0); ?></h3>
                                    <p class="mb-0">أخطاء</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- أدوات التحكم -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">أدوات التحكم</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary w-100" onclick="showExportModal()">
                                <i class="fas fa-download"></i> تصدير السجلات
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-warning w-100" onclick="showClearLogsModal()">
                                <i class="fas fa-broom"></i> مسح السجلات
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-info w-100" onclick="refreshLogs()">
                                <i class="fas fa-sync"></i> تحديث
                            </button>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <select class="form-select" onchange="changePageLimit(this.value)">
                                    <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 سجل</option>
                                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 سجل</option>
                                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 سجل</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- مرشحات البحث -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">البحث</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="البحث في الرسائل والعمليات"
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="log_level" class="form-label">مستوى السجل</label>
                            <select class="form-select" id="log_level" name="log_level">
                                <option value="">جميع المستويات</option>
                                <option value="info" <?php echo ($_GET['log_level'] ?? '') === 'info' ? 'selected' : ''; ?>>معلومات</option>
                                <option value="warning" <?php echo ($_GET['log_level'] ?? '') === 'warning' ? 'selected' : ''; ?>>تحذير</option>
                                <option value="error" <?php echo ($_GET['log_level'] ?? '') === 'error' ? 'selected' : ''; ?>>خطأ</option>
                                <option value="debug" <?php echo ($_GET['log_level'] ?? '') === 'debug' ? 'selected' : ''; ?>>تصحيح</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $_GET['start_date'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="end_date" class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $_GET['end_date'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> بحث
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="logs.php" class="btn btn-secondary">
                                    <i class="fas fa-eraser"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- جدول السجلات -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">سجلات النظام</h3>
                    <div class="card-tools">
                        <span class="badge bg-primary"><?php echo number_format($total_logs); ?> سجل</span>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>المستوى</th>
                                <th>الرسالة</th>
                                <th>العملية</th>
                                <th>المستخدم</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $level_class = 'secondary';
                                            $level_icon = 'info-circle';
                                            
                                            switch ($log['log_level']) {
                                                case 'info':
                                                    $level_class = 'info';
                                                    $level_icon = 'info-circle';
                                                    break;
                                                case 'warning':
                                                    $level_class = 'warning';
                                                    $level_icon = 'exclamation-triangle';
                                                    break;
                                                case 'error':
                                                    $level_class = 'danger';
                                                    $level_icon = 'times-circle';
                                                    break;
                                                case 'debug':
                                                    $level_class = 'secondary';
                                                    $level_icon = 'bug';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $level_class; ?>">
                                                <i class="fas fa-<?php echo $level_icon; ?>"></i>
                                                <?php echo strtoupper($log['log_level']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="log-message" title="<?php echo htmlspecialchars($log['log_message']); ?>">
                                                <?php echo htmlspecialchars(mb_substr($log['log_message'], 0, 100)); ?>
                                                <?php if (mb_strlen($log['log_message']) > 100): ?>...<?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['user_action'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['user_action']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['user_id'])): ?>
                                                <small><?php echo htmlspecialchars($log['user_id']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">نظام</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-list fa-2x text-muted mb-2"></i>
                                        <p class="text-muted">لا توجد سجلات</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal تصدير السجلات -->
<div class="modal fade" id="exportLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تصدير السجلات</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="export_logs">
                    
                    <div class="mb-3">
                        <label for="export_log_level" class="form-label">مستوى السجل</label>
                        <select class="form-select" name="log_level" id="export_log_level">
                            <option value="all">جميع المستويات</option>
                            <option value="info">معلومات</option>
                            <option value="warning">تحذير</option>
                            <option value="error">خطأ</option>
                            <option value="debug">تصحيح</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_start_date" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" name="start_date" id="export_start_date" 
                               value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_end_date" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" name="end_date" id="export_end_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> تصدير
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal مسح السجلات -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">مسح السجلات</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="clear_logs">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>تحذير:</strong> لا يمكن التراجع عن عملية مسح السجلات
                    </div>
                    
                    <div class="mb-3">
                        <label for="clear_log_type" class="form-label">نوع السجلات للمسح</label>
                        <select class="form-select" name="log_type" id="clear_log_type" required>
                            <option value="">اختر نوع السجل</option>
                            <option value="all">جميع السجلات</option>
                            <option value="info">معلومات فقط</option>
                            <option value="warning">تحذيرات فقط</option>
                            <option value="error">أخطاء فقط</option>
                            <option value="debug">تصحيح فقط</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> مسح السجلات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showExportModal() {
    new bootstrap.Modal(document.getElementById('exportLogsModal')).show();
}

function showClearLogsModal() {
    new bootstrap.Modal(document.getElementById('clearLogsModal')).show();
}

function refreshLogs() {
    window.location.reload();
}

function changePageLimit(limit) {
    const url = new URL(window.location);
    url.searchParams.set('limit', limit);
    url.searchParams.delete('page');
    window.location = url;
}
</script>

<?php include 'includes/footer.php'; ?>