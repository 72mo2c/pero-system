<?php
/**
 * Treasury Accounts Management
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

$current_page = 'accounts';
$page_title = 'إدارة الحسابات';

// معالجة العمليات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());
        
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO treasury_accounts (account_name, account_number, account_type, bank_name, balance, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['account_name'],
                $_POST['account_number'],
                $_POST['account_type'],
                $_POST['bank_name'],
                floatval($_POST['balance']),
                $_POST['description'],
                $_POST['status'],
                Session::getUserId()
            ]);
            $success = "تم إضافة الحساب بنجاح";
            
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE treasury_accounts SET account_name = ?, account_number = ?, account_type = ?, bank_name = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([
                $_POST['account_name'],
                $_POST['account_number'],
                $_POST['account_type'],
                $_POST['bank_name'],
                $_POST['description'],
                $_POST['status'],
                $_POST['account_id']
            ]);
            $success = "تم تحديث الحساب بنجاح";
        }
        
    } catch (Exception $e) {
        $error = "خطأ في العملية: " . $e->getMessage();
    }
}

try {
    $pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());
    
    // جلب جميع الحسابات
    $query = "SELECT * FROM treasury_accounts ORDER BY created_at DESC";
    $accounts = $pdo->query($query)->fetchAll();
    
} catch (Exception $e) {
    $error = "خطأ في جلب الحسابات: " . $e->getMessage();
    $accounts = [];
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
                        <i class="fas fa-university text-primary me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#accountModal">
                        <i class="fas fa-plus me-1"></i>
                        إضافة حساب جديد
                    </button>
                </div>

                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- جدول الحسابات -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">قائمة الحسابات</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>اسم الحساب</th>
                                        <th>رقم الحساب</th>
                                        <th>النوع</th>
                                        <th>البنك</th>
                                        <th>الرصيد</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($accounts)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-university fa-3x mb-3 opacity-50"></i>
                                            <br>لا توجد حسابات مسجلة
                                            <br>
                                            <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#accountModal">
                                                إضافة أول حساب
                                            </button>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($accounts as $index => $account): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($account['account_name']); ?></strong>
                                                <?php if (!empty($account['description'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($account['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($account['account_number']); ?></code>
                                            </td>
                                            <td>
                                                <?php
                                                $type_colors = [
                                                    'bank' => 'bg-primary',
                                                    'cash' => 'bg-success',
                                                    'credit' => 'bg-warning',
                                                    'investment' => 'bg-info'
                                                ];
                                                $color = $type_colors[$account['account_type']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $color; ?>">
                                                    <?php echo htmlspecialchars($account['account_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($account['bank_name']); ?></td>
                                            <td>
                                                <span class="fw-bold <?php echo $account['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo number_format($account['balance'], 2); ?> ر.س
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($account['status'] === 'active'): ?>
                                                <span class="badge bg-success">نشط</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">غير نشط</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('Y-m-d', strtotime($account['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" onclick="editAccount(<?php echo $account['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info" onclick="viewTransactions(<?php echo $account['id']; ?>)">
                                                        <i class="fas fa-history"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" onclick="deleteAccount(<?php echo $account['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Modal لإضافة/تعديل الحساب -->
    <div class="modal fade" id="accountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">إضافة حساب جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="account_id" id="accountId">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم الحساب *</label>
                                <input type="text" class="form-control" name="account_name" id="accountName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الحساب *</label>
                                <input type="text" class="form-control" name="account_number" id="accountNumber" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع الحساب *</label>
                                <select class="form-select" name="account_type" id="accountType" required>
                                    <option value="">اختر النوع</option>
                                    <option value="bank">حساب بنكي</option>
                                    <option value="cash">نقدي</option>
                                    <option value="credit">ائتماني</option>
                                    <option value="investment">استثماري</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اسم البنك</label>
                                <input type="text" class="form-control" name="bank_name" id="bankName" placeholder="اسم البنك (اختياري)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الرصيد الافتتاحي</label>
                                <input type="number" class="form-control" name="balance" id="balance" step="0.01" value="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الحالة *</label>
                                <select class="form-select" name="status" id="status" required>
                                    <option value="active">نشط</option>
                                    <option value="inactive">غير نشط</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">وصف الحساب</label>
                                <textarea class="form-control" name="description" id="description" rows="3" placeholder="وصف مختصر للحساب (اختياري)"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            حفظ الحساب
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
    // بيانات الحسابات (سيتم تحديثها من PHP)
    const accountsData = <?php echo json_encode($accounts); ?>;
    
    function editAccount(accountId) {
        const account = accountsData.find(a => a.id == accountId);
        if (!account) return;
        
        // تحديث عنوان النافذة
        document.getElementById('modalTitle').textContent = 'تعديل الحساب';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('accountId').value = accountId;
        
        // ملء البيانات
        document.getElementById('accountName').value = account.account_name;
        document.getElementById('accountNumber').value = account.account_number;
        document.getElementById('accountType').value = account.account_type;
        document.getElementById('bankName').value = account.bank_name || '';
        document.getElementById('description').value = account.description || '';
        document.getElementById('status').value = account.status;
        
        // إخفاء حقل الرصيد في التعديل (لأنه يتغير عبر المعاملات)
        document.getElementById('balance').closest('.col-md-6').style.display = 'none';
        
        // إظهار النافذة
        new bootstrap.Modal(document.getElementById('accountModal')).show();
    }
    
    function viewTransactions(accountId) {
        window.location.href = 'transactions.php?account_id=${accountId}';
    }
    
    function deleteAccount(accountId) {
        if (confirm('هل أنت متأكد من حذف هذا الحساب؟\n\nتحذير: سيتم حذف جميع المعاملات المرتبطة بهذا الحساب!')) {
            // إرسال طلب الحذف
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="account_id" value="${accountId}">
            ';
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // إعادة تعيين النافذة عند الإغلاق
    document.getElementById('accountModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').textContent = 'إضافة حساب جديد';
        document.getElementById('formAction').value = 'add';
        document.getElementById('accountId').value = '';
        document.querySelector('form').reset();
        document.getElementById('balance').closest('.col-md-6').style.display = 'block';
    });
    </script>

    <style>
    .btn-group .btn {
        border-radius: 4px;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .badge {
        font-size: 0.75em;
    }
    
    code {
        background-color: #f8f9fa;
        color: #6f42c1;
        padding: 0.25rem 0.375rem;
        border-radius: 0.25rem;
    }
    </style>
</body>
</html>