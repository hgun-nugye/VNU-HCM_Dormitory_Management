<?php

namespace App\Services;

use App\Core\Database;
use Exception;

/**
 * CHỨC NĂNG 6: Tính tiền Điện – Nước bậc thang & Kiểm tra bất thường khi nhập liệu (Utility Anomaly Detection)
 * Phân hệ Quản lý Điện Nước, Phát hành Hóa đơn & Đối soát Gạch nợ Mô phỏng
 */
class UtilityBillingService {
    // Biểu giá điện bậc thang chuẩn EVN (VNĐ/kWh)
    const TIER_DIEN = [
        ['limit' => 50,  'price' => 1806],
        ['limit' => 100, 'price' => 1866],
        ['limit' => 200, 'price' => 2167],
        ['limit' => 300, 'price' => 2729],
        ['limit' => 400, 'price' => 3050],
        ['limit' => PHP_INT_MAX, 'price' => 3151],
    ];

    // Đơn giá nước sạch KTX (VNĐ/m3)
    const DON_GIA_NUOC = 12500;

    /**
     * Kiểm tra tính bất thường của chỉ số điện/nước khi nhập liệu
     */
    public static function validateUtilityInput(string $maPhong, int $dienCu, int $dienMoi, int $nuocCu, int $nuocMoi): array {
        $warnings = [];
        $isAnomaly = false;

        // 1. Kiểm tra chỉ số mới < chỉ số cũ
        if ($dienMoi < $dienCu) {
            $warnings[] = "Chỉ số điện mới ({$dienMoi}) < chỉ số cũ ({$dienCu}). Có thể sai sót nhập liệu hoặc đồng hồ quay vòng.";
            $isAnomaly = true;
        }

        if ($nuocMoi < $nuocCu) {
            $warnings[] = "Chỉ số nước mới ({$nuocMoi}) < chỉ số cũ ({$nuocCu}). Cần kiểm tra lại.";
            $isAnomaly = true;
        }

        $dienTieuThu = max(0, $dienMoi - $dienCu);
        $nuocTieuThu = max(0, $nuocMoi - $nuocCu);

        // 2. So sánh với trung bình 3 tháng gần nhất của phòng
        $history = Database::fetchAll(
            "SELECT (DienMoi - DienCu) AS Dien, (NuocMoi - NuocCu) AS Nuoc
             FROM ChiSoDienNuoc
             WHERE MaPhong = :phong
             ORDER BY Nam DESC, Thang DESC
             LIMIT 3",
            ['phong' => $maPhong]
        );

        if (!empty($history)) {
            $avgDien = array_sum(array_column($history, 'Dien')) / count($history);
            $avgNuoc = array_sum(array_column($history, 'Nuoc')) / count($history);

            if ($avgDien > 0 && $dienTieuThu >= ($avgDien * 3)) {
                $avgDienFmt = number_format($avgDien, 1);
                $warnings[] = "Tiêu thụ điện ({$dienTieuThu} kWh) tăng đột biến gấp >3 lần trung bình 3 tháng gần nhất ({$avgDienFmt} kWh). Nghi ngờ chập điện hoặc rò rỉ!";
                $isAnomaly = true;
            }

            if ($avgNuoc > 0 && $nuocTieuThu >= ($avgNuoc * 3)) {
                $avgNuocFmt = number_format($avgNuoc, 1);
                $warnings[] = "Tiêu thụ nước ({$nuocTieuThu} m3) tăng đột biến gấp >3 lần trung bình 3 tháng gần nhất ({$avgNuocFmt} m3). Nghi ngờ rò rỉ đường ống nước!";
                $isAnomaly = true;
            }
        }

        return [
            'isAnomaly' => $isAnomaly,
            'dienTieuThu' => $dienTieuThu,
            'nuocTieuThu' => $nuocTieuThu,
            'warnings' => $warnings
        ];
    }

    /**
     * Tính tiền điện theo biểu giá bậc thang lũy tiến 6 bậc EVN
     */
    public static function calculateElectricityBill(int $kwh): float {
        $total = 0;
        $remaining = $kwh;
        $prevLimit = 0;

        foreach (self::TIER_DIEN as $tier) {
            $tierCapacity = $tier['limit'] - $prevLimit;
            if ($remaining > $tierCapacity) {
                $total += $tierCapacity * $tier['price'];
                $remaining -= $tierCapacity;
                $prevLimit = $tier['limit'];
            } else {
                $total += $remaining * $tier['price'];
                break;
            }
        }

        return $total;
    }

