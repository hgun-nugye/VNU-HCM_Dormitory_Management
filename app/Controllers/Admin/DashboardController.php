<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/**
 * Controller: Quản trị viên (Toàn quyền hệ thống)
 * Quản trị toàn diện:
 * 1. Phân quyền & Quản lý tài khoản cán bộ (Trưởng khu, Trưởng cụm, Nhân viên)
 * 2. Cơ sở vật chất KTX (Khu, Cụm, Tòa, Phòng, Giường)
 * 3. Cấu hình Đợt đăng ký & Giữ chỗ 72h
 * 4. Danh mục Loại phòng & Đơn giá chuẩn
 * 5. Danh mục Trường ĐH & Chỉ tiêu
 * 6. Giám sát Tài chính & Nhật ký Giao dịch
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();

        // 1. Thống kê toàn hệ thống
        $stats = [
            'total_students' => 0,
            'active_residents' => 0,
            'total_rooms' => 0,
            'total_beds' => 0,
            'occupied_beds' => 0,
            'pending_applications' => 0,
            'pending_maintenance' => 0,
            'total_staff' => 0
        ];

        $canBoList = [];
        $cumNhaList = [];
        $dotDangKyList = [];
        $loaiPhongList = [];
        $truongList = [];
        $recentTransactions = [];

        try {
            $stats['total_students'] = Database::fetch("SELECT COUNT(*) AS total FROM SinhVien")['total'] ?? 0;
            $stats['active_residents'] = Database::fetch("SELECT COUNT(*) AS total FROM HopDongNoiTru WHERE TrangThaiHopDong = 'Đang hiệu lực'")['total'] ?? 0;
            $stats['total_rooms'] = Database::fetch("SELECT COUNT(*) AS total FROM Phong")['total'] ?? 0;
            $stats['total_beds'] = Database::fetch("SELECT COUNT(*) AS total FROM Giuong")['total'] ?? 0;
            $stats['occupied_beds'] = Database::fetch("SELECT COUNT(*) AS total FROM Giuong WHERE TrangThaiGiuong = 'Đã có người ở'")['total'] ?? 0;
            $stats['pending_applications'] = Database::fetch("SELECT COUNT(*) AS total FROM HoSoDangKy WHERE TrangThaiHoSo = 'Chờ duyệt'")['total'] ?? 0;
            $stats['pending_maintenance'] = Database::fetch("SELECT COUNT(*) AS total FROM PhieuBaoHong WHERE TrangThaiSua = 'Chờ xử lý'")['total'] ?? 0;
            $stats['total_staff'] = Database::fetch("SELECT COUNT(*) AS total FROM NhanVien")['total'] ?? 0;

            // Danh sách cán bộ & phân quyền
            $canBoList = Database::fetchAll("
                        SELECT tk.MaTaiKhoan, tk.TenDangNhap, tk.MaVaiTro, tk.MaCumQuanLy, tk.DangHoatDong,
                            vt.TenVaiTro, nv.HoVaTen, nv.ChucVu, nv.SoDienThoai, nv.Email
                        FROM TaiKhoan tk
                        JOIN VaiTro vt ON tk.MaVaiTro = vt.MaVaiTro
                        LEFT JOIN NhanVien nv ON tk.MaTaiKhoan = nv.MaTaiKhoan
                        WHERE tk.MaVaiTro != 'SINH_VIEN' AND tk.MaVaiTro != 'TRUONG_PHONG'
                        ORDER BY tk.MaTaiKhoan DESC
                        LIMIT 5
                    ");
            // Danh mục cụm nhà & hạ tầng
            $cumNhaList = Database::fetchAll("
                SELECT c.*, k.TenKhu,
                       COUNT(DISTINCT tn.MaToaNha) AS TongToa,
                       COUNT(DISTINCT p.MaPhong) AS TongPhong,
                       COUNT(DISTINCT g.MaGiuong) AS TongGiuong
                FROM CumNha c
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                LEFT JOIN ToaNha tn ON c.MaCum = tn.MaCum
                LEFT JOIN Phong p ON tn.MaToaNha = p.MaToaNha
                LEFT JOIN Giuong g ON p.MaPhong = g.MaPhong
                GROUP BY c.MaCum
                ORDER BY c.MaKhu, c.MaCum
            ");

            // Đợt đăng ký 72h
            $dotDangKyList = Database::fetchAll("SELECT * FROM DotDangKy ORDER BY MaDot DESC LIMIT 5");

            // Loại phòng & đơn giá
            $loaiPhongList = Database::fetchAll("SELECT * FROM LoaiPhong ORDER BY DonGiaLuuTruChuan ASC");

            // Trường đại học
            $truongList = Database::fetchAll("SELECT * FROM TruongDaiHoc ORDER BY ThuocDHQGHCM DESC, MaTruong ASC");

            // Doanh thu & giao dịch
            $recentTransactions = Database::fetchAll("
                SELECT gd.*, hd.LoaiHoaDon, hd.MaSinhVien, sv.HoVaTen
                FROM GiaoDichThanhToan gd
                JOIN HoaDon hd ON gd.MaHoaDon = hd.MaHoaDon
                JOIN SinhVien sv ON hd.MaSinhVien = sv.MaSinhVien
                ORDER BY gd.ThoiGianGiaoDich DESC LIMIT 5
            ");
        } catch (\Throwable $e) {
            // Dùng dữ liệu sườn mẫu
        }

        // Dữ liệu sườn mẫu dự phòng nếu CSDL chưa có dữ liệu
        if (empty($canBoList)) {
            $canBoList = [
                ['TenDangNhap' => 'NV001', 'HoVaTen' => 'Trần Văn Minh', 'TenVaiTro' => 'Trưởng cụm nhà', 'MaCumQuanLy' => 'Cụm AG (Khu A)', 'SoDienThoai' => '0903123456', 'DangHoatDong' => 1],
                ['TenDangNhap' => 'NV002', 'HoVaTen' => 'Nguyễn Thị Thu Hương', 'TenVaiTro' => 'Nhân viên hành chính', 'MaCumQuanLy' => 'Cụm AH (Khu A)', 'SoDienThoai' => '0914234567', 'DangHoatDong' => 1],
                ['TenDangNhap' => 'TK001', 'HoVaTen' => 'Phạm Quốc Hùng', 'TenVaiTro' => 'Trưởng khu KTX', 'MaCumQuanLy' => 'Toàn bộ Khu A', 'SoDienThoai' => '0977333444', 'DangHoatDong' => 1],
                ['TenDangNhap' => 'QLKTX01', 'HoVaTen' => 'Hoàng Minh Tuấn', 'TenVaiTro' => 'Quản lý chung KTX', 'MaCumQuanLy' => 'Toàn hệ thống', 'SoDienThoai' => '0909555666', 'DangHoatDong' => 1],
            ];
        }

        if (empty($cumNhaList)) {
            $cumNhaList = [
                ['MaCum' => 'AG', 'TenCum' => 'Cụm nhà A.G', 'TenKhu' => 'Khu A', 'TongToa' => 4, 'TongPhong' => 120, 'TongGiuong' => 480],
                ['MaCum' => 'AH', 'TenCum' => 'Cụm nhà A.H', 'TenKhu' => 'Khu A', 'TongToa' => 3, 'TongPhong' => 90, 'TongGiuong' => 360],
                ['MaCum' => 'BC', 'TenCum' => 'Cụm nhà B.C', 'TenKhu' => 'Khu B', 'TongToa' => 6, 'TongPhong' => 180, 'TongGiuong' => 720]
            ];
        }

        if (empty($dotDangKyList)) {
            $dotDangKyList = [
                ['TenDot' => 'Đăng ký nội trú năm học 2026-2027', 'NamHoc' => '2026-2027', 'ThoiGianMoCong' => '2026-06-01 08:00', 'ThoiGianDongCong' => '2026-10-31 23:59', 'SoGioGiuCho' => 72]
            ];
        }

        if (empty($loaiPhongList)) {
            $loaiPhongList = [
                ['MaLoaiPhong' => 'LP4', 'TenLoaiPhong' => 'Phòng tiêu chuẩn 4 giường', 'SoGiuongToiDa' => 4, 'DonGiaLuuTruChuan' => 650000, 'DonGiaDichVuTangThem' => 0],
                ['MaLoaiPhong' => 'LP4ML', 'TenLoaiPhong' => 'Phòng 4 giường có máy lạnh', 'SoGiuongToiDa' => 4, 'DonGiaLuuTruChuan' => 650000, 'DonGiaDichVuTangThem' => 300000],
                ['MaLoaiPhong' => 'LP8', 'TenLoaiPhong' => 'Phòng tiêu chuẩn 8 giường', 'SoGiuongToiDa' => 8, 'DonGiaLuuTruChuan' => 220000, 'DonGiaDichVuTangThem' => 0]
            ];
        }

        if (empty($truongList)) {
            $truongList = [
                ['MaTruong' => 'BK', 'TenTruong' => 'Trường Đại học Bách khoa - ĐHQG-HCM', 'ThuocDHQGHCM' => 1, 'SoNamDaoTaoChuan' => 4.5],
                ['MaTruong' => 'KHTN', 'TenTruong' => 'Trường Đại học Khoa học Tự nhiên - ĐHQG-HCM', 'ThuocDHQGHCM' => 1, 'SoNamDaoTaoChuan' => 4.0],
                ['MaTruong' => 'UIT', 'TenTruong' => 'Trường Đại học Công nghệ Thông tin - ĐHQG-HCM', 'ThuocDHQGHCM' => 1, 'SoNamDaoTaoChuan' => 4.0],
                ['MaTruong' => 'UEL', 'TenTruong' => 'Trường Đại học Kinh tế - Luật - ĐHQG-HCM', 'ThuocDHQGHCM' => 1, 'SoNamDaoTaoChuan' => 4.0]
            ];
        }

        $this->view('admin.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'canBoList' => $canBoList,
            'cumNhaList' => $cumNhaList,
            'dotDangKyList' => $dotDangKyList,
            'loaiPhongList' => $loaiPhongList,
            'truongList' => $truongList,
            'recentTransactions' => $recentTransactions
        ]);
    }

    public function users(): void
    {
        $user = Auth::user();

        // 1. Danh sách tài khoản cán bộ đang quản lý trong hệ thống
        $userList = [];
        try {
            $userList = Database::fetchAll("
                SELECT tk.MaTaiKhoan, tk.TenDangNhap, tk.MaVaiTro, tk.MaCumQuanLy, tk.DangHoatDong, tk.NgayTao,
                       vt.TenVaiTro, nv.MaNhanVien, nv.HoVaTen, nv.ChucVu, nv.SoDienThoai, nv.Email,
                       cn.TenCum, k.TenKhu
                FROM TaiKhoan tk
                JOIN VaiTro vt ON tk.MaVaiTro = vt.MaVaiTro
                LEFT JOIN NhanVien nv ON tk.MaTaiKhoan = nv.MaTaiKhoan
                LEFT JOIN CumNha cn ON tk.MaCumQuanLy = cn.MaCum
                LEFT JOIN KhuKTX k ON cn.MaKhu = k.MaKhu
                WHERE tk.MaVaiTro NOT IN ('SINH_VIEN', 'TRUONG_PHONG')
                ORDER BY tk.MaTaiKhoan ASC
            ");
        } catch (\Throwable $e) {
            $userList = [];
        }

        // 2. Danh sách nhân sự / cán bộ có sẵn để gợi ý & chọn nhanh
        $allStaff = [];
        try {
            $existingStaff = Database::fetchAll("
                SELECT nv.MaNhanVien, nv.HoVaTen, nv.SoDienThoai, nv.Email, nv.ChucVu,
                       tk.MaTaiKhoan, tk.TenDangNhap, tk.MaVaiTro, tk.MaCumQuanLy
                FROM NhanVien nv
                LEFT JOIN TaiKhoan tk ON nv.MaTaiKhoan = tk.MaTaiKhoan
                ORDER BY nv.HoVaTen ASC
            ");

            $allStaffMap = [];
            foreach ($existingStaff as $st) {
                if (!empty($st['HoVaTen'])) {
                    $allStaffMap[$st['HoVaTen']] = $st;
                }
            }

            // Bổ sung các cán bộ trưởng cụm từ danh mục Cụm nhà nếu chưa có
            $cumStaff = Database::fetchAll("SELECT DISTINCT HoTenTruongCum, SoDienThoaiCum, MaCum FROM CumNha WHERE HoTenTruongCum IS NOT NULL AND HoTenTruongCum != ''");
            foreach ($cumStaff as $cs) {
                $name = trim($cs['HoTenTruongCum']);
                if (!isset($allStaffMap[$name])) {
                    $allStaffMap[$name] = [
                        'MaNhanVien' => '',
                        'HoVaTen' => $name,
                        'SoDienThoai' => $cs['SoDienThoaiCum'] ?? '',
                        'Email' => '',
                        'ChucVu' => 'Trưởng Ban quản lý cụm ' . $cs['MaCum'],
                        'MaTaiKhoan' => null,
                        'TenDangNhap' => '',
                        'MaVaiTro' => 'TRUONG_CUM',
                        'MaCumQuanLy' => $cs['MaCum']
                    ];
                }
            }

            // Thêm một số cán bộ nhân sự phổ biến để gợi ý linh hoạt
            $defaultStaffPool = [
                ['HoVaTen' => 'Lê Hoàng Nam', 'SoDienThoai' => '0908123456', 'Email' => 'nam.lh@qlktx.vn', 'ChucVu' => 'Trưởng Ban quản lý cụm BA', 'MaVaiTro' => 'TRUONG_CUM', 'MaCumQuanLy' => 'BA'],
                ['HoVaTen' => 'Ngô Thanh Sơn', 'SoDienThoai' => '0938112233', 'Email' => 'son.nt@qlktx.vn', 'ChucVu' => 'Trưởng Ban quản lý cụm BC', 'MaVaiTro' => 'TRUONG_CUM', 'MaCumQuanLy' => 'BC'],
                ['HoVaTen' => 'Võ Văn Kiệt', 'SoDienThoai' => '0912345678', 'Email' => 'kiet.vv@qlktx.vn', 'ChucVu' => 'Kỹ thuật điện lạnh KTX', 'MaVaiTro' => 'NV_THUONG', 'MaCumQuanLy' => 'AG'],
                ['HoVaTen' => 'Đặng Thị Lan', 'SoDienThoai' => '0987654321', 'Email' => 'lan.dt@qlktx.vn', 'ChucVu' => 'Kế toán quản lý hóa đơn', 'MaVaiTro' => 'NV_QUAN_LY_KTX', 'MaCumQuanLy' => '']
            ];
            foreach ($defaultStaffPool as $dp) {
                if (!isset($allStaffMap[$dp['HoVaTen']])) {
                    $allStaffMap[$dp['HoVaTen']] = [
                        'MaNhanVien' => '',
                        'HoVaTen' => $dp['HoVaTen'],
                        'SoDienThoai' => $dp['SoDienThoai'],
                        'Email' => $dp['Email'],
                        'ChucVu' => $dp['ChucVu'],
                        'MaTaiKhoan' => null,
                        'TenDangNhap' => '',
                        'MaVaiTro' => $dp['MaVaiTro'],
                        'MaCumQuanLy' => $dp['MaCumQuanLy']
                    ];
                }
            }

            $allStaff = array_values($allStaffMap);
        } catch (\Throwable $e) {
            $allStaff = [];
        }

        // 3. Danh mục Vai trò
        $vaitroList = [];
        try {
            $vaitroList = Database::fetchAll("SELECT * FROM VaiTro WHERE MaVaiTro NOT IN ('SINH_VIEN', 'TRUONG_PHONG') ORDER BY MaVaiTro ASC");
        } catch (\Throwable $e) {
            $vaitroList = [];
        }

        // 4. Danh mục Cụm nhà
        $cumNhaList = [];
        try {
            $cumNhaList = Database::fetchAll("
                SELECT c.*, k.TenKhu 
                FROM CumNha c 
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu 
                ORDER BY c.MaKhu ASC, c.MaCum ASC
            ");
        } catch (\Throwable $e) {
            $cumNhaList = [];
        }

        $flashSuccess = Session::flash('success');
        $flashError = Session::flash('error');
        $flashWarning = Session::flash('warning');

        $this->view('admin.users', [
            'user' => $user,
            'userList' => $userList,
            'allStaff' => $allStaff,
            'vaitroList' => $vaitroList,
            'cumNhaList' => $cumNhaList,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'flashWarning' => $flashWarning
        ]);
    }

    public function saveUser(): void
    {
        $request = new Request();
        $maTaiKhoan = (int)$request->input('maTaiKhoan', 0);
        $tenDangNhap = trim((string)$request->input('tenDangNhap', ''));
        $matKhau = trim((string)$request->input('matKhau', ''));
        $hoVaTen = trim((string)$request->input('hoVaTen', ''));
        $soDienThoai = trim((string)$request->input('soDienThoai', ''));
        $email = trim((string)$request->input('email', ''));
        $maVaiTro = trim((string)$request->input('maVaiTro', ''));
        $maCum = trim((string)$request->input('maCumQuanLy', ''));

        // Xử lý phạm vi quản lý: nếu là rỗng hoặc các nhãn tổng thì lưu NULL (hợp lệ với Foreign Key)
        if ($maCum === '' || $maCum === 'ALL' || $maCum === 'KHU_A' || $maCum === 'KHU_B') {
            $maCum = null;
        }

        if (empty($tenDangNhap) || empty($hoVaTen) || empty($maVaiTro)) {
            Session::flash('error', 'Vui lòng điền đầy đủ Tên đăng nhập, Họ và tên và Vai trò của cán bộ!');
            $this->redirect('admin/users');
            return;
        }

        try {
            if ($maTaiKhoan > 0) {
                // ==================== CẬP NHẬT (UPDATE) ====================
                $existing = Database::fetch("SELECT * FROM TaiKhoan WHERE MaTaiKhoan = :id", ['id' => $maTaiKhoan]);
                if (!$existing) {
                    Session::flash('error', 'Không tìm thấy tài khoản cán bộ để cập nhật!');
                    $this->redirect('admin/users');
                    return;
                }

                // Cố định tên đăng nhập khi sửa (không cho phép sửa tên đăng nhập)
                $tenDangNhap = $existing['TenDangNhap'];
                $resetPassword = (bool)$request->input('resetPassword', false);

                // Cập nhật thông tin TaiKhoan (chỉ cho phép reset mật khẩu về Demo@123)
                if ($resetPassword) {
                    $hash = Auth::hashPassword('Demo@123');
                    Database::execute("
                        UPDATE TaiKhoan 
                        SET MatKhauMaHoa = :hash, MaVaiTro = :vaiTro, MaCumQuanLy = :cum
                        WHERE MaTaiKhoan = :id
                    ", [
                        'hash' => $hash,
                        'vaiTro' => $maVaiTro,
                        'cum' => $maCum,
                        'id' => $maTaiKhoan
                    ]);
                } else {
                    Database::execute("
                        UPDATE TaiKhoan 
                        SET MaVaiTro = :vaiTro, MaCumQuanLy = :cum
                        WHERE MaTaiKhoan = :id
                    ", [
                        'vaiTro' => $maVaiTro,
                        'cum' => $maCum,
                        'id' => $maTaiKhoan
                    ]);
                }

                // Cập nhật thông tin NhanVien
                $nv = Database::fetch("SELECT * FROM NhanVien WHERE MaTaiKhoan = :id", ['id' => $maTaiKhoan]);
                if ($nv) {
                    Database::execute("
                        UPDATE NhanVien
                        SET MaNhanVien = :maNV, HoVaTen = :ten, SoDienThoai = :sdt, Email = :email
                        WHERE MaTaiKhoan = :id
                    ", [
                        'maNV' => $tenDangNhap,
                        'ten' => $hoVaTen,
                        'sdt' => $soDienThoai,
                        'email' => $email,
                        'id' => $maTaiKhoan
                    ]);
                } else {
                    Database::execute("
                        INSERT INTO NhanVien (MaNhanVien, MaTaiKhoan, HoVaTen, SoDienThoai, Email)
                        VALUES (:maNV, :id, :ten, :sdt, :email)
                    ", [
                        'maNV' => $tenDangNhap,
                        'id' => $maTaiKhoan,
                        'ten' => $hoVaTen,
                        'sdt' => $soDienThoai,
                        'email' => $email
                    ]);
                }

                if ($resetPassword) {
                    Session::flash('success', "Đã cập nhật thông tin cán bộ '{$hoVaTen}' và đặt lại mật khẩu về mặc định 'Demo@123' thành công!");
                } else {
                    Session::flash('success', "Đã cập nhật thành công thông tin & quyền hạn cho cán bộ: {$hoVaTen} ({$tenDangNhap})!");
                }
            } else {
                // ==================== THÊM MỚI (CREATE) ====================
                if (empty($matKhau)) {
                    Session::flash('error', 'Vui lòng nhập mật khẩu khởi tạo cho tài khoản cán bộ mới!');
                    $this->redirect('admin/users');
                    return;
                }

                // Kiểm tra tên đăng nhập đã tồn tại chưa
                $dup = Database::fetch("SELECT MaTaiKhoan FROM TaiKhoan WHERE TenDangNhap = :user", ['user' => $tenDangNhap]);
                if ($dup) {
                    Session::flash('error', "Tên đăng nhập '{$tenDangNhap}' đã tồn tại trong hệ thống. Vui lòng chọn tên đăng nhập khác!");
                    $this->redirect('admin/users');
                    return;
                }

                $hash = Auth::hashPassword($matKhau);
                Database::execute("
                    INSERT INTO TaiKhoan (TenDangNhap, MatKhauMaHoa, MaVaiTro, MaCumQuanLy, DangHoatDong)
                    VALUES (:user, :hash, :vaiTro, :cum, 1)
                ", [
                    'user' => $tenDangNhap,
                    'hash' => $hash,
                    'vaiTro' => $maVaiTro,
                    'cum' => $maCum
                ]);
                $newId = (int)Database::lastInsertId();

                // Tạo hoặc cập nhật hồ sơ NhanVien
                Database::execute("
                    INSERT INTO NhanVien (MaNhanVien, MaTaiKhoan, HoVaTen, SoDienThoai, Email)
                    VALUES (:maNV, :id, :ten, :sdt, :email)
                    ON DUPLICATE KEY UPDATE MaTaiKhoan = VALUES(MaTaiKhoan), HoVaTen = VALUES(HoVaTen), SoDienThoai = VALUES(SoDienThoai), Email = VALUES(Email)
                ", [
                    'maNV' => $tenDangNhap,
                    'id' => $newId,
                    'ten' => $hoVaTen,
                    'sdt' => $soDienThoai,
                    'email' => $email
                ]);

                Session::flash('success', "Đã thêm mới tài khoản cán bộ '{$hoVaTen}' ({$tenDangNhap}) với vai trò thành công!");
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi xử lý dữ liệu: ' . $e->getMessage());
        }

        $this->redirect('admin/users');
    }

    public function resetPassword(): void
    {
        $request = new Request();
        $maTaiKhoan = (int)$request->input('maTaiKhoan', 0);

        if ($maTaiKhoan <= 0) {
            Session::flash('error', 'Mã tài khoản không hợp lệ!');
            $this->redirect('admin/users');
            return;
        }

        try {
            $user = Database::fetch("
                SELECT tk.*, nv.HoVaTen 
                FROM TaiKhoan tk 
                LEFT JOIN NhanVien nv ON tk.MaTaiKhoan = nv.MaTaiKhoan 
                WHERE tk.MaTaiKhoan = :id
            ", ['id' => $maTaiKhoan]);

            if (!$user) {
                Session::flash('error', 'Không tìm thấy tài khoản để đặt lại mật khẩu!');
                $this->redirect('admin/users');
                return;
            }

            $hash = Auth::hashPassword('Demo@123');
            Database::execute("UPDATE TaiKhoan SET MatKhauMaHoa = :hash WHERE MaTaiKhoan = :id", [
                'hash' => $hash,
                'id' => $maTaiKhoan
            ]);

            $name = $user['HoVaTen'] ?? $user['TenDangNhap'];
            Session::flash('success', "Đã đặt lại mật khẩu cho cán bộ '{$name}' ({$user['TenDangNhap']}) về mặc định: Demo@123 thành công!");
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi khi đặt lại mật khẩu: ' . $e->getMessage());
        }

        $this->redirect('admin/users');
    }

    public function toggleUserStatus(): void
    {
        $request = new Request();
        $maTaiKhoan = (int)$request->input('maTaiKhoan', 0);
        $currentUser = Auth::user();

        if ($maTaiKhoan <= 0) {
            Session::flash('error', 'Mã tài khoản không hợp lệ!');
            $this->redirect('admin/users');
            return;
        }

        if ($currentUser && (int)($currentUser['MaTaiKhoan'] ?? 0) === $maTaiKhoan) {
            Session::flash('error', 'Bạn không thể tự khóa tài khoản của chính mình đang đăng nhập!');
            $this->redirect('admin/users');
            return;
        }

        try {
            $user = Database::fetch("SELECT * FROM TaiKhoan WHERE MaTaiKhoan = :id", ['id' => $maTaiKhoan]);
            if (!$user) {
                Session::flash('error', 'Không tìm thấy tài khoản cần thao tác!');
                $this->redirect('admin/users');
                return;
            }

            $newStatus = ($user['DangHoatDong'] == 1) ? 0 : 1;
            Database::execute("UPDATE TaiKhoan SET DangHoatDong = :st WHERE MaTaiKhoan = :id", [
                'st' => $newStatus,
                'id' => $maTaiKhoan
            ]);

            $stText = $newStatus == 1 ? 'MỞ KHÓA' : 'TẠM KHÓA';
            Session::flash('success', "Đã {$stText} tài khoản cán bộ '{$user['TenDangNhap']}' thành công!");
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi cập nhật trạng thái: ' . $e->getMessage());
        }

        $this->redirect('admin/users');
    }

    public function deleteUser(): void
    {
        $request = new Request();
        $maTaiKhoan = (int)$request->input('maTaiKhoan', 0);
        $currentUser = Auth::user();

        if ($maTaiKhoan <= 0) {
            Session::flash('error', 'Mã tài khoản không hợp lệ!');
            $this->redirect('admin/users');
            return;
        }

        if ($currentUser && (int)($currentUser['MaTaiKhoan'] ?? 0) === $maTaiKhoan) {
            Session::flash('error', 'Bạn không thể tự xóa tài khoản của chính bạn!');
            $this->redirect('admin/users');
            return;
        }

        try {
            $user = Database::fetch("SELECT * FROM TaiKhoan WHERE MaTaiKhoan = :id", ['id' => $maTaiKhoan]);
            if (!$user) {
                Session::flash('error', 'Không tìm thấy tài khoản cần xóa!');
                $this->redirect('admin/users');
                return;
            }

            // Kiểm tra ràng buộc bảo toàn dữ liệu (Theo tài liệu phân quyền Admin KTX):
            // 1. Đã duyệt hồ sơ đăng ký?
            $approvalCheck = Database::fetch("SELECT COUNT(*) AS total FROM HoSoDangKy WHERE NguoiDuyet = :id", ['id' => $maTaiKhoan]);
            $approvalCount = (int)($approvalCheck['total'] ?? 0);

            // 2. Đã được gán phiếu bảo trì kỹ thuật?
            $techCheck = Database::fetch("SELECT COUNT(*) AS total FROM PhieuBaoHong WHERE NhanVienKyThuat = :id", ['id' => $maTaiKhoan]);
            $techCount = (int)($techCheck['total'] ?? 0);

            if ($approvalCount > 0 || $techCount > 0) {
                // Quy tắc bảo toàn: Tự động chuyển sang khóa (DangHoatDong = 0)
                Database::execute("UPDATE TaiKhoan SET DangHoatDong = 0 WHERE MaTaiKhoan = :id", ['id' => $maTaiKhoan]);
                Session::flash('warning', "Tài khoản '{$user['TenDangNhap']}' đã tham gia duyệt {$approvalCount} hồ sơ và xử lý {$techCount} phiếu bảo trì. Theo Quy tắc bảo toàn dữ liệu (Mục 1 & 3 PDF), hệ thống không xóa vật lý mà đã tự động chuyển tài khoản sang trạng thái KHÓA!");
            } else {
                // Được phép xóa an toàn
                Database::execute("DELETE FROM NhanVien WHERE MaTaiKhoan = :id", ['id' => $maTaiKhoan]);
                Database::execute("DELETE FROM TaiKhoan WHERE MaTaiKhoan = :id", ['id' => $maTaiKhoan]);
                Session::flash('success', "Đã xóa hoàn toàn tài khoản cán bộ '{$user['TenDangNhap']}' khỏi hệ thống!");
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi khi xóa tài khoản: ' . $e->getMessage());
        }

        $this->redirect('admin/users');
    }

    public function rooms(): void
    {
        $this->view('admin.rooms', ['user' => Auth::user()]);
    }

    public function periods(): void
    {
        $this->view('admin.periods', ['user' => Auth::user()]);
    }

    public function roomTypes(): void
    {
        $this->view('admin.room_types', ['user' => Auth::user()]);
    }

    public function universities(): void
    {
        $this->view('admin.universities', ['user' => Auth::user()]);
    }
}
