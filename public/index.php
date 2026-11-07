<?php

// Front Controller - KTX ĐHQG-HCM

// 1. Load Helpers & Environment
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
load_env(dirname(__DIR__) . '/.env');
date_default_timezone_set(config('app.timezone', 'Asia/Ho_Chi_Minh'));

// Khởi tạo phiên làm việc bảo mật (OWASP standards)
require_once dirname(__DIR__) . '/app/Core/Session.php';
\App\Core\Session::start();

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
use App\Middlewares\StudentMiddleware;
use App\Middlewares\StaffMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\RoleMiddleware;

// 3. Define Routes
Router::get('/', 'HomeController@index');
Router::get('/login', 'AuthController@showLogin');
Router::post('/login', 'AuthController@login');
Router::get('/logout', 'AuthController@logout');
Router::get('/register', 'RegistrationController@show');
Router::get('/registration', 'RegistrationController@show');
Router::post('/register', 'RegistrationController@submit');
Router::post('/registration', 'RegistrationController@submit');
Router::post('/register/send-otp', 'RegistrationController@sendEmailOtp');
Router::get('/register/options', 'RegistrationController@options');
Router::post('/register/options', 'RegistrationController@options');

// Student Routes - Cổng thông tin Sinh viên (Chỉ dành riêng cho Sinh viên / Trưởng phòng nội trú)
Router::get('/student/dashboard', 'Student\\DashboardController@index', [StudentMiddleware::class]);
Router::get('/student/profile', 'Student\\ProfileController@index', [StudentMiddleware::class]);
Router::post('/student/profile/update', 'Student\\ProfileController@update', [StudentMiddleware::class]);
Router::post('/student/profile/change-password', 'Student\\ProfileController@changePassword', [StudentMiddleware::class]);
Router::get('/student/payments', 'Student\\PaymentController@index', [StudentMiddleware::class]);
Router::post('/student/payments/pay', 'Student\\PaymentController@payInvoice', [StudentMiddleware::class]);

// Student - Dịch vụ một cửa (Yêu cầu & Đơn từ trực tuyến)
Router::get('/student/requests', 'Student\\RequestController@index', [StudentMiddleware::class]);
Router::get('/student/requests/attachment', 'Student\\RequestController@attachment', [StudentMiddleware::class]);
Router::get('/student/requests/transfer', 'Student\\RequestController@transfer', [StudentMiddleware::class]);
Router::get('/student/requests/repair', 'Student\\RequestController@repair', [StudentMiddleware::class]);
Router::get('/student/requests/leave', 'Student\\RequestController@leave', [StudentMiddleware::class]);
Router::get('/student/requests/return', 'Student\\RequestController@returnRoom', [StudentMiddleware::class]);
Router::get('/student/requests/extension', 'Student\\RequestController@extension', [StudentMiddleware::class]);
Router::post('/student/requests', 'Student\\RequestController@create', [StudentMiddleware::class]);
Router::post('/student/requests/create', 'Student\\RequestController@create', [StudentMiddleware::class]);

// Staff Routes - Tách theo từng nghiệp vụ chuẩn (Chỉ dành cho Cán bộ KTX & Quản trị viên)
Router::get('/staff/dashboard', 'Staff\\DashboardController@index', [StaffMiddleware::class]);
Router::get('/staff/profile', 'Staff\\ProfileController@index', [StaffMiddleware::class]);
Router::post('/staff/profile/update', 'Staff\\ProfileController@update', [StaffMiddleware::class]);
Router::post('/staff/profile/change-password', 'Staff\\ProfileController@changePassword', [StaffMiddleware::class]);