    /**
     * Tính hóa đơn tổng hợp cho cả phòng trong tháng
     */
    public static function calculateMonthlyBill(string $maPhong, int $thang, int $nam, int $dienCu, int $dienMoi, int $nuocCu, int $nuocMoi, int $soSV): array {
        $check = self::validateUtilityInput($maPhong, $dienCu, $dienMoi, $nuocCu, $nuocMoi);
        $tienDien = self::calculateElectricityBill($check['dienTieuThu']);
        $tienNuoc = $check['nuocTieuThu'] * self::DON_GIA_NUOC;
        $tongTien = $tienDien + $tienNuoc;
        $tienMoiSV = $soSV > 0 ? round($tongTien / $soSV) : 0;

        return [
            'maPhong' => $maPhong,
            'thang' => $thang,
            'nam' => $nam,
            'dienCu' => $dienCu,
            'dienMoi' => $dienMoi,
            'nuocCu' => $nuocCu,
            'nuocMoi' => $nuocMoi,
            'dienTieuThu' => $check['dienTieuThu'],
            'nuocTieuThu' => $check['nuocTieuThu'],
            'tienDien' => $tienDien,
            'tienNuoc' => $tienNuoc,
            'tongTien' => $tongTien,
            'soSV' => $soSV,
            'tienMoiSV' => $tienMoiSV,
            'canhBaoBatThuong' => $check['isAnomaly'],
            'chiTietCanhBao' => $check['warnings']
        ];
    }

    /**
     * Lấy chỉ số kỳ trước liền kề của phòng
     */
    public static function getPreviousReading(string $maPhong, int $thang, int $nam): array {
        $prev = Database::fetch(
            "SELECT DienMoi, NuocMoi FROM ChiSoDienNuoc
             WHERE MaPhong = :p AND (Nam < :nam1 OR (Nam = :nam2 AND Thang < :thang))
             ORDER BY Nam DESC, Thang DESC LIMIT 1",
            ['p' => $maPhong, 'nam1' => $nam, 'nam2' => $nam, 'thang' => $thang]
        );

        if ($prev) {
            return [
                'dienCu' => (int)$prev['DienMoi'],
                'nuocCu' => (int)$prev['NuocMoi'],
            ];
        }

        // Nếu chưa có kỳ trước, tìm số đo mới nhất của phòng
        $latest = Database::fetch(
            "SELECT DienMoi, NuocMoi FROM ChiSoDienNuoc WHERE MaPhong = :p ORDER BY Nam DESC, Thang DESC LIMIT 1",
            ['p' => $maPhong]
        );

        return [
            'dienCu' => (int)($latest['DienMoi'] ?? 0),
            'nuocCu' => (int)($latest['NuocMoi'] ?? 0),
        ];
    }

