<?php

namespace App\Services;

use App\Core\Database;
use DateTime;
use Exception;

/**
 * CHỨC NĂNG 4: Nghiệp vụ Chuyển phòng & Tự động bù trừ chênh lệch phí (Room Transfer Reconciliation)
 * Bổ sung xử lý phê duyệt Dịch vụ Một Cửa: Chuyển phòng, Tạm vắng, Trả phòng sớm, Từ chối đơn
 */
class RoomTransferService {
    /**
     * Đồng bộ sĩ số cư trú và trạng thái phòng
     */
    public static function syncRoomOccupancy(string $maPhong): void {
        if (empty($maPhong)) return;

        $info = Database::fetch("
            SELECT p.MaPhong, p.TrangThaiPhong, lp.SoGiuongToiDa,
                   (SELECT COUNT(*) FROM Giuong WHERE MaPhong = p.MaPhong AND TrangThaiGiuong = 'Đã có người ở') AS SoSV
            FROM Phong p
            LEFT JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            WHERE p.MaPhong = :mp
        ", ['mp' => $maPhong]);

        if (!$info) return;

        $maxCap = max(1, (int)($info['SoGiuongToiDa'] ?? 4));
        $soSV = min($maxCap, max(0, (int)($info['SoSV'] ?? 0)));
        $currentStatus = $info['TrangThaiPhong'] ?? 'Còn chỗ';

        $isMaintenance = in_array($currentStatus, ['Bảo trì'], true) || str_contains(mb_strtolower($currentStatus), 'bảo trì');
        if (!$isMaintenance) {
            if ($soSV >= $maxCap) {
                $newStatus = 'Đã đầy';
            } else {
                $newStatus = 'Còn chỗ';
            }
        } else {
            $newStatus = 'Bảo trì';
        }

        Database::execute(
            "UPDATE Phong SET SoSVDangO = :sv, TrangThaiPhong = :st WHERE MaPhong = :mp",
            ['sv' => $soSV, 'st' => $newStatus, 'mp' => $maPhong]
        );
    }

    /**
     * Tính toán chênh lệch chi phí khi sinh viên chuyển từ phòng cũ sang phòng mới
     */
    public static function calculateTransferFee(string $maSinhVien, string $maGiuongMoi): array {
        // Lấy hợp đồng đang hiệu lực của sinh viên
        $hopDong = Database::fetch(
            "SELECT hd.*, g.MaPhong AS MaPhongCu, lp.DonGiaLuuTruChuan AS GiaCu, lp.DonGiaDichVuTangThem AS DVCu
             FROM HopDongNoiTru hd
             JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
             JOIN Phong p ON g.MaPhong = p.MaPhong
             JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
             WHERE hd.MaSinhVien = :sv AND hd.TrangThaiHopDong = 'Đang hiệu lực'",
            ['sv' => $maSinhVien]
        );

        if (!$hopDong) {
            return ['success' => false, 'message' => 'Sinh viên chưa có hợp đồng nội trú đang hiệu lực'];
        }

        // Lấy thông tin phòng mới
        $giuongMoi = Database::fetch(
            "SELECT g.*, p.MaPhong AS MaPhongMoi, lp.DonGiaLuuTruChuan AS GiaMoi, lp.DonGiaDichVuTangThem AS DVMoi, lp.TenLoaiPhong
             FROM Giuong g
             JOIN Phong p ON g.MaPhong = p.MaPhong
             JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
             WHERE g.MaGiuong = :maGiuong",
            ['maGiuong' => $maGiuongMoi]
        );

        if (!$giuongMoi || $giuongMoi['TrangThaiGiuong'] !== 'Trống') {
            return ['success' => false, 'message' => 'Giường mới được chọn không khả dụng'];
        }

        // Tính số tháng còn lại của hợp đồng
        $now = new DateTime();
        $endDate = new DateTime($hopDong['NgayKetThuc']);
        $diff = $now->diff($endDate);
        $remainingMonths = ($diff->y * 12) + $diff->m + ($diff->d > 10 ? 1 : 0);
        if ($remainingMonths <= 0) {
            $remainingMonths = 1;
        }

        $giaThangCu = (float)$hopDong['GiaCu'] + (float)$hopDong['DVCu'];
        $giaThangMoi = (float)$giuongMoi['GiaMoi'] + (float)$giuongMoi['DVMoi'];
        $chenhLechMoiThang = $giaThangMoi - $giaThangCu;
        $tongChenhLech = $chenhLechMoiThang * $remainingMonths;

        return [
            'success' => true,
            'maHopDong' => $hopDong['MaHopDong'],
            'phongCu' => $hopDong['MaPhongCu'],
            'phongMoi' => $giuongMoi['MaPhongMoi'],
            'soThangConLai' => $remainingMonths,
            'donGiaCu' => $giaThangCu,
            'donGiaMoi' => $giaThangMoi,
            'chenhLechMoiThang' => $chenhLechMoiThang,
            'tongChenhLech' => $tongChenhLech,
            'loaiThanhToan' => $tongChenhLech > 0 ? 'Phải đóng bổ sung' : ($tongChenhLech < 0 ? 'Được hoàn trả' : 'Không đổi')
        ];
    }

    /**
     * Nộp đơn xin chuyển phòng trên cổng Một Cửa và xử lý sinh hóa đơn chênh lệch
     */
    public static function submitTransferRequest(string $maSinhVien, string $maGiuongMoi, string $lyDo): array {
        $calc = self::calculateTransferFee($maSinhVien, $maGiuongMoi);
        if (!$calc['success']) {
            return $calc;
        }

        Database::beginTransaction();
        try {
            $maDon = 'DON-CP-' . date('Ymd') . strtoupper(substr(uniqid(), -4));

            Database::execute(
                "INSERT INTO DonTuMotCua (MaDon, MaSinhVien, MaHopDong, LoaiDon, MaGiuongChuyenDen, LyDo, SoTienChenhLechHoan, TrangThaiDuyet)
                 VALUES (:maDon, :maSV, :maHD, 'Chuyển phòng nội trú', :maGiuong, :lyDo, :tien, 'Chờ duyệt')",
                [
                    'maDon' => $maDon,
                    'maSV' => $maSinhVien,
                    'maHD' => $calc['maHopDong'],
                    'maGiuong' => $maGiuongMoi,
                    'lyDo' => $lyDo,
                    'tien' => abs($calc['tongChenhLech'])
                ]
            );

            // Nếu phòng mới đắt hơn, tự động sinh hóa đơn bổ sung
            if ($calc['tongChenhLech'] > 0) {
                $maHD = 'HD-CP-' . $maDon;
                Database::execute(
                    "INSERT INTO HoaDon (MaHoaDon, MaSinhVien, MaPhong, MaHopDong, LoaiHoaDon, SoTienPhaiDong, NgayTao, HanThanhToan, TrangThai)
                     VALUES (:maHD, :maSV, :maPhong, :maHopDong, 'Bổ sung chênh lệch phí chuyển phòng', :soTien, NOW(), DATE_ADD(NOW(), INTERVAL 72 HOUR), 'Chưa thanh toán')",
                    [
                        'maHD' => $maHD,
                        'maSV' => $maSinhVien,
                        'maPhong' => $calc['phongMoi'],
                        'maHopDong' => $calc['maHopDong'],
                        'soTien' => $calc['tongChenhLech']
                    ]
                );
            }

            Database::commit();

            try {
                MailService::notifyManagersNewRequest($maDon);
            } catch (\Throwable $e) {
                error_log('Lỗi gửi email cho cán bộ quản lý (submitTransferRequest): ' . $e->getMessage());
            }

            return [
                'success' => true,
                'maDon' => $maDon,
                'tongChenhLech' => $calc['tongChenhLech'],
                'message' => 'Nộp đơn chuyển phòng thành công. Hệ thống đã tạo bảng dự toán chênh lệch phí.'
            ];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Lỗi khi nộp đơn: ' . $e->getMessage()];
        }
    }

    /**
     * Tự động điều phối phê duyệt đơn từ Một Cửa theo loại đơn
     */
    public static function approveRequest(string $maDon, ?string $nguoiDuyet = null): array {
        $don = Database::fetch("SELECT * FROM DonTuMotCua WHERE MaDon = :maDon", ['maDon' => $maDon]);
        if (!$don) {
            return ['success' => false, 'message' => 'Không tìm thấy hồ sơ đơn từ'];
        }
        if ($don['TrangThaiDuyet'] !== 'Chờ duyệt') {
            return ['success' => false, 'message' => 'Đơn này đã được xử lý trước đó (Trạng thái: ' . $don['TrangThaiDuyet'] . ')'];
        }

        $loaiDon = mb_strtolower($don['LoaiDon'] ?? '', 'UTF-8');
        if (str_contains($loaiDon, 'chuyển phòng')) {
            return self::approveTransfer($maDon, $nguoiDuyet);
        } elseif (str_contains($loaiDon, 'tạm vắng')) {
            return self::approveAbsence($maDon, $nguoiDuyet);
        } elseif (str_contains($loaiDon, 'trả phòng')) {
            return self::approveEarlyCheckout($maDon, $nguoiDuyet);
        } else {
            // Loại đơn chung khác (Chia kỳ đóng phí, gia hạn, ...)
            Database::execute(
                "UPDATE DonTuMotCua SET TrangThaiDuyet = 'Đã duyệt' WHERE MaDon = :maDon",
                ['maDon' => $maDon]
            );
            return ['success' => true, 'message' => "Đã phê duyệt thành công đơn: {$don['LoaiDon']} (Mã: {$maDon})"];
        }
    }

    /**
     * BQL Cụm phê duyệt đơn xin chuyển phòng:
     * - Hoán đổi giường cũ -> Trống, giường mới -> Đã có người ở
     * - Cập nhật hợp đồng sinh viên
     * - Đồng bộ tính toán lại sĩ số SoSVDangO của cả 2 phòng liên quan
     */
    public static function approveTransfer(string $maDon, ?string $nguoiDuyet = null): array {
        $don = Database::fetch(
            "SELECT d.*, h.MaGiuong AS MaGiuongCu, h.TrangThaiHopDong,
                    gOld.MaPhong AS MaPhongCu,
                    gNew.MaPhong AS MaPhongMoi, gNew.TrangThaiGiuong AS TrangThaiGiuongMoi
             FROM DonTuMotCua d
             LEFT JOIN HopDongNoiTru h ON d.MaHopDong = h.MaHopDong
             LEFT JOIN Giuong gOld ON h.MaGiuong = gOld.MaGiuong
             LEFT JOIN Giuong gNew ON d.MaGiuongChuyenDen = gNew.MaGiuong
             WHERE d.MaDon = :maDon",
            ['maDon' => $maDon]
        );

        if (!$don) {
            return ['success' => false, 'message' => 'Không tìm thấy hồ sơ đơn'];
        }

        if (empty($don['MaGiuongChuyenDen'])) {
            return ['success' => false, 'message' => 'Đơn không có thông tin giường chuyển đến'];
        }

        if (($don['TrangThaiGiuongMoi'] ?? '') !== 'Trống') {
            return ['success' => false, 'message' => "Giường chuyển đến ({$don['MaGiuongChuyenDen']}) hiện không còn trống (Trạng thái: " . ($don['TrangThaiGiuongMoi'] ?? 'Không tồn tại') . ")"];
        }

        if (empty($don['MaHopDong']) || ($don['TrangThaiHopDong'] ?? '') !== 'Đang hiệu lực') {
            return ['success' => false, 'message' => 'Hợp đồng nội trú của sinh viên không còn hiệu lực'];
        }

        $maGiuongCu = $don['MaGiuongCu'];
        $maGiuongMoi = $don['MaGiuongChuyenDen'];
        $maPhongCu = $don['MaPhongCu'];
        $maPhongMoi = $don['MaPhongMoi'];

        Database::beginTransaction();
        try {
            // 1. Cập nhật hợp đồng nội trú sang giường mới
            Database::execute(
                "UPDATE HopDongNoiTru SET MaGiuong = :maGiuongMoi WHERE MaHopDong = :maHD",
                ['maGiuongMoi' => $maGiuongMoi, 'maHD' => $don['MaHopDong']]
            );

            // 2. Trả giường cũ về 'Trống'
            if ($maGiuongCu) {
                Database::execute(
                    "UPDATE Giuong SET TrangThaiGiuong = 'Trống' WHERE MaGiuong = :maGiuongCu",
                    ['maGiuongCu' => $maGiuongCu]
                );
            }

            // 3. Đánh dấu giường mới thành 'Đã có người ở'
            Database::execute(
                "UPDATE Giuong SET TrangThaiGiuong = 'Đã có người ở' WHERE MaGiuong = :maGiuongMoi",
                ['maGiuongMoi' => $maGiuongMoi]
            );

            // 4. Đồng bộ tính lại sĩ số SoSVDangO cho phòng cũ
            if ($maPhongCu) {
                self::syncRoomOccupancy($maPhongCu);
            }

            // 5. Đồng bộ tính lại sĩ số SoSVDangO cho phòng mới
            if ($maPhongMoi) {
                self::syncRoomOccupancy($maPhongMoi);
            }

            // 6. Cập nhật trạng thái đơn
            Database::execute(
                "UPDATE DonTuMotCua SET TrangThaiDuyet = 'Đã duyệt' WHERE MaDon = :maDon",
                ['maDon' => $maDon]
            );

            Database::commit();

            return [
                'success' => true,
                'message' => "Phê duyệt chuyển phòng thành công cho đơn {$maDon}! Đã chuyển SV sang giường {$maGiuongMoi} (Phòng {$maPhongMoi}) và giải phóng giường cũ {$maGiuongCu} (Phòng {$maPhongCu})."
            ];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Lỗi khi phê duyệt chuyển phòng: ' . $e->getMessage()];
        }
    }

    /**
     * Phê duyệt đơn xin tạm vắng
     */
    public static function approveAbsence(string $maDon, ?string $nguoiDuyet = null): array {
        Database::beginTransaction();
        try {
            Database::execute(
                "UPDATE DonTuMotCua SET TrangThaiDuyet = 'Đã duyệt' WHERE MaDon = :maDon",
                ['maDon' => $maDon]
            );
            Database::commit();

            return [
                'success' => true,
                'message' => "Phê duyệt đơn xin tạm vắng (Mã: {$maDon}) thành công. Hệ thống an ninh đã đồng bộ loại trừ cảnh báo vắng mặt."
            ];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Lỗi khi phê duyệt tạm vắng: ' . $e->getMessage()];
        }
    }

    /**
     * Nghiệm thu cơ sở vật chất và hoàn tất trả phòng sớm
     */
    public static function approveEarlyCheckout(string $maDon, ?string $nguoiDuyet = null): array {
        $don = Database::fetch(
            "SELECT d.*, h.MaGiuong, h.MaSinhVien, g.MaPhong
             FROM DonTuMotCua d
             LEFT JOIN HopDongNoiTru h ON d.MaHopDong = h.MaHopDong
             LEFT JOIN Giuong g ON h.MaGiuong = g.MaGiuong
             WHERE d.MaDon = :maDon",
            ['maDon' => $maDon]
        );

        if (!$don) {
            return ['success' => false, 'message' => 'Không tìm thấy hồ sơ đơn'];
        }

        Database::beginTransaction();
        try {
            // 1. Chấm dứt hợp đồng nội trú
            if (!empty($don['MaHopDong'])) {
                Database::execute(
                    "UPDATE HopDongNoiTru SET TrangThaiHopDong = 'Đã kết thúc', NgayKetThuc = CURDATE() WHERE MaHopDong = :maHD",
                    ['maHD' => $don['MaHopDong']]
                );
            }

            // 2. Trả giường về 'Trống'
            if (!empty($don['MaGiuong'])) {
                Database::execute(
                    "UPDATE Giuong SET TrangThaiGiuong = 'Trống' WHERE MaGiuong = :maGiuong",
                    ['maGiuong' => $don['MaGiuong']]
                );
            }

            // 3. Giảm sĩ số phòng & cập nhật trạng thái phòng
            if (!empty($don['MaPhong'])) {
                self::syncRoomOccupancy($don['MaPhong']);
            }

            // 4. Cập nhật trạng thái cư trú của sinh viên
            if (!empty($don['MaSinhVien'])) {
                Database::execute(
                    "UPDATE SinhVien SET TrangThaiNoiTru = 'Đã trả phòng' WHERE MaSinhVien = :maSV",
                    ['maSV' => $don['MaSinhVien']]
                );
            }

            // 5. Cập nhật đơn
            Database::execute(
                "UPDATE DonTuMotCua SET TrangThaiDuyet = 'Đã duyệt' WHERE MaDon = :maDon",
                ['maDon' => $maDon]
            );

            Database::commit();

            return [
                'success' => true,
                'message' => "Đã xác nhận nghiệm thu và chấm dứt hợp đồng cho sinh viên (Mã đơn: {$maDon}). Giường {$don['MaGiuong']} đã được thu hồi về trạng thái Trống."
            ];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Lỗi khi xử lý trả phòng sớm: ' . $e->getMessage()];
        }
    }

    /**
     * Từ chối đơn từ một cửa kèm lý do
     */
    public static function rejectRequest(string $maDon, string $lyDoTuChoi): array {
        $don = Database::fetch("SELECT * FROM DonTuMotCua WHERE MaDon = :maDon", ['maDon' => $maDon]);
        if (!$don) {
            return ['success' => false, 'message' => 'Không tìm thấy hồ sơ đơn'];
        }

        $lyDoMoi = trim(($don['LyDo'] ?? '') . " [BQL từ chối: {$lyDoTuChoi}]");

        Database::beginTransaction();
        try {
            Database::execute(
                "UPDATE DonTuMotCua SET TrangThaiDuyet = 'Từ chối', LyDo = :lyDo WHERE MaDon = :maDon",
                ['lyDo' => $lyDoMoi, 'maDon' => $maDon]
            );
            Database::commit();

            return [
                'success' => true,
                'message' => "Đã từ chối đơn {$maDon}. Lý do từ chối đã được ghi nhận vào hệ thống phản hồi sinh viên."
            ];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Lỗi khi từ chối đơn: ' . $e->getMessage()];
        }
    }
}