// Staff - Quản lý Hạ tầng KTX (Khu, Cụm, Tòa, Phòng)
Router::get('/staff/zones', 'Staff\\ZoneController@zones', [StaffMiddleware::class]);
Router::get('/staff/zones/export', 'Staff\\ZoneController@exportZones', [StaffMiddleware::class]);
Router::post('/staff/zones/export', 'Staff\\ZoneController@exportZones', [StaffMiddleware::class]);
Router::get('/staff/zones/template', 'Staff\\ZoneController@downloadTemplate', [StaffMiddleware::class]);
Router::post('/staff/zones/import', 'Staff\\ZoneController@importZones', [StaffMiddleware::class]);
Router::post('/staff/zones/save-khu', 'Staff\\ZoneController@saveKhu', [StaffMiddleware::class]);
Router::post('/staff/zones/delete-khu', 'Staff\\ZoneController@deleteKhu', [StaffMiddleware::class]);
Router::post('/staff/zones/save-cum', 'Staff\\ZoneController@saveCum', [StaffMiddleware::class]);
Router::post('/staff/zones/delete-cum', 'Staff\\ZoneController@deleteCum', [StaffMiddleware::class]);
Router::post('/staff/zones/save-toa', 'Staff\\ZoneController@saveToa', [StaffMiddleware::class]);
Router::post('/staff/zones/delete-toa', 'Staff\\ZoneController@deleteToa', [StaffMiddleware::class]);
Router::post('/staff/zones/save-room', 'Staff\\ZoneController@saveRoom', [StaffMiddleware::class]);
Router::post('/staff/zones/delete-room', 'Staff\\ZoneController@deleteRoom', [StaffMiddleware::class]);
Router::post('/staff/zones/approve-proposal', 'Staff\\ZoneController@approveProposal', [StaffMiddleware::class]);
Router::post('/staff/zones/reject-proposal', 'Staff\\ZoneController@rejectProposal', [StaffMiddleware::class]);

// Staff - Quản lý Phòng & Giường nội trú (TRUONG_TOA)
Router::get('/staff/rooms', 'Staff\\RoomController@rooms', [StaffMiddleware::class]);
Router::post('/staff/rooms/checkin', 'Staff\\RoomController@confirmCheckIn', [StaffMiddleware::class]);
Router::post('/staff/rooms/assign-leader', 'Staff\\RoomController@assignRoomLeader', [StaffMiddleware::class]);
Router::post('/staff/rooms/toggle-bed-maintenance', 'Staff\\RoomController@toggleBedMaintenance', [StaffMiddleware::class]);
Router::post('/staff/rooms/toggle-room-maintenance', 'Staff\\RoomController@toggleRoomMaintenance', [StaffMiddleware::class]);
Router::get('/staff/rooms/export', 'Staff\\RoomController@exportRooms', [StaffMiddleware::class]);
Router::post('/staff/rooms/export', 'Staff\\RoomController@exportRooms', [StaffMiddleware::class]);
Router::get('/staff/rooms/template', 'Staff\\RoomController@downloadRoomTemplate', [StaffMiddleware::class]);
Router::post('/staff/rooms/import', 'Staff\\RoomController@importRooms', [StaffMiddleware::class]);
Router::post('/staff/rooms/save-room', 'Staff\\RoomController@saveRoomFromRooms', [StaffMiddleware::class]);
Router::post('/staff/rooms/delete-room', 'Staff\\RoomController@deleteRoomFromRooms', [StaffMiddleware::class]);
Router::post('/staff/rooms/toggle-status', 'Staff\\RoomController@toggleRoomStatus', [StaffMiddleware::class]);
Router::post('/staff/rooms/add-bed', 'Staff\\RoomController@addBed', [StaffMiddleware::class]);
Router::post('/staff/rooms/update-bed-status', 'Staff\\RoomController@updateBedStatus', [StaffMiddleware::class]);
Router::post('/staff/rooms/delete-bed', 'Staff\\RoomController@deleteBed', [StaffMiddleware::class]);
Router::post('/staff/rooms/update-toa', 'Staff\\RoomController@updateToaInfo', [StaffMiddleware::class]);
Router::post('/staff/rooms/confirm-checkin', 'Staff\\RoomController@confirmCheckIn', [StaffMiddleware::class]);
Router::post('/staff/rooms/toggle-ct01', 'Staff\\RoomController@toggleCT01', [StaffMiddleware::class]);
Router::post('/staff/rooms/remove-student', 'Staff\\RoomController@removeStudentFromBed', [StaffMiddleware::class]);

