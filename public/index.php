<?php

// Front Controller - KTX ĐHQG-HCM

// Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Load Helpers & Environment
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
load_env(dirname(__DIR__) . '/.env');

// Cấu hình báo lỗi theo môi trường (.env)
$isDebug = function_exists('config') ? (bool)config('app.debug') : (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));

if ($isDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// 2. Autoloader
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $base_dir = dirname(__DIR__) . '/app/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use App\Core\Router;
use App\Middlewares\AuthMiddleware;

// 3. Define Routes
Router::get('/', 'HomeController@index');
Router::get('/login', 'AuthController@showLogin');
Router::post('/login', 'AuthController@login');
Router::get('/logout', 'AuthController@logout');

// Student Routes
Router::get('/student/dashboard', 'Student\DashboardController@index', [AuthMiddleware::class]);
Router::get('/student/profile', 'Student\ProfileController@index');
Router::get('/student/payments', 'Student\PaymentController@index');

// Staff Routes
Router::get('/staff/dashboard', 'Staff\DashboardController@index', [AuthMiddleware::class]);
Router::get('/staff/rooms', 'Staff\DashboardController@rooms', [AuthMiddleware::class]);
Router::get('/staff/applications', 'Staff\DashboardController@applications', [AuthMiddleware::class]);
Router::get('/staff/utilities', 'Staff\DashboardController@utilities', [AuthMiddleware::class]);
Router::get('/staff/requests', 'Staff\DashboardController@requests', [AuthMiddleware::class]);
Router::get('/staff/maintenance', 'Staff\DashboardController@maintenance', [AuthMiddleware::class]);
Router::get('/staff/discipline', 'Staff\DashboardController@discipline', [AuthMiddleware::class]);
Router::post('/staff/approve-application', 'Staff\DashboardController@approveApplication', [AuthMiddleware::class]);

// Admin Routes
Router::get('/admin/dashboard', 'Admin\DashboardController@index');
Router::get('/admin/users', 'Admin\DashboardController@users');
Router::post('/admin/users/save', 'Admin\DashboardController@saveUser');
Router::post('/admin/users/reset-password', 'Admin\DashboardController@resetPassword');
Router::post('/admin/users/toggle-status', 'Admin\DashboardController@toggleUserStatus');
Router::post('/admin/users/delete', 'Admin\DashboardController@deleteUser');
Router::get('/admin/rooms', 'Admin\DashboardController@rooms');
Router::get('/admin/periods', 'Admin\DashboardController@periods');
Router::get('/admin/room-types', 'Admin\DashboardController@roomTypes');
Router::get('/admin/universities', 'Admin\DashboardController@universities');

// Cron Trigger Routes
Router::get('/cron/trigger_release', function () {
    $res = \App\Services\AutoReleaseService::scanAndReleaseExpiredBeds();
    json_response([
        'status' => 'success',
        'action' => 'Cron Job 72h Auto Release Beds',
        'result' => $res
    ]);
});

Router::get('/cron/trigger_absence', function () {
    $res = \App\Services\AttendanceDisciplineService::detectLongTermAbsence(30);
    json_response([
        'status' => 'success',
        'action' => 'Cron Job 30-Day Absence Detection',
        'result' => $res
    ]);
});

// 4. Dispatch Request & Catch Errors
try {
    Router::dispatch();
} catch (Throwable $e) { // Dùng Throwable để bắt được cả Error lẫn Exception
    http_response_code(500);

    $message = $isDebug
        ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'Đã có lỗi kỹ thuật xảy ra trong quá trình xử lý yêu cầu. Vui lòng liên hệ ban quản lý KTX.';

    $fileInfo = $isDebug
        ? htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8')
        : null;

    $trace = $isDebug
        ? htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
        : null;

    echo <<<HTML
    <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #0f172a; min-height: 100vh; padding: 40px 20px; box-sizing: border-box; display: flex; justify-content: center; align-items: flex-start;">
        <div style="background: #ffffff; width: 100%; max-width: 860px; border-radius: 14px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); overflow: hidden; border: 1px solid #e2e8f0;">
            
            <div style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); padding: 22px 28px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="background: rgba(255,255,255,0.2); width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff;">
                        ⚠️
                    </div>
                    <div>
                        <h2 style="margin: 0; color: #ffffff; font-size: 19px; font-weight: 700;">Hệ Thống Gặp Sự Cố</h2>
                        <span style="color: #fecaca; font-size: 13px;">KTX ĐHQG-HCM &bull; Application Error</span>
                    </div>
                </div>
                <span style="background: #fff; color: #b91c1c; font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 9999px;">HTTP 500</span>
            </div>

            <div style="padding: 28px;">
                <div style="background: #fff5f5; border-left: 4px solid #ef4444; padding: 16px 18px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
                    <div style="font-size: 12px; font-weight: 700; color: #991b1b; text-transform: uppercase; margin-bottom: 4px;">Thông báo</div>
                    <div style="color: #7f1d1d; font-size: 15px; font-weight: 600; line-height: 1.5;">{$message}</div>
                </div>
HTML;

    if ($fileInfo) {
        echo <<<HTML
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 22px;">
                    <span style="font-weight: 600; color: #334155;">Vị trí phát sinh:</span>
                    <code style="font-family: Consolas, monospace; color: #0284c7;">{$fileInfo}</code>
                </div>
HTML;
    }

    if ($trace) {
        echo <<<HTML
                <div style="margin-top: 15px;">
                    <div style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px;">Stack Trace (Debug Mode)</div>
                    <pre style="background: #1e293b; color: #f1f5f9; padding: 18px; border-radius: 8px; font-size: 12.5px; line-height: 1.6; font-family: Consolas, monospace; overflow-x: auto;">{$trace}</pre>
                </div>
HTML;
    }

    echo <<<HTML
            </div>
            
            <div style="background: #f8fafc; padding: 14px 28px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                <a href="/" style="display: inline-flex; align-items: center; background: #0f172a; color: #fff; text-decoration: none; font-size: 13px; font-weight: 500; padding: 8px 16px; border-radius: 6px;">
                    &larr; Quay về trang chủ
                </a>
            </div>
        </div>
    </div>
HTML;
}
