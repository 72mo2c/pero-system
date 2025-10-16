<?php
/**
 * Utilities Class for Warehouse SaaS System
 * Common utility functions
 * Created: 2025-10-16
 */

class Utils {
    
    /**
     * Generate next sequence number
     */
    public static function generateSequenceNumber($prefix, $table, $column, $pdo, $length = 6) {
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING($column, ?) AS UNSIGNED)) as max_num FROM $table WHERE $column LIKE ?");
        $prefix_length = strlen($prefix) + 1;
        $stmt->execute([$prefix_length, $prefix . '%']);
        $result = $stmt->fetch();
        
        $next_number = ($result['max_num'] ?? 0) + 1;
        return $prefix . str_pad($next_number, $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Format number for display
     */
    public static function formatNumber($number, $decimals = 2) {
        return number_format($number, $decimals, '.', ',');
    }
    
    /**
     * Format currency
     */
    public static function formatCurrency($amount, $currency = 'ر.س') {
        return self::formatNumber($amount, 2) . ' ' . $currency;
    }
    
    /**
     * Format date for display
     */
    public static function formatDate($date, $format = 'Y-m-d') {
        if (empty($date) || $date === '0000-00-00') return '';
        
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        
        return $date->format($format);
    }
    
    /**
     * Format datetime for display
     */
    public static function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '';
        
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }
        
        return $datetime->format($format);
    }
    
    /**
     * Get time ago string
     */
    public static function timeAgo($datetime) {
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }
        
        $now = new DateTime();
        $diff = $now->diff($datetime);
        
        if ($diff->days > 0) {
            return $diff->days . ' يوم';
        } elseif ($diff->h > 0) {
            return $diff->h . ' ساعة';
        } elseif ($diff->i > 0) {
            return $diff->i . ' دقيقة';
        } else {
            return 'الآن';
        }
    }
    
    /**
     * Generate pagination HTML
     */
    public static function generatePagination($current_page, $total_pages, $base_url, $params = []) {
        if ($total_pages <= 1) return '';
        
        $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
        
        // Previous button
        if ($current_page > 1) {
            $prev_params = array_merge($params, ['page' => $current_page - 1]);
            $prev_url = $base_url . '?' . http_build_query($prev_params);
            $html .= '<li class="page-item"><a class="page-link" href="' . $prev_url . '">السابق</a></li>';
        }
        
        // Page numbers
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $page_params = array_merge($params, ['page' => $i]);
            $page_url = $base_url . '?' . http_build_query($page_params);
            $active = ($i === $current_page) ? ' active' : '';
            $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
        }
        
        // Next button
        if ($current_page < $total_pages) {
            $next_params = array_merge($params, ['page' => $current_page + 1]);
            $next_url = $base_url . '?' . http_build_query($next_params);
            $html .= '<li class="page-item"><a class="page-link" href="' . $next_url . '">التالي</a></li>';
        }
        
        $html .= '</ul></nav>';
        return $html;
    }
    
    /**
     * Send email notification
     */
    public static function sendEmail($to, $subject, $message, $from_name = null, $from_email = null) {
        $from_name = $from_name ?: FROM_NAME;
        $from_email = $from_email ?: FROM_EMAIL;
        
        $headers = [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: Warehouse SaaS System'
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Log activity
     */
    public static function logActivity($pdo, $user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null, $description = null) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $action,
                $table_name,
                $record_id,
                $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null,
                $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log('Activity logging error: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload file
     */
    public static function uploadFile($file, $destination_path, $allowed_types = [], $max_size = null) {
        // Validate file
        $validation_errors = Security::validateFileUpload($file, $allowed_types, $max_size);
        if (!empty($validation_errors)) {
            return ['success' => false, 'errors' => $validation_errors];
        }
        
        // Create destination directory if it doesn't exist
        if (!is_dir($destination_path)) {
            mkdir($destination_path, 0755, true);
        }
        
        // Generate secure file name
        $file_name = Security::generateSecureFileName($file['name']);
        $full_path = $destination_path . '/' . $file_name;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            return [
                'success' => true,
                'file_name' => $file_name,
                'file_path' => $full_path,
                'file_size' => $file['size']
            ];
        } else {
            return ['success' => false, 'errors' => ['خطأ في رفع الملف']];
        }
    }
    
    /**
     * Delete file safely
     */
    public static function deleteFile($file_path) {
        if (file_exists($file_path) && is_file($file_path)) {
            return unlink($file_path);
        }
        return false;
    }
    
    /**
     * Create backup
     */
    public static function createBackup($tenant_id = null) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backup_name = ($tenant_id ? "tenant_{$tenant_id}" : 'main_system') . "_backup_{$timestamp}";
            $backup_path = ROOT_PATH . '/backups/' . $backup_name . '.sql';
            
            // Create backups directory if it doesn't exist
            if (!is_dir(dirname($backup_path))) {
                mkdir(dirname($backup_path), 0755, true);
            }
            
            // Determine database name
            if ($tenant_id) {
                $database_name = DatabaseConfig::getTenantDatabaseName($tenant_id);
            } else {
                $database_name = 'warehouse_saas_main';
            }
            
            // Create mysqldump command
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s %s > %s',
                'localhost',
                'root',
                '',
                $database_name,
                $backup_path
            );
            
            exec($command, $output, $return_code);
            
            if ($return_code === 0 && file_exists($backup_path)) {
                // Compress backup
                $compressed_path = $backup_path . '.gz';
                $command = "gzip {$backup_path}";
                exec($command);
                
                return [
                    'success' => true,
                    'backup_name' => $backup_name,
                    'file_path' => file_exists($compressed_path) ? $compressed_path : $backup_path,
                    'file_size' => filesize(file_exists($compressed_path) ? $compressed_path : $backup_path)
                ];
            } else {
                return ['success' => false, 'error' => 'فشل في إنشاء النسخة الاحتياطية'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate export file (CSV/Excel)
     */
    public static function exportToCSV($data, $headers, $filename = null) {
        $filename = $filename ?: 'export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Add UTF-8 BOM for proper Arabic display in Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Generate random color
     */
    public static function generateRandomColor() {
        $colors = [
            '#007bff', '#6c757d', '#28a745', '#dc3545', '#ffc107', 
            '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997'
        ];
        return $colors[array_rand($colors)];
    }
    
    /**
     * Calculate percentage
     */
    public static function calculatePercentage($part, $whole) {
        if ($whole == 0) return 0;
        return round(($part / $whole) * 100, 2);
    }
    
    /**
     * Convert array to object
     */
    public static function arrayToObject($array) {
        return json_decode(json_encode($array));
    }
    
    /**
     * Convert object to array
     */
    public static function objectToArray($object) {
        return json_decode(json_encode($object), true);
    }
    
    /**
     * Clean string for URL
     */
    public static function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        return empty($text) ? 'n-a' : $text;
    }
    
    /**
     * Truncate text
     */
    public static function truncateText($text, $length = 100, $suffix = '...') {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $suffix;
    }
    
    /**
     * Get file size in human readable format
     */
    public static function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Send JSON response
     */
    public static function sendJsonResponse($data, $status_code = 200) {
        http_response_code($status_code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Generate QR Code data URL (simple text-based)
     */
    public static function generateSimpleQR($text, $size = 200) {
        // This is a simple placeholder - in production you would use a proper QR library
        return "data:image/svg+xml;base64," . base64_encode(
            '<svg width="' . $size . '" height="' . $size . '" xmlns="http://www.w3.org/2000/svg">' .
            '<rect width="100%" height="100%" fill="white"/>' .
            '<text x="50%" y="50%" text-anchor="middle" dy=".3em" font-family="monospace" font-size="12">QR: ' . htmlspecialchars(substr($text, 0, 20)) . '</text>' .
            '</svg>'
        );
    }
}
?>