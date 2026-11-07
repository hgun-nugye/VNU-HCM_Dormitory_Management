<?php

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use Exception;

/**
 * API Phục vụ Thanh toán Trực tuyến & Quét mã QR VCB Digibank (Android App)
 */
class PaymentApiController extends BaseApiController
{
    /**
     * API 1: Kiểm tra trạng thái hóa đơn (Polling thời gian thực trên Web)
     * GET /api/payment/check-status?invoice=HD-HS...
     */
    public function checkStatus(): void
    {
        $request = new Request();
        $invoiceId = trim((string)$request->input('invoice', ''));

        if (empty($invoiceId)) {
            $this->error('Mã hóa đơn không được để trống.', 400);
            return;
        }

        $invoice = Database::fetch(
            "SELECT hd.*, sv.HoVaTen, sv.MSSVTaiTruong, gd.MaGiaoDich, gd.KenhThanhToan, gd.ThoiGianGiaoDich
             FROM HoaDon hd
             LEFT JOIN SinhVien sv ON hd.MaSinhVien = sv.MaSinhVien
             LEFT JOIN GiaoDichThanhToan gd ON hd.MaHoaDon = gd.MaHoaDon
             WHERE hd.MaHoaDon = :hd LIMIT 1",
            ['hd' => $invoiceId]
        );

        if (!$invoice) {
            $this->notFound('Không tìm thấy hóa đơn: ' . $invoiceId);
            return;
        }

        $isPaid = ($invoice['TrangThai'] === 'Đã thanh toán');

        $this->success([
            'invoice_id' => $invoice['MaHoaDon'],
            'is_paid' => $isPaid,
            'status' => $invoice['TrangThai'],
            'amount' => (float)$invoice['SoTienPhaiDong'],
            'student_name' => $invoice['HoVaTen'] ?? '',
            'transaction' => $isPaid ? [
                'trans_id' => $invoice['MaGiaoDich'] ?? '',
                'channel' => $invoice['KenhThanhToan'] ?? 'VCB Digibank',
                'time' => $invoice['ThoiGianGiaoDich'] ?? ''
            ] : null
        ], $isPaid ? 'Hóa đơn đã được thanh toán thành công' : 'Hóa đơn chưa thanh toán');
    }

