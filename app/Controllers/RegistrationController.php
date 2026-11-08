<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Auth;
use App\Services\MailService;
use App\Services\AutoReleaseService;
use PDOException;

class RegistrationController extends Controller
{
    private const BUILDING_ACTIVE = "\u{0110}ang ho\u{1EA1}t \u{0111}\u{1ED9}ng";
    private const ROOM_MAINTENANCE = "\u{0110}ang b\u{1EA3}o tr\u{00EC}";
    private const BED_AVAILABLE = "Tr\u{1ED1}ng";
    // Some existing SQL seed records contain UTF-8 text decoded as Windows-1252.
    private const BUILDING_ACTIVE_LEGACY = "\u{00C4}\u{0090}ang ho\u{00E1}\u{00BA}\u{00A1}t \u{00C4}\u{2018}\u{00E1}\u{00BB}\u{2122}ng";
    private const ROOM_MAINTENANCE_LEGACY = "\u{00C4}\u{0090}ang b\u{00E1}\u{00BA}\u{00A3}o tr\u{00C3}\u{00AC}";
    private const BED_AVAILABLE_LEGACY = "Tr\u{00E1}\u{00BB}\u{2018}ng";

    private function validCsrf(Request $request): bool
    {
        $token = (string) $request->input('csrf_token', '');
        return hash_equals((string) \App\Core\Session::get('csrf_token', ''), $token)
            && $token !== '';
    }