    /**
     * Ghi nhận chỉ số công tơ Điện & Nước cho phòng trong kỳ
     */
    public static function recordReading(
        string $maPhong,
        int $thang,
        int $nam,
        int $dienMoi,
        int $nuocMoi,
        ?int $dienCu = null,
        ?int $nuocCu = null,
        ?int $soSV = null
    ): array {
        // Tự động lấy chỉ số cũ nếu chưa truyền
        if ($dienCu === null || $nuocCu === null) {
            $prev = self::getPreviousReading($maPhong, $thang, $nam);
            $dienCu = $dienCu ?? $prev['dienCu'];
            $nuocCu = $nuocCu ?? $prev['nuocCu'];
        }

        // Tự động lấy sĩ số phòng nếu chưa truyền
        if ($soSV === null || $soSV <= 0) {
            $room = Database::fetch("SELECT SoSVDangO FROM Phong WHERE MaPhong = :p", ['p' => $maPhong]);
            $soSV = max(1, (int)($room['SoSVDangO'] ?? 1));
        }

        // Kiểm tra ràng buộc cơ bản: số mới không được nhỏ hơn số cũ
        if ($dienMoi < $dienCu) {
            return [
                'success' => false,
                'message' => "Chỉ số điện mới ({$dienMoi}) không được nhỏ hơn chỉ số cũ ({$dienCu}). Vui lòng kiểm tra lại đồng hồ công tơ."
            ];
        }

        if ($nuocMoi < $nuocCu) {
            return [
                'success' => false,
                'message' => "Chỉ số nước mới ({$nuocMoi}) không được nhỏ hơn chỉ số cũ ({$nuocCu}). Vui lòng kiểm tra lại đồng hồ nước."
            ];
        }

        $calc = self::calculateMonthlyBill($maPhong, $thang, $nam, $dienCu, $dienMoi, $nuocCu, $nuocMoi, $soSV);

        Database::beginTransaction();
        try {
            // Kiểm tra xem phòng trong kỳ tháng/năm này đã có bản ghi chưa
            $existing = Database::fetch(
                "SELECT MaChiSo FROM ChiSoDienNuoc WHERE MaPhong = :p AND Thang = :thang AND Nam = :nam",
                ['p' => $maPhong, 'thang' => $thang, 'nam' => $nam]
            );

            if ($existing) {
                Database::execute(
                    "UPDATE ChiSoDienNuoc
                     SET DienCu = :dc, DienMoi = :dm, NuocCu = :nc, NuocMoi = :nm,
                         SoSVThucOTaiThang = :sv, TienDien = :td, TienNuoc = :tn,
                         CanhBaoBatThuong = :cb
                     WHERE MaChiSo = :id",
                    [
                        'dc' => $dienCu,
                        'dm' => $dienMoi,
                        'nc' => $nuocCu,
                        'nm' => $nuocMoi,
                        'sv' => $soSV,
                        'td' => $calc['tienDien'],
                        'tn' => $calc['tienNuoc'],
                        'cb' => $calc['canhBaoBatThuong'] ? 1 : 0,
                        'id' => $existing['MaChiSo']
                    ]
                );
            } else {
                Database::execute(
                    "INSERT INTO ChiSoDienNuoc (MaPhong, Thang, Nam, DienCu, DienMoi, NuocCu, NuocMoi, SoSVThucOTaiThang, TienDien, TienNuoc, CanhBaoBatThuong)
                     VALUES (:p, :thang, :nam, :dc, :dm, :nc, :nm, :sv, :td, :tn, :cb)",
                    [
                        'p' => $maPhong,
                        'thang' => $thang,
                        'nam' => $nam,
                        'dc' => $dienCu,
                        'dm' => $dienMoi,
                        'nc' => $nuocCu,
                        'nm' => $nuocMoi,
                        'sv' => $soSV,
                        'td' => $calc['tienDien'],
                        'tn' => $calc['tienNuoc'],
                        'cb' => $calc['canhBaoBatThuong'] ? 1 : 0
                    ]
                );
            }

            Database::commit();

            $msg = "Đã lưu thành công số đo điện nước tháng {$thang}/{$nam} cho phòng {$maPhong} (Điện: {$calc['dienTieuThu']} kWh, Nước: {$calc['nuocTieuThu']} m³, Tổng tiền: " . number_format($calc['tongTien'], 0, ',', '.') . " đ).";

            if ($calc['canhBaoBatThuong']) {
                $msg .= " ⚠️ Cảnh báo: " . implode(' | ', $calc['chiTietCanhBao']);
            }

            return [
                'success' => true,
                'message' => $msg,
                'data' => $calc,
                'isAnomaly' => $calc['canhBaoBatThuong'],
                'warnings' => $calc['chiTietCanhBao']
            ];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Lỗi khi lưu chỉ số điện nước: ' . $e->getMessage()];
        }
    }

