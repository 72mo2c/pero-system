<?php
/**
 * إصلاح بديل لمشكلة العمود is_active دون حذف البيانات
 * Alternative fix for is_active column issue without losing data
 * Created: 2025-10-16
 */

echo "<h2>إصلاح مشكلة العمود is_active (حفظ البيانات)</h2>";
echo "<p>هذا الإصلاح سيحول البيانات الموجودة بدلاً من حذفها</p>";

try {
    // Configuration
    $config = [
        'host' => 'localhost',
        'username' => 'root', 
        'password' => '',
        'database' => 'warehouse_saas_main',
        'charset' => 'utf8mb4'
    ];
    
    // Connect to database
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✓ تم الاتصال بقاعدة البيانات</p>";
    
    // Check if table exists and what columns it has
    echo "<p>جاري فحص بنية الجدول الحالية...</p>";
    
    $result = $pdo->query("DESCRIBE tenants");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    $hasIsActive = in_array('is_active', $columns);
    $hasIsApproved = in_array('is_approved', $columns);
    $hasStatus = in_array('status', $columns);
    
    echo "<p>الأعمدة الموجودة:</p>";
    echo "<ul>";
    if ($hasIsActive) echo "<li>is_active - موجود</li>";
    if ($hasIsApproved) echo "<li>is_approved - موجود</li>";
    if ($hasStatus) echo "<li>status - موجود</li>";
    echo "</ul>";
    
    // If we have is_active but not status, we need to convert
    if ($hasIsActive && !$hasStatus) {
        echo "<p>جاري تحويل البيانات من is_active/is_approved إلى status...</p>";
        
        // Add status column
        $pdo->exec("ALTER TABLE tenants ADD COLUMN status ENUM('pending', 'active', 'suspended', 'cancelled') DEFAULT 'pending'");
        echo "<p>✓ تم إضافة عمود status</p>";
        
        // Update data based on existing values
        if ($hasIsApproved) {
            // If both columns exist, use logic: active if both true, pending if approved but not active, etc.
            $pdo->exec("UPDATE tenants SET status = CASE 
                WHEN is_active = 1 AND is_approved = 1 THEN 'active'
                WHEN is_active = 0 AND is_approved = 1 THEN 'suspended'
                WHEN is_approved = 0 THEN 'pending'
                ELSE 'pending'
            END");
        } else {
            // Only is_active exists
            $pdo->exec("UPDATE tenants SET status = CASE 
                WHEN is_active = 1 THEN 'active'
                ELSE 'pending'
            END");
        }
        echo "<p>✓ تم تحويل البيانات إلى عمود status</p>";
        
        // Remove old columns
        if ($hasIsActive) {
            $pdo->exec("ALTER TABLE tenants DROP COLUMN is_active");
            echo "<p>✓ تم حذف عمود is_active</p>";
        }
        if ($hasIsApproved) {
            $pdo->exec("ALTER TABLE tenants DROP COLUMN is_approved");
            echo "<p>✓ تم حذف عمود is_approved</p>";
        }
        
        echo "<h3 style='color: green;'>✅ تم الإصلاح بنجاح مع حفظ البيانات!</h3>";
        
    } elseif ($hasStatus && !$hasIsActive) {
        echo "<h3 style='color: green;'>✅ الجدول يحتوي على البنية الصحيحة مسبقاً!</h3>";
        echo "<p>الجدول يستخدم عمود status بالفعل</p>";
        
    } else {
        echo "<h3 style='color: orange;'>⚠️ حالة غير متوقعة</h3>";
        echo "<p>الجدول يحتوي على الأعمدة التالية: " . implode(', ', $columns) . "</p>";
    }
    
    // Show current data
    echo "<hr>";
    echo "<h3>البيانات الحالية في الجدول:</h3>";
    $tenants = $pdo->query("SELECT id, tenant_id, company_name, status FROM tenants LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($tenants) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Tenant ID</th><th>Company Name</th><th>Status</th></tr>";
        foreach ($tenants as $tenant) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($tenant['id']) . "</td>";
            echo "<td>" . htmlspecialchars($tenant['tenant_id']) . "</td>";
            echo "<td>" . htmlspecialchars($tenant['company_name']) . "</td>";
            echo "<td>" . htmlspecialchars($tenant['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>لا توجد بيانات في الجدول</p>";
    }
    
    echo "<p><a href='developer_portal/tenants.php' target='_blank'>اختبر صفحة إدارة الشركات الآن</a></p>";
    echo "<p><a href='developer_portal/' target='_blank'>انتقل إلى لوحة المطور</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>خطأ:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>تأكد من:</strong></p>";
    echo "<ul>";
    echo "<li>تشغيل خادم MySQL</li>";
    echo "<li>وجود قاعدة البيانات warehouse_saas_main</li>";
    echo "<li>صحة بيانات الاتصال</li>";
    echo "</ul>";
}
?>