<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\RoomTransferService;
use App\Services\MailService;

/**
 * Controller: Tiếp nhận & Xử lý Yêu cầu / Đơn một cửa của Sinh viên (staff/requests)
 * 
 * Áp dụng Ma trận Phân quyền 4 cấp Quản lý:
 * - Cấp 4: Quản lý toàn KTX (Admin / Ban Giám đốc)
 * - Cấp 3: Trưởng Khu KTX
 * - Cấp 2: Trưởng Cụm KTX
 * - Cấp 1: Trưởng Tòa KTX
 * - Chặn 100% Non-staff (Sinh viên, Kỹ thuật, Kế toán, Lễ tân...)
 */
class RequestController extends Controller
{
    /**
     * Xác định phân quyền cán bộ truy cập phân hệ Dịch vụ Một Cửa
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
            || str_contains($chucVu, 'điện nước')
            || str_contains($chucVu, 'lễ tân');

        $isManager = in_array($role, ['ADMIN', 'QuanTriVien', 'NV_QUAN_LY_KTX', 'TRUONG_KHU', 'TRUONG_CUM', 'TRUONG_TOA'], true);

        if ($isNonManager || !$isManager) {
            $userRoleName = match ($role) {
                'SINH_VIEN' => 'Sinh viên',
                'NV_THUONG' => 'Nhân viên thường',
                'NV_LE_TAN' => 'Nhân viên Lễ tân',
                'TRUONG_PHONG' => 'Trưởng phòng sinh viên',
                'KE_TOAN' => 'Nhân viên Kế toán',
                'KY_THUAT' => 'Nhân viên Kỹ thuật',
                default => !empty($user['ChucVu']) ? $user['ChucVu'] : (!empty($role) ? $role : 'Tài khoản của bạn')
            };

            return [
                'isAllowed' => false,
                'level' => 'DENIED',
                'levelNumber' => -1,
                'role' => $role,
                'title' => 'Không có quyền truy cập',
                'badgeClass' => 'badge-danger',
                'userRoleName' => $userRoleName,
                'maKhu' => null,
                'maCum' => null,
                'maToa' => null,
                'canApprove' => false,
                'canReject' => false,
                'availableToas' => [],
                'availableCums' => [],
                'availableKhus' => [],
                'message' => "Từ chối quyền truy cập: Phân hệ Dịch vụ Một Cửa chỉ dành riêng cho Cán bộ Quản lý KTX (Trưởng Tòa, Trưởng Cụm, Trưởng Khu, Ban Giám đốc KTX). {$userRoleName} không có quyền thao tác!"
            ];
        }

        // 2. CẤP 4: Ban quản lý KTX
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
                'title' => 'Ban quản lý KTX',
                'badgeClass' => 'badge-primary',
                'userRoleName' => 'Cán bộ Quản trị',
                'maKhu' => null,
                'maCum' => null,
                'maToa' => null,
                'canApprove' => true,
                'canReject' => true,
                'availableToas' => $toas,
                'availableCums' => $cums,
                'availableKhus' => $khus,
                'message' => ''
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
                'userRoleName' => "Trưởng BQL Khu {$maKhu}",
                'maKhu' => $maKhu,
                'maCum' => null,
                'maToa' => null,
                'canApprove' => true,
                'canReject' => true,
                'availableToas' => $toas,
                'availableCums' => $cums,
                'availableKhus' => $khus,
                'message' => ''
            ];
        }

        // 4. CẤP 2: Trưởng Cụm (CUMAG -> AG...)
        if ($role === 'TRUONG_CUM') {
            $maCum = !empty($cumQuanLy) ? $cumQuanLy : 'AG';
            $cumRow = Database::fetch("
                SELECT c.MaCum, c.TenCum, c.MaKhu, k.TenKhu 
                FROM CumNha c 
                JOIN KhuKTX k ON c.MaKhu = k.MaKhu 
                WHERE c.MaCum = :c
            ", ['c' => $maCum]);

            $maKhu = $cumRow['MaKhu'] ?? 'A';
            $tenCum = $cumRow['TenCum'] ?? $maCum;

            // Làm sạch nhãn cụm: tránh lặp từ "Trưởng BQL Cụm nhà Ban Quản lý Cụm nhà A.G"
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
                'userRoleName' => 'Trưởng BQL Cụm',
                'maKhu' => $maKhu,
                'maCum' => $maCum,
                'maToa' => null,
                'canApprove' => true,
                'canReject' => true,
                'availableToas' => $toas,
                'availableCums' => [$cumRow],
                'availableKhus' => [['MaKhu' => $maKhu, 'TenKhu' => $cumRow['TenKhu'] ?? $maKhu]],
                'message' => ''
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
        }

        $maKhu = $toaRow['MaKhu'] ?? 'A';
        $maCum = $toaRow['MaCum'] ?? 'AG';
        $maToa = $toaRow['MaToaNha'] ?? 'A01';
        $tenToa = $toaRow['TenToaNha'] ?? $maToa;
        $cleanToa = preg_replace('/^Tòa\s*/ui', '', $tenToa);

