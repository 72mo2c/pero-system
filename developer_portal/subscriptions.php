<?php
/**
 * Subscription Management Page
 * إدارة الاشتراكات
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$current_page = 'subscriptions';
$page_title = 'إدارة الاشتراكات';

// معالجة العمليات
$action = $_GET['action'] ?? 'list';
$subscription_id = $_GET['id'] ?? null;

// الحصول على قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

$success_message = '';
$error_message = '';

// تحديث حالة الاشتراك
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_status') {
            $subscription_id = $_POST['subscription_id'];
            $new_status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE tenants SET subscription_status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $subscription_id]);
            
            $success_message = "تم تحديث حالة الاشتراك بنجاح";
        }
        
        if ($_POST['action'] === 'extend_subscription') {
            $subscription_id = $_POST['subscription_id'];
            $new_end_date = $_POST['new_end_date'];
            
            $stmt = $pdo->prepare("UPDATE tenants SET subscription_end = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_end_date, $subscription_id]);
            
            $success_message = "تم تمديد فترة الاشتراك بنجاح";
        }
    } catch (Exception $e) {
        $error_message = "حدث خطأ: " . $e->getMessage();
    }
}

// إحصائيات الاشتراكات
$stats = [
    'total' => 0,
    'active' => 0,
    'expired' => 0,
    'pending' => 0,
    'trial' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenants");
    $stats['total'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM tenants WHERE subscription_status = 'active'");
    $stats['active'] = $stmt->fetch()['active'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as expired FROM tenants WHERE subscription_end < CURDATE()");
    $stats['expired'] = $stmt->fetch()['expired'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM tenants WHERE subscription_status = 'pending'");
    $stats['pending'] = $stmt->fetch()['pending'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as trial FROM tenants WHERE subscription_plan = 'trial'");
    $stats['trial'] = $stmt->fetch()['trial'];
} catch (Exception $e) {
    $error_message = "خطأ في جلب الإحصائيات: " . $e->getMessage();
}

// جلب الاشتراكات
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];

if (!empty($_GET['status'])) {
    $where_conditions[] = "subscription_status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['plan'])) {
    $where_conditions[] = "subscription_plan = ?";
    $params[] = $_GET['plan'];
}

if (!empty($_GET['search'])) {
    $where_conditions[] = "(company_name LIKE ? OR email LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // عدد الاشتراكات الكلي
    $count_sql = "SELECT COUNT(*) as total FROM tenants $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_subscriptions = $stmt->fetch()['total'];
    $total_pages = ceil($total_subscriptions / $limit);
    
    // جلب الاشتراكات
    $sql = "SELECT *, 
                   CASE 
                       WHEN subscription_end < CURDATE() THEN 'منتهي'
                       WHEN subscription_status = 'active' THEN 'نشط'
                       WHEN subscription_status = 'pending' THEN 'معلق'
                       ELSE 'غير محدد'
                   END as status_text,
                   DATEDIFF(subscription_end, CURDATE()) as days_remaining
            FROM tenants 
            $where_sql 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = "خطأ في جلب الاشتراكات: " . $e->getMessage();
    $subscriptions = [];
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>إدارة الاشتراكات</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">الاشتراكات</li>
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

            <!-- إحصائيات الاشتراكات -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($stats['total']); ?></h3>
                                    <p class="mb-0">إجمالي الاشتراكات</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
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
                                    <h3><?php echo number_format($stats['active']); ?></h3>
                                    <p class="mb-0">اشتراكات نشطة</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
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
                                    <h3><?php echo number_format($stats['expired']); ?></h3>
                                    <p class="mb-0">اشتراكات منتهية</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
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
                                    <h3><?php echo number_format($stats['trial']); ?></h3>
                                    <p class="mb-0">اشتراكات تجريبية</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- مرشحات البحث -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">البحث</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="اسم الشركة أو البريد الإلكتروني"
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="status" class="form-label">حالة الاشتراك</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">جميع الحالات</option>
                                <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>نشط</option>
                                <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>معلق</option>
                                <option value="expired" <?php echo ($_GET['status'] ?? '') === 'expired' ? 'selected' : ''; ?>>منتهي</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="plan" class="form-label">نوع الاشتراك</label>
                            <select class="form-select" id="plan" name="plan">
                                <option value="">جميع الأنواع</option>
                                <option value="trial" <?php echo ($_GET['plan'] ?? '') === 'trial' ? 'selected' : ''; ?>>تجريبي</option>
                                <option value="basic" <?php echo ($_GET['plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>أساسي</option>
                                <option value="premium" <?php echo ($_GET['plan'] ?? '') === 'premium' ? 'selected' : ''; ?>>مميز</option>
                                <option value="enterprise" <?php echo ($_GET['plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>مؤسسي</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> بحث
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- جدول الاشتراكات -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">قائمة الاشتراكات</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>الشركة</th>
                                <th>البريد الإلكتروني</th>
                                <th>نوع الاشتراك</th>
                                <th>حالة الاشتراك</th>
                                <th>تاريخ البداية</th>
                                <th>تاريخ الانتهاء</th>
                                <th>الأيام المتبقية</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($subscriptions)): ?>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td><?php echo $subscription['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($subscription['company_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($subscription['email']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($subscription['subscription_plan']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = 'secondary';
                                            if ($subscription['subscription_status'] === 'active') $status_class = 'success';
                                            elseif ($subscription['subscription_status'] === 'pending') $status_class = 'warning';
                                            elseif ($subscription['days_remaining'] < 0) $status_class = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $subscription['status_text']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($subscription['subscription_start'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($subscription['subscription_end'])); ?></td>
                                        <td>
                                            <?php if ($subscription['days_remaining'] > 0): ?>
                                                <span class="text-success"><?php echo $subscription['days_remaining']; ?> يوم</span>
                                            <?php elseif ($subscription['days_remaining'] == 0): ?>
                                                <span class="text-warning">ينتهي اليوم</span>
                                            <?php else: ?>
                                                <span class="text-danger">منتهي منذ <?php echo abs($subscription['days_remaining']); ?> يوم</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="editSubscription(<?php echo $subscription['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="extendSubscription(<?php echo $subscription['id']; ?>)">
                                                    <i class="fas fa-clock"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">لا توجد اشتراكات</td>
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

<!-- Modal لتحديث حالة الاشتراك -->
<div class="modal fade" id="editSubscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تحديث حالة الاشتراك</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="subscription_id" id="editSubscriptionId">
                    
                    <div class="mb-3">
                        <label for="editStatus" class="form-label">حالة الاشتراك</label>
                        <select class="form-select" name="status" id="editStatus" required>
                            <option value="active">نشط</option>
                            <option value="pending">معلق</option>
                            <option value="suspended">معلق</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal لتمديد الاشتراك -->
<div class="modal fade" id="extendSubscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تمديد الاشتراك</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="extend_subscription">
                    <input type="hidden" name="subscription_id" id="extendSubscriptionId">
                    
                    <div class="mb-3">
                        <label for="newEndDate" class="form-label">تاريخ الانتهاء الجديد</label>
                        <input type="date" class="form-control" name="new_end_date" id="newEndDate" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">تمديد الاشتراك</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSubscription(subscriptionId) {
    document.getElementById('editSubscriptionId').value = subscriptionId;
    new bootstrap.Modal(document.getElementById('editSubscriptionModal')).show();
}

function extendSubscription(subscriptionId) {
    document.getElementById('extendSubscriptionId').value = subscriptionId;
    // تعيين تاريخ افتراضي (سنة من الآن)
    const nextYear = new Date();
    nextYear.setFullYear(nextYear.getFullYear() + 1);
    document.getElementById('newEndDate').value = nextYear.toISOString().split('T')[0];
    
    new bootstrap.Modal(document.getElementById('extendSubscriptionModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>