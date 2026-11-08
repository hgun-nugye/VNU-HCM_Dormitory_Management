<?php

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Service quản lý gửi Email & Thông báo hệ thống KTX ĐHQG-HCM
 */
class MailService
{
    /**
     * Tạo mật khẩu ngẫu nhiên an toàn (Mặc định 8 ký tự, gồm chữ hoa, chữ thường và chữ số)
     * Tránh các ký tự dễ nhầm lẫn như: 0, O, 1, l, I
     */
    public static function generatePassword(int $length = 8): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Bỏ I, O
        $lowercase = 'abcdefghijkmnpqrstuvwxyz'; // Bỏ l
        $digits = '23456789';                   // Bỏ 0, 1

        // Đảm bảo có ít nhất 1 chữ hoa, 1 chữ thường và 1 số
        $pwd = [];
        $pwd[] = $uppercase[random_int(0, strlen($uppercase) - 1)];
        $pwd[] = $lowercase[random_int(0, strlen($lowercase) - 1)];
        $pwd[] = $digits[random_int(0, strlen($digits) - 1)];

        $combined = $uppercase . $lowercase . $digits;
        for ($i = 3; $i < $length; $i++) {
            $pwd[] = $combined[random_int(0, strlen($combined) - 1)];
        }

        shuffle($pwd);
        return implode('', $pwd);
    }

    /**
     * Lấy cấu hình Mail hiện tại từ config hoặc .env
     */
    public static function getConfig(): array
    {
        $config = function_exists('config') ? config('mail') : [];
        $host = $config['host'] ?? (getenv('MAIL_HOST') ?: 'smtp.mailtrap.io');
        $port = (int)($config['port'] ?? (getenv('MAIL_PORT') ?: 2525));
        $username = $config['username'] ?? getenv('MAIL_USERNAME');
        $password = $config['password'] ?? getenv('MAIL_PASSWORD');
        if (is_string($password)) {
            $password = preg_replace('/\s+/', '', $password) ?? $password;
        }
        $encryption = $config['encryption'] ?? (getenv('MAIL_ENCRYPTION') ?: 'tls');
        $fromEmail = $config['from']['address'] ?? (getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@ktx.vnuhcm.edu.vn');
        $fromName = $config['from']['name'] ?? (getenv('MAIL_FROM_NAME') ?: 'KTX ĐHQG-HCM');

        $hasAuth = !empty($username) && $username !== 'null' && !empty($password) && $password !== 'null';

        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'from_address' => $fromEmail,
            'from_name' => $fromName,
            'has_auth' => $hasAuth
        ];
    }

    /**
     * Gửi email chung (hỗ trợ SMTP socket hoặc ghi Log hệ thống khi môi trường local/demo)
     *
     * @param string $toEmail   Email người nhận
     * @param string $toName    Tên người nhận
     * @param string $subject   Tiêu đề thư
     * @param string $htmlBody  Nội dung định dạng HTML
     * @param string $plainText Nội dung văn bản thô
     * @return array ['success' => bool, 'mode' => 'smtp'|'log'|'error', 'message' => string, 'trace' => array]
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainText = '', bool $writeLog = true): array
    {
        $toEmail = trim($toEmail);
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'mode' => 'error',
                'message' => 'Địa chỉ email người nhận không hợp lệ: ' . htmlspecialchars($toEmail),
                'trace' => []
            ];
        }

        $cfg = self::getConfig();
        $trace = [];

        // Nếu có thông tin xác thực SMTP (User & Pass không rỗng) thì tiến hành kết nối gửi qua SMTP
        if ($cfg['has_auth']) {
            try {
                $smtpSent = self::sendViaSmtp(
                    $cfg['host'],
                    $cfg['port'],
                    $cfg['encryption'],
                    $cfg['username'],
                    $cfg['password'],
                    $cfg['from_address'],
                    $cfg['from_name'],
                    $toEmail,
                    $toName,
                    $subject,
                    $htmlBody,
                    $trace
                );

                if ($smtpSent) {
                    if ($writeLog) {
                        self::logEmail($toEmail, $toName, $subject, $htmlBody, 'SMTP_SUCCESS');
                    }
                    return [
                        'success' => true,
                        'mode' => 'smtp',
                        'message' => "Đã gửi email thành công đến {$toEmail} qua máy chủ SMTP ({$cfg['host']}:{$cfg['port']}).",
                        'trace' => $trace
                    ];
                }
            } catch (Throwable $e) {
                // Ghi nhận lỗi SMTP nhưng vẫn tiếp tục ghi log dự phòng để hệ thống không bị crash
                if ($writeLog) {
                    self::logEmail($toEmail, $toName, $subject, $htmlBody, 'SMTP_ERROR: ' . $e->getMessage());
                }
                return [
                    'success' => $writeLog,
                    'mode' => $writeLog ? 'log_fallback' : 'error',
                    'message' => $writeLog
                        ? "Lỗi kết nối SMTP ngoài ({$e->getMessage()}). Nội dung thư đã được lưu an toàn vào hệ thống log KTX."
                        : "Lỗi kết nối SMTP ngoài ({$e->getMessage()}).",
                    'trace' => $trace
                ];
            }
        }

        if (!$writeLog) {
            return [
                'success' => false,
                'mode' => 'error',
                'message' => 'Tài khoản SMTP chưa được cấu hình; email chưa được gửi.',
                'trace' => ["Mode: LOCAL_LOG disabled for this message"]
            ];
        }

        // Môi trường thử nghiệm / Localhost: ghi nhận email vào log hệ thống KTX (storage/logs/mail.log)
        self::logEmail($toEmail, $toName, $subject, $htmlBody, 'LOG_SUCCESS (Dev/Local)');
        return [
            'success' => true,
            'mode' => 'log',
            'message' => "Email đã được tạo và lưu thành công vào file log (storage/logs/mail.log) do máy chủ chưa cấu hình tài khoản SMTP.",
            'trace' => ["Mode: LOCAL_LOG (MAIL_USERNAME/MAIL_PASSWORD is empty or null)"]
        ];
    }

    /**
     * Gửi email cấp lại mật khẩu 8 ký tự cho sinh viên
     */
    public static function sendStudentPasswordResetEmail(string $studentEmail, string $studentName, string $mssv, string $newPassword, ?string $university = null): array
    {
        $subject = "[KTX ĐHQG-HCM] Cấp lại mật khẩu tài khoản sinh viên - MSSV: {$mssv}";

        $loginUrl = function_exists('url') ? url('login') : 'http://localhost/Dormitory_Management/login';
        $contactUrl = function_exists('url') ? url('') : 'http://localhost/Dormitory_Management';
        $sentTime = date('H:i:s d/m/Y');

        $uniText = !empty($university) ? " - {$university}" : '';


        $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin:0; padding:20px; font-family:Arial,sans-serif; color:#333; background:#ffffff;">

    <div style="max-width:550px; margin:0 auto;">

        <h2 style="font-size:20px; color:#1e40af; margin-bottom:20px;">
            KÝ TÚC XÁ ĐHQG-HCM
        </h2>

        <hr style="border:0; border-top:1px solid #ddd;">

        <h3 style="font-size:17px; margin-top:25px;">
            Thông báo cấp lại mật khẩu
        </h3>

        <p style="font-size:14px; line-height:1.7;">
            Xin chào <strong>{$studentName}</strong>,
        </p>

        <p style="font-size:14px; line-height:1.7;">
            Yêu cầu cấp lại mật khẩu tài khoản KTX của bạn đã được xử lý thành công.
        </p>

        <p style="font-size:14px;">
            <strong>Mã số sinh viên:</strong> {$mssv}
        </p>

        <p style="font-size:14px; margin-bottom:8px;">
            <strong>Mật khẩu đăng nhập mới:</strong>
        </p>

        <div style="padding:15px; background:#f1f5f9;
            text-align:center; font-size:26px; font-weight:bold;
            color:#2563eb; letter-spacing:5px;">
            {$newPassword}
        </div>

        <p style="font-size:14px; line-height:1.8; margin-top:20px;">
            Bạn vui lòng sử dụng mật khẩu mới để đăng nhập và chủ động
            đổi mật khẩu sau khi đăng nhập thành công.
        </p>

        <p style="font-size:13px; color:red; line-height:1.7;">
            <strong>Lưu ý:</strong> Không chia sẻ mật khẩu này cho người khác.
            Nếu bạn không yêu cầu cấp lại mật khẩu, vui lòng liên hệ Ban Quản lý KTX.
        </p>

        <p style="font-size:14px; margin-top:25px;">
            Trân trọng,<br>
            <strong>Ban Quản lý Ký túc xá ĐHQG-HCM</strong>
        </p>

        <hr style="border:0; border-top:1px solid #ddd; margin-top:25px;">

        <p style="font-size:12px; color:#888; line-height:1.7;">
            Hotline: 1900 055559<br>
            Email: info@ktx.vnuhcm.edu.vn<br>
            Đây là email tự động, vui lòng không trả lời email này.
        </p>

    </div>

</body>
</html>
HTML;


        return self::send($studentEmail, $studentName, $subject, $html);
    }

    /**
     * Tìm danh sách các cán bộ quản lý liên quan phụ trách địa bàn (Tòa, Cụm, Khu, Toàn KTX)
     */
    public static function getResponsibleManagers(
        string $maToa = '',
        string $maCum = '',
        string $maKhu = '',
        ?string $destToa = null,
        ?string $destCum = null,
        ?string $destKhu = null,
        bool $includeTechnicians = false
    ): array {
        $toas = array_values(array_filter(array_unique([$maToa, $destToa ?? ''])));
        $cums = array_values(array_filter(array_unique([$maCum, $destCum ?? ''])));
        $khus = array_values(array_filter(array_unique([$maKhu, $destKhu ?? ''])));

        $conditions = [
            "tk.MaVaiTro IN ('ADMIN', 'QuanTriVien', 'NV_QUAN_LY_KTX')"
        ];
        $params = [];

        if (!empty($toas)) {
            $toaPlaceholders = [];
            foreach ($toas as $i => $t) {
                $key = 'toa_' . $i;
                $toaPlaceholders[] = ':' . $key;
                $params[$key] = $t;
            }
            $conditions[] = "(tk.MaVaiTro = 'TRUONG_TOA' AND tk.MaCumQuanLy IN (" . implode(',', $toaPlaceholders) . "))";
        }

        if (!empty($cums)) {
            $cumPlaceholders = [];
            foreach ($cums as $i => $c) {
                $key = 'cum_' . $i;
                $cumPlaceholders[] = ':' . $key;
                $params[$key] = $c;
            }
            $conditions[] = "(tk.MaVaiTro = 'TRUONG_CUM' AND tk.MaCumQuanLy IN (" . implode(',', $cumPlaceholders) . "))";
            if ($includeTechnicians) {
                $conditions[] = "(tk.MaVaiTro = 'NV_THUONG' AND tk.MaCumQuanLy IN (" . implode(',', $cumPlaceholders) . "))";
            }
        }

        if (!empty($khus)) {
            $khuPlaceholders = [];
            foreach ($khus as $i => $k) {
                $key = 'khu_' . $i;
                $khuPlaceholders[] = ':' . $key;
                $params[$key] = $k;
            }
            $conditions[] = "(tk.MaVaiTro = 'TRUONG_KHU' AND tk.MaCumQuanLy IN (" . implode(',', $khuPlaceholders) . "))";
            if ($includeTechnicians) {
                $conditions[] = "(tk.MaVaiTro = 'NV_THUONG' AND tk.MaCumQuanLy IN (" . implode(',', $khuPlaceholders) . "))";
            }
        }

        $whereClause = "(" . implode(" OR ", $conditions) . ")";

        $sql = "
            SELECT DISTINCT nv.Email, nv.HoVaTen, nv.ChucVu, tk.MaVaiTro, tk.MaCumQuanLy
            FROM TaiKhoan tk
            JOIN NhanVien nv ON tk.MaTaiKhoan = nv.MaTaiKhoan
            WHERE tk.DangHoatDong = 1
              AND nv.Email IS NOT NULL 
              AND nv.Email != ''
              AND {$whereClause}
        ";

        try {
            $rows = Database::fetchAll($sql, $params);
            $unique = [];
            foreach ($rows as $r) {
                $email = strtolower(trim((string)$r['Email']));
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $unique[$email] = $r;
                }
            }
            return array_values($unique);
        } catch (Throwable $e) {
            error_log('Lỗi truy vấn danh sách cán bộ quản lý: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gửi thông báo đến Cán bộ Quản lý liên quan khi có sinh viên đăng ký hồ sơ nội trú mới
     */
    public static function notifyManagersNewApplication(string $maHoSo): array
    {
        $maHoSo = trim($maHoSo);
        if ($maHoSo === '') {
            return ['success' => false, 'message' => 'Mã hồ sơ trống.'];
        }

        try {
            $sql = "
                SELECT hs.*, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, sv.Email AS SinhVienEmail,
                       sv.GioiTinh, tdh.TenTruong,
                       g.KyHieuGiuong, p.SoPhong, p.Tang,
                       tn.MaToaNha, tn.TenToaNha,
                       c.MaCum, c.TenCum,
                       k.MaKhu, k.TenKhu,
                       lp.TenLoaiPhong,
                       (lp.DonGiaLuuTruChuan + lp.DonGiaDichVuTangThem) AS DonGiaThang
                FROM HoSoDangKy hs
                JOIN SinhVien sv ON hs.MaSinhVien = sv.MaSinhVien
                LEFT JOIN TruongDaiHoc tdh ON sv.MaTruong = tdh.MaTruong
                JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                JOIN Phong p ON g.MaPhong = p.MaPhong
                JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                JOIN CumNha c ON tn.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                WHERE hs.MaHoSo = :maHoSo
                LIMIT 1
            ";
            $hs = Database::fetch($sql, ['maHoSo' => $maHoSo]);
            if (!$hs) {
                return ['success' => false, 'message' => "Không tìm thấy hồ sơ {$maHoSo}."];
            }

            $maToa = (string)($hs['MaToaNha'] ?? '');
            $maCum = (string)($hs['MaCum'] ?? '');
            $maKhu = (string)($hs['MaKhu'] ?? '');

            $managers = self::getResponsibleManagers($maToa, $maCum, $maKhu);
            if (empty($managers)) {
                return ['success' => false, 'message' => 'Không tìm thấy cán bộ quản lý phù hợp cho địa bàn này.'];
            }

            $actionUrl = function_exists('url')
                ? url('staff/applications?tab=cho_duyet&search=' . urlencode($maHoSo))
                : "http://localhost/Dormitory_Management/staff/applications?tab=cho_duyet&search=" . urlencode($maHoSo);

            $studentName = htmlspecialchars($hs['HoVaTen'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $mssv = htmlspecialchars($hs['MSSVTaiTruong'] ?: $hs['MaSinhVien'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $phone = htmlspecialchars($hs['SoDienThoai'] ?? 'Chưa cập nhật', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $email = htmlspecialchars($hs['SinhVienEmail'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $truong = htmlspecialchars($hs['TenTruong'] ?? 'ĐHQG-HCM', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $gender = htmlspecialchars($hs['GioiTinh'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $roomInfo = htmlspecialchars("Giường {$hs['KyHieuGiuong']} - Phòng {$hs['SoPhong']} (Tầng {$hs['Tang']}) - {$hs['TenToaNha']} ({$hs['MaToaNha']}) - Cụm {$hs['TenCum']} - {$hs['TenKhu']}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $roomType = htmlspecialchars($hs['TenLoaiPhong'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $regType = htmlspecialchars($hs['LoaiLuongDangKy'] ?? 'Đăng ký thường', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $submitTime = date('H:i:s d/m/Y', strtotime($hs['ThoiGianNop'] ?? 'now'));

            $subject = "[KTX ĐHQG-HCM] [HỒ SƠ MỚI CẦN DUYỆT] Sinh viên {$hs['HoVaTen']} ({$mssv}) - Mã HS: {$maHoSo}";

            $sentCount = 0;
            $results = [];

            foreach ($managers as $manager) {
                $managerName = htmlspecialchars($manager['HoVaTen'] ?? 'Cán bộ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $chucVu = htmlspecialchars($manager['ChucVu'] ?? ($manager['MaVaiTro'] ?? 'Cán bộ Quản lý'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin: 0; padding: 20px; font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1e40af, #2563eb); color: #ffffff; padding: 24px 20px; text-align: center;">
            <div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9;">Ký túc xá Đại học Quốc gia TP.HCM</div>
            <h1 style="margin: 6px 0 0; font-size: 20px; font-weight: 700;">HỒ SƠ ĐĂNG KÝ NỘI TRÚ MỚI CẦN GIẢI QUYẾT</h1>
        </div>

        <div style="padding: 24px;">
            <p style="margin-top: 0; font-size: 15px;">Kính gửi Quý Thầy/Cô <strong>{$managerName}</strong> (<em>{$chucVu}</em>),</p>
            <p style="font-size: 14px; color: #334155;">
                Hệ thống Quản lý KTX vừa tiếp nhận <strong>01 hồ sơ đăng ký chỗ ở nội trú mới</strong> từ sinh viên thuộc phạm vi địa bàn do Quý Thầy/Cô quản lý. Kính đề nghị Quý Thầy/Cô kiểm tra hồ sơ và thực hiện xét duyệt trong vòng <strong>24 giờ</strong> theo quy định KTX.
            </p>

            <!-- Card chi tiết -->
            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin: 20px 0;">
                <div style="font-size: 14px; font-weight: 700; color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 6px; margin-bottom: 12px;">
                    THÔNG TIN CHI TIẾT HỒ SƠ ĐĂNG KÝ
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                    <tr>
                        <td style="padding: 6px 0; color: #64748b; width: 38%;">Mã hồ sơ:</td>
                        <td style="padding: 6px 0; font-weight: 700; color: #0f172a;">{$maHoSo}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Thời gian nộp:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$submitTime}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Họ và tên sinh viên:</td>
                        <td style="padding: 6px 0; font-weight: 700; color: #1e40af; font-size: 14px;">{$studentName}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Mã số sinh viên:</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #0f172a;">{$mssv} ({$gender})</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Trường ĐH:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$truong}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Số điện thoại:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$phone}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Email sinh viên:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$email}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Vị trí phòng ở:</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #047857;">{$roomInfo}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Loại phòng:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$roomType}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Luồng đăng ký:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$regType}</td>
                    </tr>
                </table>
            </div>

            <!-- Box thời hạn -->
            <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px; border-radius: 4px; margin: 18px 0;">
                <p style="margin: 0; font-size: 13px; color: #b45309; font-weight: 700;">Quy định xét duyệt 24 giờ</p>
                <p style="margin: 4px 0 0; font-size: 13px; color: #78350f;">
                    Hồ sơ cần được xử lý kịp thời để kích hoạt thời hạn giữ chỗ thanh toán 72 giờ cho sinh viên hoặc giải phóng giường về quỹ phòng trống nếu không hợp lệ.
                </p>
            </div>

            <!-- Nút hành động trực tiếp -->
            <div style="text-align: center; margin: 26px 0 16px;">
                <a href="{$actionUrl}" style="background: #1e40af; color: #ffffff; text-decoration: none; padding: 13px 30px; font-size: 14px; font-weight: 700; border-radius: 8px; display: inline-block; box-shadow: 0 4px 8px rgba(30,64,175,0.25);">
                    XÉT DUYỆT HỒ SƠ TẠI HỆ THỐNG
                </a>
                <p style="margin: 8px 0 0; font-size: 12px; color: #64748b;">
                    Liên kết trực tiếp: <a href="{$actionUrl}" style="color: #2563eb; word-break: break-all;">{$actionUrl}</a>
                </p>
            </div>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
            <p style="font-size: 12px; color: #94a3b8; margin: 0; text-align: center;">
                Email này được gửi tự động từ Cổng thông tin Quản lý Ký túc xá ĐHQG-HCM đến Cán bộ Quản lý phụ trách địa bàn.
            </p>
        </div>
    </div>
</body>
</html>
HTML;

                $plain = "Kính gửi {$manager['HoVaTen']} ({$manager['ChucVu']}),\n\n"
                    . "Hệ thống Quản lý KTX ĐHQG-HCM vừa tiếp nhận hồ sơ đăng ký nội trú mới cần giải quyết:\n"
                    . "- Mã hồ sơ: {$maHoSo}\n"
                    . "- Sinh viên: {$hs['HoVaTen']} (MSSV: {$mssv})\n"
                    . "- Vị trí: Giường {$hs['KyHieuGiuong']} - Phòng {$hs['SoPhong']} - {$hs['TenToaNha']} - {$hs['TenCum']} - {$hs['TenKhu']}\n"
                    . "- Thời gian nộp: {$submitTime}\n"
                    . "- Hạn xử lý: Trong vòng 24 giờ\n\n"
                    . "Vui lòng đăng nhập và xét duyệt tại: {$actionUrl}\n\n"
                    . "Trân trọng,\nBan Quản lý Ký túc xá ĐHQG-HCM";

                $res = self::send($manager['Email'], $manager['HoVaTen'], $subject, $html, $plain);
                $results[] = $res;
                if ($res['success']) {
                    $sentCount++;
                }
            }

            return [
                'success' => true,
                'total_recipients' => count($managers),
                'sent' => $sentCount,
                'results' => $results
            ];
        } catch (Throwable $e) {
            error_log('Lỗi notifyManagersNewApplication: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Gửi thông báo đến Cán bộ Quản lý liên quan khi sinh viên gửi đơn từ một cửa
     */
    public static function notifyManagersNewRequest(string $maDon): array
    {
        $maDon = trim($maDon);
        if ($maDon === '') {
            return ['success' => false, 'message' => 'Mã đơn trống.'];
        }

        try {
            $sql = "
                SELECT d.*, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, sv.Email AS SinhVienEmail,
                       tdh.TenTruong,
                       gCur.KyHieuGiuong AS GiuongHienTai, pCur.SoPhong AS PhongHienTai,
                       tCur.MaToaNha AS MaToaHienTai, tCur.TenToaNha AS TenToaHienTai,
                       cCur.MaCum AS MaCumHienTai, cCur.TenCum AS TenCumHienTai,
                       kCur.MaKhu AS MaKhuHienTai, kCur.TenKhu AS TenKhuHienTai,
                       gNew.KyHieuGiuong AS GiuongMoi, pNew.SoPhong AS PhongMoi,
                       tNew.MaToaNha AS MaToaMoi, tNew.TenToaNha AS TenToaMoi,
                       cNew.MaCum AS MaCumMoi, cNew.TenCum AS TenCumMoi,
                       kNew.MaKhu AS MaKhuMoi, kNew.TenKhu AS TenKhuMoi
                FROM DonTuMotCua d
                JOIN SinhVien sv ON d.MaSinhVien = sv.MaSinhVien
                LEFT JOIN TruongDaiHoc tdh ON sv.MaTruong = tdh.MaTruong
                LEFT JOIN HopDongNoiTru hd ON d.MaHopDong = hd.MaHopDong
                LEFT JOIN Giuong gCur ON hd.MaGiuong = gCur.MaGiuong
                LEFT JOIN Phong pCur ON gCur.MaPhong = pCur.MaPhong
                LEFT JOIN ToaNha tCur ON pCur.MaToaNha = tCur.MaToaNha
                LEFT JOIN CumNha cCur ON tCur.MaCum = cCur.MaCum
                LEFT JOIN KhuKTX kCur ON cCur.MaKhu = kCur.MaKhu
                LEFT JOIN Giuong gNew ON d.MaGiuongChuyenDen = gNew.MaGiuong
                LEFT JOIN Phong pNew ON gNew.MaPhong = pNew.MaPhong
                LEFT JOIN ToaNha tNew ON pNew.MaToaNha = tNew.MaToaNha
                LEFT JOIN CumNha cNew ON tNew.MaCum = cNew.MaCum
                LEFT JOIN KhuKTX kNew ON cNew.MaKhu = kNew.MaKhu
                WHERE d.MaDon = :maDon
                LIMIT 1
            ";
            $req = Database::fetch($sql, ['maDon' => $maDon]);
            if (!$req) {
                return ['success' => false, 'message' => "Không tìm thấy đơn {$maDon}."];
            }

            $managers = self::getResponsibleManagers(
                (string)($req['MaToaHienTai'] ?? ''),
                (string)($req['MaCumHienTai'] ?? ''),
                (string)($req['MaKhuHienTai'] ?? ''),
                (string)($req['MaToaMoi'] ?? ''),
                (string)($req['MaCumMoi'] ?? ''),
                (string)($req['MaKhuMoi'] ?? '')
            );

            if (empty($managers)) {
                return ['success' => false, 'message' => 'Không tìm thấy cán bộ quản lý phụ trách địa bàn của đơn này.'];
            }

            $actionUrl = function_exists('url')
                ? url('staff/requests?search=' . urlencode($maDon))
                : "http://localhost/Dormitory_Management/staff/requests?search=" . urlencode($maDon);

            $studentName = htmlspecialchars($req['HoVaTen'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $mssv = htmlspecialchars($req['MSSVTaiTruong'] ?: $req['MaSinhVien'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $loaiDon = htmlspecialchars($req['LoaiDon'] ?? 'Đơn một cửa', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $phone = htmlspecialchars($req['SoDienThoai'] ?? 'Chưa cập nhật', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $email = htmlspecialchars($req['SinhVienEmail'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $currentRoom = htmlspecialchars("Phòng {$req['PhongHienTai']} (Giường {$req['GiuongHienTai']}) - {$req['TenToaHienTai']} - {$req['TenCumHienTai']} - {$req['TenKhuHienTai']}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $reason = htmlspecialchars($req['LyDo'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $hasProof = !empty($req['TepMinhChung']);
            $timeNow = date('H:i:s d/m/Y');

            $extraRows = '';
            if (!empty($req['PhongMoi']) || !empty($req['GiuongMoi'])) {
                $targetRoom = htmlspecialchars("Phòng {$req['PhongMoi']} (Giường {$req['GiuongMoi']}) - {$req['TenToaMoi']}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $extraRows .= "<tr><td style='padding: 6px 0; color: #64748b;'>Nguyện vọng chuyển đến:</td><td style='padding: 6px 0; font-weight: 600; color: #0284c7;'>{$targetRoom}</td></tr>";
            }
            if (!empty($req['TuNgay']) && !empty($req['DenNgay'])) {
                $tuNgayFmt = date('d/m/Y', strtotime($req['TuNgay']));
                $denNgayFmt = date('d/m/Y', strtotime($req['DenNgay']));
                $extraRows .= "<tr><td style='padding: 6px 0; color: #64748b;'>Thời gian áp dụng:</td><td style='padding: 6px 0; color: #0f172a;'>Từ <b>{$tuNgayFmt}</b> đến <b>{$denNgayFmt}</b></td></tr>";
            } elseif (!empty($req['TuNgay'])) {
                $tuNgayFmt = date('d/m/Y', strtotime($req['TuNgay']));
                $extraRows .= "<tr><td style='padding: 6px 0; color: #64748b;'>Ngày thực hiện:</td><td style='padding: 6px 0; color: #0f172a;'><b>{$tuNgayFmt}</b></td></tr>";
            } elseif (!empty($req['DenNgay'])) {
                $denNgayFmt = date('d/m/Y', strtotime($req['DenNgay']));
                $extraRows .= "<tr><td style='padding: 6px 0; color: #64748b;'>Ngày gia hạn đến:</td><td style='padding: 6px 0; color: #0f172a;'><b>{$denNgayFmt}</b></td></tr>";
            }
            if ($hasProof) {
                $extraRows .= "<tr><td style='padding: 6px 0; color: #64748b;'>Tệp minh chứng:</td><td style='padding: 6px 0; color: #166534; font-weight: 600;'>Đã đính kèm tệp minh chứng</td></tr>";
            }

            $subject = "[KTX ĐHQG-HCM] [ĐƠN TỪ MỚI CẦN GIẢI QUYẾT] {$req['LoaiDon']} - Sinh viên {$req['HoVaTen']} (Mã đơn: {$maDon})";

            $sentCount = 0;
            $results = [];

            foreach ($managers as $manager) {
                $managerName = htmlspecialchars($manager['HoVaTen'] ?? 'Cán bộ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $chucVu = htmlspecialchars($manager['ChucVu'] ?? ($manager['MaVaiTro'] ?? 'Cán bộ Quản lý'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin: 0; padding: 20px; font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #0284c7, #0369a1); color: #ffffff; padding: 24px 20px; text-align: center;">
            <div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9;">Ký túc xá Đại học Quốc gia TP.HCM - Dịch vụ Một Cửa</div>
            <h1 style="margin: 6px 0 0; font-size: 20px; font-weight: 700;">TIẾP NHẬN ĐƠN TỪ SINH VIÊN CẦN GIẢI QUYẾT</h1>
        </div>

        <div style="padding: 24px;">
            <p style="margin-top: 0; font-size: 15px;">Kính gửi Quý Thầy/Cô <strong>{$managerName}</strong> (<em>{$chucVu}</em>),</p>
            <p style="font-size: 14px; color: #334155;">
                Hệ thống Dịch vụ Một Cửa vừa tiếp nhận <strong>01 đơn yêu cầu mới ({$loaiDon})</strong> từ sinh viên thuộc phạm vi phụ trách của Quý Thầy/Cô. Kính đề nghị Quý Thầy/Cô xem xét nội dung và thực hiện phê duyệt / từ chối theo quy định.
            </p>

            <!-- Card chi tiết -->
            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin: 20px 0;">
                <div style="font-size: 14px; font-weight: 700; color: #0369a1; border-bottom: 2px solid #0284c7; padding-bottom: 6px; margin-bottom: 12px;">
                    THÔNG TIN CHI TIẾT ĐƠN TỪ
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                    <tr>
                        <td style="padding: 6px 0; color: #64748b; width: 38%;">Mã đơn từ:</td>
                        <td style="padding: 6px 0; font-weight: 700; color: #0f172a;">{$maDon}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Loại đơn:</td>
                        <td style="padding: 6px 0; font-weight: 700; color: #0284c7; font-size: 14px;">{$loaiDon}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Thời gian gửi:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$timeNow}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Họ tên sinh viên:</td>
                        <td style="padding: 6px 0; font-weight: 700; color: #0f172a;">{$studentName}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Mã số sinh viên:</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #0f172a;">{$mssv}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Số điện thoại:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$phone}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Chỗ ở hiện tại:</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #334155;">{$currentRoom}</td>
                    </tr>
                    {$extraRows}
                    <tr>
                        <td style="padding: 6px 0; color: #64748b; vertical-align: top;">Lý do / Nội dung:</td>
                        <td style="padding: 6px 0; color: #0f172a; font-style: italic;">"{$reason}"</td>
                    </tr>
                </table>
            </div>

            <!-- Nút hành động trực tiếp -->
            <div style="text-align: center; margin: 26px 0 16px;">
                <a href="{$actionUrl}" style="background: #0284c7; color: #ffffff; text-decoration: none; padding: 13px 30px; font-size: 14px; font-weight: 700; border-radius: 8px; display: inline-block; box-shadow: 0 4px 8px rgba(2,132,199,0.25);">
                    XỬ LÝ ĐƠN TỪ TẠI HỆ THỐNG
                </a>
                <p style="margin: 8px 0 0; font-size: 12px; color: #64748b;">
                    Liên kết trực tiếp: <a href="{$actionUrl}" style="color: #0284c7; word-break: break-all;">{$actionUrl}</a>
                </p>
            </div>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
            <p style="font-size: 12px; color: #94a3b8; margin: 0; text-align: center;">
                Email này được gửi tự động từ Hệ thống Dịch vụ Một Cửa - Ký túc xá ĐHQG-HCM đến Cán bộ Quản lý liên quan.
            </p>
        </div>
    </div>
</body>
</html>
HTML;

                $plain = "Kính gửi {$manager['HoVaTen']} ({$manager['ChucVu']}),\n\n"
                    . "Sinh viên {$req['HoVaTen']} (MSSV: {$mssv}) vừa gửi đơn yêu cầu mới:\n"
                    . "- Mã đơn: {$maDon}\n"
                    . "- Loại đơn: {$req['LoaiDon']}\n"
                    . "- Vị trí: {$req['PhongHienTai']} - {$req['TenToaHienTai']}\n"
                    . "- Lý do: {$req['LyDo']}\n\n"
                    . "Vui lòng xem và giải quyết tại: {$actionUrl}\n\n"
                    . "Trân trọng,\nBan Quản lý Ký túc xá ĐHQG-HCM";

                $res = self::send($manager['Email'], $manager['HoVaTen'], $subject, $html, $plain);
                $results[] = $res;
                if ($res['success']) {
                    $sentCount++;
                }
            }

            return [
                'success' => true,
                'total_recipients' => count($managers),
                'sent' => $sentCount,
                'results' => $results
            ];
        } catch (Throwable $e) {
            error_log('Lỗi notifyManagersNewRequest: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Gửi thông báo đến Cán bộ Quản lý và Kỹ thuật khi sinh viên báo hỏng / xin sửa chữa thiết bị
     */
    public static function notifyManagersNewRepairTicket(string $maPhieu): array
    {
        $maPhieu = trim($maPhieu);
        if ($maPhieu === '') {
            return ['success' => false, 'message' => 'Mã phiếu trống.'];
        }

        try {
            $sql = "
                SELECT pb.*, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, sv.Email AS SinhVienEmail,
                       p.SoPhong, tn.MaToaNha, tn.TenToaNha, c.MaCum, c.TenCum, k.MaKhu, k.TenKhu,
                       tb.TenThietBi, tb.MaSoThietBi
                FROM PhieuBaoHong pb
                LEFT JOIN SinhVien sv ON pb.MaSinhVienBao = sv.MaSinhVien
                LEFT JOIN Phong p ON pb.MaPhong = p.MaPhong
                LEFT JOIN ToaNha tn ON pb.MaToaNha = tn.MaToaNha
                LEFT JOIN CumNha c ON tn.MaCum = c.MaCum
                LEFT JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                LEFT JOIN ThietBiPhong tb ON pb.MaThietBi = tb.MaThietBi
                WHERE pb.MaPhieu = :maPhieu
                LIMIT 1
            ";
            $pb = Database::fetch($sql, ['maPhieu' => $maPhieu]);
            if (!$pb) {
                return ['success' => false, 'message' => "Không tìm thấy phiếu báo hỏng {$maPhieu}."];
            }

            $managers = self::getResponsibleManagers(
                (string)($pb['MaToaNha'] ?? ''),
                (string)($pb['MaCum'] ?? ''),
                (string)($pb['MaKhu'] ?? ''),
                null, null, null,
                true // bao gồm kỹ thuật
            );

            if (empty($managers)) {
                return ['success' => false, 'message' => 'Không tìm thấy cán bộ/kỹ thuật phụ trách địa bàn này.'];
            }

            $actionUrl = function_exists('url')
                ? url('staff/requests?search=' . urlencode($maPhieu))
                : "http://localhost/Dormitory_Management/staff/requests?search=" . urlencode($maPhieu);

            $studentName = htmlspecialchars($pb['HoVaTen'] ?? 'Sinh viên', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $mssv = htmlspecialchars($pb['MSSVTaiTruong'] ?: ($pb['MaSinhVienBao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $phone = htmlspecialchars($pb['SoDienThoai'] ?? 'Chưa cập nhật', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $location = htmlspecialchars("Phòng {$pb['SoPhong']} - {$pb['TenToaNha']} ({$pb['MaToaNha']}) - Cụm {$pb['TenCum']} - {$pb['TenKhu']}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $equipment = htmlspecialchars(($pb['TenThietBi'] ?? 'Thiết bị phòng') . (!empty($pb['MaSoThietBi']) ? " ({$pb['MaSoThietBi']})" : ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $desc = htmlspecialchars($pb['MoTaHuHong'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $timeNow = date('H:i:s d/m/Y', strtotime($pb['NgayBao'] ?? 'now'));

            $subject = "[KTX ĐHQG-HCM] [BÁO HỎNG] Sự cố thiết bị tại Phòng {$pb['SoPhong']} - {$pb['TenToaNha']} - Mã: {$maPhieu}";

            $sentCount = 0;
            $results = [];

            foreach ($managers as $manager) {
                $managerName = htmlspecialchars($manager['HoVaTen'] ?? 'Cán bộ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $chucVu = htmlspecialchars($manager['ChucVu'] ?? ($manager['MaVaiTro'] ?? 'Cán bộ Quản lý'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin: 0; padding: 20px; font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        
        <div style="background: linear-gradient(135deg, #d97706, #b45309); color: #ffffff; padding: 24px 20px; text-align: center;">
            <div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9;">Ký túc xá Đại học Quốc gia TP.HCM - Quản trị Cơ sở vật chất</div>
            <h1 style="margin: 6px 0 0; font-size: 20px; font-weight: 700;">YÊU CẦU SỬA CHỮA THIẾT BỊ CẦN XỬ LÝ</h1>
        </div>

        <div style="padding: 24px;">
            <p style="margin-top: 0; font-size: 15px;">Kính gửi Quý Thầy/Cô / Cán bộ Kỹ thuật <strong>{$managerName}</strong> (<em>{$chucVu}</em>),</p>
            <p style="font-size: 14px; color: #334155;">
                Hệ thống vừa tiếp nhận <strong>01 phiếu báo hỏng thiết bị nội trú</strong> từ sinh viên thuộc khu vực phụ trách. Kính đề nghị Quý Thầy/Cô xem xét điều phối hoặc xử lý bảo trì kỹ thuật:
            </p>

            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin: 20px 0;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                    <tr>
                        <td style="padding: 6px 0; color: #64748b; width: 38%;">Mã phiếu báo:</td>
                        <td style="padding: 6px 0; font-weight: 700; color: #0f172a;">{$maPhieu}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Thời gian báo:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$timeNow}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Vị trí phòng ở:</td>
                        <td style="padding: 6px 0; font-weight: 700; color: #b45309;">{$location}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Thiết bị sự cố:</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #0f172a;">{$equipment}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Sinh viên báo hỏng:</td>
                        <td style="padding: 6px 0; color: #0f172a;">{$studentName} (MSSV: {$mssv} - SĐT: {$phone})</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b; vertical-align: top;">Mô tả hư hỏng:</td>
                        <td style="padding: 6px 0; color: #991b1b; font-weight: 600;">"{$desc}"</td>
                    </tr>
                </table>
            </div>

            <div style="text-align: center; margin: 26px 0 16px;">
                <a href="{$actionUrl}" style="background: #d97706; color: #ffffff; text-decoration: none; padding: 13px 30px; font-size: 14px; font-weight: 700; border-radius: 8px; display: inline-block; box-shadow: 0 4px 8px rgba(217,119,6,0.25);">
                    ĐIỀU PHỐI & XỬ LÝ SỬA CHỮA
                </a>
            </div>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
            <p style="font-size: 12px; color: #94a3b8; margin: 0; text-align: center;">
                Email này được gửi tự động từ Hệ thống Quản lý KTX ĐHQG-HCM đến Cán bộ Quản lý và Bộ phận Kỹ thuật.
            </p>
        </div>
    </div>
</body>
</html>
HTML;

                $plain = "Kính gửi {$manager['HoVaTen']} ({$manager['ChucVu']}),\n\n"
                    . "Yêu cầu sửa chữa thiết bị mới tại KTX:\n"
                    . "- Mã phiếu: {$maPhieu}\n"
                    . "- Vị trí: {$pb['SoPhong']} - {$pb['TenToaNha']}\n"
                    . "- Thiết bị: {$equipment}\n"
                    . "- Mô tả: {$pb['MoTaHuHong']}\n"
                    . "- Sinh viên: {$studentName} (SĐT: {$phone})\n\n"
                    . "Xem và điều phối tại: {$actionUrl}\n\n"
                    . "Trân trọng,\nBan Quản lý Ký túc xá ĐHQG-HCM";

                $res = self::send($manager['Email'], $manager['HoVaTen'], $subject, $html, $plain);
                $results[] = $res;
                if ($res['success']) {
                    $sentCount++;
                }
            }

            return [
                'success' => true,
                'total_recipients' => count($managers),
                'sent' => $sentCount,
                'results' => $results
            ];
        } catch (Throwable $e) {
            error_log('Lỗi notifyManagersNewRepairTicket: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Gửi email thông báo cho sinh viên khi hồ sơ đăng ký được phê duyệt giữ chỗ 72h
     */
    public static function notifyStudentApplicationApproved(string $maHoSo): array
    {
        try {
            $sql = "
                SELECT hs.*, sv.HoVaTen, sv.Email, sv.MSSVTaiTruong,
                       g.KyHieuGiuong, p.SoPhong, tn.TenToaNha, c.TenCum, k.TenKhu
                FROM HoSoDangKy hs
                JOIN SinhVien sv ON hs.MaSinhVien = sv.MaSinhVien
                JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                JOIN Phong p ON g.MaPhong = p.MaPhong
                JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                JOIN CumNha c ON tn.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                WHERE hs.MaHoSo = :hs LIMIT 1
            ";
            $hs = Database::fetch($sql, ['hs' => $maHoSo]);
            if (!$hs || empty($hs['Email'])) {
                return ['success' => false, 'message' => 'Không tìm thấy hồ sơ hoặc email sinh viên.'];
            }

            $studentName = htmlspecialchars($hs['HoVaTen'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $hanChot = !empty($hs['HanChotThanhToan']) ? date('H:i d/m/Y', strtotime($hs['HanChotThanhToan'])) : '72 giờ kể từ thời điểm duyệt';
            $paymentUrl = function_exists('url') ? url('student/payment') : 'http://localhost/Dormitory_Management/student/payment';
            $roomInfo = htmlspecialchars("Giường {$hs['KyHieuGiuong']} - Phòng {$hs['SoPhong']} - {$hs['TenToaNha']} ({$hs['TenKhu']})", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $subject = "[KTX ĐHQG-HCM] Chúc mừng! Hồ sơ đăng ký nội trú #{$maHoSo} đã được phê duyệt giữ chỗ 72h";

            $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="font-family: Arial, sans-serif; background: #f8fafc; padding: 20px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="background: #166534; color: #fff; padding: 22px; text-align: center;">
            <h2 style="margin: 0; font-size: 20px;">HỒ SƠ ĐĂNG KÝ NỘI TRÚ ĐÃ ĐƯỢC DUYỆT</h2>
        </div>
        <div style="padding: 24px;">
            <p>Xin chào <strong>{$studentName}</strong>,</p>
            <p>Ban Quản lý Ký túc xá ĐHQG-HCM thông báo hồ sơ đăng ký chỗ ở <strong>#{$maHoSo}</strong> của bạn đã được xét duyệt thành công!</p>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin: 18px 0;">
                <p style="margin: 4px 0;"><strong>Vị trí được giữ chỗ:</strong> {$roomInfo}</p>
                <p style="margin: 4px 0; color: #b91c1c;"><strong>Hạn chót thanh toán lệ phí (72 giờ):</strong> {$hanChot}</p>
                <p style="margin: 4px 0; font-size: 13px; color: #4b5563;">* Quá thời hạn trên nếu chưa hoàn tất thanh toán, hệ thống sẽ tự động hủy giữ chỗ.</p>
            </div>
            <div style="text-align: center; margin: 24px 0;">
                <a href="{$paymentUrl}" style="background: #166534; color: #fff; text-decoration: none; padding: 12px 28px; font-weight: bold; border-radius: 6px; display: inline-block;">TIẾN HÀNH THANH TOÁN LỆ PHÍ</a>
            </div>
            <p style="font-size: 13px; color: #64748b;">Trân trọng,<br>Ban Quản lý Ký túc xá ĐHQG-HCM</p>
        </div>
    </div>
</body>
</html>
HTML;

            return self::send($hs['Email'], $hs['HoVaTen'], $subject, $html);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Gửi email thông báo cho sinh viên khi hồ sơ đăng ký bị từ chối
     */
    public static function notifyStudentApplicationRejected(string $maHoSo, string $lyDo = ''): array
    {
        try {
            $sql = "
                SELECT hs.*, sv.HoVaTen, sv.Email, sv.MSSVTaiTruong
                FROM HoSoDangKy hs
                JOIN SinhVien sv ON hs.MaSinhVien = sv.MaSinhVien
                WHERE hs.MaHoSo = :hs LIMIT 1
            ";
            $hs = Database::fetch($sql, ['hs' => $maHoSo]);
            if (!$hs || empty($hs['Email'])) {
                return ['success' => false, 'message' => 'Không tìm thấy hồ sơ hoặc email sinh viên.'];
            }

            $studentName = htmlspecialchars($hs['HoVaTen'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $reasonText = htmlspecialchars($lyDo ?: ($hs['GhiChuTuChoi'] ?: 'Không đáp ứng điều kiện tiếp nhận hồ sơ theo quy định'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $subject = "[KTX ĐHQG-HCM] Thông báo kết quả xét duyệt hồ sơ đăng ký nội trú #{$maHoSo}";

            $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="font-family: Arial, sans-serif; background: #f8fafc; padding: 20px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="background: #991b1b; color: #fff; padding: 22px; text-align: center;">
            <h2 style="margin: 0; font-size: 20px;">KẾT QUẢ XÉT DUYỆT HỒ SƠ NỘI TRÚ</h2>
        </div>
        <div style="padding: 24px;">
            <p>Xin chào <strong>{$studentName}</strong>,</p>
            <p>Ban Quản lý Ký túc xá ĐHQG-HCM xin thông báo hồ sơ đăng ký chỗ ở <strong>#{$maHoSo}</strong> của bạn chưa được duyệt.</p>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 18px 0;">
                <p style="margin: 4px 0; color: #991b1b;"><strong>Lý do từ chối:</strong></p>
                <p style="margin: 4px 0; font-style: italic;">"{$reasonText}"</p>
            </div>
            <p style="font-size: 14px;">Nếu có bất kỳ thắc mắc nào, bạn vui lòng liên hệ Văn phòng Ban Quản lý Cụm nhà hoặc Tổng đài CTSV để được hỗ trợ.</p>
            <p style="font-size: 13px; color: #64748b;">Trân trọng,<br>Ban Quản lý Ký túc xá ĐHQG-HCM</p>
        </div>
    </div>
</body>
</html>
HTML;

            return self::send($hs['Email'], $hs['HoVaTen'], $subject, $html);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Gửi email thông báo cho sinh viên khi đơn một cửa được duyệt hoặc bị từ chối
     */
    public static function notifyStudentRequestDecision(string $maDon, string $status, string $lyDo = ''): array
    {
        try {
            $sql = "
                SELECT d.*, sv.HoVaTen, sv.Email
                FROM DonTuMotCua d
                JOIN SinhVien sv ON d.MaSinhVien = sv.MaSinhVien
                WHERE d.MaDon = :don LIMIT 1
            ";
            $req = Database::fetch($sql, ['don' => $maDon]);
            if (!$req || empty($req['Email'])) {
                return ['success' => false, 'message' => 'Không tìm thấy đơn hoặc email sinh viên.'];
            }

            $studentName = htmlspecialchars($req['HoVaTen'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $loaiDon = htmlspecialchars($req['LoaiDon'] ?? 'Đơn từ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $isApproved = ($status === 'Đã duyệt');
            $color = $isApproved ? '#166534' : '#991b1b';
            $statusText = $isApproved ? 'ĐÃ ĐƯỢC PHÊ DUYỆT' : 'KHÔNG ĐƯỢC PHÊ DUYỆT';

            $subject = "[KTX ĐHQG-HCM] Kết quả xử lý đơn {$req['LoaiDon']} - Mã đơn: {$maDon}";

            $detailText = $isApproved
                ? "<p style='color: #166534;'>Đơn yêu cầu của bạn đã được Cán bộ Quản lý kiểm tra và phê duyệt thành công. Vui lòng kiểm tra lại trạng thái hợp đồng hoặc thông tin cư trú trên Cổng thông tin sinh viên.</p>"
                : "<p style='color: #991b1b;'>Đơn yêu cầu của bạn đã bị từ chối với lý do: <em>\"" . htmlspecialchars($lyDo ?: 'Không đáp ứng điều kiện theo quy định', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"</em>.</p>";

            $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="font-family: Arial, sans-serif; background: #f8fafc; padding: 20px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="background: {$color}; color: #fff; padding: 22px; text-align: center;">
            <h2 style="margin: 0; font-size: 20px;">KẾT QUẢ GIẢI QUYẾT ĐƠN TỪ - {$statusText}</h2>
        </div>
        <div style="padding: 24px;">
            <p>Xin chào <strong>{$studentName}</strong>,</p>
            <p>Ban Quản lý Ký túc xá ĐHQG-HCM thông báo kết quả giải quyết đơn <strong>{$loaiDon}</strong> (Mã đơn: <strong>#{$maDon}</strong>):</p>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 18px 0;">
                {$detailText}
            </div>
            <p style="font-size: 13px; color: #64748b;">Trân trọng,<br>Ban Quản lý Ký túc xá ĐHQG-HCM</p>
        </div>
    </div>
</body>
</html>
HTML;

            return self::send($req['Email'], $req['HoVaTen'], $subject, $html);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lấy các dòng log email gần đây nhất từ storage/logs/mail.log
     */
    public static function getRecentLogs(int $limit = 5): array
    {
        try {
            $logFile = dirname(__DIR__, 2) . '/storage/logs/mail.log';
            if (!file_exists($logFile)) {
                return [];
            }
            $content = file_get_contents($logFile);
            $separator = str_repeat('-', 80);
            $parts = explode($separator, $content);
            $logs = [];
            foreach (array_reverse($parts) as $part) {
                $trimmed = trim($part);
                if (!empty($trimmed)) {
                    $logs[] = $trimmed;
                }
                if (count($logs) >= $limit) {
                    break;
                }
            }
            return $logs;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Ghi nhận lịch sử gửi thư vào storage/logs/mail.log
     */
    private static function logEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $status): void
    {
        try {
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/mail.log';
            $time = date('Y-m-d H:i:s');
            $separator = str_repeat('-', 80);

            $entry = "[$time] STATUS: {$status}\n";
            $entry .= "TO: {$toName} <{$toEmail}>\n";
            $entry .= "SUBJECT: {$subject}\n";
            $entry .= "CONTENT_HTML_LEN: " . strlen($htmlBody) . " bytes\n";
            $entry .= "{$separator}\n\n";

            file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Không ngắt luồng nếu lỗi ghi file log
        }
    }

    /**
     * Gửi trực tiếp qua SMTP Socket với xử lý mã trạng thái chuẩn RFC
     */
    private static function sendViaSmtp(string $host, int $port, string $encryption, string $user, string $pass, string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $htmlBody, array &$trace = []): bool
    {
        $timeout = 8;
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new \Exception("Không thể kết nối đến máy chủ SMTP {$host}:{$port} ({$errstr})");
        }

        stream_set_timeout($socket, $timeout);
        $read = self::smtpRead($socket);
        $trace[] = "CONNECT: " . trim($read);
        if (substr($read, 0, 3) !== '220') {
            throw new \Exception("Máy chủ SMTP không sẵn sàng: {$read}");
        }

        // HELO/EHLO
        self::smtpWrite($socket, "EHLO " . gethostname());
        $read = self::smtpRead($socket);
        $trace[] = "EHLO: " . trim($read);

        // STARTTLS
        if ($encryption === 'tls') {
            self::smtpWrite($socket, "STARTTLS");
            $read = self::smtpRead($socket);
            $trace[] = "STARTTLS: " . trim($read);
            if (substr($read, 0, 3) !== '220') {
                throw new \Exception("Máy chủ không hỗ trợ STARTTLS: {$read}");
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \Exception("Không thể thiết lập mã hóa TLS với máy chủ SMTP");
            }
            self::smtpWrite($socket, "EHLO " . gethostname());
            $read = self::smtpRead($socket);
            $trace[] = "EHLO_TLS: " . trim($read);
        }

        // AUTH LOGIN
        self::smtpWrite($socket, "AUTH LOGIN");
        $read = self::smtpRead($socket);
        $trace[] = "AUTH_LOGIN: " . trim($read);
        if (substr($read, 0, 3) !== '334') {
            throw new \Exception("Máy chủ từ chối lệnh AUTH LOGIN: {$read}");
        }

        self::smtpWrite($socket, base64_encode($user));
        $read = self::smtpRead($socket);
        $trace[] = "USER: " . trim($read);
        if (substr($read, 0, 3) !== '334') {
            throw new \Exception("Tên đăng nhập SMTP bị từ chối: {$read}");
        }

        self::smtpWrite($socket, base64_encode($pass));
        $read = self::smtpRead($socket);
        $trace[] = "PASS: " . trim($read);
        if (substr($read, 0, 3) !== '235') {
            throw new \Exception("Xác thực SMTP thất bại (Sai mật khẩu hoặc cần App Password): {$read}");
        }

        // MAIL FROM
        self::smtpWrite($socket, "MAIL FROM: <{$fromEmail}>");
        $read = self::smtpRead($socket);
        $trace[] = "MAIL_FROM: " . trim($read);
        if (substr($read, 0, 3) !== '250') {
            throw new \Exception("Địa chỉ gửi MAIL FROM bị từ chối: {$read}");
        }

        // RCPT TO
        self::smtpWrite($socket, "RCPT TO: <{$toEmail}>");
        $read = self::smtpRead($socket);
        $trace[] = "RCPT_TO: " . trim($read);
        if (substr($read, 0, 3) !== '250' && substr($read, 0, 3) !== '251') {
            throw new \Exception("Địa chỉ nhận RCPT TO bị từ chối: {$read}");
        }

        // DATA
        self::smtpWrite($socket, "DATA");
        $read = self::smtpRead($socket);
        $trace[] = "DATA: " . trim($read);
        if (substr($read, 0, 3) !== '354') {
            throw new \Exception("Máy chủ không sẵn sàng nhận dữ liệu DATA: {$read}");
        }

        $headers = [];
        $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
        $headers[] = "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Date: " . date('r');

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        self::smtpWrite($socket, $payload);
        $read = self::smtpRead($socket);
        $trace[] = "BODY_SEND: " . trim($read);
        if (substr($read, 0, 3) !== '250') {
            throw new \Exception("Lỗi gửi nội dung thư: {$read}");
        }

        // QUIT
        self::smtpWrite($socket, "QUIT");
        @fclose($socket);

        return true;
    }

    private static function smtpWrite($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    private static function smtpRead($socket): string
    {
        $res = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            $res .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $res;
    }
}
