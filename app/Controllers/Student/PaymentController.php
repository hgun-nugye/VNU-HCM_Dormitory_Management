<?php

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AutoReleaseService;
use Exception;

class PaymentController extends Controller {
    public function index(): void {
        $user = Auth::user();
        $mssv = $user['profile']['MaSinhVien'] ?? $user['TenDangNhap'] ?? '';
        $hoaDons = [];
        $chiSoDienNuoc = [];
        $giaoDichs = [];

        try {
            // Tự động thu hồi và hủy hóa đơn nếu quá hạn 72h
            AutoReleaseService::scanAndReleaseExpiredBeds();

            $hoaDons = Database::fetchAll(
                "SELECT * FROM HoaDon WHERE MaSinhVien = :mssv ORDER BY NgayTao DESC LIMIT 20",
                ['mssv' => $mssv]
            );
            $chiSoDienNuoc = Database::fetchAll(
                "SELECT DISTINCT cs.*, p.SoPhong, tn.TenToaNha
                 FROM ChiSoDienNuoc cs
                 JOIN Phong p ON cs.MaPhong = p.MaPhong
                 JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
                 JOIN HopDongNoiTru hd ON hd.MaGiuong IN (SELECT MaGiuong FROM Giuong WHERE MaPhong = p.MaPhong)
                 WHERE hd.MaSinhVien = :mssv
                 ORDER BY cs.Nam DESC, cs.Thang DESC LIMIT 20",
                ['mssv' => $mssv]
            );
            $giaoDichs = Database::fetchAll(
                "SELECT gd.*, hd.LoaiHoaDon
                 FROM GiaoDichThanhToan gd
                 JOIN HoaDon hd ON gd.MaHoaDon = hd.MaHoaDon
                 WHERE hd.MaSinhVien = :mssv
                 ORDER BY gd.ThoiGianGiaoDich DESC LIMIT 20",
                ['mssv' => $mssv]
            );
        } catch (\Throwable $e) {
            // Keep empty lists so the view reports no data instead of fake records.
        }

        // Tự động nhận diện IP Wi-Fi/LAN của máy tính để nhúng vào mã QR kết nối từ điện thoại
        $serverIp = function_exists('get_local_server_ip') ? get_local_server_ip() : ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
        $serverPort = $_SERVER['SERVER_PORT'] ?? 80;
        $portSuffix = ($serverPort != 80 && $serverPort != 443) ? ":{$serverPort}" : '';

        $this->view('student.payments', [
            'hoaDons' => $hoaDons,
            'chiSoDienNuoc' => $chiSoDienNuoc,
            'giaoDichs' => $giaoDichs,
            'serverIp' => $serverIp,
            'serverPortSuffix' => $portSuffix,
            'tongCongNo' => array_sum(array_map(
                fn($hd) => $hd['TrangThai'] === 'Đã thanh toán' ? 0 : (int) $hd['SoTienPhaiDong'],
                $hoaDons
            )),
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
            'title' => 'Thông tin thanh toán'
        ]);
    }

