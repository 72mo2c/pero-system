<?php
/**
 * Activity Log - System Activity Tracking
 * Created: 2025-10-16
 * Author: MiniMax Agent
 */

require_once '../shared/includes/header.php';
require_once '../config/config.php';
require_once '../shared/classes/Session.php';
require_once '../shared/classes/Security.php';
require_once '../shared/classes/Utils.php';

// التحقق من المصادقة وصلاحية الوصول
if (!Session::isAuthenticated()) {
    header('Location: login.php');
    exit();
}

// التحقق من صلاحية المدير/المشرف
$user_role = Session::getUserRole();
if (!in_array($user_role, ['admin', 'manager'])) {
    header('Location: dashboard.php');
    exit();
}

$current_page = 'activity_log';
$page_title = 'سجل الأنشطة';

// متغيرات الفلترة
$filter_user = $_GET['user'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    $pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());
    
    // بناء الاستعلام مع الفلاتر
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($filter_user)) {
        $where_conditions[] = 'username LIKE ?';
        $params[] = '%' . $filter_user . '%';
    }
    
    if (!empty($filter_action)) {
        $where_conditions[] = 'action LIKE ?';
        $params[] = '%' . $filter_action . '%';
    }
    
    if (!empty($filter_date_from)) {
        $where_conditions[] = 'DATE(created_at) >= ?';
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $where_conditions[] = 'DATE(created_at) <= ?';
        $params[] = $filter_date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // عدد السجلات الإجمالي
    $count_query = "SELECT COUNT(*) as total FROM activity_logs WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // استعلام السجلات
    $query = "SELECT * FROM activity_logs WHERE $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "خطأ في جلب سجل الأنشطة: " . $e->getMessage();
    $activities = [];
    $total_records = 0;
    $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - نظام إدارة المخازن</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="container-fluid">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-history text-primary me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="exportToCSV()">
                            <i class="fas fa-download me-1"></i>
                            تصدير CSV
                        </button>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <!-- فلاتر البحث -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            فلاتر البحث
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">المستخدم</label>
                                    <input type="text" class="form-control" name="user" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="اسم المستخدم">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">النشاط</label>
                                    <input type="text" class="form-control" name="action" value="<?php echo htmlspecialchars($filter_action); ?>" placeholder="نوع النشاط">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">من تاريخ</label>
                                    <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">إلى تاريخ</label>
                                    <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i>
                                        بحث
                                    </button>
                                    <a href="?" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i>
                                        إعادة تعيين
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- جدول السجلات -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">سجل الأنشطة (<?php echo number_format($total_records); ?> سجل)</h5>
                        <small class="text-muted">الصفحة <?php echo $page; ?> من <?php echo $total_pages; ?></small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>المستخدم</th>
                                        <th>النشاط</th>
                                        <th>التفاصيل</th>
                                        <th>عنوان IP</th>
                                        <th>User Agent</th>
                                        <th>التاريخ والوقت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activities)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fas fa-history fa-3x mb-3 opacity-50"></i>
                                            <br>لا توجد أنشطة مسجلة
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($activities as $index => $activity): ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td>
                                                <span class="fw-bold"><?php echo htmlspecialchars($activity['username']); ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($activity['user_role']); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $action_icons = [
                                                    'login' => 'fas fa-sign-in-alt text-success',
                                                    'logout' => 'fas fa-sign-out-alt text-warning',
                                                    'create' => 'fas fa-plus text-success',
                                                    'update' => 'fas fa-edit text-primary',
                                                    'delete' => 'fas fa-trash text-danger',
                                                    'view' => 'fas fa-eye text-info',
                                                    'export' => 'fas fa-download text-success',
                                                    'import' => 'fas fa-upload text-primary'
                                                ];
                                                $icon = $action_icons[$activity['action']] ?? 'fas fa-activity text-secondary';
                                                ?>
                                                <i class="<?php echo $icon; ?> me-2"></i>
                                                <?php echo htmlspecialchars($activity['action']); ?>
                                            </td>
                                            <td>
                                                <div class="activity-details">
                                                    <?php echo htmlspecialchars($activity['details']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="text-muted"><?php echo htmlspecialchars($activity['ip_address']); ?></code>
                                            </td>
                                            <td>
                                                <small class="text-muted" title="<?php echo htmlspecialchars($activity['user_agent']); ?>">
                                                    <?php echo substr(htmlspecialchars($activity['user_agent']), 0, 30) . '...'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="text-nowrap">
                                                    <?php echo date('Y-m-d', strtotime($activity['created_at'])); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></small>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="تنقل الصفحات">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>">السابق</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>">التالي</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
    function exportToCSV() {
        // إنشاء URL للتصدير مع الفلاتر الحالية
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'csv');
        
        // إنشاء رابط للتحميل
        const link = document.createElement('a');
        link.href = 'activity_log.php?' + params.toString();
        link.download = 'activity_log_' + new Date().toISOString().split('T')[0] + '.csv';
        link.click();
    }
    
    // إضافة tooltip للعناصر
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>

    <style>
    .activity-details {
        max-width: 300px;
        word-wrap: break-word;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .btn-group .btn {
        border-radius: 6px;
    }
    </style>
</body>
</html>