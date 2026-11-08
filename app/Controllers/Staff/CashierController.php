<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use Exception;

/**
 * Controller: Quầy Thu Ngân Tiền Mặt & Quản lý Tài vụ (staff/cashier)
 * 
 * Nghiệp vụ chuyên biệt phục vụ vai trò Kế toán & Ban Quản lý KTX:
 * - Tra cứu công nợ đa năng (MSSV, Họ tên, CCCD, Mã hồ sơ, Mã hóa đơn)
 * - Thu tiền mặt tại quầy & tự động tính tiền thối lại
 * - Gạch nợ tức thì, kích hoạt hợp đồng nội trú (cho phép nhận phòng)
 * - In Phiếu thu / Biên lai tiền mặt chuẩn Kế toán A5/A4
 * - Sổ quỹ tiền mặt & Thống kê doanh thu ca trực
 */
class CashierController extends Controller
{
    /**
     * Xác định quyền hạn truy cập Quầy Thu Ngân
     */
    public function getStaffScope(?array $user = null): array
    {
        if (!$user) {
            $user = Auth::user() ?? [];
        }

        $role = $user['MaVaiTro'] ?? $user['VaiTro'] ?? '';
        $username = strtoupper($user['TenDangNhap'] ?? '');
        $chucVu = mb_strtolower($user['ChucVu'] ?? $user['profile']['ChucVu'] ?? '');

        $isAccountant = Auth::isKeToan();

        $roleName = 'Cán bộ KTX';
        if ($isAccountant) {
            $roleName = 'Chuyên viên Kế toán & Thu ngân';
        }

        return [
            'isAllowed' => $isAccountant,
            'isAccountant' => $isAccountant,
            'roleName' => $roleName,
            'userId' => $user['MaNguoiDung'] ?? null,
            'userName' => $user['HoVaTen'] ?? $user['TenDangNhap'] ?? 'Thu ngân KTX'
        ];
    }

