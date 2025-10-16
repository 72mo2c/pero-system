<?php
/**
 * Security Class for Warehouse SaaS System
 * Handles security, encryption, and protection mechanisms
 * Created: 2025-10-16
 */

class Security {
    private static $initialized = false;
    private static $csrf_tokens = [];
    
    /**
     * Initialize security settings
     */
    public static function initialize() {
        if (self::$initialized) return;
        
        // Set security headers
        self::setSecurityHeaders();
        
        // Initialize CSRF protection
        self::initCSRF();
        
        self::$initialized = true;
    }
    
    /**
     * Set security headers
     */
    private static function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self';");
        
        // HTTPS enforcement (if HTTPS is available)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Initialize CSRF protection
     */
    private static function initCSRF() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        self::$csrf_tokens = &$_SESSION['csrf_tokens'];
        
        // Clean expired tokens
        self::cleanExpiredTokens();
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken($name = 'default') {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + CSRF_TOKEN_EXPIRY;
        
        self::$csrf_tokens[$name] = [
            'token' => $token,
            'expiry' => $expiry
        ];
        
        return $token;
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token, $name = 'default') {
        if (!isset(self::$csrf_tokens[$name])) {
            return false;
        }
        
        $stored = self::$csrf_tokens[$name];
        
        // Check expiry
        if (time() > $stored['expiry']) {
            unset(self::$csrf_tokens[$name]);
            return false;
        }
        
        // Verify token
        $valid = hash_equals($stored['token'], $token);
        
        // Remove token after use (one-time use)
        if ($valid) {
            unset(self::$csrf_tokens[$name]);
        }
        
        return $valid;
    }
    
    /**
     * Get CSRF token HTML input
     */
    public static function getCSRFInput($name = 'default') {
        $token = self::generateCSRFToken($name);
        return "<input type='hidden' name='csrf_token' value='{$token}' />";
    }
    