    /**
     * POST: Xác nhận thanh toán trực tuyến qua web
     */
    public function payInvoice(): void
    {
        $request = new Request();
        $user = Auth::user();
        $mssv = $user['profile']['MaSinhVien'] ?? $user['TenDangNhap'] ?? '';

        $invoiceId = trim((string)$request->input('invoice_id', ''));
        $channel = trim((string)$request->input('payment_channel', 'VCB Digibank - QR'));

        if (empty($invoiceId)) {
            Session::flash('error', 'Mã hóa đơn không hợp lệ.');
            $this->redirect('student/payments');
            return;
        }

        // Kiểm tra hóa đơn thuộc về sinh viên đang đăng nhập
        $invoice = Database::fetch("SELECT * FROM HoaDon WHERE MaHoaDon = :hd AND MaSinhVien = :sv LIMIT 1", [
            'hd' => $invoiceId,
            'sv' => $mssv
        ]);

        if (!$invoice) {
            Session::flash('error', 'Không tìm thấy hóa đơn hoặc bạn không có quyền thanh toán hóa đơn này.');
            $this->redirect('student/payments');
            return;
        }

        if ($invoice['TrangThai'] === 'Đã thanh toán') {
            Session::flash('success', "Hóa đơn {$invoiceId} đã được thanh toán trước đó.");
            $this->redirect('student/payments');
            return;
        }

        Database::beginTransaction();
        try {
            $transId = 'VCB' . date('YmdHis') . strtoupper(substr(uniqid(), -4));
            $refCode = 'WEB-' . date('Ymd') . '-' . substr($invoiceId, -5);
            $payAmount = (float)$invoice['SoTienPhaiDong'];

            // 1. Ghi nhận giao dịch
            Database::execute(
                "INSERT INTO GiaoDichThanhToan 
                 (MaGiaoDich, MaHoaDon, LoaiGiaoDich, KenhThanhToan, MaThamChieuNganHang, SoTien, ThoiGianGiaoDich, KetQuaGiaoDich)
                 VALUES (:maGD, :maHD, 'Thu tiền', :channel, :ref, :tien, NOW(), 'Thành công')",
                [
                    'maGD' => $transId,
                    'maHD' => $invoiceId,
                    'channel' => $channel,
                    'ref' => $refCode,
                    'tien' => $payAmount
                ]
            );

            // 2. Chuyển trạng thái hóa đơn
            Database::execute(
                "UPDATE HoaDon SET TrangThai = 'Đã thanh toán' WHERE MaHoaDon = :hd",
                ['hd' => $invoiceId]
            );

            // 3. Nếu là hóa đơn tiền phòng (HD-HS...) -> Kích hoạt hợp đồng
            if (str_starts_with($invoiceId, 'HD-HS')) {
                $maHoSo = substr($invoiceId, 3);
                Database::execute(
                    "UPDATE HoSoDangKy SET TrangThaiHoSo = 'Đã duyệt' WHERE MaHoSo = :hs",
                    ['hs' => $maHoSo]
                );

                $hoSo = Database::fetch("SELECT * FROM HoSoDangKy WHERE MaHoSo = :hs LIMIT 1", ['hs' => $maHoSo]);
                if ($hoSo && !empty($hoSo['MaGiuong'])) {
                    $existingHd = Database::fetch("SELECT MaHopDong FROM HopDongNoiTru WHERE MaHoSo = :hs LIMIT 1", ['hs' => $maHoSo]);
                    if (!$existingHd) {
                        $maHopDong = 'HD' . date('Y') . strtoupper(substr(uniqid(), -4));
                        $ngayBatDau = date('Y-m-d');
                        $ngayKetThuc = date('Y-m-d', strtotime('+10 months'));

                        Database::execute(
                            "INSERT INTO HopDongNoiTru 
                             (MaHopDong, MaHoSo, MaSinhVien, MaGiuong, NgayBatDau, NgayKetThuc, DaNopMauCT01, TrangThaiHopDong)
                             VALUES (:maHD, :maHS, :maSV, :maGiuong, :bd, :kt, 0, 'Chờ hiệu lực')",
                            [
                                'maHD' => $maHopDong,
                                'maHS' => $maHoSo,
                                'maSV' => $hoSo['MaSinhVien'],
                                'maGiuong' => $hoSo['MaGiuong'],
                                'bd' => $ngayBatDau,
                                'kt' => $ngayKetThuc
                            ]
                        );

                        Database::execute(
                            "UPDATE HoaDon SET MaHopDong = :maHD WHERE MaHoaDon = :hd",
                            ['maHD' => $maHopDong, 'hd' => $invoiceId]
                        );
                    }
                }
            }

            Database::commit();

            Session::flash('success', "Thanh toán thành công hóa đơn <b>{$invoiceId}</b> (Mã GD: <b>{$transId}</b>)! Hệ thống đã xác nhận thanh toán tự động.");
        } catch (Exception $e) {
            Database::rollBack();
            Session::flash('error', 'Lỗi khi xử lý thanh toán: ' . $e->getMessage());
        }

        $this->redirect('student/payments');
    }
}