    public function show(): void
    {
        // Tự động giải phóng các giường quá hạn 72h để sinh viên mới có thể thấy phòng trống
        AutoReleaseService::scanAndReleaseExpiredBeds();

        $user = Auth::user();
        $studentPrefill = null;
        if ($user) {
            $mssv = $user['profile']['MaSinhVien'] ?? $user['TenDangNhap'] ?? '';
            if ($mssv !== '') {
                $studentPrefill = Database::fetch(
                    "SELECT sv.*, tk.TenDangNhap FROM SinhVien sv
                     LEFT JOIN TaiKhoan tk ON tk.MaTaiKhoan = sv.MaTaiKhoan
                     WHERE sv.MaSinhVien = :mssv LIMIT 1",
                    ['mssv' => $mssv]
                );
                $existingApplication = Database::fetch(
                    "SELECT MaHoSo FROM HoSoDangKy
                     WHERE MaSinhVien = :mssv
                       AND TrangThaiHoSo IN ('Chờ duyệt', 'Chờ thanh toán')
                     LIMIT 1",
                    ['mssv' => $mssv]
                );
                if ($existingApplication) {
                    $this->redirect('student/dashboard');
                    return;
                }
            }
        }

        if (!Session::has('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }

        // Kiểm tra đợt đăng ký đang có hiệu lực
        $activeDot = Database::fetch(
            "SELECT * FROM DotDangKy
             WHERE ThoiGianMoCong <= NOW() AND ThoiGianDongCong >= NOW()
             ORDER BY ThoiGianDongCong DESC LIMIT 1"
        );
        $isOpen = !empty($activeDot);
        $upcomingDot = null;
        $latestClosedDot = null;

        if (!$isOpen) {
            $upcomingDot = Database::fetch(
                "SELECT * FROM DotDangKy
                 WHERE ThoiGianMoCong > NOW()
                 ORDER BY ThoiGianMoCong ASC LIMIT 1"
            );
            $latestClosedDot = Database::fetch(
                "SELECT * FROM DotDangKy
                 WHERE ThoiGianDongCong < NOW()
                 ORDER BY ThoiGianDongCong DESC LIMIT 1"
            );
        }

        $universities = Database::fetchAll(
            "SELECT MaTruong, TenTruong FROM TruongDaiHoc WHERE DangHopTac = 1 ORDER BY TenTruong"
        );
        $areas = Database::fetchAll(
            "SELECT MaKhu, TenKhu FROM KhuKTX ORDER BY MaKhu"
        );
        $roomTypes = Database::fetchAll(
            "SELECT MaLoaiPhong, TenLoaiPhong FROM LoaiPhong ORDER BY MaLoaiPhong"
        );

        $this->view('registration', [
            'title' => 'Đăng ký hồ sơ nội trú',
            'isOpen' => $isOpen,
            'activeDot' => $activeDot,
            'upcomingDot' => $upcomingDot,
            'latestClosedDot' => $latestClosedDot,
            'universities' => $universities,
            'areas' => $areas,
            'roomTypes' => $roomTypes,
            'studentPrefill' => $studentPrefill,
            'old' => Session::flash('old') ?? [],
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function sendEmailOtp(): void
    {
        (new \App\Controllers\Api\RegistrationApiController())->sendOtp();
    }

    private function emailOtpError(string $email, string $code): ?string
    {
        $otp = Session::get('registration_email_otp');
        if (!is_array($otp) || empty($otp['hash'])) {
            return 'Vui lòng gửi mã xác thực về email trước.';
        }
        if (!hash_equals((string) ($otp['email'] ?? ''), strtolower(trim($email)))) {
            return 'Email đã thay đổi. Vui lòng gửi mã xác thực mới đến email hiện tại.';
        }
        if ((int) ($otp['expires_at'] ?? 0) < time()) {
            Session::remove('registration_email_otp');
            return 'Mã xác thực đã hết hạn. Vui lòng gửi mã mới.';
        }
        if ((int) ($otp['attempts'] ?? 0) >= 5) {
            Session::remove('registration_email_otp');
            return 'Bạn đã nhập sai mã quá số lần cho phép. Vui lòng gửi mã mới.';
        }
        if ($code === '' || !password_verify($code, (string) $otp['hash'])) {
            $otp['attempts'] = (int) ($otp['attempts'] ?? 0) + 1;
            Session::set('registration_email_otp', $otp);
            return 'Mã xác thực không chính xác.';
        }
        return null;
    }

    public function options(): void
    {
        (new \App\Controllers\Api\RegistrationApiController())->options();
    }

    private function redirectWithError(string $message, ?Request $request = null): void
    {
        if ($request) {
            $all = $request->all();
            unset($all['password'], $all['confirm_password'], $all['csrf_token'], $all['_token']);
            Session::flash('old', $all);
        }
        Session::flash('error', $message);
        $this->redirect('register');
    }

    public function submit(): void
    {
        $request = new Request();
        if (!$this->validCsrf($request)) {
            $this->redirectWithError('Phiên biểu mẫu đã hết hạn. Vui lòng tải lại trang.', $request);
            return;
        }

        // 1. RÀNG BUỘC THỜI GIAN ĐỢT ĐĂNG KÝ: Cổng phải đang mở
        $activeDot = Database::fetch(
            "SELECT MaDot, TenDot, SoGioGiuCho FROM DotDangKy
             WHERE ThoiGianMoCong <= NOW() AND ThoiGianDongCong >= NOW()
             ORDER BY ThoiGianDongCong DESC LIMIT 1"
        );
        if (!$activeDot) {
            $this->redirectWithError('Cổng đăng ký nội trú hiện đang đóng hoặc chưa tới thời gian tiếp nhận. Vui lòng theo dõi thông báo đợt đăng ký tiếp theo!', $request);
            return;
        }

        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('confirm_password', '');
        $email = trim((string) $request->input('email', ''));
        $hoTen = trim((string) $request->input('hoTen', ''));
        $mssv = trim((string) $request->input('mssv', ''));
        $cccd = trim((string) $request->input('cccd', ''));
        $phone = trim((string) $request->input('phone', ''));
        $birthday = trim((string) $request->input('birthday', ''));
        $gender = trim((string) $request->input('gender', ''));
        $university = trim((string) $request->input('university', ''));
        $bhyt = trim((string) $request->input('bhyt', ''));
        $bedId = trim((string) $request->input('bed', ''));
        $registrationType = trim((string) $request->input('registrationType', 'Đăng ký thường'));
        $user = Auth::user();
        $studentId = (string) ($user['profile']['MaSinhVien'] ?? ($user['TenDangNhap'] ?? ''));
        $existingProfile = $studentId !== '' ? Database::fetch(
            'SELECT * FROM SinhVien WHERE MaSinhVien = :id LIMIT 1', ['id' => $studentId]
        ) : null;
        $isExistingStudent = $existingProfile !== null;
        if ($isExistingStudent) {
            $mssv = $studentId;
            $hoTen = (string) $existingProfile['HoVaTen'];
            $cccd = (string) ($existingProfile['SoCCCD'] ?? '');
            $phone = (string) ($existingProfile['SoDienThoai'] ?? '');
            $birthday = (string) ($existingProfile['NgaySinh'] ?? '');
            $gender = (string) ($existingProfile['GioiTinh'] ?? '');
            $university = (string) ($existingProfile['MaTruong'] ?? '');
            $bhyt = (string) ($existingProfile['MaSoBHYT'] ?? '');
            $email = (string) ($existingProfile['Email'] ?? $email);
            $username = (string) ($user['TenDangNhap'] ?? $studentId);
            $password = $confirmPassword = 'existing-account';
        }
        if ((!$isExistingStudent && ($username === '' || $password === '' || $confirmPassword === '')) || $hoTen === '' || $mssv === '' || $cccd === '' || (!$isExistingStudent && $phone === '') || $email === '' || $university === '' || $bedId === '') {
            $this->redirectWithError('Vui lòng điền đầy đủ thông tin tài khoản, hồ sơ và vị trí giường.', $request);
            return;
        }

        if (mb_strlen($username) > 50 || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            $this->redirectWithError('Tên đăng nhập không được dài quá 50 ký tự hoặc chứa ký tự điều khiển.', $request);
            return;
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $mssv)) {
            $this->redirectWithError('MSSV chỉ được chứa chữ cái không dấu và chữ số.', $request);
            return;
        }
        if (!preg_match('/^[0-9]{12}$/', $cccd)) {
            $this->redirectWithError('Số CCCD phải gồm đúng 12 chữ số.', $request);
            return;
        }
        if ($phone !== '' && !preg_match('/^[0-9]{9,11}$/', $phone)) {
            $this->redirectWithError('Số điện thoại phải gồm từ 9 đến 11 chữ số.', $request);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirectWithError('Email cá nhân không hợp lệ.', $request);
            return;
        }
        if (mb_strlen($email) > 100) {
            $this->redirectWithError('Email không được dài quá 100 ký tự.', $request);
            return;
        }
        if (!$isExistingStudent && mb_strlen($password) < 6) {
            $this->redirectWithError('Mật khẩu phải có ít nhất 6 ký tự.', $request);
            return;
        }
        if (!$isExistingStudent && $password !== $confirmPassword) {
            $this->redirectWithError('Mật khẩu nhập lại chưa khớp.', $request);
            return;
        }
        $otpError = $this->emailOtpError($email, (string) $request->input('email_otp', ''));
        if ($otpError !== null) {
            $this->redirectWithError($otpError, $request);
            return;
        }

        try {
            $existingStudent = Database::fetch(
                "SELECT MaSinhVien FROM SinhVien
                 WHERE MaSinhVien = :maSinhVien OR MSSVTaiTruong = :mssvTaiTruong LIMIT 1",
                ['maSinhVien' => $mssv, 'mssvTaiTruong' => $mssv]
            );
            if ($existingStudent && (!$isExistingStudent || $existingStudent['MaSinhVien'] !== $studentId)) {
                $existingApplication = Database::fetch(
                    "SELECT MaHoSo FROM HoSoDangKy
                     WHERE MaSinhVien = :mssv
                       AND TrangThaiHoSo IN ('Chờ duyệt', 'Chờ thanh toán')
                     LIMIT 1",
                    ['mssv' => $existingStudent['MaSinhVien']]
                );
                Session::flash(
                    'error',
                    $existingApplication
                        ? 'MSSV này đã có hồ sơ đang xử lý và không thể đăng ký lại.'
                        : 'MSSV này đã có tài khoản trong hệ thống. Hãy đăng nhập hoặc liên hệ Ban Quản lý KTX.'
                );
                $this->redirect($existingApplication ? 'student/dashboard' : 'login');
                return;
            }

            $existingAccount = $isExistingStudent ? null : Database::fetch(
                "SELECT MaTaiKhoan FROM TaiKhoan
                 WHERE TenDangNhap = :username OR TenDangNhap = :mssv LIMIT 1",
                ['username' => $username, 'mssv' => $mssv]
            );
            if ($existingAccount) {
                $this->redirectWithError('Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.', $request);
                return;
            }

            $usernameStudent = $isExistingStudent ? null : Database::fetch(
                "SELECT MaSinhVien FROM SinhVien
                 WHERE MaSinhVien = :usernameMaSinhVien OR MSSVTaiTruong = :usernameMssv LIMIT 1",
                ['usernameMaSinhVien' => $username, 'usernameMssv' => $username]
            );
            if ($usernameStudent) {
                $this->redirectWithError('Tên đăng nhập này đã được sử dụng làm mã sinh viên. Vui lòng chọn tên khác.', $request);
                return;
            }

            $existingCccd = $isExistingStudent ? null : Database::fetch(
                "SELECT MaSinhVien FROM SinhVien WHERE SoCCCD = :cccd LIMIT 1",
                ['cccd' => $cccd]
            );
            if ($existingCccd) {
                $this->redirectWithError('Số CCCD này đã được đăng ký trong hệ thống.', $request);
                return;
            }

            $existingEmailStudent = Database::fetch(
                "SELECT MaSinhVien FROM SinhVien WHERE Email = :email LIMIT 1",
                ['email' => $email]
            );
            $existingEmailStaff = Database::fetch(
                "SELECT MaNhanVien FROM NhanVien WHERE Email = :email LIMIT 1",
                ['email' => $email]
            );
            if (($existingEmailStudent && (!$isExistingStudent || $existingEmailStudent['MaSinhVien'] !== $studentId)) || $existingEmailStaff) {
                $this->redirectWithError('Email này đã được sử dụng cho một tài khoản khác.', $request);
                return;
            }

            if ($phone !== '' && !$isExistingStudent) {
                $existingPhoneStudent = Database::fetch(
                    "SELECT MaSinhVien FROM SinhVien WHERE SoDienThoai = :phone LIMIT 1",
                    ['phone' => $phone]
                );
                $existingPhoneStaff = Database::fetch(
                    "SELECT MaNhanVien FROM NhanVien WHERE SoDienThoai = :phone LIMIT 1",
                    ['phone' => $phone]
                );
                if ($existingPhoneStudent || $existingPhoneStaff) {
                    $this->redirectWithError('Số điện thoại này đã được sử dụng cho một tài khoản khác.', $request);
                    return;
                }
            }
        } catch (\Throwable $e) {
            $this->redirectWithError('Không thể kiểm tra thông tin đăng ký. Vui lòng thử lại.', $request);
            return;
        }

        $studentId = $isExistingStudent ? $studentId : $mssv;

        $uploads = [
            'cccdFront' => 'cccd_front',
            'cccdBack' => 'cccd_back',
            'portrait' => 'portrait',
            'bhytImage' => 'bhyt',
            'admissionLetter' => 'admission',
        ];
        $uploadedPaths = [];
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/registration';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        try {
            foreach ($uploads as $inputName => $prefix) {
                if (!isset($_FILES[$inputName]) || ($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file = $_FILES[$inputName];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException('Tải tệp thất bại: ' . $inputName);
                }
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new \RuntimeException('Mỗi tệp tải lên không được vượt quá 5 MB.');
                }
                $mime = mime_content_type($file['tmp_name']);
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    throw new \RuntimeException('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.');
                }
                $extension = match ($mime) {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg',
                };
                $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename);
                $uploadedPaths[$inputName] = 'storage/uploads/registration/' . $filename;
            }

            Database::beginTransaction();

            $bed = Database::fetch(
                "SELECT g.MaGiuong, p.MaPhong, tn.GioiTinhQuyDinh, tn.TenToaNha
                 FROM Giuong g
                 JOIN Phong p ON p.MaPhong = g.MaPhong
                 JOIN ToaNha tn ON tn.MaToaNha = p.MaToaNha
                 WHERE g.MaGiuong = :bed
                   AND g.TrangThaiGiuong IN (:bedStatus, :bedStatusLegacy, 'Trong')
                   AND p.TrangThaiPhong NOT IN (:roomMaintenance, :roomMaintenanceLegacy, 'Bảo trì')
                   AND tn.TrangThaiToaNha NOT IN ('Ngưng sử dụng', 'Tạm đóng bảo trì')
                 FOR UPDATE",
                [
                    'bed' => $bedId,
                    'bedStatus' => self::BED_AVAILABLE,
                    'bedStatusLegacy' => self::BED_AVAILABLE_LEGACY,
                    'roomMaintenance' => self::ROOM_MAINTENANCE,
                    'roomMaintenanceLegacy' => self::ROOM_MAINTENANCE_LEGACY,
                ]
            );
            if (!$bed || ($bed['GioiTinhQuyDinh'] !== 'Tất cả' && $bed['GioiTinhQuyDinh'] !== $gender)) {
                throw new \RuntimeException('Giường đã chọn không còn trống hoặc không phù hợp giới tính.');
            }

            $dot = Database::fetch(
                "SELECT MaDot FROM DotDangKy
                 WHERE ThoiGianMoCong <= NOW() AND ThoiGianDongCong >= NOW()
                 ORDER BY ThoiGianDongCong DESC LIMIT 1"
            );
            if (!$dot) {
                throw new \RuntimeException('Cổng đăng ký nội trú đã kết thúc thời hạn tiếp nhận hồ sơ trong quá trình xử lý.');
            }

            if (!$isExistingStudent) {
            Database::execute(
                "INSERT INTO TaiKhoan (TenDangNhap, MatKhauMaHoa, MaVaiTro, DangHoatDong)
                 VALUES (:username, :password, 'SINH_VIEN', 1)",
                ['username' => $username, 'password' => password_hash($password, PASSWORD_DEFAULT)]
            );
            $accountId = (int) Database::lastInsertId();

            Database::execute(
                "INSERT INTO SinhVien
                 (MaSinhVien, MaTaiKhoan, MaTruong, MSSVTaiTruong, HoVaTen, NgaySinh, GioiTinh,
                  SoCCCD, SoDienThoai, Email, MaSoBHYT, AnhThe4x6, AnhCCCD, AnhMinhChungHocTap, TrangThaiNoiTru)
                 VALUES
                 (:student, :account, :university, :mssv, :name, :birthday, :gender,
                  :cccd, :phone, :email, :bhyt, :portrait, :cccdImage, :admission, 'Chưa ở')",
                [
                    'student' => $studentId,
                    'account' => $accountId,
                    'university' => $university,
                    'mssv' => $mssv !== '' ? $mssv : null,
                    'name' => $hoTen,
                    'birthday' => $birthday !== '' ? $birthday : null,
                    'gender' => $gender,
                    'cccd' => $cccd,
                    'phone' => $phone !== '' ? $phone : null,
                    'email' => $email,
                    'bhyt' => $bhyt !== '' ? $bhyt : null,
                    'portrait' => $uploadedPaths['portrait'] ?? null,
                    'cccdImage' => $uploadedPaths['cccdFront'] ?? null,
                    'admission' => $uploadedPaths['admissionLetter'] ?? null,
                ]
            );
            }

            $applicationId = 'HS' . date('Y') . bin2hex(random_bytes(4));
            Database::execute(
                "INSERT INTO HoSoDangKy
                 (MaHoSo, MaSinhVien, MaDot, MaGiuong, LoaiLuongDangKy, NgayKetThucChon, TrangThaiHoSo)
                 VALUES (:application, :student, :dot, :bed, :type, NULL, 'Chờ duyệt')",
                [
                    'application' => $applicationId,
                    'student' => $studentId,
                    'dot' => (int) $dot['MaDot'],
                    'bed' => $bedId,
                    'type' => $registrationType,
                ]
            );

            Database::execute(
                "UPDATE Giuong SET TrangThaiGiuong = 'Đang giữ chỗ'
                 WHERE MaGiuong = :bed
                   AND TrangThaiGiuong IN (:available, :availableLegacy, 'Trong')",
                [
                    'bed' => $bedId,
                    'available' => self::BED_AVAILABLE,
                    'availableLegacy' => self::BED_AVAILABLE_LEGACY,
                ]
            );

            Database::commit();
            Session::remove('registration_email_otp');
            $loginUrl = function_exists('url') ? url('login') : '';
            $subject = '[KTX ĐHQG-HCM] Hồ sơ đăng ký nội trú đã được tiếp nhận';
            $safeUsername = htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeName = htmlspecialchars($hoTen, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            
            $plain = "Xin chào {$hoTen},\n\n"
                . "Hồ sơ đăng ký nội trú của bạn đã được tiếp nhận thành công vào hệ thống!\n"
                . "--------------------------------------------------------\n"
                . "THÔNG TIN TÀI KHOẢN VÀ HỒ SƠ:\n"
                . "- Tên đăng nhập: {$username}\n"
                . "- Mã hồ sơ đăng ký: {$applicationId}\n"
                . "- Cổng đăng nhập KTX: {$loginUrl}\n"
                . "--------------------------------------------------------\n"
                . "Mật khẩu do bạn tự tạo khi đăng ký; email này không chứa mật khẩu.\n\n"
                . "Trân trọng,\nBan Quản lý Ký túc xá ĐHQG-HCM";

            $html = <<<HTML
<div style="font-family: Arial, 'Segoe UI', sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #ffffff;">
    <div style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: #ffffff; padding: 26px 20px; text-align: center;">
        <h2 style="margin: 0; font-size: 21px; font-weight: 700; letter-spacing: 0.5px;">KÝ TÚC XÁ ĐHQG-HCM</h2>
        <p style="margin: 6px 0 0; font-size: 13px; opacity: 0.92;">Cổng thông tin Đăng ký & Quản lý Cư trú Nội trú</p>
    </div>
    <div style="padding: 24px;">
        <p style="font-size: 15px; margin-top: 0;">Xin chào <strong>{$safeName}</strong>,</p>
        <p style="font-size: 14px; color: #475569;">Hồ sơ đăng ký chỗ ở nội trú của bạn đã được tiếp nhận thành công vào hệ thống. Đây là thông tin tra cứu hồ sơ:</p>
        
        <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin: 20px 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="padding: 7px 0; color: #64748b; width: 45%;">Tên đăng nhập:</td>
                    <td style="padding: 7px 0; font-weight: 700; color: #1e40af; font-size: 15px;">{$safeUsername}</td>
                </tr>
                <tr>
                    <td style="padding: 7px 0; color: #64748b;">Mã hồ sơ đăng ký:</td>
                    <td style="padding: 7px 0; font-weight: 600; color: #0f172a;">{$applicationId}</td>
                </tr>
            </table>
        </div>

        <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 14px; border-radius: 4px; margin-bottom: 22px;">
            <p style="margin: 0; font-size: 13px; color: #1e40af; font-weight: 700;">Thông tin bảo mật</p>
            <p style="margin: 6px 0 0; font-size: 13px; color: #334155;">Mật khẩu do bạn tự tạo khi đăng ký và không được gửi qua email. Hãy giữ kín mật khẩu của mình.</p>
        </div>

        <div style="text-align: center; margin: 26px 0;">
            <a href="{$loginUrl}" style="background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; font-size: 14px; font-weight: 700; border-radius: 6px; display: inline-block;">Đăng nhập hệ thống</a>
        </div>

        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
        <p style="font-size: 12px; color: #94a3b8; margin: 0; text-align: center;">Đây là email tự động từ Hệ thống Quản lý KTX ĐHQG-HCM. Vui lòng không trả lời thư này.</p>
    </div>
</div>
HTML;
            $mailResult = MailService::send($email, $hoTen, $subject, $html, $plain);
            $emailDelivered = ($mailResult['mode'] ?? '') === 'smtp';

            // Gửi thông báo đến Cán bộ Quản lý liên quan phụ trách địa bàn để giải quyết hồ sơ đăng ký
            try {
                MailService::notifyManagersNewApplication($applicationId);
            } catch (\Throwable $e) {
                error_log('Lỗi gửi email thông báo cán bộ quản lý (đăng ký mới): ' . $e->getMessage());
            }

            Session::flash(
                'success',
                "Đăng ký nội trú thành công! Mã hồ sơ: <b>{$applicationId}</b>.<br>"
                . "Tài khoản <b>{$username}</b> đã được tạo bằng mật khẩu bạn chọn.<br>"
                . ($emailDelivered
                    ? "Thông báo hồ sơ đã được gửi đến email <b>{$email}</b>."
                    : "Bạn có thể đăng nhập bằng tên đăng nhập và mật khẩu đã chọn.")
            );
            $this->redirect('login');
        } catch (\Throwable $e) {
            if (Database::getConnection()->inTransaction()) {
                Database::rollBack();
            }
            $errorMessage = $e instanceof PDOException && $e->getCode() === '23000'
                ? 'Một hoặc nhiều thông tin (tên đăng nhập, MSSV, CCCD, email hoặc số điện thoại) vừa được tài khoản khác đăng ký. Vui lòng kiểm tra lại.'
                : ($e->getMessage() ?: 'Không thể tạo hồ sơ. Vui lòng thử lại.');
            $this->redirectWithError($errorMessage, $request);
        }
    }
}