    /**
     * Clean expired CSRF tokens
     */
    private static function cleanExpiredTokens() {
        $current_time = time();
        foreach (self::$csrf_tokens as $name => $data) {
            if ($current_time > $data['expiry']) {
                unset(self::$csrf_tokens[$name]);
            }
        }
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_HASH_ALGO);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Sanitize input
     */
    public static function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                // Allow safe HTML tags
                return strip_tags($input, '<p><br><strong><em><u><ol><ul><li>');
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate input
     */
    public static function validateInput($input, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule_set) {
            $value = isset($input[$field]) ? $input[$field] : null;
            
            foreach ($rule_set as $rule => $parameter) {
                switch ($rule) {
                    case 'required':
                        if ($parameter && (empty($value) && $value !== '0')) {
                            $errors[$field][] = "الحقل {$field} مطلوب";
                        }
                        break;
                    
                    case 'min_length':
                        if (!empty($value) && strlen($value) < $parameter) {
                            $errors[$field][] = "الحقل {$field} يجب أن يكون على الأقل {$parameter} أحرف";
                        }
                        break;
                    
                    case 'max_length':
                        if (!empty($value) && strlen($value) > $parameter) {
                            $errors[$field][] = "الحقل {$field} يجب أن يكون أقل من {$parameter} أحرف";
                        }
                        break;
                    
                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "الحقل {$field} يجب أن يكون بريد إلكتروني صحيح";
                        }
                        break;
                    
                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $errors[$field][] = "الحقل {$field} يجب أن يكون رقماً";
                        }
                        break;
                    
                    case 'min_value':
                        if (!empty($value) && is_numeric($value) && $value < $parameter) {
                            $errors[$field][] = "الحقل {$field} يجب أن يكون أكبر من أو يساوي {$parameter}";
                        }
                        break;
                    
                    case 'max_value':
                        if (!empty($value) && is_numeric($value) && $value > $parameter) {
                            $errors[$field][] = "الحقل {$field} يجب أن يكون أقل من أو يساوي {$parameter}";
                        }
                        break;
                    
                    case 'regex':
                        if (!empty($value) && !preg_match($parameter, $value)) {
                            $errors[$field][] = "تنسيق الحقل {$field} غير صحيح";
                        }
                        break;
                    
                    case 'in':
                        if (!empty($value) && !in_array($value, $parameter)) {
                            $errors[$field][] = "قيمة الحقل {$field} غير صحيحة";
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Check for SQL injection patterns
     */
    public static function detectSQLInjection($input) {
        $patterns = [
            '/union.*select/i',
            '/select.*from/i',
            '/insert.*into/i',
            '/update.*set/i',
            '/delete.*from/i',
            '/drop.*table/i',
            '/create.*table/i',
            '/alter.*table/i',
            '/exec.*\(/i',
            '/execute.*\(/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for XSS patterns
     */
    public static function detectXSS($input) {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>/i',
            '/<link[^>]*>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload.*=/i',
            '/onclick.*=/i',
            '/onerror.*=/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log security incident
     */
    public static function logSecurityIncident($type, $description, $data = []) {
        $log_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'data' => $data
        ];
        
        $log_message = json_encode($log_data, JSON_UNESCAPED_UNICODE);
        error_log("[SECURITY] {$log_message}");
        
        // Also log to database if available
        try {
            $pdo = DatabaseConfig::getMainConnection();
            $stmt = $pdo->prepare("
                INSERT INTO system_error_logs (error_type, error_message, request_data, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'security_incident',
                $description,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Ignore database errors for security logging
        }
    }
    
    /**
     * Rate limiting
     */
    public static function checkRateLimit($key, $max_attempts = 10, $time_window = 3600) {
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $current_time = time();
        $rate_key = $key . '_' . $_SERVER['REMOTE_ADDR'];
        
        if (!isset($_SESSION['rate_limits'][$rate_key])) {
            $_SESSION['rate_limits'][$rate_key] = [
                'count' => 1,
                'start_time' => $current_time
            ];
            return true;
        }
        
        $rate_data = $_SESSION['rate_limits'][$rate_key];
        
        // Reset if time window passed
        if (($current_time - $rate_data['start_time']) > $time_window) {
            $_SESSION['rate_limits'][$rate_key] = [
                'count' => 1,
                'start_time' => $current_time
            ];
            return true;
        }
        
        // Check if limit exceeded
        if ($rate_data['count'] >= $max_attempts) {
            self::logSecurityIncident('rate_limit_exceeded', "Rate limit exceeded for key: {$key}", [
                'key' => $key,
                'attempts' => $rate_data['count'],
                'time_window' => $time_window
            ]);
            return false;
        }
        
        // Increment count
        $_SESSION['rate_limits'][$rate_key]['count']++;
        return true;
    }
    
    /**
     * Generate secure file name
     */
    public static function generateSecureFileName($original_name) {
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
        $unique_id = uniqid('', true);
        
        return $safe_name . '_' . $unique_id . '.' . $extension;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowed_types = [], $max_size = null) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'حجم الملف كبير جداً';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'تم رفع الملف جزئياً فقط';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'لم يتم رفع أي ملف';
                    break;
                default:
                    $errors[] = 'خطأ في رفع الملف';
            }
            return $errors;
        }
        
        // Check file size
        $max_size = $max_size ?: MAX_FILE_SIZE;
        if ($file['size'] > $max_size) {
            $errors[] = 'حجم الملف كبير جداً. الحد الأقصى ' . number_format($max_size / 1024 / 1024, 1) . ' ميجابايت';
        }
        
        // Check file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!empty($allowed_types) && !in_array($file_extension, $allowed_types)) {
            $errors[] = 'نوع الملف غير مسموح. الأنواع المسموحة: ' . implode(', ', $allowed_types);
        }
        
        // Check for dangerous files
        $dangerous_extensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'asp', 'aspx', 'jsp', 'pl', 'py', 'sh', 'exe', 'bat', 'cmd'];
        if (in_array($file_extension, $dangerous_extensions)) {
            $errors[] = 'نوع الملف غير آمن';
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mime_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv'
        ];
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            $errors[] = 'نوع الملف غير مدعوم';
        }
        
        return $errors;
    }
    
    /**
     * Encrypt sensitive data
     */
    public static function encrypt($data, $key = null) {
        $key = $key ?: self::getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt($data, $key = null) {
        $key = $key ?: self::getEncryptionKey();
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key
     */
    private static function getEncryptionKey() {
        // In production, this should be stored securely
        return hash('sha256', 'warehouse_saas_encryption_key_2025', true);
    }
}
?>