        return [
            'isAllowed' => true,
            'level' => 'TOA',
            'levelNumber' => 1,
            'role' => $role,
            'title' => "Trưởng Tòa {$cleanToa}",
            'badgeClass' => 'badge-success',
            'userRoleName' => "Trưởng Tòa {$cleanToa}",
            'maKhu' => $maKhu,
            'maCum' => $maCum,
            'maToa' => $maToa,
            'maToaNha' => $maToa,
            'canApprove' => true,
            'canReject' => true,
            'availableToas' => [$toaRow],
            'availableCums' => [['MaCum' => $maCum, 'TenCum' => $toaRow['TenCum'] ?? $maCum, 'MaKhu' => $maKhu]],
            'availableKhus' => [['MaKhu' => $maKhu, 'TenKhu' => $toaRow['TenKhu'] ?? $maKhu]],
            'message' => ''
        ];
    }

    /**
     * Màn hình chính Dịch vụ Một Cửa (Danh sách đơn từ sinh viên)
     */
    public function requests(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);

        // NẾU TÀI KHOẢN BỊ CHẶN: Render view chặn ngay lập tức
        if (!$scope['isAllowed']) {
            $this->view('staff.requests', [
                'user' => $user,
                'scope' => $scope,
                'requests' => [],
                'pendingRequests' => [],
                'transferRequests' => [],
                'checkoutRequests' => [],
                'historyRequests' => [],
                'kpis' => ['pending' => 0, 'transfer' => 0, 'checkout' => 0, 'resolved' => 0, 'total' => 0],
                'selectedType' => 'all',
                'selectedStatus' => 'all',
                'selectedKhu' => '',
                'selectedCum' => '',
                'selectedToa' => '',
                'selectedTang' => '',
                'searchTerm' => '',
                'activeTab' => 'pending',
                'success' => Session::flash('success'),
                'error' => Session::flash('error')
            ]);
            return;
        }

        // Đọc tham số lọc & tìm kiếm
        $activeTab = trim((string)($_GET['tab'] ?? 'pending'));
        $selectedType = trim((string)($_GET['type'] ?? 'all'));
        $selectedStatus = trim((string)($_GET['status'] ?? 'all'));
        $selectedKhu = trim((string)($_GET['khu'] ?? ''));
        $selectedCum = trim((string)($_GET['cum'] ?? ''));
        $selectedToa = trim((string)($_GET['toa'] ?? ''));
        $selectedTang = trim((string)($_GET['tang'] ?? ''));
        $searchTerm = trim((string)($_GET['search'] ?? ''));

        // Áp đặt phạm vi cứng theo quyền hạn cán bộ
        if ($scope['level'] === 'TOA') {
            $selectedToa = $scope['maToa'];
            $selectedCum = $scope['maCum'];
            $selectedKhu = $scope['maKhu'];
        } elseif ($scope['level'] === 'CUM') {
            $selectedCum = $scope['maCum'];
            $selectedKhu = $scope['maKhu'];
        } elseif ($scope['level'] === 'KHU') {
            $selectedKhu = $scope['maKhu'];
        }

        // Xây dựng câu truy vấn lọc dữ liệu
        $where = ["1=1"];
        $params = [];

        // Ràng buộc địa bàn theo cán bộ
        if (!empty($selectedToa)) {
            $where[] = "tCur.MaToaNha = :f_toa";
            $params['f_toa'] = $selectedToa;
        } elseif (!empty($selectedCum)) {
            $where[] = "cCur.MaCum = :f_cum";
            $params['f_cum'] = $selectedCum;
        } elseif (!empty($selectedKhu)) {
            $where[] = "cCur.MaKhu = :f_khu";
            $params['f_khu'] = $selectedKhu;
        }

        if (!empty($selectedTang)) {
            $where[] = "pCur.Tang = :f_tang";
            $params['f_tang'] = $selectedTang;
        }

        // Lọc theo loại đơn
        if ($selectedType === 'chuyen-phong') {
            $where[] = "(d.LoaiDon LIKE '%chuyển phòng%' OR d.LoaiDon LIKE '%Chuyển phòng%')";
        } elseif ($selectedType === 'tam-vang') {
            $where[] = "(d.LoaiDon LIKE '%tạm vắng%' OR d.LoaiDon LIKE '%Tạm vắng%')";
        } elseif ($selectedType === 'tra-phong') {
            $where[] = "(d.LoaiDon LIKE '%trả phòng%' OR d.LoaiDon LIKE '%Trả phòng%')";
        } elseif ($selectedType === 'dong-phi') {
            $where[] = "(d.LoaiDon LIKE '%phí%' OR d.LoaiDon LIKE '%học kỳ%')";
        }

        // Lọc theo trạng thái
        if ($selectedStatus === 'cho-duyet') {
            $where[] = "d.TrangThaiDuyet = 'Chờ duyệt'";
        } elseif ($selectedStatus === 'da-duyet') {
            $where[] = "d.TrangThaiDuyet = 'Đã duyệt'";
        } elseif ($selectedStatus === 'tu-choi') {
            $where[] = "(d.TrangThaiDuyet = 'Từ chối' OR d.TrangThaiDuyet = 'Đã từ chối')";
        }

        // Tìm kiếm thông minh
        if (!empty($searchTerm)) {
            $where[] = "(d.MaDon LIKE :kw1 OR sv.HoVaTen LIKE :kw2 OR sv.MSSVTaiTruong LIKE :kw3 OR sv.SoDienThoai LIKE :kw4 OR pCur.SoPhong LIKE :kw5 OR d.MaSinhVien LIKE :kw6)";
            $kwVal = "%{$searchTerm}%";
            $params['kw1'] = $kwVal;
            $params['kw2'] = $kwVal;
            $params['kw3'] = $kwVal;
            $params['kw4'] = $kwVal;
            $params['kw5'] = $kwVal;
            $params['kw6'] = $kwVal;
        }

        $sql = "
            SELECT d.*,
                   sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, sv.MaTruong, sv.GioiTinh,
                   dh.TenTruong,
                   hd.MaGiuong AS MaGiuongHienTai,
                   gCur.MaPhong AS MaPhongHienTai,
                   pCur.SoPhong AS SoPhongHienTai,
                   pCur.Tang AS TangHienTai,
                   tCur.TenToaNha AS TenToaHienTai,
                   tCur.MaToaNha AS MaToaHienTai,
                   cCur.MaCum AS MaCumHienTai,
                   cCur.TenCum AS TenCumHienTai,
                   kCur.MaKhu AS MaKhuHienTai,
                   gNew.MaPhong AS MaPhongMoi,
                   pNew.SoPhong AS SoPhongMoi,
                   tNew.TenToaNha AS TenToaMoi,
                   tNew.MaToaNha AS MaToaMoi,
                   cNew.MaCum AS MaCumMoi
            FROM DonTuMotCua d
            LEFT JOIN SinhVien sv ON d.MaSinhVien = sv.MaSinhVien
            LEFT JOIN TruongDaiHoc dh ON sv.MaTruong = dh.MaTruong
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
            WHERE " . implode(' AND ', $where) . "
            ORDER BY (d.TrangThaiDuyet = 'Chờ duyệt') DESC, d.MaDon DESC
        ";

        $requests = Database::fetchAll($sql, $params);

        // Phân loại danh sách theo 4 Tabs
        $pendingRequests = [];
        $transferRequests = [];
        $checkoutRequests = [];
        $historyRequests = [];

        foreach ($requests as $r) {
            $loaiDon = mb_strtolower($r['LoaiDon'] ?? '', 'UTF-8');
            $status = $r['TrangThaiDuyet'] ?? '';

            if ($status === 'Chờ duyệt') {
                $pendingRequests[] = $r;
            } else {
                $historyRequests[] = $r;
            }

            if (str_contains($loaiDon, 'chuyển phòng')) {
                $transferRequests[] = $r;
            }

            if (str_contains($loaiDon, 'trả phòng') || str_contains($loaiDon, 'tạm vắng')) {
                $checkoutRequests[] = $r;
            }
        }

        // Tính KPI thống kê
        $kpis = [
            'pending' => count($pendingRequests),
            'transfer' => count($transferRequests),
            'checkout' => count($checkoutRequests),
            'resolved' => count(array_filter($requests, fn($x) => ($x['TrangThaiDuyet'] ?? '') === 'Đã duyệt')),
            'total' => count($requests)
        ];

        $this->view('staff.requests', [
            'user' => $user,
            'scope' => $scope,
            'requests' => $requests,
            'pendingRequests' => $pendingRequests,
            'transferRequests' => $transferRequests,
            'checkoutRequests' => $checkoutRequests,
            'historyRequests' => $historyRequests,
            'kpis' => $kpis,
            'selectedType' => $selectedType,
            'selectedStatus' => $selectedStatus,
            'selectedKhu' => $selectedKhu,
            'selectedCum' => $selectedCum,
            'selectedToa' => $selectedToa,
            'selectedTang' => $selectedTang,
            'searchTerm' => $searchTerm,
            'activeTab' => $activeTab,
            'success' => Session::flash('success'),
            'error' => Session::flash('error')
        ]);
    }

    /**
     * POST: Phê duyệt đơn một cửa
     */
    public function approveRequest(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);

        if (!$scope['isAllowed'] || !$scope['canApprove']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền phê duyệt đơn một cửa!');
            $this->redirect('staff/requests');
            return;
        }

        $request = new Request();
        $maDon = trim((string)$request->input('maDon', ''));
        $tab = trim((string)$request->input('tab', 'pending'));
        $userId = Auth::id() ?? 3;

        if (empty($maDon)) {
            Session::flash('error', 'Mã đơn không hợp lệ.');
            $this->redirect('staff/requests?tab=' . urlencode($tab));
            return;
        }

        // Kiểm tra phạm vi phân quyền của đơn
        if ($scope['level'] === 'TOA') {
            $don = Database::fetch("
                SELECT p.MaToaNha 
                FROM DonTuMotCua d
                LEFT JOIN HopDongNoiTru hd ON d.MaHopDong = hd.MaHopDong
                LEFT JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
                LEFT JOIN Phong p ON g.MaPhong = p.MaPhong
                WHERE d.MaDon = :md
            ", ['md' => $maDon]);

            if (!$don || $don['MaToaNha'] !== $scope['maToa']) {
                Session::flash('error', 'Từ chối quyền: Đơn này thuộc sinh viên ngoài Tòa nhà bạn quản lý!');
                $this->redirect('staff/requests?tab=' . urlencode($tab));
                return;
            }
        }

        $result = RoomTransferService::approveRequest($maDon, (string)$userId);

        if ($result['success']) {
            // Gửi email thông báo cho sinh viên về kết quả duyệt đơn
            try {
                MailService::notifyStudentRequestDecision($maDon, 'Đã duyệt');
            } catch (\Throwable $e) {
                error_log('Lỗi gửi email cho sinh viên khi duyệt đơn: ' . $e->getMessage());
            }
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('staff/requests?tab=' . urlencode($tab));
    }

    /**
     * POST: Từ chối đơn một cửa
     */
    public function rejectRequest(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);

        if (!$scope['isAllowed'] || !$scope['canReject']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền từ chối đơn một cửa!');
            $this->redirect('staff/requests');
            return;
        }

        $request = new Request();
        $maDon = trim((string)$request->input('maDon', ''));
        $lyDo = trim((string)$request->input('lyDoTuChoi', ''));
        $tab = trim((string)$request->input('tab', 'pending'));

        if (empty($maDon)) {
            Session::flash('error', 'Mã đơn không hợp lệ.');
            $this->redirect('staff/requests?tab=' . urlencode($tab));
            return;
        }

        if (empty($lyDo)) {
            $lyDo = 'Không đáp ứng điều kiện theo quy định của KTX';
        }

        // Kiểm tra phạm vi phân quyền
        if ($scope['level'] === 'TOA') {
            $don = Database::fetch("
                SELECT p.MaToaNha 
                FROM DonTuMotCua d
                LEFT JOIN HopDongNoiTru hd ON d.MaHopDong = hd.MaHopDong
                LEFT JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
                LEFT JOIN Phong p ON g.MaPhong = p.MaPhong
                WHERE d.MaDon = :md
            ", ['md' => $maDon]);

            if (!$don || $don['MaToaNha'] !== $scope['maToa']) {
                Session::flash('error', 'Từ chối quyền: Đơn này thuộc sinh viên ngoài Tòa nhà bạn quản lý!');
                $this->redirect('staff/requests?tab=' . urlencode($tab));
                return;
            }
        }

        $result = RoomTransferService::rejectRequest($maDon, $lyDo);

        if ($result['success']) {
            // Gửi email thông báo cho sinh viên về việc từ chối đơn
            try {
                MailService::notifyStudentRequestDecision($maDon, 'Từ chối', $lyDo);
            } catch (\Throwable $e) {
                error_log('Lỗi gửi email cho sinh viên khi từ chối đơn: ' . $e->getMessage());
            }
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('staff/requests?tab=' . urlencode($tab));
    }

    /**
     * GET: Xuất file Excel / CSV danh sách đơn một cửa (kèm UTF-8 BOM)
     */
    public function exportRequests(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);

        if (!$scope['isAllowed']) {
            header('Location: ' . url('staff/requests'));
            exit;
        }

        $tab = trim((string)($_GET['tab'] ?? 'pending'));
        $selectedType = trim((string)($_GET['type'] ?? 'all'));
        $selectedStatus = trim((string)($_GET['status'] ?? 'all'));
        $selectedToa = trim((string)($_GET['toa'] ?? $scope['maToa'] ?? ''));
        $selectedCum = trim((string)($_GET['cum'] ?? $scope['maCum'] ?? ''));
        $selectedKhu = trim((string)($_GET['khu'] ?? $scope['maKhu'] ?? ''));

        $filename = "Don_Mot_Cua_" . $tab . "_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM cho Excel mở tiếng Việt

        // Dòng tiêu đề cột
        fputcsv($output, [
            'Mã Đơn',
            'Loại Đơn',
            'Họ và Tên Sinh Viên',
            'MSSV',
            'Trường ĐH',
            'Số Điện Thoại',
            'Vị Trí Phòng Hiện Tại',
            'Vị Trí Giường / Phòng Mới (nếu có)',
            'Chênh Lệch Phí (VNĐ)',
            'Từ Ngày',
            'Đến Ngày',
            'Trạng Thái Duyệt',
            'Lý Do Sinh Viên',
            'Lý Do BQL / Ghi Chú'
        ]);

        $where = ["1=1"];
        $params = [];

        if (!empty($selectedToa)) {
            $where[] = "tCur.MaToaNha = :toa";
            $params['toa'] = $selectedToa;
        } elseif (!empty($selectedCum)) {
            $where[] = "cCur.MaCum = :cum";
            $params['cum'] = $selectedCum;
        } elseif (!empty($selectedKhu)) {
            $where[] = "cCur.MaKhu = :khu";
            $params['khu'] = $selectedKhu;
        }

        if ($tab === 'pending') {
            $where[] = "d.TrangThaiDuyet = 'Chờ duyệt'";
        } elseif ($tab === 'transfer') {
            $where[] = "(d.LoaiDon LIKE '%chuyển phòng%' OR d.LoaiDon LIKE '%Chuyển phòng%')";
        } elseif ($tab === 'checkout') {
            $where[] = "(d.LoaiDon LIKE '%trả phòng%' OR d.LoaiDon LIKE '%tạm vắng%')";
        } elseif ($tab === 'history') {
            $where[] = "d.TrangThaiDuyet IN ('Đã duyệt', 'Từ chối', 'Đã từ chối')";
        }

        $sql = "
            SELECT d.*,
                   sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai,
                   dh.TenTruong,
                   pCur.SoPhong AS SoPhongHienTai,
                   tCur.TenToaNha AS TenToaHienTai,
                   pNew.SoPhong AS SoPhongMoi,
                   tNew.TenToaNha AS TenToaMoi
            FROM DonTuMotCua d
            LEFT JOIN SinhVien sv ON d.MaSinhVien = sv.MaSinhVien
            LEFT JOIN TruongDaiHoc dh ON sv.MaTruong = dh.MaTruong
            LEFT JOIN HopDongNoiTru hd ON d.MaHopDong = hd.MaHopDong
            LEFT JOIN Giuong gCur ON hd.MaGiuong = gCur.MaGiuong
            LEFT JOIN Phong pCur ON gCur.MaPhong = pCur.MaPhong
            LEFT JOIN ToaNha tCur ON pCur.MaToaNha = tCur.MaToaNha
            LEFT JOIN CumNha cCur ON tCur.MaCum = cCur.MaCum
            LEFT JOIN Giuong gNew ON d.MaGiuongChuyenDen = gNew.MaGiuong
            LEFT JOIN Phong pNew ON gNew.MaPhong = pNew.MaPhong
            LEFT JOIN ToaNha tNew ON pNew.MaToaNha = tNew.MaToaNha
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.MaDon DESC
        ";

        $rows = Database::fetchAll($sql, $params);

        foreach ($rows as $r) {
            $viTriCu = !empty($r['SoPhongHienTai']) ? ("P." . $r['SoPhongHienTai'] . " (" . $r['TenToaHienTai'] . ")") : 'N/A';
            $viTriMoi = !empty($r['SoPhongMoi']) ? ("P." . $r['SoPhongMoi'] . " (" . $r['TenToaMoi'] . ")") : '---';

            // Phân tách lý do SV và lý do BQL nếu có
            $lyDoSV = $r['LyDo'] ?? '';
            $lyDoBQL = '';
            if (str_contains($lyDoSV, '[BQL từ chối:')) {
                $parts = explode('[BQL từ chối:', $lyDoSV);
                $lyDoSV = trim($parts[0] ?? '');
                $lyDoBQL = rtrim(trim($parts[1] ?? ''), ']');
            }

            fputcsv($output, [
                $r['MaDon'],
                $r['LoaiDon'],
                $r['HoVaTen'] ?? 'N/A',
                $r['MSSVTaiTruong'] ?? $r['MaSinhVien'],
                $r['TenTruong'] ?? '',
                $r['SoDienThoai'] ?? '',
                $viTriCu,
                $viTriMoi,
                $r['SoTienChenhLechHoan'],
                $r['TuNgay'] ?? '',
                $r['DenNgay'] ?? '',
                $r['TrangThaiDuyet'],
                $lyDoSV,
                $lyDoBQL
            ]);
        }

        fclose($output);
        exit;
    }
}