// Staff - Hồ sơ Đăng ký & Giữ chỗ 72h
Router::get('/staff/applications', 'Staff\\ApplicationController@applications', [StaffMiddleware::class]);
Router::get('/staff/applications/export', 'Staff\\ApplicationController@exportApplications', [StaffMiddleware::class]);
Router::get('/staff/applications/template', 'Staff\\ApplicationController@downloadTemplate', [StaffMiddleware::class]);
Router::post('/staff/applications/import', 'Staff\\ApplicationController@importApplications', [StaffMiddleware::class]);
Router::post('/staff/approve-application', 'Staff\\ApplicationController@approveApplication', [StaffMiddleware::class]);
Router::post('/staff/reject-application', 'Staff\\ApplicationController@rejectApplication', [StaffMiddleware::class]);
Router::post('/staff/applications/approve', 'Staff\\ApplicationController@approveApplication', [StaffMiddleware::class]);
Router::post('/staff/applications/reject', 'Staff\\ApplicationController@rejectApplication', [StaffMiddleware::class]);
Router::post('/staff/applications/scan-release', 'Staff\\ApplicationController@scanRelease72h', [StaffMiddleware::class]);
Router::post('/staff/applications/approve-miengiam', 'Staff\\ApplicationController@approveMienGiam', [StaffMiddleware::class]);
Router::post('/staff/applications/reject-miengiam', 'Staff\\ApplicationController@rejectMienGiam', [StaffMiddleware::class]);
Router::post('/staff/applications/create-period', 'Staff\\ApplicationController@createPeriod', [StaffMiddleware::class]);
Router::post('/staff/applications/close-period', 'Staff\\ApplicationController@closePeriod', [StaffMiddleware::class]);
Router::post('/staff/applications/extend-period', 'Staff\\ApplicationController@extendPeriod', [StaffMiddleware::class]);

// Staff - Quầy Thu Ngân Tiền Mặt & Quản lý Tài vụ (Kế toán & Quản lý KTX)
Router::get('/staff/cashier', 'Staff\\CashierController@index', [StaffMiddleware::class]);
Router::get('/staff/cashier/search', 'Staff\\CashierController@search', [StaffMiddleware::class]);
Router::post('/staff/cashier/pay', 'Staff\\CashierController@processPayment', [StaffMiddleware::class]);
Router::get('/staff/cashier/receipt', 'Staff\\CashierController@receipt', [StaffMiddleware::class]);
Router::get('/staff/cashier/export', 'Staff\\CashierController@exportDaily', [StaffMiddleware::class]);

// Staff - Tiện ích, Yêu cầu, Bảo trì, Kỷ luật
Router::get('/staff/utilities', 'Staff\\UtilityController@utilities', [StaffMiddleware::class]);
Router::get('/staff/utilities/export', 'Staff\\UtilityController@exportUtilities', [StaffMiddleware::class]);
Router::get('/staff/utilities/template', 'Staff\\UtilityController@downloadTemplate', [StaffMiddleware::class]);
Router::post('/staff/utilities/import', 'Staff\\UtilityController@importReadings', [StaffMiddleware::class]);
Router::post('/staff/utilities/save-reading', 'Staff\\UtilityController@saveReading', [StaffMiddleware::class]);
Router::post('/staff/utilities/publish-invoice', 'Staff\\UtilityController@publishInvoice', [StaffMiddleware::class]);
Router::post('/staff/utilities/publish-cluster', 'Staff\\UtilityController@publishClusterInvoices', [StaffMiddleware::class]);
Router::post('/staff/utilities/confirm-payment', 'Staff\\UtilityController@confirmPayment', [StaffMiddleware::class]);
Router::get('/staff/requests', 'Staff\\RequestController@requests', [StaffMiddleware::class]);
Router::get('/staff/requests/export', 'Staff\\RequestController@exportRequests', [StaffMiddleware::class]);
Router::post('/staff/requests/approve', 'Staff\\RequestController@approveRequest', [StaffMiddleware::class]);
Router::post('/staff/requests/reject', 'Staff\\RequestController@rejectRequest', [StaffMiddleware::class]);
Router::get('/staff/maintenance', 'Staff\\MaintenanceController@maintenance', [StaffMiddleware::class]);
Router::get('/staff/maintenance/export', 'Staff\\MaintenanceController@exportMaintenance', [StaffMiddleware::class]);
Router::post('/staff/maintenance/assign', 'Staff\\MaintenanceController@assign', [StaffMiddleware::class]);
Router::post('/staff/maintenance/create-staff-ticket', 'Staff\\MaintenanceController@createStaffTicket', [StaffMiddleware::class]);
Router::post('/staff/maintenance/update-progress', 'Staff\\MaintenanceController@updateProgress', [StaffMiddleware::class]);
Router::post('/staff/maintenance/complete', 'Staff\\MaintenanceController@complete', [StaffMiddleware::class]);
Router::post('/staff/maintenance/cancel', 'Staff\\MaintenanceController@cancel', [StaffMiddleware::class]);
Router::get('/staff/discipline', 'Staff\\DisciplineController@discipline', [StaffMiddleware::class]);
Router::post('/staff/discipline/record', 'Staff\\DisciplineController@record', [StaffMiddleware::class]);
Router::post('/staff/discipline/lock-submit', 'Staff\\DisciplineController@lockSubmit', [StaffMiddleware::class]);
Router::post('/staff/discipline/simulate-scan', 'Staff\\DisciplineController@simulateScan', [StaffMiddleware::class]);
Router::post('/staff/discipline/absence-action', 'Staff\\DisciplineController@absenceAction', [StaffMiddleware::class]);
Router::get('/staff/discipline/history', 'Staff\\DisciplineController@history', [StaffMiddleware::class]);
Router::get('/staff/discipline/export', 'Staff\\DisciplineController@export', [StaffMiddleware::class]);
Router::post('/staff/discipline/import', 'Staff\\DisciplineController@import', [StaffMiddleware::class]);

