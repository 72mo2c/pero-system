<?php
/**
 * Financial Transactions Management
 * Created: 2025-10-16
 * Author: MiniMax Agent
 */

require_once '../shared/includes/header.php';
require_once '../config/config.php';
require_once '../shared/classes/Session.php';
require_once '../shared/classes/Security.php';
require_once '../shared/classes/Utils.php';

// التحقق من المصادقة
if (!Session::isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$current_page = 'transactions';
$page_title = 'الحركات المالية';

// متغيرات الفلترة
$filter_account = $_GET['account_id'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    $pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());
    
    // جلب قائمة الحسابات للفلترة
    $accounts_query = "SELECT id, account_name FROM treasury_accounts WHERE status = 'active' ORDER BY account_name";
    $accounts = $pdo->query($accounts_query)->fetchAll();
    
    // بناء الاستعلام مع الفلاتر
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($filter_account)) {
        $where_conditions[] = 't.account_id = ?';
        $params[] = $filter_account;
    }
    
    if (!empty($filter_type)) {
        $where_conditions[] = 't.transaction_type = ?';
        $params[] = $filter_type;
    }
    
    if (!empty($filter_date_from)) {
        $where_conditions[] = 'DATE(t.created_at) >= ?';
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $where_conditions[] = 'DATE(t.created_at) <= ?';
        $params[] = $filter_date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // عدد السجلات الإجمالي
    $count_query = "SELECT COUNT(*) as total FROM treasury_transactions t WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // استعلام المعاملات
    $query = "SELECT t.*, ta.account_name, u.full_name as created_by_name 
        FROM treasury_transactions t 
        JOIN treasury_accounts ta ON t.account_id = ta.id 
        LEFT JOIN users u ON t.created_by = u.id 
        WHERE $where_clause 
        ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // إحصائيات سريعة
    $stats_query = "SELECT 
        SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credits,
        SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debits,
        COUNT(*) as total_transactions
        FROM treasury_transactions t WHERE $where_clause";
    $stats_stmt = $pdo->prepare($query);
    $stats_stmt->execute(array_slice($params, 0, -2)); // إزالة LIMIT و OFFSET
    $stats = $stats_stmt->fetch();
    
} catch (Exception $e) {
    $error = "خطأ في جلب المعاملات: " . $e->getMessage();
    $transactions = [];
    $accounts = [];
    $total_records = 0;
    $total_pages = 0;
    $stats = ['total_credits' => 0, 'total_debits' => 0, 'total_transactions' => 0];
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
                        <i class="fas fa-exchange-alt text-primary me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transactionModal">
                            <i class="fas fa-plus me-1"></i>
                            معاملة جديدة
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="exportTransactions()">
                            <i class="fas fa-download me-1"></i>
                            تصدير
                        </button>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <!-- إحصائيات سريعة -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-arrow-down fa-2x mb-2 opacity-75"></i>
                                <h4><?php echo number_format($stats['total_credits'], 2); ?> ر.س</h4>
                                <small>إجمالي الدائن</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-arrow-up fa-2x mb-2 opacity-75"></i>
                                <h4><?php echo number_format($stats['total_debits'], 2); ?> ر.س</h4>
                                <small>إجمالي المدين</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-balance-scale fa-2x mb-2 opacity-75"></i>
                                <h4><?php echo number_format($stats['total_credits'] - $stats['total_debits'], 2); ?> ر.س</h4>
                                <small>الرصيد الصافي</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-list fa-2x mb-2 opacity-75"></i>
                                <h4><?php echo number_format($stats['total_transactions']); ?></h4>
                                <small>عدد المعاملات</small>
                            </div>
                        </div>
                    </div>
                </div>

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
                                    <label class="form-label">الحساب</label>
                                    <select class="form-select" name="account_id">
                                        <option value="">جميع الحسابات</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>" <?php echo $filter_account == $account['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($account['account_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">نوع المعاملة</label>
                                    <select class="form-select" name="type">
                                        <option value="">جميع الأنواع</option>
                                        <option value="credit" <?php echo $filter_type == 'credit' ? 'selected' : ''; ?>>دائن</option>
                                        <option value="debit" <?php echo $filter_type == 'debit' ? 'selected' : ''; ?>>مدين</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">من تاريخ</label>
                                    <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">إلى تاريخ</label>
                                    <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
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

                <!-- جدول المعاملات -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">سجل المعاملات (<?php echo number_format($total_records); ?> معاملة)</h5>
                        <small class="text-muted">الصفحة <?php echo $page; ?> من <?php echo $total_pages; ?></small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>الحساب</th>
                                        <th>النوع</th>
                                        <th>المبلغ</th>
                                        <th>البيان</th>
                                        <th>المرجع</th>
                                        <th>المُنشئ</th>
                                        <th>التاريخ</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-exchange-alt fa-3x mb-3 opacity-50"></i>
                                            <br>لا توجد معاملات مسجلة
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $index => $transaction): ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($transaction['account_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($transaction['transaction_type'] == 'credit'): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-arrow-down me-1"></i>
                                                    دائن
                                                </span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-arrow-up me-1"></i>
                                                    مدين
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold <?php echo $transaction['transaction_type'] == 'credit' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $transaction['transaction_type'] == 'credit' ? '+' : '-'; ?>
                                                    <?php echo number_format($transaction['amount'], 2); ?> ر.س
                                                </span>
                                            </td>
                                            <td>
                                                <div class="transaction-description">
                                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($transaction['reference'])): ?>
                                                <code><?php echo htmlspecialchars($transaction['reference']); ?></code>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($transaction['created_by_name']); ?></small>
                                            </td>
                                            <td>
                                                <div class="text-nowrap">
                                                    <?php echo date('Y-m-d', strtotime($transaction['created_at'])); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($transaction['created_at'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-info" onclick="viewTransaction(<?php echo $transaction['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary" onclick="printTransaction(<?php echo $transaction['id']; ?>)">
                                                        <i class="fas fa-print"></i>
                                                    </button>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&account_id=<?php echo urlencode($filter_account); ?>&type=<?php echo urlencode($filter_type); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>">السابق</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&account_id=<?php echo urlencode($filter_account); ?>&type=<?php echo urlencode($filter_type); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&account_id=<?php echo urlencode($filter_account); ?>&type=<?php echo urlencode($filter_type); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>">التالي</a>
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

    <!-- Modal لإضافة معاملة جديدة -->
    <div class="modal fade" id="transactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة معاملة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_transaction">
                        
                        <div class="mb-3">
                            <label class="form-label">الحساب *</label>
                            <select class="form-select" name="account_id" required>
                                <option value="">اختر الحساب</option>
                                <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo htmlspecialchars($account['account_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">نوع المعاملة *</label>
                            <select class="form-select" name="transaction_type" required>
                                <option value="">اختر النوع</option>
                                <option value="credit">دائن (إيداع)</option>
                                <option value="debit">مدين (سحب)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">المبلغ *</label>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">البيان *</label>
                            <textarea class="form-control" name="description" rows="3" required placeholder="وصف المعاملة"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">المرجع</label>
                            <input type="text" class="form-control" name="reference" placeholder="رقم المرجع (اختياري)">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            حفظ المعاملة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
    function viewTransaction(transactionId) {
        alert('سيتم فتح تفاصيل المعاملة رقم: ' + transactionId);
        // هنا يمكن إضافة نافذة منبثقة لعرض تفاصيل المعاملة
    }
    
    function printTransaction(transactionId) {
        alert('سيتم طباعة المعاملة رقم: ' + transactionId);
        // هنا يمكن إضافة وظيفة الطباعة
    }
    
    function exportTransactions() {
        // إنشاء URL للتصدير مع الفلاتر الحالية
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'csv');
        
        // إنشاء رابط للتحميل
        const link = document.createElement('a');
        link.href = 'transactions.php?' + params.toString();
        link.download = 'transactions_' + new Date().toISOString().split('T')[0] + '.csv';
        link.click();
    }
    </script>

    <style>
    .transaction-description {
        max-width: 200px;
        word-wrap: break-word;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .btn-group .btn {
        border-radius: 4px;
    }
    
    .badge {
        font-size: 0.75em;
    }
    </style>
</body>
</html>