    /**
     * Phát hành Hóa đơn Điện nước cho 1 phòng
     */
    public static function publishInvoice(string $maPhong, int $thang, int $nam): array {
        $reading = Database::fetch(
            "SELECT * FROM ChiSoDienNuoc WHERE MaPhong = :p AND Thang = :thang AND Nam = :nam",
            ['p' => $maPhong, 'thang' => $thang, 'nam' => $nam]
        );

        if (!$reading) {
            return ['success' => false, 'message' => "Chưa có dữ liệu chỉ số điện nước tháng {$thang}/{$nam} cho phòng {$maPhong}."];
        }

        $tongTien = (float)$reading['TienDien'] + (float)$reading['TienNuoc'];
        if ($tongTien <= 0) {
            return ['success' => false, 'message' => "Tổng tiền điện nước bằng 0, không cần phát hành hóa đơn."];
        }

        // Mã hóa đơn chuẩn: HD-DN-{MaPhongClean}-{NamThang} (ví dụ: HD-DN-A01101-202610)
        $cleanPhong = preg_replace('/[^A-Za-z0-9]/', '', $maPhong);
        $maHoaDon = sprintf('HD-DN-%s-%04d%02d', $cleanPhong, $nam, $thang);

        // Kiểm tra xem hóa đơn này đã được phát hành chưa
        $existing = Database::fetch("SELECT MaHoaDon, TrangThai FROM HoaDon WHERE MaHoaDon = :hd", ['hd' => $maHoaDon]);
        if ($existing) {
            return ['success' => false, 'message' => "Hóa đơn kỳ này đã được phát hành trước đó (Mã: {$maHoaDon}, Trạng thái: {$existing['TrangThai']})."];
        }

        // Tìm sinh viên đại diện nhận hóa đơn (ưu tiên Trưởng phòng, nếu chưa có thì lấy SV đầu tiên đang lưu trú)
        $rep = Database::fetch(
            "SELECT p.TruongPhong, hd.MaSinhVien, hd.MaHopDong
             FROM Phong p
             LEFT JOIN Giuong g ON p.MaPhong = g.MaPhong
             LEFT JOIN HopDongNoiTru hd ON g.MaGiuong = hd.MaGiuong AND hd.TrangThaiHopDong = 'Đang hiệu lực'
             WHERE p.MaPhong = :p
             ORDER BY (hd.MaSinhVien = p.TruongPhong) DESC
             LIMIT 1",
            ['p' => $maPhong]
        );

        $maSV = !empty($rep['TruongPhong']) ? $rep['TruongPhong'] : ($rep['MaSinhVien'] ?? null);
        $maHD = $rep['MaHopDong'] ?? null;

        // Nếu phòng không có SV nào nhưng có hợp đồng cũ gần nhất
        if (!$maSV) {
            $lastResident = Database::fetch(
                "SELECT hd.MaSinhVien, hd.MaHopDong FROM HopDongNoiTru hd
                 JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
                 WHERE g.MaPhong = :p ORDER BY hd.NgayBatDau DESC LIMIT 1",
                ['p' => $maPhong]
            );
            $maSV = $lastResident['MaSinhVien'] ?? null;
            $maHD = $lastResident['MaHopDong'] ?? null;
        }

        Database::beginTransaction();
        try {
            $loaiHD = sprintf('Điện nước tháng %02d/%04d (Phòng %s)', $thang, $nam, $maPhong);

            Database::execute(
                "INSERT INTO HoaDon (MaHoaDon, MaSinhVien, MaPhong, MaHopDong, LoaiHoaDon, SoTienPhaiDong, NgayTao, HanThanhToan, TrangThai)
                 VALUES (:maHDN, :maSV, :maPhong, :maHopDong, :loaiHD, :soTien, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 'Chưa thanh toán')",
                [
                    'maHDN' => $maHoaDon,
                    'maSV' => $maSV,
                    'maPhong' => $maPhong,
                    'maHopDong' => $maHD,
                    'loaiHD' => $loaiHD,
                    'soTien' => $tongTien
                ]
            );

            Database::commit();

            return [
                'success' => true,
                'maHoaDon' => $maHoaDon,
                'soTien' => $tongTien,
                'message' => "Đã phát hành thành công hóa đơn {$maHoaDon} cho phòng {$maPhong} với số tiền " . number_format($tongTien, 0, ',', '.') . " đ. Hạn nộp: 10 ngày."
            ];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Lỗi khi phát hành hóa đơn: ' . $e->getMessage()];
        }
    }