// Admin Routes - Tách theo từng nghiệp vụ chuẩn (Chỉ dành riêng cho Quản trị viên hệ thống)
Router::get('/admin/dashboard', 'Admin\\DashboardController@index', [AdminMiddleware::class]);

// Admin - Quản lý Cán bộ & Tài khoản
Router::get('/admin/users', 'Admin\\UserController@users', [AdminMiddleware::class]);
Router::get('/admin/users/export', 'Admin\\UserController@exportUsers', [AdminMiddleware::class]);
Router::post('/admin/users/export', 'Admin\\UserController@exportUsers', [AdminMiddleware::class]);
Router::get('/admin/users/template', 'Admin\\UserController@downloadUserTemplate', [AdminMiddleware::class]);
Router::post('/admin/users/import', 'Admin\\UserController@importUsers', [AdminMiddleware::class]);
Router::post('/admin/users/save', 'Admin\\UserController@saveUser', [AdminMiddleware::class]);
Router::post('/admin/users/reset-password', 'Admin\\UserController@resetPassword', [AdminMiddleware::class]);
Router::post('/admin/users/toggle-status', 'Admin\\UserController@toggleUserStatus', [AdminMiddleware::class]);
Router::post('/admin/users/delete', 'Admin\\UserController@deleteUser', [AdminMiddleware::class]);

// Admin - Quản lý Tài khoản & Hồ sơ Sinh viên
Router::get('/admin/students', 'Admin\\StudentController@students', [AdminMiddleware::class]);
Router::get('/admin/students/export', 'Admin\\StudentController@exportStudents', [AdminMiddleware::class]);
Router::post('/admin/students/export', 'Admin\\StudentController@exportStudents', [AdminMiddleware::class]);
Router::get('/admin/students/template', 'Admin\\StudentController@downloadStudentTemplate', [AdminMiddleware::class]);
Router::post('/admin/students/import', 'Admin\\StudentController@importStudents', [AdminMiddleware::class]);
Router::post('/admin/students/save', 'Admin\\StudentController@saveStudent', [AdminMiddleware::class]);
Router::post('/admin/students/reset-password', 'Admin\\StudentController@resetPassword', [AdminMiddleware::class]);
Router::post('/admin/students/toggle-status', 'Admin\\StudentController@toggleStudentStatus', [AdminMiddleware::class]);
Router::post('/admin/students/delete', 'Admin\\StudentController@deleteStudent', [AdminMiddleware::class]);
Router::post('/admin/students/test-mail', 'Admin\\StudentController@testMail', [AdminMiddleware::class]);

