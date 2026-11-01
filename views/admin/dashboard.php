<?php
ob_start();
?>

<div class="container">
    <!-- Header -->
    <div class="page-header" style="margin-bottom: 24px;">
        <div>
            <h1 class="page-title">Quản trị hệ thống KTX ĐHQG-HCM</h1>
            <p class="page-subtitle">Bảng điều khiển trung tâm &bull; Toàn quyền quản trị cơ sở vật chất, nhân sự và tài chính toàn KTX</p>
        </div>
        <div>
            <span class="badge badge-info" style="font-size: 0.85rem; padding: 6px 14px;">
                Quản trị viên toàn hệ thống
            </span>
        </div>
    </div>

    <!-- Chỉ số toàn hệ thống -->
    <div class="grid grid-cols-4" style="gap: 18px; margin-bottom: 28px;">
        <div class="card stat-card" style="border-left: 4px solid #2563eb;">
            <div class="stat-label">Tổng sinh viên</div>
            <div class="stat-val" style="color: #1e40af;"><?= number_format($stats['total_students'] ?? 0) ?></div>
            <div class="stat-sub">Hồ sơ đã tạo tài khoản</div>
        </div>
        <div class="card stat-card" style="border-left: 4px solid #10b981;">
            <div class="stat-label">Hợp đồng hiệu lực</div>
            <div class="stat-val" style="color: #15803d;"><?= number_format($stats['active_residents'] ?? 0) ?></div>
            <div class="stat-sub">Đang lưu trú nội trú</div>
        </div>
        <div class="card stat-card" style="border-left: 4px solid #7c3aed;">
            <div class="stat-label">Tổng sức chứa giường</div>
            <div class="stat-val" style="color: #6d28d9;"><?= number_format($stats['total_beds'] ?? 0) ?></div>
            <div class="stat-sub">Khu A và Khu B</div>
        </div>
        <div class="card stat-card" style="border-left: 4px solid #f59e0b;">
            <div class="stat-label">Cán bộ Ban Quản lý</div>
            <div class="stat-val" style="color: #b45309;"><?= number_format($stats['total_staff'] ?? 0) ?></div>
            <div class="stat-sub">Phụ trách các khu & cụm</div>
        </div>
    </div>

    <!-- PHÂN HỆ 1: QUẢN TRỊ TÀI KHOẢN & PHÂN QUYỀN CÁN BỘ -->
    <div id="phan-quyen" class="card" style="margin-bottom: 24px;">
        <div class="card-title">
            <span>1. Quản lý tài khoản cán bộ & Phân quyền phụ trách</span>
            <a href="<?= url('admin/users') ?>" class="btn btn-primary btn-sm" style="border-radius: 6px; text-decoration: none;">
                + Thêm cán bộ mới
            </a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tên đăng nhập</th>
                        <th>Họ và tên cán bộ</th>
                        <th>Vai trò phân quyền</th>
                        <th>Phạm vi được giao phụ trách</th>
                        <th>Số điện thoại</th>
                        <th>Trạng thái</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($canBoList as $cb): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($cb['TenDangNhap']) ?></b></td>
                            <td><?= htmlspecialchars($cb['HoVaTen'] ?? '') ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($cb['TenVaiTro']) ?></span></td>
                            <td><b><?= htmlspecialchars($cb['MaCumQuanLy'] ?? 'Toàn hệ thống') ?></b></td>
                            <td><?= htmlspecialchars($cb['SoDienThoai'] ?? '') ?></td>
                            <td>
                                <span class="badge badge-success">Đang hoạt động</span>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?= url('admin/users') ?>" class="btn btn-outline btn-sm" style="border-radius: 6px; text-decoration: none;">
                                    Phân quyền
                                </a>
                                <a href="<?= url('admin/users') ?>" class="btn btn-outline btn-sm" style="border-radius: 6px; text-decoration: none;">
                                    Quản lý
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PHÂN HỆ 2: CƠ SỞ VẬT CHẤT & HẠ TẦNG KTX -->
    <div id="co-so-vat-chat" class="card" style="margin-bottom: 24px;">
        <div class="card-title">
            <span>2. Danh mục Cụm nhà, Tòa nhà & Sức chứa phòng</span>
            <a href="<?= url('admin/rooms') ?>" class="btn btn-primary btn-sm" style="border-radius: 6px; text-decoration: none;">
                + Thêm tòa / cụm & phòng
            </a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã cụm</th>
                        <th>Tên cụm nhà</th>
                        <th>Khu KTX</th>
                        <th>Số tòa nhà</th>
                        <th>Tổng số phòng</th>
                        <th>Tổng sức chứa giường</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cumNhaList as $c): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($c['MaCum']) ?></b></td>
                            <td><?= htmlspecialchars($c['TenCum']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($c['TenKhu'] ?? 'Khu A') ?></span></td>
                            <td><b><?= $c['TongToa'] ?></b> tòa</td>
                            <td><b><?= $c['TongPhong'] ?></b> phòng</td>
                            <td><b><?= $c['TongGiuong'] ?></b> giường</td>
                            <td style="text-align: right;">
                                <a href="<?= url('admin/rooms') ?>" class="btn btn-outline btn-sm" style="border-radius: 6px; text-decoration: none;">
                                    Cấu hình phòng
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PHÂN HỆ 3: CẤU HÌNH ĐỢT ĐĂNG KÝ & HẠN GIỮ CHỖ 72H -->
    <div id="dot-dang-ky" class="card" style="margin-bottom: 24px;">
        <div class="card-title">
            <span>3. Cấu hình đợt đăng ký lưu trú & Hạn định giữ chỗ</span>
            <a href="<?= url('admin/periods') ?>" class="btn btn-primary btn-sm" style="border-radius: 6px; text-decoration: none;">
                + Mở đợt đăng ký mới
            </a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tên đợt tuyển sinh</th>
                        <th>Năm học</th>
                        <th>Thời gian mở cổng</th>
                        <th>Thời gian đóng cổng</th>
                        <th>Hạn định giữ chỗ</th>
                        <th>Trạng thái</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dotDangKyList as $d): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($d['TenDot']) ?></b></td>
                            <td><?= htmlspecialchars($d['NamHoc']) ?></td>
                            <td><?= $d['ThoiGianMoCong'] ?></td>
                            <td><?= $d['ThoiGianDongCong'] ?></td>
                            <td><span class="badge badge-info"><?= $d['SoGioGiuCho'] ?> giờ</span></td>
                            <td><span class="badge badge-success">Đang mở</span></td>
                            <td style="text-align: right;">
                                <a href="<?= url('admin/periods') ?>" class="btn btn-outline btn-sm" style="border-radius: 6px; text-decoration: none;">
                                    Chỉnh sửa
                                </a>
                                <a href="<?= url('admin/periods') ?>" class="btn btn-outline btn-sm" style="border-radius: 6px; text-decoration: none;">
                                    Quản lý đợt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PHÂN HỆ 4: DANH MỤC LOẠI PHÒNG & ĐƠN GIÁ CHUẨN -->
    <div id="loai-phong" class="card" style="margin-bottom: 24px;">
        <div class="card-title">
            <span>4. Danh mục Loại phòng & Đơn giá lưu trú chuẩn</span>
            <a href="<?= url('admin/room-types') ?>" class="btn btn-primary btn-sm" style="border-radius: 6px; text-decoration: none;">
                + Thêm loại phòng
            </a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã loại</th>
                        <th>Tên loại phòng</th>
                        <th>Sức chứa</th>
                        <th>Đơn giá chuẩn / tháng</th>
                        <th>Dịch vụ gia tăng (Máy lạnh)</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loaiPhongList as $lp): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($lp['MaLoaiPhong']) ?></b></td>
                            <td><?= htmlspecialchars($lp['TenLoaiPhong']) ?></td>
                            <td><?= $lp['SoGiuongToiDa'] ?> người</td>
                            <td><b><?= format_money($lp['DonGiaLuuTruChuan']) ?></b></td>
                            <td><?= format_money($lp['DonGiaDichVuTangThem']) ?></td>
                            <td style="text-align: right;">
                                <a href="<?= url('admin/room-types') ?>" class="btn btn-outline btn-sm" style="border-radius: 6px; text-decoration: none;">
                                    Sửa giá
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PHÂN HỆ 5: TRƯỜNG ĐẠI HỌC THÀNH VIÊN -->
    <div id="truong-hoc" class="card" style="margin-bottom: 24px;">
        <div class="card-title">
            <span>5. Danh mục Trường Đại học liên kết & Chỉ tiêu</span>
            <a href="<?= url('admin/universities') ?>" class="btn btn-primary btn-sm" style="border-radius: 6px; text-decoration: none;">
                + Thêm trường
            </a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã trường</th>
                        <th>Tên trường đại học</th>
                        <th>Trực thuộc ĐHQG-HCM</th>
                        <th>Thời gian đào tạo chuẩn</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($truongList as $t): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($t['MaTruong']) ?></b></td>
                            <td><?= htmlspecialchars($t['TenTruong']) ?></td>
                            <td>
                                <?php if ($t['ThuocDHQGHCM']): ?>
                                    <span class="badge badge-success">✓ Thành viên ĐHQG-HCM</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f1f5f9; color:#475569;">Ngoài ĐHQG-HCM</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $t['SoNamDaoTaoChuan'] ?> năm</td>
                            <td style="text-align: right;">
                                <a href="<?= url('admin/universities') ?>" class="btn btn-outline btn-sm" style="border-radius: 6px; text-decoration: none;">
                                    Sửa
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PHÂN HỆ 6: GIÁM SÁT TÀI CHÍNH & GIAO DỊCH TOÀN HỆ THỐNG -->
    <div id="tai-chinh" class="card">
        <div class="card-title">
            <span>6. Nhật ký giao dịch tài chính toàn hệ thống</span>
            <button type="button" class="btn btn-outline btn-sm" style="border-radius: 6px;" onclick="alert('Xuất báo cáo tài chính toàn hệ thống ra Excel')">
                Xuất báo cáo tài chính
            </button>
        </div>
        <?php if (!empty($recentTransactions)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã giao dịch</th>
                            <th>Mã hóa đơn</th>
                            <th>Sinh viên</th>
                            <th>Kênh thanh toán</th>
                            <th>Số tiền</th>
                            <th>Thời gian</th>
                            <th>Kết quả</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $tx): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($tx['MaGiaoDich']) ?></code></td>
                                <td><b><?= htmlspecialchars($tx['MaHoaDon']) ?></b></td>
                                <td><?= htmlspecialchars($tx['HoVaTen']) ?> (<?= htmlspecialchars($tx['MaSinhVien']) ?>)</td>
                                <td><?= htmlspecialchars($tx['KenhThanhToan'] ?? 'Mã QR KTX') ?></td>
                                <td><b><?= format_money($tx['SoTien']) ?></b></td>
                                <td><?= $tx['ThoiGianGiaoDich'] ?></td>
                                <td>
                                    <span class="badge badge-success">
                                        <?= htmlspecialchars($tx['KetQuaGiaoDich']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted" style="font-size: 0.9rem;">Chưa có giao dịch thanh toán nào được ghi nhận.</p>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = "Quản trị hệ thống - KTX ĐHQG-HCM";
require dirname(__DIR__) . '/layouts/main.php';
?>