    /**
     * API 2: Xác nhận thanh toán từ App Android VCB Digibank
     * POST /api/payment/vcb-confirm
     * Payload: { "invoice_id": "...", "amount": 3250000, "source_account": "0123456789", "sender_name": "NGUYEN VAN A" }
     */
    public function confirmVcbPayment(): void
    {
        // Cho phép CORS để ứng dụng di động gọi vào dễ dàng
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $request = new Request();
        // Hỗ trợ cả JSON body (từ Android) và Form-data
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        $invoiceId = trim((string)($jsonData['invoice_id'] ?? $request->input('invoice_id', '')));
        $amount = (float)($jsonData['amount'] ?? $request->input('amount', 0));
        $sourceAccount = trim((string)($jsonData['source_account'] ?? $request->input('source_account', '0123456789 (VCB)')));
        $senderName = trim((string)($jsonData['sender_name'] ?? $request->input('sender_name', 'NGUYEN VAN A')));

        if (empty($invoiceId)) {
            $this->error('Mã hóa đơn không hợp lệ.', 400);
            return;
        }

        $invoice = Database::fetch("SELECT * FROM HoaDon WHERE MaHoaDon = :hd LIMIT 1", ['hd' => $invoiceId]);
        if (!$invoice) {
            $this->notFound('Không tìm thấy hóa đơn ' . $invoiceId);
            return;
        }

        if ($invoice['TrangThai'] === 'Đã thanh toán') {
            $this->success([
                'invoice_id' => $invoiceId,
                'status' => 'Đã thanh toán',
                'already_paid' => true
            ], 'Hóa đơn này đã được thanh toán trước đó.');
            return;
        }

        Database::beginTransaction();
        try {
            $transId = 'VCB' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $refCode = 'QR-VCB-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $payAmount = $amount > 0 ? $amount : (float)$invoice['SoTienPhaiDong'];

            // 1. Ghi nhận bút toán vào GiaoDichThanhToan
            Database::execute(
                "INSERT INTO GiaoDichThanhToan 
                 (MaGiaoDich, MaHoaDon, LoaiGiaoDich, KenhThanhToan, MaThamChieuNganHang, SoTien, ThoiGianGiaoDich, KetQuaGiaoDich)
                 VALUES (:maGD, :maHD, 'Thu tiền', 'VCB Digibank - Chuyển khoản QR', :ref, :tien, NOW(), 'Thành công')",
                [
                    'maGD' => $transId,
                    'maHD' => $invoiceId,
                    'ref' => $refCode,
                    'tien' => $payAmount
                ]
            );

            // 2. Chuyển trạng thái Hóa đơn sang 'Đã thanh toán'
            Database::execute(
                "UPDATE HoaDon SET TrangThai = 'Đã thanh toán' WHERE MaHoaDon = :hd",
                ['hd' => $invoiceId]
            );

            // 3. Nếu là hóa đơn lệ phí phòng (HD-HS... hoặc Lệ phí chỗ ở học kỳ) &rarr; Kích hoạt hợp đồng
            $maHoSo = null;
            if (str_starts_with($invoiceId, 'HD-HS')) {
                $maHoSo = substr($invoiceId, 3); // Lấy 'HS...'
            }

            $createdContract = null;
            if ($maHoSo) {
                // Đổi trạng thái Hồ sơ đăng ký sang 'Đã duyệt' (thỏa mãn chk_hoso_trangthai)
                Database::execute(
                    "UPDATE HoSoDangKy SET TrangThaiHoSo = 'Đã duyệt' WHERE MaHoSo = :hs",
                    ['hs' => $maHoSo]
                );

                $hoSo = Database::fetch("SELECT * FROM HoSoDangKy WHERE MaHoSo = :hs LIMIT 1", ['hs' => $maHoSo]);
                if ($hoSo && !empty($hoSo['MaGiuong'])) {
                    // Kiểm tra xem đã có hợp đồng chưa
                    $existingHd = Database::fetch("SELECT MaHopDong FROM HopDongNoiTru WHERE MaHoSo = :hs LIMIT 1", ['hs' => $maHoSo]);
                    if (!$existingHd) {
                        $maHopDong = 'HD' . date('Y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
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

                        // Cập nhật mã hợp đồng vào hóa đơn
                        Database::execute(
                            "UPDATE HoaDon SET MaHopDong = :maHD WHERE MaHoaDon = :hd",
                            ['maHD' => $maHopDong, 'hd' => $invoiceId]
                        );

                        // Cập nhật trạng thái giường sang 'Đã có người ở'
                        Database::execute(
                            "UPDATE Giuong SET TrangThaiGiuong = 'Đã có người ở' WHERE MaGiuong = :mg",
                            ['mg' => $hoSo['MaGiuong']]
                        );

                        // Cập nhật trạng thái sinh viên sang 'Đang nội trú'
                        Database::execute(
                            "UPDATE SinhVien SET TrangThaiNoiTru = 'Đang nội trú' WHERE MaSinhVien = :sv",
                            ['sv' => $hoSo['MaSinhVien']]
                        );

                        // Cập nhật sĩ số phòng
                        $bed = Database::fetch("SELECT MaPhong FROM Giuong WHERE MaGiuong = :mg", ['mg' => $hoSo['MaGiuong']]);
                        if ($bed) {
                            $room = Database::fetch(
                                "SELECT p.MaPhong, p.SoSVDangO, lp.SoGiuongToiDa 
                                 FROM Phong p 
                                 JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong 
                                 WHERE p.MaPhong = :mp",
                                ['mp' => $bed['MaPhong']]
                            );
                            if ($room) {
                                $newCount = (int)$room['SoSVDangO'] + 1;
                                $newStatus = ($newCount >= (int)$room['SoGiuongToiDa']) ? 'Đã đầy' : 'Còn chỗ';
                                Database::execute(
                                    "UPDATE Phong SET SoSVDangO = :cnt, TrangThaiPhong = :ttp WHERE MaPhong = :mp",
                                    [
                                        'cnt' => $newCount,
                                        'ttp' => $newStatus,
                                        'mp' => $room['MaPhong']
                                    ]
                                );
                            }
                        }

                        $createdContract = $maHopDong;
                    }
                }
            }

            Database::commit();

            $this->success([
                'invoice_id' => $invoiceId,
                'trans_id' => $transId,
                'amount' => $payAmount,
                'sender' => $senderName,
                'source_account' => $sourceAccount,
                'contract_id' => $createdContract,
                'time' => date('d/m/Y H:i:s')
            ], 'Giao dịch qua VCB Digibank thành công! Hệ thống đã tự động gạch nợ.');
        } catch (Exception $e) {
            Database::rollBack();
            $this->error('Lỗi khi xử lý giao dịch: ' . $e->getMessage(), 500);
        }
    }

    /**
     * API 3: Nút bấm mô phỏng nhanh ngay trên Web (Dự phòng khi không dùng điện thoại)
     * POST /api/payment/simulate
     */
    public function simulateWebPayment(): void
    {
        $request = new Request();
        $invoiceId = trim((string)$request->input('invoice_id', ''));
        $channel = trim((string)$request->input('channel', 'VCB Digibank - QR'));

        if (empty($invoiceId)) {
            $this->error('Mã hóa đơn không hợp lệ.', 400);
            return;
        }

        // Gọi lại logic thanh toán VCB
        $_POST['invoice_id'] = $invoiceId;
        $_POST['amount'] = 0;
        $_POST['source_account'] = '0123456789 (VCB Digibank)';
        $_POST['sender_name'] = 'Sinh viên KTX ĐHQG-HCM';

        $this->confirmVcbPayment();
    }
}

