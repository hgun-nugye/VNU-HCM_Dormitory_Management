<?php

/**
 * Load environment variables from .env file if not already loaded
 */
if (!function_exists('load_env')) {
    function load_env($filePath) {
        if (!file_exists($filePath)) {
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                $value = trim($value, '"\'');
                if (getenv($name) === false) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

/**
 * Get config value
 */
if (!function_exists('config')) {
    function config($key, $default = null) {
        static $configs = [];
        $parts = explode('.', $key);
        $file = $parts[0];
        
        if (!isset($configs[$file])) {
            $filePath = dirname(__DIR__, 2) . "/config/{$file}.php";
            if (file_exists($filePath)) {
                $configs[$file] = require $filePath;
            } else {
                $configs[$file] = [];
            }
        }
        
        $value = $configs[$file];
        for ($i = 1; $i < count($parts); $i++) {
            if (isset($value[$parts[$i]])) {
                $value = $value[$parts[$i]];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}

/**
 * Render a view file
 */
if (!function_exists('view')) {
    function view($viewName, $data = []) {
        extract($data);
        $viewPath = dirname(__DIR__, 2) . '/views/' . str_replace('.', '/', $viewName) . '.php';
        if (!file_exists($viewPath)) {
            throw new Exception("View file not found: {$viewPath}");
        }
        require $viewPath;
    }
}

/**
 * Redirect to an internal or external URL
 */
if (!function_exists('redirect')) {
    function redirect($path) {
        if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            $path = url($path);
        }
        header("Location: {$path}");
        exit;
    }
}

/**
 * Generate full URL based on application base path
 */
if (!function_exists('url')) {
    function url($path = '') {
        $baseUrl = rtrim(config('app.url', 'http://localhost/Dormitory_Management'), '/');
        $path = ltrim($path, '/');
        return $baseUrl . ($path ? '/' . $path : '');
    }
}

/**
 * Asset URL helper
 */
if (!function_exists('asset')) {
    function asset($path) {
        return url('public/assets/' . ltrim($path, '/'));
    }
}

/**
 * Current Request URI helper
 */
if (!function_exists('current_uri')) {
    function current_uri() {
        if (!class_exists('\App\Core\Request')) {
            require_once dirname(__DIR__) . '/Core/Request.php';
        }
        return (new \App\Core\Request())->getUri();
    }
}

/**
 * Check if the given route path is currently active
 */
if (!function_exists('is_active_nav')) {
    function is_active_nav($path) {
        $currentUri = trim(current_uri(), '/');
        $targetPath = trim($path, '/');

        if ($targetPath === '' || $targetPath === '/') {
            return $currentUri === '' || $currentUri === '/';
        }

        return $currentUri === $targetPath || str_starts_with($currentUri, $targetPath . '/');
    }
}

/**
 * Return 'active' class string if the route is active
 */
if (!function_exists('nav_active_class')) {
    function nav_active_class($path, $activeClass = 'active') {
        return is_active_nav($path) ? $activeClass : '';
    }
}

/**
 * Session helper
 */
if (!function_exists('session')) {
    function session($key = null, $default = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($key === null) {
            return $_SESSION;
        }
        return $_SESSION[$key] ?? $default;
    }
}

/**
 * Auth helper
 */
if (!function_exists('auth')) {
    function auth() {
        return \App\Core\Auth::user();
    }
}

/**
 * Format money in VND
 */
if (!function_exists('format_money')) {
    function format_money($amount) {
        return number_format($amount, 0, ',', '.') . ' VNĐ';
    }
}

/**
 * CSRF token helper
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
    }
}

/**
 * Response JSON
 */
if (!function_exists('json_response')) {
    function json_response($data, $statusCode = 200) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

/**
 * Đọc số tiền thành chữ tiếng Việt (Chuẩn Kế toán Tài chính)
 */
if (!function_exists('convert_number_to_vietnamese_words')) {
    function convert_number_to_vietnamese_words($number) {
        $number = (float)$number;
        if ($number == 0) {
            return 'Không đồng';
        }

        $digits = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
        $units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

        $readThreeDigits = function ($n, $full = false) use ($digits) {
            $hundred = (int)floor($n / 100);
            $ten = (int)floor(($n % 100) / 10);
            $unit = (int)($n % 10);
            $res = '';

            if ($hundred > 0 || $full) {
                $res .= $digits[$hundred] . ' trăm ';
            }

            if ($ten > 1) {
                $res .= $digits[$ten] . ' mươi ';
                if ($unit == 1) $res .= 'mốt ';
                elseif ($unit == 5) $res .= 'lăm ';
                elseif ($unit > 0) $res .= $digits[$unit] . ' ';
            } elseif ($ten == 1) {
                $res .= 'mười ';
                if ($unit == 5) $res .= 'lăm ';
                elseif ($unit > 0) $res .= $digits[$unit] . ' ';
            } elseif ($ten == 0 && ($hundred > 0 || $full)) {
                if ($unit > 0) {
                    $res .= 'lẻ ' . $digits[$unit] . ' ';
                }
            } else {
                if ($unit > 0) {
                    $res .= $digits[$unit] . ' ';
                }
            }

            return trim($res);
        };

        $groups = [];
        $temp = $number;
        while ($temp > 0) {
            $groups[] = (int)fmod($temp, 1000);
            $temp = floor($temp / 1000);
        }

        $words = [];
        $count = count($groups);
        for ($i = $count - 1; $i >= 0; $i--) {
            $g = $groups[$i];
            if ($g > 0) {
                $isFull = ($i < $count - 1);
                $w = $readThreeDigits($g, $isFull);
                if (!empty($w)) {
                    $words[] = $w . ' ' . $units[$i];
                }
            }
        }

        $result = trim(implode(' ', $words));
        // Viết hoa chữ cái đầu và thêm đuôi đồng
        $result = mb_strtoupper(mb_substr($result, 0, 1)) . mb_substr($result, 1) . ' đồng chẵn';
        return preg_replace('/\s+/', ' ', $result);
    }
}

/**
 * Debug and Die
 */
if (!function_exists('dd')) {
    function dd(...$vars) {
        echo '<style>body{background:#18181b;color:#e4e4e7;padding:20px;font-family:monospace;font-size:14px;}</style>';
        echo '<h2>Debugging:</h2>';
        foreach ($vars as $v) {
            echo '<pre style="background:#27272a;padding:15px;border-radius:8px;border:1px solid #3f3f46;">';
            var_dump($v);
            echo '</pre>';
        }
        exit;
    }
}

/**
 * Tự động phát hiện địa chỉ IP Wi-Fi/LAN của máy chủ (dành cho kết nối app điện thoại qua mã QR)
 */
if (!function_exists('get_local_server_ip')) {
    function get_local_server_ip(): string {
        static $cachedIp = null;
        if ($cachedIp !== null) {
            return $cachedIp;
        }

        // 1. Nếu người dùng truy cập qua IP LAN trực tiếp trên thanh địa chỉ trình duyệt
        if (!empty($_SERVER['HTTP_HOST'])) {
            $host = explode(':', $_SERVER['HTTP_HOST'])[0];
            if ($host !== 'localhost' && $host !== '127.0.0.1' && filter_var($host, FILTER_VALIDATE_IP)) {
                return $cachedIp = $host;
            }
        }

        // 2. Tự động đọc IP Wi-Fi/LAN qua ipconfig trên Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = @shell_exec('ipconfig');
            if (!empty($output)) {
                $adapters = preg_split('/(?:\r?\n)(?=[a-zA-Z0-9][^\r\n]*adapter)/i', $output);
                
                // Ưu tiên 1: Adapter Wi-Fi có Default Gateway (đang kết nối internet thực tế)
                foreach ($adapters as $adapter) {
                    if (preg_match('/Wi-Fi|Wireless|WLAN/i', $adapter)) {
                        if (preg_match('/IPv4 Address[ .:]+([0-9.]+)/i', $adapter, $ipMatch)) {
                            $ip = trim($ipMatch[1]);
                            if (preg_match('/Default Gateway[ .:]+([0-9.]+)/i', $adapter, $gwMatch) && trim($gwMatch[1]) !== '') {
                                return $cachedIp = $ip;
                            }
                        }
                    }
                }

                // Ưu tiên 2: Bất kỳ Adapter Wi-Fi nào có IPv4 (không bị disconnected)
                foreach ($adapters as $adapter) {
                    if (preg_match('/Wi-Fi|Wireless|WLAN/i', $adapter) && !preg_match('/Media disconnected/i', $adapter)) {
                        if (preg_match('/IPv4 Address[ .:]+([0-9.]+)/i', $adapter, $ipMatch)) {
                            return $cachedIp = trim($ipMatch[1]);
                        }
                    }
                }

                // Ưu tiên 3: Adapter Ethernet vật lý có Default Gateway (bỏ qua VMware, VirtualBox, vEthernet)
                foreach ($adapters as $adapter) {
                    if (!preg_match('/VMware|VirtualBox|vEthernet|Loopback/i', $adapter)) {
                        if (preg_match('/IPv4 Address[ .:]+([0-9.]+)/i', $adapter, $ipMatch)) {
                            $ip = trim($ipMatch[1]);
                            if (preg_match('/Default Gateway[ .:]+([0-9.]+)/i', $adapter, $gwMatch) && trim($gwMatch[1]) !== '') {
                                return $cachedIp = $ip;
                            }
                        }
                    }
                }
            }
        }

        // 3. Fallback bằng gethostbynamel (loại trừ các card mạng ảo VMware và loopback)
        $ips = @gethostbynamel(gethostname());
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                if ($ip !== '127.0.0.1' && !str_starts_with($ip, '192.168.17.') && !str_starts_with($ip, '192.168.179.') && !str_ends_with($ip, '.1')) {
                    return $cachedIp = $ip;
                }
            }
            foreach ($ips as $ip) {
                if ($ip !== '127.0.0.1') {
                    return $cachedIp = $ip;
                }
            }
        }

        return $cachedIp = ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
    }
}
