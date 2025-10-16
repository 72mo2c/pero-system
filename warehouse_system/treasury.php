<?php
/**
 * Treasury Management - Financial Account Management
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

$current_page = 'treasury';
$page_title = 'إدارة الخزينة';

try {
    $pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());
    
    // إحصائيات الخزينة
    $stats_query = "SELECT 
        COUNT(*) as total_accounts,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_accounts,
        SUM(balance) as total_balance
        FROM treasury_accounts";
    $stats = $pdo->query($stats_query)->fetch();
    
    // الحسابات النشطة
    $accounts_query = "SELECT * FROM treasury_accounts WHERE status = 'active' ORDER BY created_at DESC LIMIT 10";
    $accounts = $pdo->query($accounts_query)->fetchAll();
    
    // آخر المعاملات
    $transactions_query = "SELECT t.*, ta.account_name 
        FROM treasury_transactions t 
        JOIN treasury_accounts ta ON t.account_id = ta.id 
        ORDER BY t.created_at DESC LIMIT 10";
    $recent_transactions = $pdo->query($transactions_query)->fetchAll();
    
} catch (Exception $e) {
    $error = "خطأ في جلب بيانات الخزينة: " . $e->getMessage();
    $stats = ['total_accounts' => 0, 'active_accounts' => 0, 'total_balance' => 0];
    $accounts = [];
    $recent_transactions = [];
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
                        <i class="fas fa-coins text-primary me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-group">
                        <a href="treasury_accounts.php" class="btn btn-primary">
                            <i class="fas fa-university me-1"></i>
                            إدارة الحسابات
                        </a>
                        <a href="transactions.php" class="btn btn-outline-primary">
                            <i class="fas fa-exchange-alt me-1"></i>
                            المعاملات المالية
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <!-- بطاقات الإحصائيات -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">إجمالي الحسابات</h5>
                                        <h2 class="mb-0"><?php echo number_format($stats['total_accounts']); ?></h2>
                                    </div>
                                    <div class="text-end">
                                        <i class="fas fa-university fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">الحسابات النشطة</h5>
                                        <h2 class="mb-0"><?php echo number_format($stats['active_accounts']); ?></h2>
                                    </div>
                                    <div class="text-end">
                                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">إجمالي الرصيد</h5>
                                        <h2 class="mb-0"><?php echo number_format($stats['total_balance'], 2); ?> ر.س</h2>
                                    </div>
                                    <div class="text-end">
                                        <i class="fas fa-coins fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- أدوات سريعة -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tools me-2"></i>
                            أدوات سريعة
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <button class="btn btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                    <i class="fas fa-plus-circle mb-2"></i>
                                    <br>إضافة معاملة جديدة
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-primary w-100" onclick="transferMoney()">
                                    <i class="fas fa-exchange-alt mb-2"></i>
                                    <br>تحويل بين الحسابات
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-info w-100" onclick="generateReport()">
                                    <i class="fas fa-chart-line mb-2"></i>
                                    <br>تقرير مالي
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-warning w-100" onclick="reconcileAccounts()">
                                    <i class="fas fa-balance-scale mb-2"></i>
                                    <br>مطابقة الحسابات
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- الحسابات النشطة -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-university me-2"></i>
                                    الحسابات النشطة
                                </h5>
                                <a href="treasury_accounts.php" class="btn btn-sm btn-outline-primary">
                                    عرض الكل
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>اسم الحساب</th>
                                                <th>النوع</th>
                                                <th>الرصيد</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($accounts)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">
                                                    <i class="fas fa-university fa-2x mb-2 opacity-50"></i>
                                                    <br>لا توجد حسابات نشطة
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($accounts as $account): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($account['account_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($account['account_number']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($account['account_type']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold <?php echo $account['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo number_format($account['balance'], 2); ?> ر.س
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">نشط</span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- آخر المعاملات -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    آخر المعاملات
                                </h5>
                                <a href="transactions.php" class="btn btn-sm btn-outline-primary">
                                    عرض الكل
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>الحساب</th>
                                                <th>النوع</th>
                                                <th>المبلغ</th>
                                                <th>التاريخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_transactions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">
                                                    <i class="fas fa-exchange-alt fa-2x mb-2 opacity-50"></i>
                                                    <br>لا توجد معاملات حديثة
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_transactions as $transaction): ?>
                                                <tr>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($transaction['account_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $type_icon = $transaction['transaction_type'] == 'credit' ? 'fas fa-arrow-down text-success' : 'fas fa-arrow-up text-danger';
                                                        $type_text = $transaction['transaction_type'] == 'credit' ? 'دائن' : 'مدين';
                                                        ?>
                                                        <i class="<?php echo $type_icon; ?> me-1"></i>
                                                        <?php echo $type_text; ?>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold <?php echo $transaction['transaction_type'] == 'credit' ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo number_format($transaction['amount'], 2); ?> ر.س
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal لإضافة معاملة جديدة -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة معاملة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTransactionForm">
                        <div class="mb-3">
                            <label class="form-label">الحساب</label>
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
                            <label class="form-label">نوع المعاملة</label>
                            <select class="form-select" name="transaction_type" required>
                                <option value="">اختر النوع</option>
                                <option value="credit">دائن (إيداع)</option>
                                <option value="debit">مدين (سحب)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">المبلغ</label>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">البيان</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">المرجع</label>
                            <input type="text" class="form-control" name="reference" placeholder="رقم المرجع (اختياري)">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="submitTransaction()">
                        <i class="fas fa-save me-1"></i>
                        حفظ المعاملة
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
    function submitTransaction() {
        const form = document.getElementById('addTransactionForm');
        const formData = new FormData(form);
        
        // هنا يمكن إضافة كود AJAX لإرسال البيانات
        alert('سيتم إضافة المعاملة...');
        
        // إغلاق النافذة المنبثقة
        bootstrap.Modal.getInstance(document.getElementById('addTransactionModal')).hide();
        
        // إعادة تحميل الصفحة
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
    
    function transferMoney() {
        alert('سيتم فتح صفحة التحويل بين الحسابات...');
        // window.location.href = 'transfer.php';
    }
    
    function generateReport() {
        alert('سيتم إنشاء التقرير المالي...');
        // window.location.href = 'financial_report.php';
    }
    
    function reconcileAccounts() {
        alert('سيتم فتح صفحة مطابقة الحسابات...');
        // window.location.href = 'reconcile.php';
    }
    </script>

    <style>
    .btn-group .btn {
        border-radius: 6px;
    }
    
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .btn-outline-success:hover,
    .btn-outline-primary:hover,
    .btn-outline-info:hover,
    .btn-outline-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .btn-outline-success,
    .btn-outline-primary,
    .btn-outline-info,
    .btn-outline-warning {
        transition: all 0.3s ease;
        padding: 1rem;
        height: auto;
    }
    </style>
</body>
</html>