    /**
     * Màn hình chính Quầy Thu Ngân (POS & Sổ quỹ tiền mặt)
     */
    public function index(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);

        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền truy cập: Phân hệ Quầy Thu Ngân chỉ dành riêng cho Nhân viên Kế toán / Thu ngân KTX!');
            $this->redirect('staff/dashboard');
            return;
        }

        $request = new Request();
        $activeTab = trim((string)$request->input('tab', 'pos'));
        $searchKeyword = trim((string)$request->input('search', ''));
        $journalDate = trim((string)$request->input('date', date('Y-m-d')));

        $studentData = null;
        $unpaidInvoices = [];
        $totalDebt = 0;
        $roomInfo = null;

        // Xử lý Tra cứu Sinh viên & Công nợ
        if (!empty($searchKeyword)) {
            try {
                $studentData = $this->findStudent($searchKeyword);

                if ($studentData) {
                    $maSV = $studentData['MaSinhVien'];

                    // 1. Lấy thông tin phòng ở hiện tại hoặc giường đang giữ chỗ
                    $roomInfo = Database::fetch("
                        SELECT g.KyHieuGiuong, g.MaGiuong, p.SoPhong, p.MaPhong, tn.TenToaNha, tn.MaToaNha, cn.TenCum, k.TenKhu,
                               hd.MaHopDong, hd.TrangThaiHopDong, hd.NgayNhanPhong
                        FROM HopDongNoiTru hd
                        JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
                        JOIN Phong p ON g.MaPhong = p.MaPhong
                        JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                        JOIN CumNha cn ON tn.MaCum = cn.MaCum
                        JOIN KhuKTX k ON cn.MaKhu = k.MaKhu
                        WHERE hd.MaSinhVien = :sv AND hd.TrangThaiHopDong IN ('Đang hiệu lực', 'Chờ hiệu lực')
                        ORDER BY hd.NgayBatDau DESC, hd.MaHopDong DESC LIMIT 1
                    ", ['sv' => $maSV]);

                    if (!$roomInfo) {
                        // Nếu chưa có hợp đồng, kiểm tra hồ sơ đang giữ chỗ 72h
                        $roomInfo = Database::fetch("
                            SELECT g.KyHieuGiuong, g.MaGiuong, p.SoPhong, p.MaPhong, tn.TenToaNha, tn.MaToaNha, cn.TenCum, k.TenKhu,
                                   hs.MaHoSo, hs.TrangThaiHoSo, hs.HanChotThanhToan
                            FROM HoSoDangKy hs
                            JOIN Giuong g ON hs.MaGiuong = g.MaGiuong
                            JOIN Phong p ON g.MaPhong = p.MaPhong
                            JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                            JOIN CumNha cn ON tn.MaCum = cn.MaCum
                            JOIN KhuKTX k ON cn.MaKhu = k.MaKhu
                            WHERE hs.MaSinhVien = :sv AND hs.TrangThaiHoSo IN ('Chờ thanh toán', 'Đã duyệt')
                            ORDER BY hs.ThoiGianNop DESC LIMIT 1
                        ", ['sv' => $maSV]);
                    }

                    // 2. Lấy TẤT CẢ các hóa đơn chưa thanh toán của sinh viên
                    $unpaidInvoices = Database::fetchAll("
                        SELECT hd.*, p.SoPhong, tn.TenToaNha
                        FROM HoaDon hd
                        LEFT JOIN Phong p ON hd.MaPhong = p.MaPhong
                        LEFT JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                        WHERE hd.MaSinhVien = :sv AND hd.TrangThai = 'Chưa thanh toán'
                        ORDER BY hd.NgayTao ASC
                    ", ['sv' => $maSV]);

                    foreach ($unpaidInvoices as $inv) {
                        $totalDebt += (float)$inv['SoTienPhaiDong'];
                    }
                }
            } catch (\Throwable $e) {
                error_log('Lỗi tra cứu công nợ sinh viên (CashierController): ' . $e->getMessage());
                Session::flash('error', 'Lỗi khi tra cứu công nợ: ' . $e->getMessage());
            }
        }

        // Xử lý dữ liệu Sổ quỹ tiền mặt (Tab Nhật ký thu)
        $journalTransactions = [];
        $journalStats = [
            'totalCash' => 0,
            'count' => 0,
            'roomFeeCash' => 0,
            'utilityCash' => 0
        ];

        if ($activeTab === 'journal') {
            $journalTransactions = Database::fetchAll("
                SELECT gd.*, hd.LoaiHoaDon, hd.MaSinhVien, sv.HoVaTen, sv.MSSVTaiTruong, p.SoPhong, tn.TenToaNha
                FROM GiaoDichThanhToan gd
                JOIN HoaDon hd ON gd.MaHoaDon = hd.MaHoaDon
                LEFT JOIN SinhVien sv ON hd.MaSinhVien = sv.MaSinhVien
                LEFT JOIN Phong p ON hd.MaPhong = p.MaPhong
                LEFT JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                WHERE gd.KenhThanhToan LIKE '%Tiền mặt%' AND DATE(gd.ThoiGianGiaoDich) = :d
                ORDER BY gd.ThoiGianGiaoDich DESC
            ", ['d' => $journalDate]);

            foreach ($journalTransactions as $t) {
                $tien = (float)$t['SoTien'];
                $journalStats['totalCash'] += $tien;
                $journalStats['count']++;
                if (str_contains($t['LoaiHoaDon'], 'Điện') || str_contains($t['LoaiHoaDon'], 'Nước')) {
                    $journalStats['utilityCash'] += $tien;
                } else {
                    $journalStats['roomFeeCash'] += $tien;
                }
            }
        }

        // Kiểm tra xem có yêu cầu bật Modal In phiếu thu vừa tạo không
        $printReceipt = Session::get('print_receipt');
        if (!empty($printReceipt)) {
            // Không xóa ngay để view kịp render, sau đó dọn dẹp
            Session::remove('print_receipt');
        }

        $this->view('staff.cashier', [
            'user' => $user,
            'scope' => $scope,
            'activeTab' => $activeTab,
            'searchKeyword' => $searchKeyword,
            'studentData' => $studentData,
            'roomInfo' => $roomInfo,
            'unpaidInvoices' => $unpaidInvoices,
            'totalDebt' => $totalDebt,
            'journalDate' => $journalDate,
            'journalTransactions' => $journalTransactions,
            'journalStats' => $journalStats,
            'printReceipt' => $printReceipt
        ]);
    }

    /**
     * API Tìm kiếm nhanh (Auto-suggest) qua AJAX
     * GET /staff/cashier/search?q=...
     */
    public function search(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);
        if (!$scope['isAllowed']) {
            json_response(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $request = new Request();
        $q = trim((string)$request->input('q', ''));

        if (mb_strlen($q) < 2) {
            json_response(['success' => true, 'data' => []]);
            return;
        }

        try {
            $results = Database::fetchAll("
                SELECT sv.MaSinhVien, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, sv.SoCCCD, sv.SoCCCD AS CCCD,
                       COUNT(CASE WHEN hd.TrangThai = 'Chưa thanh toán' THEN 1 END) AS SoHoaDonNo,
                       SUM(CASE WHEN hd.TrangThai = 'Chưa thanh toán' THEN hd.SoTienPhaiDong ELSE 0 END) AS TongNo
                FROM SinhVien sv
                LEFT JOIN HoaDon hd ON sv.MaSinhVien = hd.MaSinhVien
                WHERE sv.MSSVTaiTruong LIKE :q1 
                   OR sv.MaSinhVien LIKE :q2
                   OR sv.SoCCCD LIKE :q3
                   OR sv.SoDienThoai LIKE :q4
                   OR sv.HoVaTen LIKE :q5
                   OR hd.MaHoaDon LIKE :q6
                   OR EXISTS (SELECT 1 FROM HoSoDangKy hs WHERE hs.MaSinhVien = sv.MaSinhVien AND hs.MaHoSo LIKE :q7)
                GROUP BY sv.MaSinhVien, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai, sv.SoCCCD
                ORDER BY SoHoaDonNo DESC, sv.HoVaTen ASC
                LIMIT 8
            ", [
                'q1' => "%$q%",
                'q2' => "%$q%",
                'q3' => "%$q%",
                'q4' => "%$q%",
                'q5' => "%$q%",
                'q6' => "%$q%",
                'q7' => "%$q%"
            ]);

            json_response([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Throwable $e) {
            error_log('Lỗi API cashier search: ' . $e->getMessage());
            json_response(['success' => false, 'data' => [], 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Xử lý Thu tiền mặt tại quầy & Gạch nợ
     * POST /staff/cashier/pay
     */
    public function processPayment(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);

        if (!$scope['isAllowed']) {
            Session::flash('error', 'Từ chối quyền: Bạn không có quyền thu tiền mặt!');
            $this->redirect('staff/cashier');
            return;
        }

        $request = new Request();
        $maSinhVien = trim((string)$request->input('maSinhVien', ''));
        $selectedInvoices = $request->input('invoices', []);
        $cashGiven = (float)str_replace(['.', ',', ' '], '', (string)$request->input('cash_given', 0));
        $notes = trim((string)$request->input('notes', ''));

        if (empty($maSinhVien) || empty($selectedInvoices) || !is_array($selectedInvoices)) {
            Session::flash('error', 'Vui lòng chọn ít nhất một hóa đơn cần thu tiền mặt!');
            $this->redirect('staff/cashier?search=' . urlencode($maSinhVien));
            return;
        }

        $student = Database::fetch("SELECT * FROM SinhVien WHERE MaSinhVien = :sv LIMIT 1", ['sv' => $maSinhVien]);
        if (!$student) {
            Session::flash('error', 'Không tìm thấy hồ sơ sinh viên!');
            $this->redirect('staff/cashier');
            return;
        }

        // Lấy danh sách hóa đơn hợp lệ cần thanh toán
        $placeholders = [];
        $params = ['sv' => $maSinhVien];
        foreach ($selectedInvoices as $idx => $id) {
            $key = "hd_{$idx}";
            $placeholders[] = ":$key";
            $params[$key] = trim((string)$id);
        }

        $sql = "SELECT * FROM HoaDon WHERE MaSinhVien = :sv AND TrangThai = 'Chưa thanh toán' AND MaHoaDon IN (" . implode(',', $placeholders) . ")";
        $invoicesToPay = Database::fetchAll($sql, $params);

        if (empty($invoicesToPay)) {
            Session::flash('error', 'Không tìm thấy hóa đơn chưa thanh toán phù hợp!');
            $this->redirect('staff/cashier?search=' . urlencode($maSinhVien));
            return;
        }

        $totalAmount = 0;
        foreach ($invoicesToPay as $inv) {
            $totalAmount += (float)$inv['SoTienPhaiDong'];
        }

        if ($cashGiven > 0 && $cashGiven < $totalAmount) {
            Session::flash('error', 'Số tiền khách đưa (' . number_format($cashGiven) . ' đ) nhỏ hơn tổng số tiền cần thanh toán (' . number_format($totalAmount) . ' đ)!');
            $this->redirect('staff/cashier?search=' . urlencode($maSinhVien));
            return;
        }

        // Tạo mã phiếu thu thống nhất cho lần thu tiền mặt này
        $receiptCode = 'PT' . date('Ymd') . strtoupper(substr(uniqid(), -4));
        $changeAmount = ($cashGiven > $totalAmount) ? ($cashGiven - $totalAmount) : 0;
        $cashierName = $user['HoVaTen'] ?? $user['TenDangNhap'] ?? 'Kế toán KTX';

        Database::beginTransaction();
        try {
            $paidInvoiceCodes = [];

            foreach ($invoicesToPay as $inv) {
                $maHD = $inv['MaHoaDon'];
                $soTien = (float)$inv['SoTienPhaiDong'];
                $transId = 'TM' . date('YmdHis') . strtoupper(substr(uniqid(), -3));

                // 1. Ghi nhận giao dịch tiền mặt
                Database::execute("
                    INSERT INTO GiaoDichThanhToan 
                    (MaGiaoDich, MaHoaDon, LoaiGiaoDich, KenhThanhToan, MaThamChieuNganHang, SoTien, ThoiGianGiaoDich, KetQuaGiaoDich)
                    VALUES (:maGD, :maHD, 'Thu tiền', 'Tiền mặt tại quầy', :ref, :tien, NOW(), 'Thành công')
                ", [
                    'maGD' => $transId,
                    'maHD' => $maHD,
                    'ref' => $receiptCode,
                    'tien' => $soTien
                ]);

                // 2. Gạch nợ Hóa đơn -> 'Đã thanh toán'
                Database::execute("
                    UPDATE HoaDon SET TrangThai = 'Đã thanh toán' WHERE MaHoaDon = :hd
                ", ['hd' => $maHD]);

                // 3. Nếu là hóa đơn giữ chỗ 72h (HD-HS...) -> Đổi Hồ sơ sang 'Đã duyệt' và Tạo hợp đồng nội trú
                if (str_starts_with($maHD, 'HD-HS')) {
                    $maHoSo = substr($maHD, 3);

                    Database::execute("
                        UPDATE HoSoDangKy SET TrangThaiHoSo = 'Đã duyệt' WHERE MaHoSo = :hs
                    ", ['hs' => $maHoSo]);

                    $hoSo = Database::fetch("SELECT * FROM HoSoDangKy WHERE MaHoSo = :hs LIMIT 1", ['hs' => $maHoSo]);
                    if ($hoSo && !empty($hoSo['MaGiuong'])) {
                        $existingHd = Database::fetch("SELECT MaHopDong FROM HopDongNoiTru WHERE MaHoSo = :hs LIMIT 1", ['hs' => $maHoSo]);
                        if (!$existingHd) {
                            $maHopDong = 'HD' . date('Y') . strtoupper(substr(uniqid(), -4));
                            $ngayBatDau = date('Y-m-d');
                            $ngayKetThuc = date('Y-m-d', strtotime('+10 months'));

                            Database::execute("
                                INSERT INTO HopDongNoiTru 
                                (MaHopDong, MaHoSo, MaSinhVien, MaGiuong, NgayBatDau, NgayKetThuc, DaNopMauCT01, TrangThaiHopDong)
                                VALUES (:maHD, :maHS, :maSV, :maGiuong, :bd, :kt, 0, 'Chờ hiệu lực')
                            ", [
                                'maHD' => $maHopDong,
                                'maHS' => $maHoSo,
                                'maSV' => $hoSo['MaSinhVien'],
                                'maGiuong' => $hoSo['MaGiuong'],
                                'bd' => $ngayBatDau,
                                'kt' => $ngayKetThuc
                            ]);

                            Database::execute("
                                UPDATE HoaDon SET MaHopDong = :maHD WHERE MaHoaDon = :hd
                            ", ['maHD' => $maHopDong, 'hd' => $maHD]);
                        }
                    }
                }

                $paidInvoiceCodes[] = $maHD;
            }

            Database::commit();

            // Lưu dữ liệu Phiếu Thu vào Session để tự động mở cửa sổ In Phiếu Thu
            $receiptData = [
                'receipt_code' => $receiptCode,
                'created_at' => date('d/m/Y H:i'),
                'cashier_name' => $cashierName,
                'student_name' => $student['HoVaTen'],
                'mssv' => $student['MSSVTaiTruong'] ?? $student['MaSinhVien'],
                'phone' => $student['SoDienThoai'] ?? '',
                'invoices' => $invoicesToPay,
                'total_amount' => $totalAmount,
                'total_words' => function_exists('convert_number_to_vietnamese_words') ? convert_number_to_vietnamese_words($totalAmount) : (number_format($totalAmount) . ' đồng'),
                'cash_given' => $cashGiven > 0 ? $cashGiven : $totalAmount,
                'change_amount' => $changeAmount,
                'notes' => $notes ?: 'Thu tiền mặt tại Văn phòng Kế toán KTX'
            ];
            Session::set('print_receipt', $receiptData);

            Session::flash('success', "Thu tiền mặt thành công! Đã gạch nợ " . count($paidInvoiceCodes) . " hóa đơn (" . number_format($totalAmount, 0, ',', '.') . " đ). Mã phiếu thu: {$receiptCode}");
            $this->redirect('staff/cashier?search=' . urlencode($maSinhVien) . '&receipt=open');

        } catch (Exception $e) {
            Database::rollBack();
            Session::flash('error', 'Lỗi khi xử lý thu tiền mặt: ' . $e->getMessage());
            $this->redirect('staff/cashier?search=' . urlencode($maSinhVien));
        }
    }

    /**
     * Xem lại / In Phiếu thu độc lập
     * GET /staff/cashier/receipt?ref=PT...
     */
    public function receipt(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);
        if (!$scope['isAllowed']) {
            die('Từ chối quyền truy cập: Chỉ nhân viên Kế toán / Thu ngân mới có quyền xem phiếu thu.');
        }

        $request = new Request();
        $ref = trim((string)$request->input('ref', ''));

        if (empty($ref)) {
            die('Mã phiếu thu không hợp lệ.');
        }

        $transactions = Database::fetchAll("
            SELECT gd.*, hd.LoaiHoaDon, hd.MaSinhVien, sv.HoVaTen, sv.MSSVTaiTruong, sv.SoDienThoai,
                   p.SoPhong, tn.TenToaNha
            FROM GiaoDichThanhToan gd
            JOIN HoaDon hd ON gd.MaHoaDon = hd.MaHoaDon
            LEFT JOIN SinhVien sv ON hd.MaSinhVien = sv.MaSinhVien
            LEFT JOIN Phong p ON hd.MaPhong = p.MaPhong
            LEFT JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
            WHERE gd.MaThamChieuNganHang = :ref
        ", ['ref' => $ref]);

        if (empty($transactions)) {
            die('Không tìm thấy thông tin phiếu thu: ' . htmlspecialchars($ref));
        }

        $first = $transactions[0];
        $total = 0;
        foreach ($transactions as $t) {
            $total += (float)$t['SoTien'];
        }

        $user = Auth::user();
        $cashierName = $user['HoVaTen'] ?? $user['TenDangNhap'] ?? 'Kế toán KTX';

        $receiptData = [
            'receipt_code' => $ref,
            'created_at' => date('d/m/Y H:i', strtotime($first['ThoiGianGiaoDich'])),
            'cashier_name' => $cashierName,
            'student_name' => $first['HoVaTen'],
            'mssv' => $first['MSSVTaiTruong'] ?? $first['MaSinhVien'],
            'phone' => $first['SoDienThoai'] ?? '',
            'room' => (!empty($first['SoPhong']) ? "Phòng {$first['SoPhong']} - {$first['TenToaNha']}" : '---'),
            'invoices' => $transactions,
            'total_amount' => $total,
            'total_words' => function_exists('convert_number_to_vietnamese_words') ? convert_number_to_vietnamese_words($total) : (number_format($total) . ' đồng'),
            'notes' => 'Thu tiền mặt tại quầy Kế toán KTX'
        ];

        $this->view('staff.receipt_print', [
            'receipt' => $receiptData
        ]);
    }

    /**
     * Xuất Báo Cáo Sổ Quỹ Tiền Mặt (CSV Excel UTF-8 BOM)
     * GET /staff/cashier/export?date=YYYY-MM-DD
     */
    public function exportDaily(): void
    {
        $user = Auth::user();
        $scope = $this->getStaffScope($user);
        if (!$scope['isAllowed']) {
            die('Từ chối quyền truy cập: Chỉ nhân viên Kế toán / Thu ngân mới có quyền xuất báo cáo sổ quỹ.');
        }

        $request = new Request();
        $date = trim((string)$request->input('date', date('Y-m-d')));

        $transactions = Database::fetchAll("
            SELECT gd.MaGiaoDich, gd.MaThamChieuNganHang AS MaPhieuThu, gd.ThoiGianGiaoDich, gd.SoTien,
                   hd.MaHoaDon, hd.LoaiHoaDon, sv.MSSVTaiTruong, sv.HoVaTen, sv.SoDienThoai,
                   p.SoPhong, tn.TenToaNha
            FROM GiaoDichThanhToan gd
            JOIN HoaDon hd ON gd.MaHoaDon = hd.MaHoaDon
            LEFT JOIN SinhVien sv ON hd.MaSinhVien = sv.MaSinhVien
            LEFT JOIN Phong p ON hd.MaPhong = p.MaPhong
            LEFT JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
            WHERE gd.KenhThanhToan LIKE '%Tiền mặt%' AND DATE(gd.ThoiGianGiaoDich) = :d
            ORDER BY gd.ThoiGianGiaoDich ASC
        ", ['d' => $date]);

        $fileName = "So_Quy_Tien_Mat_KTX_" . date('Ymd', strtotime($date)) . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $output = fopen('php://output', 'w');
        // Ghi UTF-8 BOM để Excel hiển thị tiếng Việt không bị lỗi font
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['BÁO CÁO SỔ QUỸ THU TIỀN MẶT KÝ TÚC XÁ ĐHQG-HCM']);
        fputcsv($output, ['Ngày thu:', date('d/m/Y', strtotime($date))]);
        fputcsv($output, ['Thời gian xuất file:', date('d/m/Y H:i:s')]);
        fputcsv($output, []);

        fputcsv($output, [
            'STT', 'Mã phiếu thu', 'Mã giao dịch', 'Thời gian thu',
            'MSSV', 'Họ và tên sinh viên', 'Số điện thoại', 'Phòng - Tòa',
            'Mã hóa đơn', 'Khoản thu', 'Số tiền (VNĐ)', 'Hình thức'
        ]);

        $stt = 1;
        $total = 0;
        foreach ($transactions as $t) {
            $tien = (float)$t['SoTien'];
            $total += $tien;
            fputcsv($output, [
                $stt++,
                $t['MaPhieuThu'] ?? '---',
                $t['MaGiaoDich'],
                date('d/m/Y H:i:s', strtotime($t['ThoiGianGiaoDich'])),
                $t['MSSVTaiTruong'] ?? '',
                $t['HoVaTen'] ?? '',
                $t['SoDienThoai'] ?? '',
                (!empty($t['SoPhong']) ? "P.{$t['SoPhong']} - {$t['TenToaNha']}" : '---'),
                $t['MaHoaDon'],
                $t['LoaiHoaDon'],
                number_format($tien, 0, '', ''),
                'Tiền mặt tại quầy'
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['', '', '', '', '', '', '', '', 'TỔNG CỘNG TIỀN MẶT:', number_format($total, 0, '', '') . ' đ']);

        fclose($output);
        exit;
    }

    /**
     * Tìm kiếm sinh viên theo từ khóa linh hoạt
     */
    private function findStudent(string $query): ?array
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        try {
            // 1. Tìm chính xác theo MSSV, Mã sinh viên, Số CCCD hoặc Số điện thoại
            $sv = Database::fetch("
                SELECT sv.*, t.TenTruong 
                FROM SinhVien sv 
                LEFT JOIN TruongDaiHoc t ON sv.MaTruong = t.MaTruong 
                WHERE sv.MSSVTaiTruong = :q OR sv.MaSinhVien = :q2 OR sv.SoCCCD = :q3 OR sv.SoDienThoai = :q4
                LIMIT 1
            ", ['q' => $q, 'q2' => $q, 'q3' => $q, 'q4' => $q]);

            if ($sv) return $sv;

            // 2. Tìm theo Mã hóa đơn (HD-...)
            if (str_starts_with($q, 'HD') || str_contains($q, 'HD-')) {
                $inv = Database::fetch("SELECT MaSinhVien FROM HoaDon WHERE MaHoaDon = :hd OR MaHoaDon LIKE :hd2 LIMIT 1", ['hd' => $q, 'hd2' => "%$q%"]);
                if ($inv && !empty($inv['MaSinhVien'])) {
                    return $this->findStudent($inv['MaSinhVien']);
                }
            }

            // 3. Tìm theo Mã hồ sơ (HS...)
            if (str_starts_with($q, 'HS')) {
                $hs = Database::fetch("SELECT MaSinhVien FROM HoSoDangKy WHERE MaHoSo = :hs OR MaHoSo LIKE :hs2 LIMIT 1", ['hs' => $q, 'hs2' => "%$q%"]);
                if ($hs && !empty($hs['MaSinhVien'])) {
                    return $this->findStudent($hs['MaSinhVien']);
                }
            }

            // 4. Tìm gần đúng theo họ tên
            $sv = Database::fetch("
                SELECT sv.*, t.TenTruong 
                FROM SinhVien sv 
                LEFT JOIN TruongDaiHoc t ON sv.MaTruong = t.MaTruong 
                WHERE sv.HoVaTen LIKE :q
                LIMIT 1
            ", ['q' => "%$q%"]);

            if ($sv) return $sv;

            // 5. Tìm gần đúng theo MSSV, Số CCCD hoặc Số điện thoại
            $sv = Database::fetch("
                SELECT sv.*, t.TenTruong 
                FROM SinhVien sv 
                LEFT JOIN TruongDaiHoc t ON sv.MaTruong = t.MaTruong 
                WHERE sv.MSSVTaiTruong LIKE :q1 OR sv.MaSinhVien LIKE :q2 OR sv.SoCCCD LIKE :q3 OR sv.SoDienThoai LIKE :q4
                LIMIT 1
            ", ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"]);

            return $sv ?: null;
        } catch (\Throwable $e) {
            error_log('Lỗi findStudent CashierController: ' . $e->getMessage());
            return null;
        }
    }
}