    /**
     * Chốt sổ & Phát hành Hóa đơn Điện nước hàng loạt cho toàn Cụm nhà
     */
    public static function publishClusterInvoices(string $maCum, int $thang, int $nam): array {
        // Lấy danh sách các phòng trong cụm đã ghi số đo trong kỳ mà chưa phát hành hóa đơn
        $rooms = Database::fetchAll(
            "SELECT cs.MaPhong, cs.TienDien, cs.TienNuoc
             FROM ChiSoDienNuoc cs
             JOIN Phong p ON cs.MaPhong = p.MaPhong
             JOIN ToaNha tn ON p.MaToaNha = tn.MaToaNha
             WHERE tn.MaCum = :cum AND cs.Thang = :thang AND cs.Nam = :nam",
            ['cum' => $maCum, 'thang' => $thang, 'nam' => $nam]
        );

        if (empty($rooms)) {
            return ['success' => false, 'message' => "Không tìm thấy phòng nào có số đo điện nước tháng {$thang}/{$nam} thuộc Cụm {$maCum}."];
        }

        $publishedCount = 0;
        $skippedCount = 0;

        foreach ($rooms as $r) {
            $res = self::publishInvoice($r['MaPhong'], $thang, $nam);
            if ($res['success']) {
                $publishedCount++;
            } else {
                $skippedCount++;
            }
        }

        return [
            'success' => true,
            'published' => $publishedCount,
            'skipped' => $skippedCount,
            'message' => "Đã chốt sổ phát hành thành công {$publishedCount} hóa đơn điện nước cho Cụm {$maCum} (Bỏ qua: {$skippedCount} phòng đã có hóa đơn hoặc chưa hợp lệ)."
        ];
    }

    /**
     * Xác nhận thanh toán mô phỏng / Gạch nợ tự động (Demo)
     */
    public static function simulatePayment(string $maHoaDon, string $phuongThuc = 'Mã QR KTX'): array {
        $invoice = Database::fetch("SELECT * FROM HoaDon WHERE MaHoaDon = :hd", ['hd' => $maHoaDon]);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Không tìm thấy thông tin hóa đơn.'];
        }

        if ($invoice['TrangThai'] === 'Đã thanh toán') {
            return ['success' => false, 'message' => "Hóa đơn {$maHoaDon} đã được thanh toán trước đó rồi."];
        }

        Database::beginTransaction();
        try {
            $maGD = 'GD' . date('YmdHis') . strtoupper(substr(uniqid(), -3));
            $maThamChieu = 'QR-DN-' . date('Ymd') . '-' . substr($maHoaDon, -6);

            // 1. Ghi nhận bút toán vào GiaoDichThanhToan
            Database::execute(
                "INSERT INTO GiaoDichThanhToan (MaGiaoDich, MaHoaDon, LoaiGiaoDich, KenhThanhToan, MaThamChieuNganHang, SoTien, ThoiGianGiaoDich, KetQuaGiaoDich)
                 VALUES (:maGD, :maHD, 'Thu tiền', :kenh, :ref, :tien, NOW(), 'Thành công')",
                [
                    'maGD' => $maGD,
                    'maHD' => $maHoaDon,
                    'kenh' => $phuongThuc,
                    'ref' => $maThamChieu,
                    'tien' => $invoice['SoTienPhaiDong']
                ]
            );

            // 2. Chuyển trạng thái Hóa đơn sang 'Đã thanh toán'
            Database::execute(
                "UPDATE HoaDon SET TrangThai = 'Đã thanh toán' WHERE MaHoaDon = :hd",
                ['hd' => $maHoaDon]
            );

            // 3. Nếu là hóa đơn lệ phí phòng (HD-HS... hoặc Lệ phí chỗ ở) -> Kích hoạt hợp đồng nội trú
            if (str_starts_with($maHoaDon, 'HD-HS')) {
                $maHoSo = substr($maHoaDon, 3);
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
                            ['maHD' => $maHopDong, 'hd' => $maHoaDon]
                        );
                    }
                }
            }

            Database::commit();

            return [
                'success' => true,
                'maGiaoDich' => $maGD,
                'soTien' => $invoice['SoTienPhaiDong'],
                'message' => "Xác nhận thanh toán mô phỏng thành công cho hóa đơn {$maHoaDon} (Mã GD: {$maGD})! Hệ thống đã gạch nợ tức thì số tiền " . number_format($invoice['SoTienPhaiDong'], 0, ',', '.') . " đ."
            ];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Lỗi khi xác nhận thanh toán: ' . $e->getMessage()];
        }
    }
}
