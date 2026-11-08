<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AutoReleaseService;
use App\Services\MailService;

/**
 * Controller: Quản lý Hồ sơ Đăng ký nội trú & Xét duyệt 72h
 * View: views/staff/applications.php
 * Phân quyền 4 cấp: Ban Giám đốc (ALL) / Trưởng Khu (KHU) / Trưởng Cụm (CUM) / Trưởng Tòa (TOA)
 * Chặn 100%: Kế toán, Kỹ thuật điện nước, Nhân viên thường, Sinh viên
 */
class ApplicationController extends Controller
{
    /**
     * Xác định phân quyền cán bộ quản lý Xét duyệt 72h (RBAC Scope Matrix)
     */
    public function getStaffScope(?array $user = null): array
    {
        if (!$user) {
            $user = Auth::user() ?? [];
        }

        $role = $user['MaVaiTro'] ?? $user['VaiTro'] ?? '';
        $cumQuanLy = trim((string)($user['MaCumQuanLy'] ?? ''));
        $username = strtoupper($user['TenDangNhap'] ?? '');
        $chucVu = mb_strtolower($user['ChucVu'] ?? $user['profile']['ChucVu'] ?? '');

        // 1. CHẶN TUYỆT ĐỐI NHÂN VIÊN KHÔNG PHẢI CÁN BỘ QUẢN LÝ
        $isNonManager = in_array($role, ['KE_TOAN', 'NV_THUONG', 'SINH_VIEN', 'TRUONG_PHONG'], true)
            || str_starts_with($username, 'NV_KT')
            || str_starts_with($username, 'KT')
            || str_contains($chucVu, 'kế toán')
            || str_contains($chucVu, 'kỹ thuật')
            || str_contains($chucVu, 'điện nước');

        $isManager = in_array($role, ['ADMIN', 'QuanTriVien', 'NV_QUAN_LY_KTX', 'TRUONG_KHU', 'TRUONG_CUM', 'TRUONG_TOA'], true);

        if ($isNonManager || !$isManager) {
            return [
                'isAllowed' => false,
                'level' => 'DENIED',
                'levelNumber' => -1,
                'role' => $role,
                'title' => 'Không có quyền truy cập',
                'badgeClass' => 'badge-danger',
                'maKhu' => null,
                'maCum' => null,
                'maToa' => null,
                'canApprove' => false,
                'canReject' => false,
                'canCancel' => false,
                'canScanRelease' => false,
                'availableToas' => [],
                'availableCums' => [],
                'availableKhus' => [],
                'message' => 'Từ chối quyền truy cập: Phân hệ Xét duyệt Đăng ký & Giữ chỗ 72h chỉ dành riêng cho Cán bộ Quản lý (Trưởng Tòa, Trưởng Cụm, Trưởng Khu, Ban Giám đốc KTX). Nhân viên Kế toán và Kỹ thuật không có quyền thao tác!'
            ];
        }

        // 2. CẤP 4: Ban quản lý KTX)
        if ($role === 'ADMIN' || $role === 'QuanTriVien' || $role === 'NV_QUAN_LY_KTX') {
            $khus = Database::fetchAll("SELECT MaKhu, TenKhu FROM KhuKTX ORDER BY MaKhu ASC");
            $cums = Database::fetchAll("SELECT MaCum, TenCum, MaKhu FROM CumNha ORDER BY MaKhu ASC, MaCum ASC");
            $toas = Database::fetchAll("
                SELECT t.MaToaNha, t.TenToaNha, t.TongSoTang, t.GioiTinhQuyDinh, t.TrangThaiToaNha, c.MaCum, c.TenCum, c.MaKhu, k.TenKhu
                FROM ToaNha t
                JOIN CumNha c ON t.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                ORDER BY c.MaKhu ASC, c.MaCum ASC, t.MaToaNha ASC
            ");

            return [
                'isAllowed' => true,
                'level' => 'ALL',
                'levelNumber' => 4,
                'role' => $role,
                'title' => 'Ban quản lý KTX)',
                'badgeClass' => 'badge-primary',
                'maKhu' => null,
                'maCum' => null,
                'maToa' => null,
                'canApprove' => true,
                'canReject' => true,
                'canCancel' => true,
                'canScanRelease' => true,
                'availableToas' => $toas,
                'availableCums' => $cums,
                'availableKhus' => $khus
            ];
        }

        // 3. CẤP 3: Trưởng Khu (TK001 -> Khu A, TK002 -> Khu B)
        if ($role === 'TRUONG_KHU' || $cumQuanLy === 'A' || $cumQuanLy === 'B') {
            $maKhu = in_array($cumQuanLy, ['A', 'B']) ? $cumQuanLy : 'A';
            $khus = Database::fetchAll("SELECT MaKhu, TenKhu FROM KhuKTX WHERE MaKhu = :k", ['k' => $maKhu]);
            $cums = Database::fetchAll("SELECT MaCum, TenCum, MaKhu FROM CumNha WHERE MaKhu = :k ORDER BY MaCum ASC", ['k' => $maKhu]);
            $toas = Database::fetchAll("
                SELECT t.MaToaNha, t.TenToaNha, t.TongSoTang, t.GioiTinhQuyDinh, t.TrangThaiToaNha, c.MaCum, c.TenCum, c.MaKhu, k.TenKhu
                FROM ToaNha t
                JOIN CumNha c ON t.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                WHERE c.MaKhu = :k
                ORDER BY c.MaCum ASC, t.MaToaNha ASC
            ", ['k' => $maKhu]);

            return [
                'isAllowed' => true,
                'level' => 'KHU',
                'levelNumber' => 3,
                'role' => $role,
                'title' => "Trưởng BQL Khu {$maKhu}",
                'badgeClass' => 'badge-info',
                'maKhu' => $maKhu,
                'maCum' => null,
                'maToa' => null,
                'canApprove' => true,
                'canReject' => true,
                'canCancel' => true,
                'canScanRelease' => true,
                'availableToas' => $toas,
                'availableCums' => $cums,
                'availableKhus' => $khus
            ];
        }

        // 4. CẤP 2: Trưởng Cụm (TC001 -> CUMAG...)
        if ($role === 'TRUONG_CUM' || str_starts_with($cumQuanLy, 'CUM') || in_array($cumQuanLy, ['AG', 'AH', 'BA', 'BB'])) {
            $maCum = !empty($cumQuanLy) ? $cumQuanLy : 'AG';
            $cumRow = Database::fetch("SELECT c.MaCum, c.TenCum, c.MaKhu, k.TenKhu FROM CumNha c JOIN KhuKTX k ON c.MaKhu = k.MaKhu WHERE c.MaCum = :c", ['c' => $maCum]);
            if (!$cumRow) {
                $cumRow = Database::fetch("SELECT c.MaCum, c.TenCum, c.MaKhu, k.TenKhu FROM CumNha c JOIN KhuKTX k ON c.MaKhu = k.MaKhu LIMIT 1");
                $maCum = $cumRow['MaCum'] ?? 'AG';
            }
            $maKhu = $cumRow['MaKhu'] ?? 'A';
            $tenCum = $cumRow['TenCum'] ?? $maCum;

            $cleanCum = preg_replace('/^(Ban Quản lý\s*|Cụm\s*nhà\s*)+/ui', '', $tenCum);
            $cumTitle = "Trưởng BQL Cụm " . (!empty($cleanCum) ? $cleanCum : $tenCum);

            $toas = Database::fetchAll("
                SELECT t.MaToaNha, t.TenToaNha, t.TongSoTang, t.GioiTinhQuyDinh, t.TrangThaiToaNha, c.MaCum, c.TenCum, c.MaKhu, k.TenKhu
                FROM ToaNha t
                JOIN CumNha c ON t.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                WHERE t.MaCum = :c
                ORDER BY t.MaToaNha ASC
            ", ['c' => $maCum]);

            return [
                'isAllowed' => true,
                'level' => 'CUM',
                'levelNumber' => 2,
                'role' => $role,
                'title' => $cumTitle,
                'badgeClass' => 'badge-warning',
                'maKhu' => $maKhu,
                'maCum' => $maCum,
                'maToa' => null,
                'canApprove' => true,
                'canReject' => true,
                'canCancel' => true,
                'canScanRelease' => true,
                'availableToas' => $toas,
                'availableCums' => [$cumRow],
                'availableKhus' => [['MaKhu' => $maKhu, 'TenKhu' => $cumRow['TenKhu'] ?? $maKhu]]
            ];
        }

        // 5. CẤP 1: Trưởng Tòa (TT001 -> A01...)
        $maToa = !empty($cumQuanLy) ? $cumQuanLy : 'A01';
        $toaRow = Database::fetch("
            SELECT t.MaToaNha, t.TenToaNha, t.TongSoTang, t.GioiTinhQuyDinh, t.TrangThaiToaNha, c.MaCum, c.TenCum, c.MaKhu, k.TenKhu
            FROM ToaNha t
            JOIN CumNha c ON t.MaCum = c.MaCum
            JOIN KhuKTX k ON c.MaKhu = k.MaKhu
            WHERE t.MaToaNha = :t
        ", ['t' => $maToa]);

        if (!$toaRow) {
            $toaRow = Database::fetch("
                SELECT t.MaToaNha, t.TenToaNha, t.TongSoTang, t.GioiTinhQuyDinh, t.TrangThaiToaNha, c.MaCum, c.TenCum, c.MaKhu, k.TenKhu
                FROM ToaNha t
                JOIN CumNha c ON t.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                ORDER BY t.MaToaNha ASC LIMIT 1
            ");
            $maToa = $toaRow['MaToaNha'] ?? 'A01';
        }

        $rawToaName = $toaRow['TenToaNha'] ?? $maToa;
        $cleanToaName = preg_replace('/^(tòa\s*|toà\s*)+/ui', '', trim($rawToaName));
        $displayToa = !empty($cleanToaName) ? $cleanToaName : $rawToaName;

        return [
            'isAllowed' => true,
            'level' => 'TOA',
            'levelNumber' => 1,
            'role' => $role,
            'title' => "Trưởng tòa " . $displayToa,
            'badgeClass' => 'badge-success',
            'maKhu' => $toaRow['MaKhu'] ?? 'A',
            'maCum' => $toaRow['MaCum'] ?? 'AG',
            'maToa' => $maToa,
            'canApprove' => true,
            'canReject' => true,
            'canCancel' => true,
            'canScanRelease' => false,
            'availableToas' => [$toaRow],
            'availableCums' => [['MaCum' => $toaRow['MaCum'], 'TenCum' => $toaRow['TenCum'] ?? $toaRow['MaCum'], 'MaKhu' => $toaRow['MaKhu']]],
            'availableKhus' => [['MaKhu' => $toaRow['MaKhu'], 'TenKhu' => $toaRow['TenKhu'] ?? $toaRow['MaKhu']]]
        ];
    }

    /**
     * Danh sách hồ sơ đăng ký & duyệt chỗ 72h (Hỗ trợ Cascading Filters & Quick Search)
     */
    public function applications(): void
    {
        $user = Auth::user() ?? [];
        $scope = $this->getStaffScope($user);

        // NẾU KHÔNG CÓ QUYỀN QUẢN LÝ: HIỂN THỊ MÀN HÌNH CHẶN 100%
        if (!$scope['isAllowed']) {
            $this->view('staff.applications', [
                'user' => $user,
                'isAllowed' => false,
                'scope' => $scope,
                'roleName' => $user['VaiTro'] ?? $user['MaVaiTro'] ?? 'Nhân viên',
                'pendingApplications' => [],
                'active72hApplications' => [],
                'historyApplications' => [],
                'mienGiamList' => [],
                'dotDangKyList' => [],
                'stats' => ['pending' => 0, 'active' => 0, 'expired' => 0, 'completed' => 0, 'miengiam' => 0],
                'filters' => ['khu' => '', 'cum' => '', 'toa' => '', 'status' => '', 'search' => '', 'tab' => 'cho_duyet'],
                'availableKhus' => [],
                'availableCums' => [],
                'availableToas' => [],
                'flashSuccess' => Session::flash('success'),
                'flashError' => Session::flash('error'),
                'flashWarning' => Session::flash('warning')
            ]);
            return;
        }

        $request = new Request();
        $filterKhu = trim((string)$request->input('khu', ''));
        $filterCum = trim((string)$request->input('cum', ''));
        $filterToa = trim((string)$request->input('toa', ''));
        $filterStatus = trim((string)$request->input('status', ''));
        $search = trim((string)$request->input('search', ''));
        $activeTab = trim((string)$request->input('tab', 'cho_duyet'));

        // Áp đặt phạm vi bảo mật theo cấp bậc cán bộ
        if ($scope['level'] === 'KHU') {
            $filterKhu = $scope['maKhu'];
        } elseif ($scope['level'] === 'CUM') {
            $filterKhu = $scope['maKhu'];
            $filterCum = $scope['maCum'];
        } elseif ($scope['level'] === 'TOA') {
            $filterKhu = $scope['maKhu'];
            $filterCum = $scope['maCum'];
            $filterToa = $scope['maToa'];
        }

        // TIẾN TRÌNH TỰ ĐỘNG HỦY GIỮ CHỖ CÁC HỒ SƠ QUÁ HẠN 72H (BACKGROUND AUTO-RELEASE)
        $autoReleaseResult = AutoReleaseService::scanAndReleaseExpiredBeds();
        $autoReleasedCount = (int)($autoReleaseResult['total_released'] ?? 0);
        $autoReleasedMessage = '';
        if ($autoReleasedCount > 0) {
            $autoReleasedMessage = "Hệ thống đã tự động hủy giữ chỗ và giải phóng {$autoReleasedCount} vị trí giường quá hạn thanh toán 72 giờ về lại quỹ phòng trống!";
        }

        // Lọc danh sách cụm và tòa cascading tương thích
        $availableKhus = $scope['availableKhus'] ?? [];
        $availableCums = $scope['availableCums'] ?? [];
        $availableToas = $scope['availableToas'] ?? [];

        if (!empty($filterKhu)) {
            $availableCums = array_values(array_filter($availableCums, function($c) use ($filterKhu) {
                return ($c['MaKhu'] ?? '') === $filterKhu;
            }));
            $availableToas = array_values(array_filter($availableToas, function($t) use ($filterKhu) {
                return ($t['MaKhu'] ?? '') === $filterKhu;
            }));
        }

        if (!empty($filterCum)) {
            $availableToas = array_values(array_filter($availableToas, function($t) use ($filterCum) {
                return ($t['MaCum'] ?? '') === $filterCum;
            }));
        }

        // Xây dựng điều kiện lọc chung
        $scopeConditions = [];
        $queryParams = [];

        if (!empty($filterToa)) {
            $scopeConditions[] = "p.MaToaNha = :f_toa";
            $queryParams['f_toa'] = $filterToa;
        } elseif (!empty($filterCum)) {
            $scopeConditions[] = "tn.MaCum = :f_cum";
            $queryParams['f_cum'] = $filterCum;
        } elseif (!empty($filterKhu)) {
            $scopeConditions[] = "c.MaKhu = :f_khu";
            $queryParams['f_khu'] = $filterKhu;
        }

        $searchSql = "";
        if (!empty($search)) {
            $searchSql = " AND (hs.MaHoSo LIKE :kw1 OR sv.HoVaTen LIKE :kw2 OR sv.MSSVTaiTruong LIKE :kw3 OR sv.SoDienThoai LIKE :kw4 OR sv.SoCCCD LIKE :kw5 OR p.SoPhong LIKE :kw6)";
            $kw = "%{$search}%";
            $queryParams['kw1'] = $kw;
            $queryParams['kw2'] = $kw;
            $queryParams['kw3'] = $kw;
            $queryParams['kw4'] = $kw;
            $queryParams['kw5'] = $kw;
            $queryParams['kw6'] = $kw;
        }

        $baseScopeWhere = !empty($scopeConditions) ? " AND " . implode(" AND ", $scopeConditions) : "";

        $pendingApplications = [];
        $active72hApplications = [];
        $historyApplications = [];
        $mienGiamList = [];

        try {
            // 1. Danh sách hồ sơ Chờ duyệt mới (Cần duyệt trong 24h)
            $sqlPending = "
                SELECT hs.*, sv.HoVaTen, sv.GioiTinh, sv.SoCCCD, sv.SoDienThoai, sv.MSSVTaiTruong,
                       t.TenTruong, p.SoPhong, tn.TenToaNha, tn.MaToaNha, tn.MaCum, c.TenCum, c.MaKhu, k.TenKhu,
                       g.KyHieuGiuong, lp.TenLoaiPhong,
                       (lp.DonGiaLuuTruChuan + lp.DonGiaDichVuTangThem) AS DonGiaThang
                FROM HoSoDangKy hs
                JOIN SinhVien sv ON hs.MaSinhVien = sv.MaSinhVien
                LEFT JOIN TruongDaiHoc t ON sv.MaTruong = t.MaTruong
                JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                JOIN Phong p ON g.MaPhong = p.MaPhong
                JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                JOIN CumNha c ON tn.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                WHERE hs.TrangThaiHoSo = 'Chờ duyệt' {$baseScopeWhere} {$searchSql}
                ORDER BY hs.ThoiGianNop ASC
            ";
            $pendingApplications = Database::fetchAll($sqlPending, $queryParams);

            // 2. Danh sách hồ sơ đang trong hạn 72h (Chờ thanh toán)
            $sqlActive = "
                SELECT hs.*, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, p.SoPhong, tn.TenToaNha, tn.MaToaNha, tn.MaCum, c.TenCum, c.MaKhu, k.TenKhu,
                       g.KyHieuGiuong, lp.TenLoaiPhong,
                       hd.SoTienPhaiDong, hd.TrangThai AS TrangThaiHoaDon,
                       TIMESTAMPDIFF(MINUTE, NOW(), hs.HanChotThanhToan) AS SoPhutConLai,
                       TIMESTAMPDIFF(HOUR, NOW(), hs.HanChotThanhToan) AS SoGioConLai
                FROM HoSoDangKy hs
                JOIN SinhVien sv ON hs.MaSinhVien = sv.MaSinhVien
                JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                JOIN Phong p ON g.MaPhong = p.MaPhong
                JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                JOIN CumNha c ON tn.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN HoaDon hd ON hd.MaHoaDon = CONCAT('HD-', hs.MaHoSo)
                WHERE hs.TrangThaiHoSo = 'Chờ thanh toán' {$baseScopeWhere} {$searchSql}
                ORDER BY hs.HanChotThanhToan ASC
            ";
            $active72hApplications = Database::fetchAll($sqlActive, $queryParams);

            // 3. Lịch sử hồ sơ đã xử lý (Đã duyệt, Đã thanh toán, Quá hạn 72h, Đã từ chối)
            $historyStatusSql = " AND hs.TrangThaiHoSo IN ('Đã duyệt', 'Đã từ chối', 'Từ chối', 'Hết hạn - Đã hủy chỗ', 'Hết hạn thanh toán', 'Đã hủy')";
            if (!empty($filterStatus) && $filterStatus !== 'ALL') {
                $historyStatusSql = " AND hs.TrangThaiHoSo = :f_stat";
                $queryParams['f_stat'] = $filterStatus;
            }

            $sqlHistory = "
                SELECT hs.*, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, p.SoPhong, tn.TenToaNha, tn.MaToaNha, tn.MaCum, c.TenCum, c.MaKhu, k.TenKhu,
                       g.KyHieuGiuong, lp.TenLoaiPhong,
                       hd.SoTienPhaiDong, hd.TrangThai AS TrangThaiHoaDon
                FROM HoSoDangKy hs
                JOIN SinhVien sv ON hs.MaSinhVien = sv.MaSinhVien
                JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                JOIN Phong p ON g.MaPhong = p.MaPhong
                JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                JOIN CumNha c ON tn.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN HoaDon hd ON hd.MaHoaDon = CONCAT('HD-', hs.MaHoSo)
                WHERE 1=1 {$historyStatusSql} {$baseScopeWhere} {$searchSql}
                ORDER BY COALESCE(hs.ThoiGianDuyet, hs.ThoiGianNop) DESC
                LIMIT 100
            ";
            $historyApplications = Database::fetchAll($sqlHistory, $queryParams);

            // 4. Danh sách hồ sơ thẩm định Miễn giảm nội trú
            $mgScopeWhere = "";
            $mgParams = [];
            if (!empty($filterToa)) {
                $mgScopeWhere .= " AND p.MaToaNha = :mg_toa";
                $mgParams['mg_toa'] = $filterToa;
            } elseif (!empty($filterCum)) {
                $mgScopeWhere .= " AND tn.MaCum = :mg_cum";
                $mgParams['mg_cum'] = $filterCum;
            } elseif (!empty($filterKhu)) {
                $mgScopeWhere .= " AND c.MaKhu = :mg_khu";
                $mgParams['mg_khu'] = $filterKhu;
            }
            if (!empty($search)) {
                $mgScopeWhere .= " AND (mg.MaSinhVien LIKE :mg_kw1 OR sv.HoVaTen LIKE :mg_kw2 OR sv.MSSVTaiTruong LIKE :mg_kw3)";
                $mgKw = "%{$search}%";
                $mgParams['mg_kw1'] = $mgKw;
                $mgParams['mg_kw2'] = $mgKw;
                $mgParams['mg_kw3'] = $mgKw;
            }

            $sqlMienGiam = "
                SELECT mg.*, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, tdh.TenTruong,
                       p.SoPhong, tn.TenToaNha, g.KyHieuGiuong
                FROM HoSoMienGiam mg
                JOIN SinhVien sv ON mg.MaSinhVien = sv.MaSinhVien
                LEFT JOIN TruongDaiHoc tdh ON sv.MaTruong = tdh.MaTruong
                LEFT JOIN HopDongNoiTru hd ON mg.MaHopDong = hd.MaHopDong
                LEFT JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
                LEFT JOIN Phong p ON g.MaPhong = p.MaPhong
                LEFT JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                LEFT JOIN CumNha c ON tn.MaCum = c.MaCum
                WHERE 1=1 {$mgScopeWhere}
                ORDER BY mg.MaMienGiam DESC
            ";
            $mienGiamList = Database::fetchAll($sqlMienGiam, $mgParams);

            // 5. Thống kê KPI Nhanh
            $statParams = [];
            $statScopeWhere = "";
            if (!empty($filterToa)) {
                $statScopeWhere .= " AND p.MaToaNha = :st_toa";
                $statParams['st_toa'] = $filterToa;
            } elseif (!empty($filterCum)) {
                $statScopeWhere .= " AND tn.MaCum = :st_cum";
                $statParams['st_cum'] = $filterCum;
            } elseif (!empty($filterKhu)) {
                $statScopeWhere .= " AND c.MaKhu = :st_khu";
                $statParams['st_khu'] = $filterKhu;
            }

            $statRow = Database::fetch("
                SELECT 
                    SUM(CASE WHEN hs.TrangThaiHoSo = 'Chờ duyệt' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN hs.TrangThaiHoSo = 'Chờ thanh toán' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN hs.TrangThaiHoSo IN ('Hết hạn - Đã hủy chỗ', 'Hết hạn thanh toán') THEN 1 ELSE 0 END) AS expired,
                    SUM(CASE WHEN hs.TrangThaiHoSo = 'Đã duyệt' THEN 1 ELSE 0 END) AS completed
                FROM HoSoDangKy hs
                JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                JOIN Phong p ON g.MaPhong = p.MaPhong
                JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                JOIN CumNha c ON tn.MaCum = c.MaCum
                WHERE 1=1 {$statScopeWhere}
            ", $statParams);

            $stats = [
                'pending' => (int)($statRow['pending'] ?? 0),
                'active' => (int)($statRow['active'] ?? 0),
                'expired' => (int)($statRow['expired'] ?? 0),
                'completed' => (int)($statRow['completed'] ?? 0),
                'miengiam' => count($mienGiamList)
            ];

        } catch (\Throwable $e) {
            $stats = ['pending' => 0, 'active' => 0, 'expired' => 0, 'completed' => 0, 'miengiam' => 0];
        }

        // 6. Danh sách các Đợt Đăng Ký Nội Trú (Dành cho Quản lý KTX theo dõi & điều hành)
        $dotDangKyList = [];
        try {
            $sqlDot = "
                SELECT d.*,
                       COUNT(hs.MaHoSo) AS TongHoSo,
                       SUM(CASE WHEN hs.TrangThaiHoSo = 'Đã duyệt' THEN 1 ELSE 0 END) AS SoHoSoDaDuyet,
                       SUM(CASE WHEN hs.TrangThaiHoSo = 'Chờ duyệt' THEN 1 ELSE 0 END) AS SoHoSoChoDuyet,
                       SUM(CASE WHEN hs.TrangThaiHoSo = 'Chờ thanh toán' THEN 1 ELSE 0 END) AS SoHoSoChoThanhToan,
                       CASE
                           WHEN NOW() < d.ThoiGianMoCong THEN 'Sắp mở'
                           WHEN NOW() BETWEEN d.ThoiGianMoCong AND d.ThoiGianDongCong THEN 'Đang mở cổng'
                           ELSE 'Đã đóng cổng'
                       END AS TrangThaiDot
                FROM dotdangky d
                LEFT JOIN hosodangky hs ON d.MaDot = hs.MaDot
                GROUP BY d.MaDot
                ORDER BY d.MaDot DESC
            ";
            $dotDangKyList = Database::fetchAll($sqlDot);
        } catch (\Throwable $e) {
            $dotDangKyList = [];
        }

        $this->view('staff.applications', [
            'user' => $user,
            'isAllowed' => true,
            'scope' => $scope,
            'roleName' => $user['VaiTro'] ?? $user['MaVaiTro'] ?? 'Cán bộ',
            'pendingApplications' => $pendingApplications,
            'active72hApplications' => $active72hApplications,
            'historyApplications' => $historyApplications,
            'mienGiamList' => $mienGiamList,
            'dotDangKyList' => $dotDangKyList,
            'stats' => $stats,
            'filters' => [
                'khu' => $filterKhu,
                'cum' => $filterCum,
                'toa' => $filterToa,
                'status' => $filterStatus,
                'search' => $search,
                'tab' => $activeTab
            ],
            'availableKhus' => $availableKhus,
            'availableCums' => $availableCums,
            'availableToas' => $availableToas,
            'autoReleasedCount' => $autoReleasedCount ?? 0,
            'autoReleasedMessage' => $autoReleasedMessage ?? '',
            'flashSuccess' => Session::flash('success'),
            'flashError' => Session::flash('error'),
            'flashWarning' => Session::flash('warning')
        ]);
    }

    /**
     * Phê duyệt hồ sơ đăng ký và cấp quyền giữ chỗ 72h
     */
    public function approveApplication(): void
    {
        $scope = $this->getStaffScope();
        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền thực hiện thao tác này.');
            $this->redirect('staff/applications');
            return;
        }

        $request = new Request();
        $maHoSo = trim((string)$request->input('maHoSo', ''));
        $userId = Auth::id() ?? 3;

        if (empty($maHoSo)) {
            Session::flash('error', 'Mã hồ sơ không hợp lệ.');
            $this->redirect('staff/applications');
            return;
        }

        $result = AutoReleaseService::approveApplication($maHoSo, $userId, 72);

        if ($result['success']) {
            $hanChotFmt = date('d/m/Y H:i', strtotime($result['hanChotThanhToan']));
            // Gửi email thông báo cho sinh viên về hạn thanh toán giữ chỗ 72h
            try {
                MailService::notifyStudentApplicationApproved($maHoSo);
            } catch (\Throwable $e) {
                error_log('Lỗi gửi email cho sinh viên khi duyệt hồ sơ: ' . $e->getMessage());
            }
            Session::flash('success', "Đã phê duyệt giữ chỗ 72h cho hồ sơ {$maHoSo}. Hạn chót thanh toán: {$hanChotFmt}. Hóa đơn lệ phí đã được tự động khởi tạo và thông báo đến email sinh viên.");
        } else {
            Session::flash('error', $result['message'] ?? 'Phê duyệt hồ sơ thất bại.');
        }

        $this->redirect('staff/applications?tab=dang_giu_cho');
    }

    /**
     * Từ chối hồ sơ đăng ký nội trú & giải phóng giường
     */
    public function rejectApplication(): void
    {
        $scope = $this->getStaffScope();
        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền thực hiện thao tác này.');
            $this->redirect('staff/applications');
            return;
        }

        $request = new Request();
        $maHoSo = trim((string)$request->input('maHoSo', ''));
        $lyDo = trim((string)$request->input('lyDo', ''));
        $userId = Auth::id() ?? 3;

        if (empty($maHoSo)) {
            Session::flash('error', 'Mã hồ sơ không hợp lệ.');
            $this->redirect('staff/applications');
            return;
        }

        $result = AutoReleaseService::rejectApplication($maHoSo, $userId, $lyDo);

        if ($result['success']) {
            // Gửi email thông báo cho sinh viên về lý do từ chối
            try {
                MailService::notifyStudentApplicationRejected($maHoSo, $lyDo);
            } catch (\Throwable $e) {
                error_log('Lỗi gửi email cho sinh viên khi từ chối hồ sơ: ' . $e->getMessage());
            }
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message'] ?? 'Từ chối hồ sơ thất bại.');
        }

        $this->redirect('staff/applications');
    }

    /**
     * Cán bộ hoặc client kích hoạt quét thu hồi giường quá hạn 72h tức thời (Background / Realtime Auto-Release)
     */
    public function scanRelease72h(): void
    {
        $scope = $this->getStaffScope();
        $request = new Request();
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || ($request->input('ajax') == '1');

        if (!$scope['isAllowed']) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Từ chối quyền: Bạn không có quyền thực hiện thao tác này.'], 403);
                return;
            }
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền thực hiện thao tác này.');
            $this->redirect('staff/applications');
            return;
        }

        $result = AutoReleaseService::scanAndReleaseExpiredBeds();
        $totalReleased = (int)($result['total_released'] ?? 0);
        $totalScanned = (int)($result['total_scanned'] ?? 0);

        if ($isAjax) {
            $this->json([
                'success' => true,
                'total_scanned' => $totalScanned,
                'total_released' => $totalReleased,
                'released_beds' => $result['released_beds'] ?? [],
                'released_records' => $result['released_records'] ?? [],
                'message' => $totalReleased > 0
                    ? "Đã tự động hủy giữ chỗ và giải phóng {$totalReleased} vị trí giường quá hạn 72h."
                    : "Hệ thống đã kiểm tra: Hiện không có hồ sơ nào quá hạn 72h."
            ]);
            return;
        }

        if ($totalReleased > 0) {
            Session::flash('success', "Đã hoàn tất quét đối soát 72h: Tìm thấy {$totalScanned} hồ sơ và tự động giải phóng {$totalReleased} vị trí giường quá hạn thanh toán về lại quỹ phòng trống!");
        } else {
            Session::flash('success', "Đã quét đối soát 72h hệ thống: Hiện không có hồ sơ nào quá hạn thanh toán cần thu hồi.");
        }

        $this->redirect('staff/applications?tab=dang_giu_cho');
    }

    /**
     * Phê duyệt hồ sơ miễn giảm nội trú
     */
    public function approveMienGiam(): void
    {
        $scope = $this->getStaffScope();
        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền thực hiện thao tác này.');
            $this->redirect('staff/applications');
            return;
        }

        $request = new Request();
        $maMienGiam = (int)$request->input('maMienGiam', 0);

        if ($maMienGiam <= 0) {
            Session::flash('error', 'Mã hồ sơ miễn giảm không hợp lệ.');
            $this->redirect('staff/applications?tab=mien_giam');
            return;
        }

        try {
            Database::execute("
                UPDATE HoSoMienGiam 
                SET TrangThaiDuyet = 'Đã duyệt' 
                WHERE MaMienGiam = :id
            ", ['id' => $maMienGiam]);

            Session::flash('success', "Đã phê duyệt hồ sơ miễn giảm nội trú #{$maMienGiam} thành công.");
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi khi duyệt hồ sơ miễn giảm: ' . $e->getMessage());
        }

        $this->redirect('staff/applications?tab=mien_giam');
    }

    /**
     * Từ chối hồ sơ miễn giảm nội trú
     */
    public function rejectMienGiam(): void
    {
        $scope = $this->getStaffScope();
        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền thực hiện thao tác này.');
            $this->redirect('staff/applications');
            return;
        }

        $request = new Request();
        $maMienGiam = (int)$request->input('maMienGiam', 0);

        if ($maMienGiam <= 0) {
            Session::flash('error', 'Mã hồ sơ miễn giảm không hợp lệ.');
            $this->redirect('staff/applications?tab=mien_giam');
            return;
        }

        try {
            Database::execute("
                UPDATE HoSoMienGiam 
                SET TrangThaiDuyet = 'Từ chối' 
                WHERE MaMienGiam = :id
            ", ['id' => $maMienGiam]);

            Session::flash('success', "Đã từ chối hồ sơ miễn giảm nội trú #{$maMienGiam}.");
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi khi từ chối hồ sơ miễn giảm: ' . $e->getMessage());
        }

        $this->redirect('staff/applications?tab=mien_giam');
    }

    /**
     * Xuất danh sách hồ sơ đăng ký ra tệp CSV (Tương thích tốt với Microsoft Excel qua UTF-8 BOM)
     */
    public function exportApplications(): void
    {
        $scope = $this->getStaffScope();
        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền kết xuất danh sách hồ sơ.');
            $this->redirect('staff/applications');
            return;
        }

        $request = new Request();
        $filterKhu = trim((string)$request->input('khu', ''));
        $filterCum = trim((string)$request->input('cum', ''));
        $filterToa = trim((string)$request->input('toa', ''));
        $filterStatus = trim((string)$request->input('status', ''));
        $search = trim((string)$request->input('search', ''));
        $activeTab = trim((string)$request->input('tab', 'cho_duyet'));

        // Áp đặt phạm vi bảo mật theo cấp bậc cán bộ
        if ($scope['level'] === 'KHU') {
            $filterKhu = $scope['maKhu'];
        } elseif ($scope['level'] === 'CUM') {
            $filterKhu = $scope['maKhu'];
            $filterCum = $scope['maCum'];
        } elseif ($scope['level'] === 'TOA') {
            $filterKhu = $scope['maKhu'];
            $filterCum = $scope['maCum'];
            $filterToa = $scope['maToa'];
        }

        // TIẾN TRÌNH TỰ ĐỘNG HỦY GIỮ CHỖ CÁC HỒ SƠ QUÁ HẠN 72H (BACKGROUND AUTO-RELEASE)
        $autoReleaseResult = AutoReleaseService::scanAndReleaseExpiredBeds();
        $autoReleasedCount = (int)($autoReleaseResult['total_released'] ?? 0);
        $autoReleasedMessage = '';
        if ($autoReleasedCount > 0) {
            $autoReleasedMessage = "Hệ thống đã tự động hủy giữ chỗ và giải phóng {$autoReleasedCount} vị trí giường quá hạn thanh toán 72 giờ về lại quỹ phòng trống!";
        }

        $scopeConditions = [];
        $queryParams = [];

        if (!empty($filterToa)) {
            $scopeConditions[] = "p.MaToaNha = :f_toa";
            $queryParams['f_toa'] = $filterToa;
        } elseif (!empty($filterCum)) {
            $scopeConditions[] = "tn.MaCum = :f_cum";
            $queryParams['f_cum'] = $filterCum;
        } elseif (!empty($filterKhu)) {
            $scopeConditions[] = "c.MaKhu = :f_khu";
            $queryParams['f_khu'] = $filterKhu;
        }

        $searchSql = "";
        if (!empty($search)) {
            $searchSql = " AND (hs.MaHoSo LIKE :kw1 OR sv.HoVaTen LIKE :kw2 OR sv.MSSVTaiTruong LIKE :kw3 OR sv.SoDienThoai LIKE :kw4 OR sv.SoCCCD LIKE :kw5 OR p.SoPhong LIKE :kw6)";
            $kw = "%{$search}%";
            $queryParams['kw1'] = $kw;
            $queryParams['kw2'] = $kw;
            $queryParams['kw3'] = $kw;
            $queryParams['kw4'] = $kw;
            $queryParams['kw5'] = $kw;
            $queryParams['kw6'] = $kw;
        }

        $baseScopeWhere = !empty($scopeConditions) ? " AND " . implode(" AND ", $scopeConditions) : "";

        // Xác định điều kiện trạng thái theo Tab
        $statusCondition = "";
        if ($activeTab === 'cho_duyet') {
            $statusCondition = " AND hs.TrangThaiHoSo = 'Chờ duyệt'";
            $tabLabel = "Cho_Duyet";
        } elseif ($activeTab === 'dang_giu_cho') {
            $statusCondition = " AND hs.TrangThaiHoSo = 'Chờ thanh toán'";
            $tabLabel = "Dang_Giu_Cho_72h";
        } elseif ($activeTab === 'lich_su') {
            if (!empty($filterStatus) && $filterStatus !== 'ALL') {
                $statusCondition = " AND hs.TrangThaiHoSo = :f_stat";
                $queryParams['f_stat'] = $filterStatus;
            } else {
                $statusCondition = " AND hs.TrangThaiHoSo IN ('Đã duyệt', 'Đã từ chối', 'Từ chối', 'Hết hạn - Đã hủy chỗ', 'Hết hạn thanh toán', 'Đã hủy')";
            }
            $tabLabel = "Lich_Su_Xu_Ly";
        } elseif ($activeTab === 'mien_giam') {
            // Xử lý riêng cho tab Miễn giảm
            $this->exportMienGiam($baseScopeWhere, $search, $queryParams);
            return;
        } else {
            $tabLabel = "Tat_Ca";
        }

        try {
            $sql = "
                SELECT hs.*, sv.HoVaTen, sv.GioiTinh, sv.SoCCCD, sv.SoDienThoai, sv.Email, sv.MSSVTaiTruong,
                       t.TenTruong, p.SoPhong, tn.TenToaNha, tn.MaToaNha, tn.MaCum, c.TenCum, c.MaKhu, k.TenKhu,
                       g.KyHieuGiuong, lp.TenLoaiPhong,
                       (lp.DonGiaLuuTruChuan + lp.DonGiaDichVuTangThem) AS DonGiaThang,
                       hd.SoTienPhaiDong, hd.TrangThai AS TrangThaiHoaDon
                FROM HoSoDangKy hs
                JOIN SinhVien sv ON hs.MaSinhVien = sv.MaSinhVien
                LEFT JOIN TruongDaiHoc t ON sv.MaTruong = t.MaTruong
                JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                JOIN Phong p ON g.MaPhong = p.MaPhong
                JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                JOIN CumNha c ON tn.MaCum = c.MaCum
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN HoaDon hd ON hd.MaHoaDon = CONCAT('HD-', hs.MaHoSo)
                WHERE 1=1 {$statusCondition} {$baseScopeWhere} {$searchSql}
                ORDER BY hs.ThoiGianNop DESC
            ";
            $records = Database::fetchAll($sql, $queryParams);

            $filename = "DS_HoSo_72h_{$tabLabel}_" . date('Ymd_His') . ".csv";

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

            // Header columns
            fputcsv($output, [
                'STT',
                'Mã hồ sơ',
                'MSSV',
                'Họ và tên',
                'Giới tính',
                'Số điện thoại',
                'Email',
                'Trường ĐH',
                'Khu',
                'Cụm',
                'Tòa nhà',
                'Phòng',
                'Giường',
                'Loại phòng',
                'Trạng thái hồ sơ',
                'Thời gian nộp',
                'Thời gian duyệt',
                'Hạn chót thanh toán',
                'Lệ phí chỗ ở (VNĐ)',
                'Trạng thái hóa đơn',
                'Lý do từ chối / Ghi chú'
            ]);

            $stt = 1;
            foreach ($records as $r) {
                fputcsv($output, [
                    $stt++,
                    $r['MaHoSo'],
                    $r['MSSVTaiTruong'] ?? '',
                    $r['HoVaTen'] ?? '',
                    $r['GioiTinh'] ?? '',
                    $r['SoDienThoai'] ?? '',
                    $r['Email'] ?? '',
                    $r['TenTruong'] ?? '',
                    $r['TenKhu'] ?? $r['MaKhu'] ?? '',
                    $r['TenCum'] ?? $r['MaCum'] ?? '',
                    $r['TenToaNha'] ?? $r['MaToaNha'] ?? '',
                    $r['SoPhong'] ?? '',
                    $r['KyHieuGiuong'] ?? '',
                    $r['TenLoaiPhong'] ?? '',
                    $r['TrangThaiHoSo'] ?? '',
                    !empty($r['ThoiGianNop']) ? date('d/m/Y H:i', strtotime($r['ThoiGianNop'])) : '',
                    !empty($r['ThoiGianDuyet']) ? date('d/m/Y H:i', strtotime($r['ThoiGianDuyet'])) : '',
                    !empty($r['HanChotThanhToan']) ? date('d/m/Y H:i', strtotime($r['HanChotThanhToan'])) : '',
                    (int)($r['SoTienPhaiDong'] ?? (($r['DonGiaThang'] ?? 0) * 5)),
                    $r['TrangThaiHoaDon'] ?? 'Chưa tạo',
                    $r['GhiChuTuChoi'] ?? ''
                ]);
            }

            fclose($output);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi kết xuất danh sách hồ sơ: ' . $e->getMessage());
            $this->redirect('staff/applications');
        }
    }

    /**
     * Xuất danh sách hồ sơ miễn giảm nội trú ra file CSV
     */
    private function exportMienGiam(string $baseScopeWhere, string $search, array $queryParams): void
    {
        try {
            $mgScopeWhere = "";
            $mgParams = [];
            if (isset($queryParams['f_toa'])) {
                $mgScopeWhere .= " AND p.MaToaNha = :mg_toa";
                $mgParams['mg_toa'] = $queryParams['f_toa'];
            } elseif (isset($queryParams['f_cum'])) {
                $mgScopeWhere .= " AND tn.MaCum = :mg_cum";
                $mgParams['mg_cum'] = $queryParams['f_cum'];
            } elseif (isset($queryParams['f_khu'])) {
                $mgScopeWhere .= " AND c.MaKhu = :mg_khu";
                $mgParams['mg_khu'] = $queryParams['f_khu'];
            }
            if (!empty($search)) {
                $mgScopeWhere .= " AND (mg.MaSinhVien LIKE :mg_kw1 OR sv.HoVaTen LIKE :mg_kw2 OR sv.MSSVTaiTruong LIKE :mg_kw3)";
                $mgKw = "%{$search}%";
                $mgParams['mg_kw1'] = $mgKw;
                $mgParams['mg_kw2'] = $mgKw;
                $mgParams['mg_kw3'] = $mgKw;
            }

            $sqlMienGiam = "
                SELECT mg.*, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, tdh.TenTruong,
                       p.SoPhong, tn.TenToaNha, g.KyHieuGiuong
                FROM HoSoMienGiam mg
                JOIN SinhVien sv ON mg.MaSinhVien = sv.MaSinhVien
                LEFT JOIN TruongDaiHoc tdh ON sv.MaTruong = tdh.MaTruong
                LEFT JOIN HopDongNoiTru hd ON mg.MaHopDong = hd.MaHopDong
                LEFT JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
                LEFT JOIN Phong p ON g.MaPhong = p.MaPhong
                LEFT JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                LEFT JOIN CumNha c ON tn.MaCum = c.MaCum
                WHERE 1=1 {$mgScopeWhere}
                ORDER BY mg.MaMienGiam DESC
            ";
            $records = Database::fetchAll($sqlMienGiam, $mgParams);

            $filename = "DS_HoSo_Mien_Giam_" . date('Ymd_His') . ".csv";

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'STT',
                'Mã miễn giảm',
                'MSSV',
                'Họ và tên',
                'Số điện thoại',
                'Trường ĐH',
                'Hợp đồng',
                'Phòng',
                'Giường',
                'Đối tượng miễn giảm',
                '% Miễn giảm',
                'Số tiền hoàn trả (VNĐ)',
                'Trạng thái xét duyệt'
            ]);

            $stt = 1;
            foreach ($records as $r) {
                fputcsv($output, [
                    $stt++,
                    $r['MaMienGiam'],
                    $r['MSSVTaiTruong'] ?? $r['MaSinhVien'],
                    $r['HoVaTen'] ?? '',
                    $r['SoDienThoai'] ?? '',
                    $r['TenTruong'] ?? '',
                    $r['MaHopDong'] ?? '',
                    $r['SoPhong'] ?? '',
                    $r['KyHieuGiuong'] ?? '',
                    $r['DoiTuongMienGiam'] ?? '',
                    $r['PhanTramGiam'] . '%',
                    (int)($r['SoTienHoanTra'] ?? 0),
                    $r['TrangThaiDuyet'] ?? 'Chờ duyệt'
                ]);
            }

            fclose($output);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi kết xuất danh sách miễn giảm: ' . $e->getMessage());
            $this->redirect('staff/applications?tab=mien_giam');
        }
    }

    /**
     * Tải file CSV mẫu để nhập kết quả xét duyệt đợt đăng ký giữ chỗ 72h
     */
    public function downloadTemplate(): void
    {
        $scope = $this->getStaffScope();
        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền tải file mẫu.');
            $this->redirect('staff/applications');
            return;
        }

        $filename = 'Mau_Xet_Duyet_Ho_So_72h.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

        // Header
        fputcsv($output, [
            'MaHoSo',
            'KetQuaXetDuyet',
            'LyDoTuChoi'
        ]);

        // Dữ liệu mẫu hướng dẫn
        fputcsv($output, [
            'HS-2026-101',
            'Duyet',
            ''
        ]);
        fputcsv($output, [
            'HS-2026-102',
            'TuChoi',
            'Không thuộc diện ưu tiên đợt 1 / Thông tin CCCD không khớp'
        ]);
        fputcsv($output, [
            'HS-2026-103',
            'Duyet',
            ''
        ]);

        fclose($output);
        exit;
    }

    /**
     * Nhập file CSV để xét duyệt hàng loạt hồ sơ đăng ký giữ chỗ 72h
     */
    public function importApplications(): void
    {
        $scope = $this->getStaffScope();
        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền thực hiện nhập file xét duyệt.');
            $this->redirect('staff/applications');
            return;
        }

        if (!isset($_FILES['importFile']) || $_FILES['importFile']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Vui lòng chọn tệp CSV hợp lệ để nhập kết quả xét duyệt!');
            $this->redirect('staff/applications');
            return;
        }

        $file = $_FILES['importFile'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) {
            Session::flash('error', 'Hệ thống chỉ hỗ trợ tệp định dạng CSV (.csv) hoặc văn bản (.txt)!');
            $this->redirect('staff/applications');
            return;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Session::flash('error', 'Không thể mở tệp tải lên để đọc dữ liệu!');
            $this->redirect('staff/applications');
            return;
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);
                Session::flash('error', 'Tệp tải lên rỗng!');
                $this->redirect('staff/applications');
                return;
            }

            // Xóa UTF-8 BOM
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

            $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
            if (substr_count($firstLine, "\t") > substr_count($firstLine, $delimiter)) {
                $delimiter = "\t";
            }
            $headers = str_getcsv(trim($firstLine), $delimiter);

            $normalize = function(string $str): string {
                $str = mb_strtolower(trim($str), 'UTF-8');
                $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u", 'a', $str);
                $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u", 'e', $str);
                $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/u", 'i', $str);
                $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u", 'o', $str);
                $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u", 'u', $str);
                $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/u", 'y', $str);
                $str = preg_replace("/(đ)/u", 'd', $str);
                return preg_replace('/[^a-z0-9]/', '', $str);
            };

            $colMap = [];
            foreach ($headers as $idx => $h) {
                $clean = $normalize($h);
                if (in_array($clean, ['mahoso', 'hoso', 'id', 'applicationid', 'ma'])) {
                    $colMap['mahoso'] = $idx;
                } elseif (in_array($clean, ['ketquaxetduyet', 'ketqua', 'result', 'status', 'duyet', 'action', 'trangthai'])) {
                    $colMap['ketqua'] = $idx;
                } elseif (in_array($clean, ['lydotuchoi', 'lydo', 'reason', 'ghichu', 'note'])) {
                    $colMap['lydo'] = $idx;
                }
            }

            if (!isset($colMap['mahoso']) || !isset($colMap['ketqua'])) {
                fclose($handle);
                Session::flash('error', 'Cấu trúc file không hợp lệ! File cần có tối thiểu 2 cột: "MaHoSo" và "KetQuaXetDuyet".');
                $this->redirect('staff/applications');
                return;
            }

            $userId = Auth::id() ?? 3;
            $approvedCount = 0;
            $rejectedCount = 0;
            $skippedCount = 0;
            $errorRows = [];

            $rowNum = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNum++;
                if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                    continue;
                }

                $maHoSo = isset($colMap['mahoso']) && isset($row[$colMap['mahoso']]) ? trim($row[$colMap['mahoso']]) : '';
                $rawResult = isset($colMap['ketqua']) && isset($row[$colMap['ketqua']]) ? trim($row[$colMap['ketqua']]) : '';
                $lyDo = isset($colMap['lydo']) && isset($row[$colMap['lydo']]) ? trim($row[$colMap['lydo']]) : '';

                if (empty($maHoSo)) {
                    $skippedCount++;
                    continue;
                }

                // Kiểm tra hồ sơ có tồn tại và trong phạm vi không
                $hsRow = Database::fetch("
                    SELECT hs.MaHoSo, hs.TrangThaiHoSo, p.MaToaNha, tn.MaCum, c.MaKhu
                    FROM HoSoDangKy hs
                    JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                    JOIN Phong p ON g.MaPhong = p.MaPhong
                    JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                    JOIN CumNha c ON tn.MaCum = c.MaCum
                    WHERE hs.MaHoSo = :hs
                ", ['hs' => $maHoSo]);

                if (!$hsRow) {
                    $errorRows[] = "Dòng {$rowNum}: Không tìm thấy hồ sơ {$maHoSo}";
                    continue;
                }

                // Phân quyền theo cấp cán bộ
                if ($scope['level'] === 'TOA' && $hsRow['MaToaNha'] !== $scope['maToa']) {
                    $errorRows[] = "Dòng {$rowNum}: Hồ sơ {$maHoSo} thuộc Tòa {$hsRow['MaToaNha']}, ngoài phạm vi của bạn";
                    continue;
                }
                if ($scope['level'] === 'CUM' && $hsRow['MaCum'] !== $scope['maCum']) {
                    $errorRows[] = "Dòng {$rowNum}: Hồ sơ {$maHoSo} thuộc Cụm {$hsRow['MaCum']}, ngoài phạm vi của bạn";
                    continue;
                }
                if ($scope['level'] === 'KHU' && $hsRow['MaKhu'] !== $scope['maKhu']) {
                    $errorRows[] = "Dòng {$rowNum}: Hồ sơ {$maHoSo} thuộc Khu {$hsRow['MaKhu']}, ngoài phạm vi của bạn";
                    continue;
                }

                $resClean = $normalize($rawResult);
                $isApprove = in_array($resClean, ['duyet', 'daduyet', 'approve', 'approved', 'pass', 'ok', '1', 'yes', 'true']);
                $isReject = in_array($resClean, ['tuchoi', 'tuchoiduyet', 'reject', 'rejected', 'fail', '0', 'no', 'false']);

                if ($isApprove) {
                    if ($hsRow['TrangThaiHoSo'] !== 'Chờ duyệt') {
                        $errorRows[] = "Dòng {$rowNum}: Hồ sơ {$maHoSo} đang ở trạng thái '{$hsRow['TrangThaiHoSo']}', chỉ duyệt hồ sơ 'Chờ duyệt'";
                        continue;
                    }
                    $res = AutoReleaseService::approveApplication($maHoSo, $userId, 72);
                    if ($res['success']) {
                        $approvedCount++;
                    } else {
                        $errorRows[] = "Dòng {$rowNum}: Duyệt {$maHoSo} thất bại - " . ($res['message'] ?? '');
                    }
                } elseif ($isReject) {
                    if (!in_array($hsRow['TrangThaiHoSo'], ['Chờ duyệt', 'Chờ thanh toán'])) {
                        $errorRows[] = "Dòng {$rowNum}: Hồ sơ {$maHoSo} đang ở trạng thái '{$hsRow['TrangThaiHoSo']}', không thể từ chối";
                        continue;
                    }
                    $rejectReason = !empty($lyDo) ? $lyDo : 'Từ chối qua file đợt xét duyệt tập trung';
                    $res = AutoReleaseService::rejectApplication($maHoSo, $userId, $rejectReason);
                    if ($res['success']) {
                        $rejectedCount++;
                    } else {
                        $errorRows[] = "Dòng {$rowNum}: Từ chối {$maHoSo} thất bại - " . ($res['message'] ?? '');
                    }
                } else {
                    $skippedCount++;
                }
            }

            fclose($handle);

            $msg = "Nhập dữ liệu xét duyệt hoàn tất: Phê duyệt {$approvedCount} hồ sơ (cấp hạn 72h), Từ chối {$rejectedCount} hồ sơ, Bỏ qua {$skippedCount} dòng.";
            if (!empty($errorRows)) {
                $errPreview = array_slice($errorRows, 0, 3);
                $msg .= " Lưu ý: " . implode('; ', $errPreview) . (count($errorRows) > 3 ? " (và " . (count($errorRows) - 3) . " lỗi khác)" : "");
                Session::flash('warning', $msg);
            } else {
                Session::flash('success', $msg);
            }

        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi trong quá trình đọc và xử lý file CSV: ' . $e->getMessage());
        }

        $this->redirect('staff/applications');
    }

    /**
     * Tạo Đợt Đăng Ký Mới (Chỉ dành riêng cho Cán bộ Quản lý KTX cấp cao: Giám đốc / Admin)
     */
    public function createPeriod(): void
    {
        $request = new Request();
        $token = (string) $request->input('csrf_token', '');
        if (!hash_equals((string) Session::get('csrf_token', ''), $token) || $token === '') {
            Session::flash('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        $user = Auth::user() ?? [];
        $scope = $this->getStaffScope($user);
        if (!$scope['isAllowed'] || $scope['level'] !== 'ALL') {
            Session::flash('error', 'Từ chối quyền hạn: Chỉ Cán bộ Quản lý KTX cấp cao (Ban Giám đốc / Admin) mới có thẩm quyền tạo và cấu hình Đợt đăng ký nội trú!');
            $this->redirect('staff/applications');
            return;
        }

        $tenDot = trim((string)$request->input('tenDot', ''));
        $namHoc = trim((string)$request->input('namHoc', ''));
        $thoiGianMoCong = trim((string)$request->input('thoiGianMoCong', ''));
        $thoiGianDongCong = trim((string)$request->input('thoiGianDongCong', ''));
        $mocKetThuc1 = trim((string)$request->input('mocKetThuc1', ''));
        $mocKetThuc2 = trim((string)$request->input('mocKetThuc2', ''));
        $soGioGiuCho = (int)$request->input('soGioGiuCho', 72);

        if (empty($tenDot) || empty($namHoc) || empty($thoiGianMoCong) || empty($thoiGianDongCong)) {
            Session::flash('error', 'Vui lòng nhập đầy đủ các thông tin bắt buộc: Tên đợt, Năm học, Thời gian mở cổng và Thời gian đóng cổng!');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        $normalizeDt = function(string $dt): ?string {
            $timestamp = strtotime($dt);
            if ($timestamp === false) {
                return null;
            }
            return date('Y-m-d H:i:s', $timestamp);
        };

        $moCongFmt = $normalizeDt($thoiGianMoCong);
        $dongCongFmt = $normalizeDt($thoiGianDongCong);

        if (!$moCongFmt || !$dongCongFmt) {
            Session::flash('error', 'Định dạng thời gian mở hoặc đóng cổng không hợp lệ!');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        // Ràng buộc 1: chk_dot_thoigian -> ThoiGianDongCong > ThoiGianMoCong
        if (strtotime($dongCongFmt) <= strtotime($moCongFmt)) {
            Session::flash('error', 'Lỗi ràng buộc: Thời gian đóng cổng phải sau thời gian mở cổng!');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        // Ràng buộc 2: chk_dot_mocketthuc -> MocKetThuc2 >= MocKetThuc1
        $m1 = !empty($mocKetThuc1) ? date('Y-m-d', strtotime($mocKetThuc1)) : null;
        $m2 = !empty($mocKetThuc2) ? date('Y-m-d', strtotime($mocKetThuc2)) : null;
        if ($m1 && $m2 && strtotime($m2) < strtotime($m1)) {
            Session::flash('error', 'Lỗi ràng buộc: Mốc kết thúc kỳ 2 phải sau hoặc bằng mốc kết thúc kỳ 1!');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        // Ràng buộc 3: chk_dot_giucho -> SoGioGiuCho > 0
        if ($soGioGiuCho <= 0) {
            Session::flash('error', 'Lỗi ràng buộc: Số giờ giữ chỗ thanh toán phải lớn hơn 0!');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        try {
            Database::execute("
                INSERT INTO dotdangky 
                (TenDot, NamHoc, ThoiGianMoCong, ThoiGianDongCong, MocKetThuc1, MocKetThuc2, SoGioGiuCho)
                VALUES 
                (:tenDot, :namHoc, :moCong, :dongCong, :m1, :m2, :soGio)
            ", [
                'tenDot' => $tenDot,
                'namHoc' => $namHoc,
                'moCong' => $moCongFmt,
                'dongCong' => $dongCongFmt,
                'm1' => $m1,
                'm2' => $m2,
                'soGio' => $soGioGiuCho
            ]);

            Session::flash('success', "Đã tạo thành công Đợt đăng ký mới: \"{$tenDot}\" (Năm học {$namHoc}). Cổng đăng ký sẽ mở và tiếp nhận hồ sơ theo đúng khung thời gian cấu hình!");
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi khi lưu đợt đăng ký: ' . $e->getMessage());
        }

        $this->redirect('staff/applications?tab=dot_dang_ky');
    }

    /**
     * Đóng cổng đợt đăng ký sớm / khẩn cấp (Chỉ Cán bộ Quản lý KTX ALL)
     */
    public function closePeriod(): void
    {
        $request = new Request();
        $token = (string) $request->input('csrf_token', '');
        if (!hash_equals((string) Session::get('csrf_token', ''), $token) || $token === '') {
            Session::flash('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        $scope = $this->getStaffScope();
        if (!$scope['isAllowed'] || $scope['level'] !== 'ALL') {
            Session::flash('error', 'Từ chối quyền hạn: Chỉ Cán bộ Quản lý KTX cấp cao mới có quyền đóng cổng đợt đăng ký!');
            $this->redirect('staff/applications');
            return;
        }

        $maDot = (int)$request->input('maDot', 0);
        if ($maDot <= 0) {
            Session::flash('error', 'Mã đợt đăng ký không hợp lệ!');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        try {
            $dot = Database::fetch("SELECT * FROM dotdangky WHERE MaDot = :id", ['id' => $maDot]);
            if (!$dot) {
                Session::flash('error', 'Không tìm thấy đợt đăng ký chỉ định!');
                $this->redirect('staff/applications?tab=dot_dang_ky');
                return;
            }

            Database::execute("
                UPDATE dotdangky 
                SET ThoiGianDongCong = NOW() 
                WHERE MaDot = :id AND ThoiGianDongCong > NOW()
            ", ['id' => $maDot]);

            Session::flash('success', "Đã đóng cổng đợt đăng ký \"{$dot['TenDot']}\" thành công. Hệ thống dừng tiếp nhận hồ sơ mới!");
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi đóng cổng: ' . $e->getMessage());
        }

        $this->redirect('staff/applications?tab=dot_dang_ky');
    }

    /**
     * Gia hạn thời gian đóng cổng đợt đăng ký (Chỉ Cán bộ Quản lý KTX ALL)
     */
    public function extendPeriod(): void
    {
        $request = new Request();
        $token = (string) $request->input('csrf_token', '');
        if (!hash_equals((string) Session::get('csrf_token', ''), $token) || $token === '') {
            Session::flash('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        $scope = $this->getStaffScope();
        if (!$scope['isAllowed'] || $scope['level'] !== 'ALL') {
            Session::flash('error', 'Từ chối quyền hạn: Chỉ Cán bộ Quản lý KTX cấp cao mới có quyền gia hạn đợt đăng ký!');
            $this->redirect('staff/applications');
            return;
        }

        $maDot = (int)$request->input('maDot', 0);
        $thoiGianDongCongMoi = trim((string)$request->input('thoiGianDongCongMoi', ''));
        $timestamp = strtotime($thoiGianDongCongMoi);

        if ($maDot <= 0 || !$timestamp) {
            Session::flash('error', 'Thông tin gia hạn thời gian không hợp lệ!');
            $this->redirect('staff/applications?tab=dot_dang_ky');
            return;
        }

        $dongCongFmt = date('Y-m-d H:i:s', $timestamp);

        try {
            $dot = Database::fetch("SELECT * FROM dotdangky WHERE MaDot = :id", ['id' => $maDot]);
            if (!$dot) {
                Session::flash('error', 'Không tìm thấy đợt đăng ký chỉ định!');
                $this->redirect('staff/applications?tab=dot_dang_ky');
                return;
            }

            if (strtotime($dongCongFmt) <= strtotime($dot['ThoiGianMoCong'])) {
                Session::flash('error', 'Thời gian đóng cổng gia hạn phải sau thời gian mở cổng!');
                $this->redirect('staff/applications?tab=dot_dang_ky');
                return;
            }

            Database::execute("
                UPDATE dotdangky 
                SET ThoiGianDongCong = :dongCong 
                WHERE MaDot = :id
            ", [
                'dongCong' => $dongCongFmt,
                'id' => $maDot
            ]);

            Session::flash('success', "Đã gia hạn thành công thời gian đóng cổng cho đợt \"{$dot['TenDot']}\" đến " . date('H:i d/m/Y', $timestamp) . "!");
        } catch (\Throwable $e) {
            Session::flash('error', 'Lỗi cập nhật gia hạn: ' . $e->getMessage());
        }

        $this->redirect('staff/applications?tab=dot_dang_ky');
    }
}
