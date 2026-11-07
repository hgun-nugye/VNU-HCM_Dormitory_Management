<?php
ob_start();
$hoaDons = $hoaDons ?? [];
$chiSoDienNuoc = $chiSoDienNuoc ?? [];
$giaoDichs = $giaoDichs ?? [];
$tongCongNo = $tongCongNo ?? 0;
$serverIp = $serverIp ?? (function_exists('get_local_server_ip') ? get_local_server_ip() : '127.0.0.1');
$serverPortSuffix = $serverPortSuffix ?? '';

$unpaidInvoices = array_filter($hoaDons, fn($hd) => ($hd['TrangThai'] ?? '') !== 'Đã thanh toán');
$paidCount = count($hoaDons) - count($unpaidInvoices);

$latestUtility = !empty($chiSoDienNuoc) ? $chiSoDienNuoc[0] : null;
?>

<div class="container">
    <div style="display: flex; gap: 8px; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 14px;">
        <a href="<?= url('/') ?>" style="color: var(--text-muted); text-decoration: none;">Trang chủ</a>
        <span>/</span>
        <span style="color: var(--text-primary); font-weight: 600;">Thông tin thanh toán</span>
    </div>

    <?php if (!empty($success)): ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 14px 18px; border-radius: 8px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; font-size: 0.95rem;">
            <span style="font-size: 1.25rem;">✅</span>
            <div><?= $success ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; font-size: 0.95rem;">
            <span style="font-size: 1.25rem;">⚠️</span>
            <div><?= $error ?></div>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Thông tin thanh toán & Cổng thu lệ phí</h1>
            <p style="color: var(--text-muted); margin: 4px 0 0; font-size: 0.875rem;">
                Hỗ trợ thanh toán tự động qua App ngân hàng Vietcombank (VCB Digibank QR) hoặc nộp tiền mặt tại phòng Kế toán.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-4" style="margin-bottom: 24px;">
        <div class="card stat-card" style="border-top: 3px solid <?= $tongCongNo > 0 ? '#ef4444' : '#10b981' ?>;">
            <span class="stat-label">Tổng công nợ hiện tại</span>
            <div class="stat-val" style="font-size: 1.45rem; color: <?= $tongCongNo > 0 ? '#b91c1c' : '#15803d' ?>;">
                <?= format_money($tongCongNo) ?>
            </div>
            <span class="stat-sub">
                <?= count($unpaidInvoices) > 0 ? count($unpaidInvoices) . ' hóa đơn cần thanh toán' : 'Không còn công nợ quá hạn' ?>
            </span>
        </div>

        <div class="card stat-card" style="border-top: 3px solid #3b82f6;">
            <span class="stat-label">Điện nước kỳ mới nhất</span>
            <div class="stat-val" style="font-size: 1.45rem; color: #1e40af;">
                <?php if ($latestUtility): ?>
                    <?= format_money($latestUtility['TienMoiSV'] ?? ((($latestUtility['TienDien'] ?? 0) + ($latestUtility['TienNuoc'] ?? 0)) / max(1, (int)($latestUtility['SoSVThucOTaiThang'] ?? 1)))) ?>
                <?php else: ?>
                    0 đ
                <?php endif; ?>
            </div>
            <span class="stat-sub">
                <?= $latestUtility ? 'Kỳ tháng ' . $latestUtility['Thang'] . '/' . $latestUtility['Nam'] : 'Chưa có kỳ chỉ số mới' ?>
            </span>
        </div>

        <div class="card stat-card" style="border-top: 3px solid #10b981;">
            <span class="stat-label">Hóa đơn đã hoàn thành</span>
            <div class="stat-val" style="font-size: 1.45rem; color: #10b981;">
                <?= $paidCount ?>
            </div>
            <span class="stat-sub">
                Đã đối soát vào hệ thống
            </span>
        </div>

        <div class="card stat-card" style="border-top: 3px solid #005a3c;">
            <span class="stat-label">Lịch sử giao dịch VCB</span>
            <div class="stat-val" style="font-size: 1.45rem; color: #005a3c;">
                <?= count($giaoDichs) ?>
            </div>
            <span class="stat-sub">
                Giao dịch đối soát tức thì
            </span>
        </div>
    </div>

    <!-- DANH SÁCH HÓA ĐƠN -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Danh sách hóa đơn lệ phí</span>
            <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">
                Tự động xác nhận thanh toán ngay khi quét mã thành công
            </span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã hóa đơn</th>
                        <th>Khoản thu</th>
                        <th>Kỳ thanh toán</th>
                        <th>Số tiền phải đóng</th>
                        <th>Hạn nộp</th>
                        <th>Trạng thái</th>
                        <th style="text-align: right;">Hành động thanh toán</th>
                    </tr>
                </thead>
                <tbody id="invoiceTableBody">
                    <?php if (empty($hoaDons)): ?>
                        <tr>
                            <td colspan="7" class="text-muted" style="text-align: center; padding: 24px 0;">
                                Hiện tại bạn chưa có hóa đơn lệ phí nào phát sinh.
                            </td>
                        </tr>
                        <?php else: foreach ($hoaDons as $hd): ?>
                            <tr class="invoice-row" id="invoice-row-<?= htmlspecialchars($hd['MaHoaDon']) ?>">
                                <td><code style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($hd['MaHoaDon']) ?></code></td>
                                <td>
                                    <b><?= htmlspecialchars($hd['LoaiHoaDon']) ?></b>
                                    <?php if (!empty($hd['GhiChu'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($hd['GhiChu']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($hd['KyThanhToan'] ?? 'Định kỳ') ?></td>
                                <td><b style="font-size: 0.95rem; color: var(--text-primary);"><?= format_money($hd['SoTienPhaiDong']) ?></b></td>
                                <td>
                                    <?= !empty($hd['HanThanhToan']) ? date('d/m/Y', strtotime($hd['HanThanhToan'])) : '-' ?>
                                </td>
                                <td>
                                    <?php if (($hd['TrangThai'] ?? '') === 'Đã thanh toán'): ?>
                                        <span class="badge badge-success" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; font-weight:700;">Đã thanh toán</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; font-weight:700;"> Chưa thanh toán</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if (($hd['TrangThai'] ?? '') !== 'Đã thanh toán'): ?>
                                        <button type="button" class="btn btn-primary btn-sm btn-open-vcb-pay" 
                                                data-invoice-id="<?= htmlspecialchars($hd['MaHoaDon']) ?>"
                                                data-invoice-type="<?= htmlspecialchars($hd['LoaiHoaDon'] ?? 'Lệ phí KTX') ?>"
                                                data-amount="<?= (float)$hd['SoTienPhaiDong'] ?>"
                                                onclick="openVcbModal(this.getAttribute('data-invoice-id'), this.getAttribute('data-invoice-type'), parseFloat(this.getAttribute('data-amount')))"
                                                style="background: #005a3c; border-color: #005a3c; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; box-shadow: 0 2px 4px rgba(0,90,60,0.2); cursor: pointer;">
                                            Thanh toán
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #059669; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">Đã tất toán</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>

        <div id="invoicePaginationBar" style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 10px;">
            <span id="invoicePaginationInfo" style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;"></span>
            <div id="invoicePaginationControls" style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;"></div>
        </div>
    </div>

    <!-- BẢNG ĐIỆN NƯỚC -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-title">
            <span>Bảng theo dõi điện - nước phòng ở định kỳ</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kỳ tháng</th>
                        <th>Phòng / Tòa</th>
                        <th>Điện</th>
                        <th>Số kWh</th>
                        <th>Tiền điện</th>
                        <th>Nước</th>
                        <th>Số m³</th>
                        <th>Tiền nước</th>
                        <th>Tổng tiền</th>
                        <th>Số người</th>
                        <th>Chia mỗi SV</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($chiSoDienNuoc)): ?>
                        <tr>
                            <td colspan="11" class="text-muted" style="text-align: center; padding: 24px 0;">
                                Chưa có dữ liệu chỉ số điện nước cho phòng đang lưu trú.
                            </td>
                        </tr>
                        <?php else: foreach ($chiSoDienNuoc as $cs):
                            $dienTieuThu = $cs['DienTieuThu'] ?? ($cs['DienMoi'] - $cs['DienCu']);
                            $nuocTieuThu = $cs['NuocTieuThu'] ?? ($cs['NuocMoi'] - $cs['NuocCu']);
                            $tongTien = ($cs['TienDien'] ?? 0) + ($cs['TienNuoc'] ?? 0);
                            $tienMoiSV = $cs['TienMoiSV'] ?? (!empty($cs['SoSVThucOTaiThang']) ? ($tongTien / $cs['SoSVThucOTaiThang']) : 0);
                        ?>
                            <tr>
                                <td><b>Tháng <?= $cs['Thang'] ?>/<?= $cs['Nam'] ?></b></td>
                                <td>Phòng <?= htmlspecialchars($cs['SoPhong'] ?? '304') ?> (<?= htmlspecialchars($cs['TenToaNha'] ?? 'Tòa A01') ?>)</td>
                                <td><?= number_format($cs['DienCu']) ?> &rarr; <?= number_format($cs['DienMoi']) ?></td>
                                <td><span class="badge badge-info"><?= number_format($dienTieuThu) ?> kWh</span></td>
                                <td><?= format_money($cs['TienDien']) ?></td>
                                <td><?= number_format($cs['NuocCu']) ?> &rarr; <?= number_format($cs['NuocMoi']) ?></td>
                                <td><span class="badge badge-info"><?= number_format($nuocTieuThu) ?> m³</span></td>
                                <td><?= format_money($cs['TienNuoc']) ?></td>
                                <td><?= format_money($cs['TienNuoc'] + $cs['TienDien']) ?></td>
                                <td><?= $cs['SoSVThucOTaiThang'] ?> người</td>
                                <td><b style="color: var(--primary); font-size: 0.95rem;"><?= format_money($tienMoiSV) ?></b></td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LỊCH SỬ GIAO DỊCH -->
    <div class="card">
        <div class="card-title">
            <span>Lịch sử giao dịch thanh toán đã ghi nhận</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã giao dịch</th>
                        <th>Mã hóa đơn</th>
                        <th>Khoản thu</th>
                        <th>Kênh thanh toán</th>
                        <th>Thời gian giao dịch</th>
                        <th>Số tiền</th>
                        <th>Kết quả</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($giaoDichs)): ?>
                        <tr>
                            <td colspan="7" class="text-muted" style="text-align: center; padding: 20px 0;">
                                Chưa có giao dịch thanh toán nào được ghi nhận.
                            </td>
                        </tr>
                        <?php else: foreach ($giaoDichs as $gd): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($gd['MaGiaoDich']) ?></code></td>
                                <td><b><?= htmlspecialchars($gd['MaHoaDon']) ?></b></td>
                                <td><?= htmlspecialchars($gd['LoaiHoaDon'] ?? 'Lệ phí') ?></td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:4px; font-weight:600; color:#005a3c;"><?= htmlspecialchars($gd['KenhThanhToan']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($gd['ThoiGianGiaoDich']) ?></td>
                                <td><b><?= format_money($gd['SoTien']) ?></b></td>
                                <td>
                                    <span class="badge badge-success" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0;">
                                        <?= htmlspecialchars($gd['KetQuaGiaoDich']) ?>
                                    </span>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- HƯỚNG DẪN NỘP TIỀN MẶT -->
    <div id="payment-instruction" class="card" style="margin-top: 24px; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border: 1px solid #bbf7d0;">
        <div class="card-title">
            <span style="color: #005a3c; display: flex; align-items: center; gap: 8px;">
                Phương thức Nộp tiền mặt trực tiếp tại Phòng Kế toán
            </span>
        </div>

        <div class="payment-info-grid" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; align-items: stretch;">
            <div class="payment-info-box" style="padding: 18px; background: #ffffff; border: 1px solid #dcfce7; border-radius: 10px; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; display: flex; flex-direction: column;">
                <div style="font-weight: 700; color: #005a3c; margin-bottom: 12px;">Địa điểm tiếp nhận nộp tiền mặt</div>
                <div style="background: #f8fafc; padding: 14px 16px; border-radius: 8px; border: 1px solid #e2e8f0; flex: 1;">
                    <div style="margin-bottom: 8px;">• <b>Khu A:</b> Văn phòng Ban Quản lý Cụm A (Tầng trệt Tòa A01).</div>
                    <div style="margin-bottom: 8px;">• <b>Khu B:</b> Văn phòng Tài vụ Trung tâm KTX Khu B (Nhà Điều hành).</div>
                    <div style="margin-bottom: 8px;">• <b>Thời gian làm việc:</b> Thứ 2 &ndash; Thứ 6 (07:30 &ndash; 17:00).</div>
                </div>
            </div>

            <div class="payment-info-box" style="padding: 18px; background: #ffffff; border: 1px solid #dcfce7; border-radius: 10px; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.7; display: flex; flex-direction: column;">
                <div style="font-weight: 700; color: #005a3c; margin-bottom: 12px;">Quy định đối soát & Nhận phòng</div>
                <div style="background: #f8fafc; padding: 14px 16px; border-radius: 8px; border: 1px solid #e2e8f0; flex: 1;">
                    <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                        <li style="margin-bottom: 10px;">Thanh toán bằng <b>App VCB Digibank quét QR</b>: Hệ thống <b>tự động xác nhận thanh toán ngay lập tức</b>.</li>
                        <li style="margin-bottom: 10px;">Ngay khi hóa đơn chuyển sang <b>Đã thanh toán</b>, hợp đồng nội trú được kích hoạt tự động.</li>
                        <li>Trưởng Tòa sẽ đối soát và bấm <b>"Xác nhận nhận phòng"</b> khi bạn đến KTX bàn giao thủ tục cư trú (Mẫu CT01).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL THANH TOÁN ĐA CỔNG (VCB DIGIBANK QR & CÁC CỔNG MINH HỌA)        -->
<!-- ====================================================================== -->
<div id="vcbPaymentModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 16px;">
    <div style="background: #ffffff; width: 100%; max-width: 820px; max-height: 92vh; overflow-y: auto; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 2px solid #005a3c; animation: modalFadeIn 0.3s ease-out; display: flex; flex-direction: column;">
        
        <!-- Header Cổng Thanh Toán -->
        <div style="background: linear-gradient(135deg, #005a3c 0%, #007a4d 100%); color: #ffffff; padding: 16px 22px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; border-radius: 14px 14px 0 0;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(255, 255, 255, 0.15); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: #ffffff;">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; letter-spacing: 0.2px;">Cổng Thanh Toán Trực Tuyến</h3>
                    <p style="margin: 2px 0 0; font-size: 0.78rem; opacity: 0.9;">Hệ thống thanh toán tự động 24/7 &bull; KTX ĐHQG-HCM</p>
                </div>
            </div>
            <button type="button" onclick="closeVcbModal()" style="background: rgba(255, 255, 255, 0.15); border: none; color: #ffffff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; cursor: pointer; line-height: 1; transition: background 0.2s;" title="Đóng cửa sổ">&times;</button>
        </div>

        <!-- Body Modal: 2 Cột (Trái: Mã QR & Thông tin thanh toán | Phải: Chọn hình thức thanh toán) -->
        <div class="vcb-modal-grid" style="display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 20px; padding: 22px; flex: 1;">
            
            <!-- CỘT TRÁI: THÔNG TIN HÓA ĐƠN & MÃ QR THANH TOÁN -->
            <div style="display: flex; flex-direction: column; justify-content: space-between;">
                <!-- Thông tin tóm tắt -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #005a3c; border-radius: 10px; padding: 12px 16px; margin-bottom: 12px; font-size: 0.85rem; line-height: 1.6;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span style="color: #64748b;">Mã hóa đơn:</span>
                        <code style="color: #005a3c; font-weight: 700; font-size: 0.9rem;" id="vcbModalInvoiceId">HD-HS...</code>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span style="color: #64748b;">Khoản thu:</span>
                        <span id="vcbModalInvoiceType" style="font-weight: 600; color: #1e293b;">Lệ phí phòng</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 6px; border-top: 1px dashed #cbd5e1; margin-top: 6px;">
                        <span style="color: #0f172a; font-weight: 700;">Số tiền thanh toán:</span>
                        <strong style="color: #005a3c; font-size: 1.35rem; font-weight: 800; font-family: monospace;" id="vcbModalAmount">0 đ</strong>
                    </div>
                </div>

                <!-- Khung Mã QR -->
                <div style="text-align: center; margin-bottom: 8px;">
                    <div id="vcbQrWrapper" style="display: inline-block; padding: 12px; background: #ffffff; border: 2px dashed #005a3c; border-radius: 16px; box-shadow: 0 4px 14px rgba(0,90,60,0.08); position: relative;">
                        <img id="vcbQrImage" src="" alt="Mã QR VCB Digibank" style="width: 195px; height: 195px; display: block; border-radius: 10px;">
                    </div>

                    <div style="margin-top: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.82rem; color: #005a3c; font-weight: 600; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 5px 14px; border-radius: 20px;">
                        <span class="vcb-pulse-dot"></span>
                        <span>Mở App VCB Digibank trên điện thoại để quét mã</span>
                    </div>
                    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 5px;">
                        (Hệ thống tự động đồng bộ thời gian thực &bull; Tự động đóng khi hoàn tất)
                    </div>
                </div>

                <!-- Badge bảo mật & chuẩn VietQR cân đối bố cục -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; gap: 10px; margin-top: 6px;">
                    <div style="width: 30px; height: 30px; border-radius: 6px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; line-height: 1.4;">
                        Cổng thanh toán tự động theo tiêu chuẩn <b>VietQR</b>. Kết quả giao dịch được cập nhật tức thì.
                    </div>
                </div>
            </div>

            <!-- CỘT PHẢI: CHỌN HÌNH THỨC THANH TOÁN -->
            <div style="display: flex; flex-direction: column; justify-content: space-between; border-left: 1px solid #e2e8f0; padding-left: 20px;" class="vcb-right-col">
                <div>
                    <h4 style="margin: 0 0 12px; font-size: 0.95rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">Chọn phương thức thanh toán</h4>
                    <!-- Danh sách hình thức thanh toán -->
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        
                        <!-- 1. VCB Digibank QR (Khuyên dùng - Đang hoạt động) -->
                        <label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 10px; border: 2px solid #005a3c; background: #f0fdf4; cursor: pointer; position: relative; box-shadow: 0 2px 6px rgba(0,90,60,0.06);">
                            <input type="radio" name="payment_method_choice" value="vcb" checked style="margin-top: 3px; accent-color: #005a3c;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="font-weight: 700; font-size: 0.88rem; color: #005a3c; display: flex; align-items: center; gap: 6px;">
                                        <span>🏦 VCB Digibank QR Pay</span>
                                    </div>
                                    <span style="font-size: 0.68rem; background: #005a3c; color: #ffffff; padding: 2px 8px; border-radius: 10px; font-weight: 700;">Đang chọn</span>
                                </div>
                                <div style="font-size: 0.75rem; color: #065f46; margin-top: 3px; line-height: 1.4;">
                                    Quét mã QR tự động 24/7 qua App VCB Digibank hoặc ứng dụng Ngân hàng. Thanh toán tự động tức thì.
                                </div>
                            </div>
                        </label>

                        <!-- 2. Ví điện tử MoMo (Minh họa) -->
                        <label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 10px; border: 1px solid #e2e8f0; background: #ffffff; cursor: not-allowed; opacity: 0.7; position: relative;">
                            <input type="radio" name="payment_method_choice" value="momo" disabled style="margin-top: 3px;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="font-weight: 600; font-size: 0.88rem; color: #a50064; display: flex; align-items: center; gap: 6px;">
                                        <span>🟣 Ví điện tử MoMo</span>
                                    </div>
                                    <span style="font-size: 0.68rem; background: #f1f5f9; color: #64748b; padding: 2px 7px; border-radius: 8px;">Sắp hỗ trợ</span>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 3px; line-height: 1.4;">
                                    Thanh toán tiện lợi qua mã QR Ví điện tử MoMo.
                                </div>
                            </div>
                        </label>

                        <!-- 3. Cổng VNPAY / Thẻ ATM (Minh họa) -->
                        <label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 10px; border: 1px solid #e2e8f0; background: #ffffff; cursor: not-allowed; opacity: 0.7; position: relative;">
                            <input type="radio" name="payment_method_choice" value="vnpay" disabled style="margin-top: 3px;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="font-weight: 600; font-size: 0.88rem; color: #005baa; display: flex; align-items: center; gap: 6px;">
                                        <span>💳 Cổng VNPAY / Thẻ ATM</span>
                                    </div>
                                    <span style="font-size: 0.68rem; background: #f1f5f9; color: #64748b; padding: 2px 7px; border-radius: 8px;">Sắp hỗ trợ</span>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 3px; line-height: 1.4;">
                                    Thẻ nội địa NAPAS & thẻ quốc tế Visa, MasterCard, JCB.
                                </div>
                            </div>
                        </label>

                        <!-- 4. Tiền mặt tại Kế toán (Minh họa) -->
                        <label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 10px; border: 1px solid #e2e8f0; background: #ffffff; cursor: not-allowed; opacity: 0.7; position: relative;">
                            <input type="radio" name="payment_method_choice" value="cash" disabled style="margin-top: 3px;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="font-weight: 600; font-size: 0.88rem; color: #b45309; display: flex; align-items: center; gap: 6px;">
                                        <span>💵 Tiền mặt tại phòng Kế toán</span>
                                    </div>
                                    <span style="font-size: 0.68rem; background: #fefce8; color: #a16207; border: 1px solid #fef08a; padding: 1px 6px; border-radius: 8px;">Trực tiếp</span>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 3px; line-height: 1.4;">
                                    Nộp tiền trực tiếp tại Văn phòng BQL Cụm A hoặc Khu B để Kế toán thu tiền.
                                </div>
                            </div>
                        </label>
                    </div>

                    <div style="margin-top: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; font-size: 0.75rem; color: #64748b; line-height: 1.5;">
                        <b style="color: #005a3c;">Ghi chú:</b> Hiện tại phương thức thanh toán tự động qua <b>VCB Digibank QR Pay</b> đang hoạt động. Sinh viên chỉ cần quét mã QR ở bên trái để hoàn tất giao dịch.
                    </div>
                </div>

                <!-- Nút Đóng -->
                <div style="text-align: right; margin-top: 16px;">
                    <button type="button" onclick="closeVcbModal()" class="btn btn-outline btn-sm" style="border-radius: 8px; padding: 7px 24px; font-size: 0.85rem; color: #475569; font-weight: 600; border-color: #cbd5e1; background: #ffffff;">
                        Đóng cửa sổ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-12px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.vcb-pulse-dot {
    width: 8px;
    height: 8px;
    background-color: #10b981;
    border-radius: 50%;
    display: inline-block;
    animation: vcbPulse 1.5s infinite;
}
@keyframes vcbPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
@media (max-width: 768px) {
    .vcb-modal-grid {
        grid-template-columns: 1fr !important;
    }
    .vcb-right-col {
        border-left: none !important;
        border-top: 1px solid #e2e8f0 !important;
        padding-left: 0 !important;
        padding-top: 18px !important;
    }
}
</style>

<script>
    // ==========================================
    // LOGIC MODAL VCB DIGIBANK & POLLING
    // ==========================================
    let vcbPollingTimer = null;
    let activeInvoiceId = null;

    function openVcbModal(invoiceId, invoiceType, amount) {
        activeInvoiceId = invoiceId;

        const invIdEl = document.getElementById('vcbModalInvoiceId');
        if (invIdEl) invIdEl.innerText = invoiceId;

        const formInvEl = document.getElementById('vcbFormInvoiceId');
        if (formInvEl) formInvEl.value = invoiceId;

        const invTypeEl = document.getElementById('vcbModalInvoiceType');
        if (invTypeEl) invTypeEl.innerText = invoiceType || 'Lệ phí KTX';

        const amtEl = document.getElementById('vcbModalAmount');
        if (amtEl) amtEl.innerText = new Intl.NumberFormat('vi-VN').format(amount) + ' đ';

        // Chuẩn bị dữ liệu QR code cho App Android Vietcombank quét
        const serverHost = "<?= htmlspecialchars($serverIp . $serverPortSuffix) ?>";
        const qrPayload = JSON.stringify({
            bank: "VCB_DIGIBANK",
            action: "KTX_PAYMENT",
            invoice_id: invoiceId,
            amount: amount,
            description: "Thanh toan " + invoiceId,
            receiver: "KTX DHQG-HCM",
            confirm_url: "http://" + serverHost + "/Dormitory_Management/api/payment/vcb-confirm"
        });

        // Tạo mã QR bằng API qrcode chuẩn (dung lượng nhẹ, sắc nét)
        const qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=6&data=" + encodeURIComponent(qrPayload);
        const qrImg = document.getElementById('vcbQrImage');
        if (qrImg) qrImg.src = qrUrl;

        // Mở Modal
        const modal = document.getElementById('vcbPaymentModal');
        if (modal) modal.style.display = 'flex';

        // Bắt đầu Polling thời gian thực: kiểm tra mỗi 2 giây xem điện thoại đã trả tiền chưa
        startVcbPolling(invoiceId);
    }

    function closeVcbModal() {
        stopVcbPolling();
        const modal = document.getElementById('vcbPaymentModal');
        if (modal) modal.style.display = 'none';
        activeInvoiceId = null;
    }

    // Đóng modal khi click vào vùng tối bên ngoài modal hoặc bấm Escape
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('vcbPaymentModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeVcbModal();
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeVcbModal();
            }
        });
    });

    function startVcbPolling(invoiceId) {
        stopVcbPolling();
        vcbPollingTimer = setInterval(function() {
            if (!invoiceId) return;

            fetch("<?= url('api/payment/check-status') ?>?invoice=" + encodeURIComponent(invoiceId))
                .then(res => res.json())
                .then(data => {
                    if (data && data.success && data.data && data.data.is_paid) {
                        // Điện thoại vừa quét và thanh toán thành công!
                        stopVcbPolling();
                        // Tắt pop up ngay lập tức mà không cần hiện dấu tick xanh
                        closeVcbModal();
                        // Reload trang để hiển thị trạng thái Đã thanh toán
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.log("Polling checking payment...", err);
                });
        }, 2000);
    }

    function stopVcbPolling() {
        if (vcbPollingTimer) {
            clearInterval(vcbPollingTimer);
            vcbPollingTimer = null;
        }
    }

    // ==========================================
    // PHÂN TRANG DANH SÁCH HÓA ĐƠN
    // ==========================================
    const INVOICES_PER_PAGE = 5;
    let invoiceCurrentPage = 1;

    function renderInvoicePagination() {
        const rows = Array.from(document.querySelectorAll('#invoiceTableBody tr.invoice-row'));
        const total = rows.length;
        const infoEl = document.getElementById('invoicePaginationInfo');
        const controlsEl = document.getElementById('invoicePaginationControls');
        const barEl = document.getElementById('invoicePaginationBar');

        if (total <= INVOICES_PER_PAGE) {
            rows.forEach(row => { row.style.display = ''; });
            if (barEl) barEl.style.display = 'none';
            return;
        }
        if (barEl) barEl.style.display = 'flex';

        const totalPages = Math.ceil(total / INVOICES_PER_PAGE);
        if (invoiceCurrentPage > totalPages) invoiceCurrentPage = totalPages;
        if (invoiceCurrentPage < 1) invoiceCurrentPage = 1;

        const startIdx = (invoiceCurrentPage - 1) * INVOICES_PER_PAGE;
        const endIdx = startIdx + INVOICES_PER_PAGE;

        rows.forEach((row, idx) => {
            row.style.display = (idx >= startIdx && idx < endIdx) ? '' : 'none';
        });

        if (infoEl) {
            const displayStart = startIdx + 1;
            const displayEnd = Math.min(endIdx, total);
            infoEl.innerHTML = `Hiển thị <b>${displayStart} - ${displayEnd}</b> trên tổng số <b>${total}</b> hóa đơn (Trang <b>${invoiceCurrentPage}</b> / <b>${totalPages}</b>)`;
        }

        if (!controlsEl) return;
        if (totalPages <= 1) {
            controlsEl.innerHTML = '';
            return;
        }

        let html = '';
        const disabledPrev = invoiceCurrentPage === 1 ? 'disabled style="opacity: 0.45; cursor: not-allowed; padding: 5px 10px; border-radius: 6px;"' : 'style="padding: 5px 10px; border-radius: 6px;"';
        html += `<button type="button" class="btn btn-outline btn-sm" onclick="goToInvoicePage(${invoiceCurrentPage - 1})" ${disabledPrev}>&larr; Trước</button>`;

        for (let p = 1; p <= totalPages; p++) {
            if (p === invoiceCurrentPage) {
                html += `<button type="button" class="btn btn-primary btn-sm" style="background:#005a3c; border-color:#005a3c; padding: 5px 10px; border-radius: 6px; font-weight: 700;">${p}</button>`;
            } else {
                html += `<button type="button" class="btn btn-outline btn-sm" onclick="goToInvoicePage(${p})" style="padding: 5px 10px; border-radius: 6px;">${p}</button>`;
            }
        }

        const disabledNext = invoiceCurrentPage === totalPages ? 'disabled style="opacity: 0.45; cursor: not-allowed; padding: 5px 10px; border-radius: 6px;"' : 'style="padding: 5px 10px; border-radius: 6px;"';
        html += `<button type="button" class="btn btn-outline btn-sm" onclick="goToInvoicePage(${invoiceCurrentPage + 1})" ${disabledNext}>Sau &rarr;</button>`;

        controlsEl.innerHTML = html;
    }

    function goToInvoicePage(page) {
        invoiceCurrentPage = page;
        renderInvoicePagination();
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderInvoicePagination();
    });
</script>

<?php
$content = ob_get_clean();
$title = 'Thông tin thanh toán - KTX ĐHQG-HCM';
require dirname(__DIR__) . '/layouts/main.php';