Router::get('/admin/periods', 'Admin\\DashboardController@periods', [AdminMiddleware::class]);
Router::get('/admin/room-types', 'Admin\\DashboardController@roomTypes', [AdminMiddleware::class]);

// Admin - Quản lý Trường Đại học liên kết
Router::get('/admin/universities', 'Admin\\UniversityController@universities', [AdminMiddleware::class]);
Router::get('/admin/universities/export', 'Admin\\UniversityController@exportUniversities', [AdminMiddleware::class]);
Router::post('/admin/universities/export', 'Admin\\UniversityController@exportUniversities', [AdminMiddleware::class]);
Router::get('/admin/universities/template', 'Admin\\UniversityController@downloadUniversityTemplate', [AdminMiddleware::class]);
Router::post('/admin/universities/import', 'Admin\\UniversityController@importUniversities', [AdminMiddleware::class]);
Router::post('/admin/universities/save', 'Admin\\UniversityController@saveUniversity', [AdminMiddleware::class]);
Router::post('/admin/universities/toggle-status', 'Admin\\UniversityController@toggleUniversityStatus', [AdminMiddleware::class]);
Router::post('/admin/universities/delete', 'Admin\\UniversityController@deleteUniversity', [AdminMiddleware::class]);

// RESTful API Routes (app/Controllers/Api)

// 1. Phân hệ Đăng ký nội trú (Registration APIs - Công khai cho thí sinh/sinh viên tra cứu)
Router::post('/api/registration/send-otp', 'Api\\RegistrationApiController@sendOtp');
Router::get('/api/registration/options', 'Api\\RegistrationApiController@options');
Router::post('/api/registration/options', 'Api\\RegistrationApiController@options');
Router::get('/api/registration/check-mssv', 'Api\\RegistrationApiController@checkMssv');
Router::post('/api/registration/check-mssv', 'Api\\RegistrationApiController@checkMssv');

// 2. Phân hệ Hồ sơ & Giữ chỗ 72h (Application APIs)
Router::post('/api/applications/scan-release', 'Api\\ApplicationApiController@scanRelease72h', [StaffMiddleware::class]);
Router::get('/api/applications/status', 'Api\\ApplicationApiController@status');

// 3. Phân hệ Kỷ luật, Rèn luyện & Quẹt thẻ Barrier (Discipline APIs)
Router::get('/api/discipline/history', 'Api\\DisciplineApiController@history', [StaffMiddleware::class]);
Router::post('/api/discipline/scan', 'Api\\DisciplineApiController@scan', [StaffMiddleware::class]);

// 4. Phân hệ Sơ đồ phòng & Hạ tầng KTX (Room APIs)
Router::get('/api/rooms', 'Api\\RoomApiController@index');
Router::get('/api/rooms/beds', 'Api\\RoomApiController@beds');
Router::get('/api/rooms/stats', 'Api\\RoomApiController@stats');

// 5. Phân hệ Tác vụ Tự động (Cron & Automation APIs - Bảo vệ chỉ cho phép Staff/Admin trigger)
Router::get('/api/cron/release-beds', 'Api\\CronApiController@triggerRelease', [StaffMiddleware::class]);
Router::post('/api/cron/release-beds', 'Api\\CronApiController@triggerRelease', [StaffMiddleware::class]);
Router::get('/api/cron/check-absence', 'Api\\CronApiController@triggerAbsence', [StaffMiddleware::class]);
Router::post('/api/cron/check-absence', 'Api\\CronApiController@triggerAbsence', [StaffMiddleware::class]);
Router::get('/cron/trigger_release', 'Api\\CronApiController@triggerRelease', [StaffMiddleware::class]);
Router::get('/cron/trigger_absence', 'Api\\CronApiController@triggerAbsence', [StaffMiddleware::class]);

// 6. Phân hệ Thanh toán & Cổng QR VCB Digibank (Payment APIs - Cho phép App Android gọi trực tiếp)
Router::get('/api/payment/check-status', 'Api\\PaymentApiController@checkStatus');
Router::post('/api/payment/vcb-confirm', 'Api\\PaymentApiController@confirmVcbPayment');
Router::post('/api/payment/simulate', 'Api\\PaymentApiController@simulateWebPayment');

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
