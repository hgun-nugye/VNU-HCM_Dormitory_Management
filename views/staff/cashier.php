<?php
ob_start();

$user = $user ?? \App\Core\Auth::user();
$scope = $scope ?? [];
$activeTab = $activeTab ?? 'pos';
$searchKeyword = $searchKeyword ?? '';
$studentData = $studentData ?? null;
$roomInfo = $roomInfo ?? null;
$unpaidInvoices = $unpaidInvoices ?? [];
$totalDebt = $totalDebt ?? 0;
$journalDate = $journalDate ?? date('Y-m-d');
$journalTransactions = $journalTransactions ?? [];
$journalStats = $journalStats ?? ['totalCash' => 0, 'count' => 0, 'roomFeeCash' => 0, 'utilityCash' => 0];
$printReceipt = $printReceipt ?? null;

$formatTenToa = function (?string $rawName, ?string $code = ''): string {
    if (!$rawName) return $code ?: '---';
    $clean = preg_replace('/^(tòa\s*|toà\s*)+/ui', '', trim($rawName));
    return 'Tòa ' . (!empty($clean) ? $clean : $rawName);
};
?>

<div class="container">

    <div style="margin-bottom: 20px;">
        <!-- BREADCRUMB -->
        <div style="display: flex; gap: 8px; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">
            <a href="<?= url('staff/dashboard') ?>" style="color: var(--text-muted); text-decoration: none;">BQL KTX</a>
            <span>/</span>
            <span style="color: var(--text-primary); font-weight: 500;">Quầy Thu Ngân</span>
        </div>

        <!-- PAGE HEADER (ĐỒNG BỘ NGUYÊN BẢN STAFF/UTILITIES) -->
        <div class="page-header" style="align-items: center; flex-wrap: wrap; gap: 12px; display: flex; justify-content: space-between;">
            <div>
                <h1 class="page-title">Quầy Thu Ngân</h1>
                <p class="page-subtitle" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px;">
                    <span>Phạm vi cán bộ:</span>
                    <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.8rem; padding: 4px 10px; font-weight: 600;">
                        <i class="fa-solid fa-building-user" style="margin-right: 6px;"></i> Chuyên viên Kế toán KTX
                    </span>
                </p>
            </div>

            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <a href="<?= url('staff/cashier/export' . (!empty($journalDate) ? '?date=' . urlencode($journalDate) : '')) ?>" class="btn btn-outline" style="height: 38px; border-radius: 8px; font-weight: 600; font-size: 0.875rem; padding: 8px 14px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; color: #15803d; border-color: #86efac; background: #f0fdf4; text-decoration: none;" title="Xuất báo cáo sổ quỹ ra Excel/CSV">
                    <i class="fa-solid fa-file-excel" style="color: #16a34a;"></i> Xuất Excel (<?= $journalStats['count'] ?? 0 ?>)
                </a>

                <a href="<?= url('staff/dashboard') ?>" class="btn btn-outline" style="height: 38px; border-radius: 8px; font-weight: 600; font-size: 0.875rem; padding: 8px 14px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none;">
                    <i class="fa-solid fa-arrow-left"></i> Về Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- FLASH MESSAGES -->
    <?php if (\App\Core\Session::has('success')): ?>
        <div class="alert alert-success" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
            <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i> <b>Thành công:</b> <?= \App\Core\Session::flash('success') ?>
        </div>
    <?php endif; ?>

    <?php if (\App\Core\Session::has('error')): ?>
        <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
            <i class="fa-solid fa-circle-xmark" style="margin-right: 6px;"></i> <b>Lỗi:</b> <?= \App\Core\Session::flash('error') ?>
        </div>
    <?php endif; ?>

    <!-- 4 STAT KPI CARDS (ĐỒNG BỘ CHUẨN GRID GRID-COLS-4 CỦA STAFF/UTILITIES) -->
    <div class="grid grid-cols-4" style="gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px 20px; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Tiền mặt thu hôm nay</span>
                <span style="background: #e0f2fe; color: #0284c7; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">Sổ quỹ</span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 700; color: #0284c7; margin: 4px 0;">
                <?= number_format($journalStats['totalCash'] ?? 0, 0, ',', '.') ?> <span style="font-size: 0.95rem; font-weight: 500;">đ</span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Đã gạch nợ thành công</div>
        </div>

        <div class="card" style="padding: 16px 20px; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Số phiếu thu đã lập</span>
                <span style="background: #ccfbf1; color: #0d9488; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">Giao dịch</span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 700; color: #0d9488; margin: 4px 0;">
                <?= number_format($journalStats['count'] ?? 0) ?> <span style="font-size: 0.95rem; font-weight: 500;">phiếu</span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Thu tiền mặt tại quầy</div>
        </div>

        <div class="card" style="padding: 16px 20px; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Lệ phí phòng & 72h</span>
                <span style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">Tiền phòng</span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 700; color: #92400e; margin: 4px 0;">
                <?= number_format($journalStats['roomFeeCash'] ?? 0, 0, ',', '.') ?> <span style="font-size: 0.95rem; font-weight: 500;">đ</span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Lệ phí nội trú học kỳ</div>
        </div>

        <div class="card" style="padding: 16px 20px; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Tiền điện nước thu quầy</span>
                <span style="background: #ecfdf5; color: #166534; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">Điện nước</span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 700; color: #16a34a; margin: 4px 0;">
                <?= number_format($journalStats['utilityCash'] ?? 0, 0, ',', '.') ?> <span style="font-size: 0.95rem; font-weight: 500;">đ</span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Hóa đơn dịch vụ hàng tháng</div>
        </div>
    </div>

    <!-- THANH BỘ LỌC TÌM KIẾM (ĐỒNG BỘ VỚI BOX FILTER CỦA STAFF/UTILITIES) -->
    <div class="card" style="margin-bottom: 24px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); background: #f8fafc;">
        <form method="GET" action="<?= url('staff/cashier') ?>" id="cashierSearchForm" style="margin: 0;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div style="position: relative; flex: 1; min-width: 280px;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                    <input type="text" name="search" id="cashierSearchInput" class="btn-outline"
                        placeholder="Tìm kiếm MSSV, họ tên, CCCD, số điện thoại, mã hồ sơ (HS...), mã HĐ (HD...)..."
                        value="<?= htmlspecialchars($searchKeyword) ?>"
                        autocomplete="off"
                        style="width: 100%; padding: 8px 12px 8px 36px; border-radius: 6px; font-size: 0.85rem; height: 38px; background: #fff;" required autofocus>
                    
                    <!-- Dropdown gợi ý tức thì (Auto-suggest) -->
                    <div id="cashierSuggestDropdown" style="display: none; position: absolute; left: 0; right: 0; top: 44px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 999; max-height: 280px; overflow-y: auto;"></div>
                </div>

                <button type="submit" class="btn btn-primary" style="height: 38px; border-radius: 6px; font-weight: 600; font-size: 0.875rem; padding: 8px 18px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-search"></i> Tra cứu công nợ
                </button>

                <?php if (!empty($searchKeyword)): ?>
                    <a href="<?= url('staff/cashier?tab=' . urlencode($activeTab)) ?>" class="btn btn-outline" style="border-radius: 6px; height: 38px; padding: 0 14px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: #dc2626; border-color: #fca5a5; background: #fff5f5; font-weight: 600; font-size: 0.85rem;" title="Xóa tìm kiếm">
                        <i class="fa-solid fa-filter-circle-xmark"></i> Xóa tìm kiếm
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- TAB NAVIGATION BAR (ĐỒNG BỘ NGUYÊN BẢN STAFF/UTILITIES) -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; flex-wrap: wrap;">
        <a href="<?= url('staff/cashier?tab=pos' . (!empty($searchKeyword) ? '&search=' . urlencode($searchKeyword) : '')) ?>" 
            class="tab-btn <?= ($activeTab === 'pos') ? 'active' : '' ?>" id="tabBtnPos"
            style="background: <?= ($activeTab === 'pos') ? '#eff6ff' : 'transparent' ?>; border: none; padding: 10px 18px; font-size: 0.95rem; font-weight: <?= ($activeTab === 'pos') ? '700' : '600' ?>; cursor: pointer; border-radius: 8px; color: <?= ($activeTab === 'pos') ? 'var(--primary)' : 'var(--text-secondary)' ?>; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            Ghi nhận Thu tiền mặt tại quầy (POS)
            <?php if (!empty($unpaidInvoices)): ?>
                <span class="badge" style="font-size: 0.75rem; margin-left: 6px; padding: 3px 8px; background: #fee2e2; color: #991b1b; border: none; font-weight: 700;"><?= count($unpaidInvoices) ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= url('staff/cashier?tab=journal') ?>" 
            class="tab-btn <?= ($activeTab === 'journal') ? 'active' : '' ?>" id="tabBtnJournal"
            style="background: <?= ($activeTab === 'journal') ? '#eff6ff' : 'transparent' ?>; border: none; padding: 10px 18px; font-size: 0.95rem; font-weight: <?= ($activeTab === 'journal') ? '700' : '600' ?>; cursor: pointer; border-radius: 8px; color: <?= ($activeTab === 'journal') ? 'var(--primary)' : 'var(--text-secondary)' ?>; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            Sổ quỹ & Lịch sử thu tiền mặt
            <span class="badge" style="font-size: 0.75rem; margin-left: 6px; padding: 3px 8px; background: #e0f2fe; color: #0369a1; border: none; font-weight: 700;"><?= $journalStats['count'] ?? 0 ?></span>
        </a>
    </div>

    <!-- ========================================================== -->
    <!-- TAB 1: THU TIỀN MẶT TẠI QUẦY (POS)                         -->
    <!-- ========================================================== -->
    <?php if ($activeTab === 'pos'): ?>
        <?php if (!empty($searchKeyword)): ?>
            <?php if (empty($studentData)): ?>
                <!-- KHÔNG TÌM THẤY SINH VIÊN -->
                <div class="card" style="margin-bottom: 24px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03); text-align: center; padding: 48px 20px;">
                    <div style="font-size: 2.4rem; margin-bottom: 8px; color: var(--text-muted);"><i class="fa-solid fa-magnifying-glass"></i></div>
                    <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-primary); margin-bottom: 4px;">Không tìm thấy thông tin sinh viên</div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                        Không có dữ liệu khớp với từ khóa "<b><?= htmlspecialchars($searchKeyword) ?></b>". Vui lòng kiểm tra lại MSSV hoặc CCCD.
                    </div>
                    <a href="<?= url('staff/cashier') ?>" class="btn btn-outline btn-sm" style="border-radius: 6px;">Tra cứu lại</a>
                </div>
            <?php else: ?>
                <!-- ĐÃ TÌM THẤY SINH VIÊN: 2 CỘT THÔNG TIN & BẢNG CÔNG NỢ -->
                <div style="display: grid; grid-template-columns: 340px 1fr; gap: 20px; align-items: flex-start;">
                    
                    <!-- CỘT TRÁI: THẺ SINH VIÊN & VỊ TRÍ PHÒNG -->
                    <div class="card" style="border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03); padding: 18px; border-radius: 10px;">
                        <div class="card-title" style="margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 0.95rem; font-weight: 700;">Hồ sơ sinh viên</span>
                            <span class="badge badge-info" style="font-size: 0.75rem;">MSSV</span>
                        </div>

                        <div style="text-align: center; margin-bottom: 16px;">
                            <div style="width: 54px; height: 54px; border-radius: 50%; background: #eff6ff; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; margin: 0 auto 8px; border: 2px solid #bfdbfe;">
                                <?= mb_substr($studentData['HoVaTen'] ?? 'SV', 0, 1) ?>
                            </div>
                            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--text-primary);">
                                <?= htmlspecialchars($studentData['HoVaTen']) ?>
                            </h3>
                            <div style="font-family: monospace; font-weight: 700; color: var(--primary); font-size: 0.9rem; margin-top: 2px;">
                                <?= htmlspecialchars($studentData['MSSVTaiTruong'] ?? $studentData['MaSinhVien']) ?>
                            </div>
                        </div>

                        <div style="font-size: 0.85rem; line-height: 1.9; color: var(--text-secondary);">
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #f1f5f9; padding: 3px 0;">
                                <span style="color: var(--text-muted);">Số CCCD:</span>
                                <b><?= htmlspecialchars($studentData['CCCD'] ?? '---') ?></b>
                            </div>
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #f1f5f9; padding: 3px 0;">
                                <span style="color: var(--text-muted);">Số ĐT:</span>
                                <b><?= htmlspecialchars($studentData['SoDienThoai'] ?? '---') ?></b>
                            </div>
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #f1f5f9; padding: 3px 0;">
                                <span style="color: var(--text-muted);">Trường ĐH:</span>
                                <b style="max-width: 180px; text-align: right; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($studentData['TenTruong'] ?? '') ?>">
                                    <?= htmlspecialchars($studentData['TenTruong'] ?? 'ĐHQG-HCM') ?>
                                </b>
                            </div>
                        </div>

                        <!-- VỊ TRÍ PHÒNG Ở / GIỮ CHỖ -->
                        <div style="margin-top: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Phòng & Giường</span>
                                <?php if (!empty($roomInfo['TrangThaiHoSo']) && $roomInfo['TrangThaiHoSo'] === 'Chờ thanh toán'): ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 0.7rem;">Giữ chỗ 72h</span>
                                <?php elseif (!empty($roomInfo['TrangThaiHopDong'])): ?>
                                    <span class="badge" style="background: #dcfce7; color: #166534; font-size: 0.7rem;"><?= htmlspecialchars($roomInfo['TrangThaiHopDong']) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($roomInfo): ?>
                                <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary);">
                                    <?= htmlspecialchars($formatTenToa($roomInfo['TenToaNha'] ?? ('Tòa ' . ($roomInfo['MaToaNha'] ?? '')))) ?> - Phòng <?= htmlspecialchars($roomInfo['SoPhong'] ?? '') ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                                    Giường: <b style="color: var(--primary);"><?= htmlspecialchars($roomInfo['KyHieuGiuong'] ?? $roomInfo['MaGiuong']) ?></b> &bull; <?= htmlspecialchars($roomInfo['TenCum'] ?? '') ?>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.8rem; color: var(--text-muted); font-style: italic;">
                                    Chưa xếp vị trí phòng ở.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TỔNG CÔNG NỢ -->
                        <div style="margin-top: 14px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; text-align: center;">
                            <div style="font-size: 0.8rem; color: #991b1b; font-weight: 600;">Tổng công nợ cần thanh toán:</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #b91c1c; margin: 4px 0;">
                                <?= number_format($totalDebt, 0, ',', '.') ?> <span style="font-size: 0.85rem; font-weight: 500;">đ</span>
                            </div>
                            <div style="font-size: 0.75rem; color: #991b1b;">
                                <?= count($unpaidInvoices) ?> khoản nợ chưa nộp
                            </div>
                        </div>
                    </div>

                    <!-- CỘT PHẢI: BẢNG HÓA ĐƠN & KHUNG THU TIỀN MẶT -->
                    <div>
                        <?php if (empty($unpaidInvoices)): ?>
                            <div class="card" style="border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03); padding: 48px 20px; text-align: center;">
                                <div style="font-size: 2.2rem; margin-bottom: 8px; color: #16a34a;"><i class="fa-solid fa-circle-check"></i></div>
                                <div style="font-weight: 700; font-size: 1.05rem; color: #166534;">Sinh viên không có khoản nợ nào!</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                                    Tất cả hóa đơn tiền phòng và điện nước của sinh viên <b><?= htmlspecialchars($studentData['HoVaTen']) ?></b> đều đã được tất toán.
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- FORM THU TIỀN MẶT TẠI QUẦY -->
                            <form method="POST" action="<?= url('staff/cashier/pay') ?>" id="cashierPaymentForm" onsubmit="return validateCashierPayment();">
                                <?= csrf_field() ?>
                                <input type="hidden" name="maSinhVien" value="<?= htmlspecialchars($studentData['MaSinhVien']) ?>">

                                <!-- BẢNG DANH SÁCH HÓA ĐƠN -->
                                <div class="card" style="margin-bottom: 24px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                                    <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span>Danh sách các khoản nợ của sinh viên</span>
                                            <span class="badge badge-danger"><?= count($unpaidInvoices) ?> khoản nợ</span>
                                        </div>
                                        <label style="font-size: 0.8rem; font-weight: 600; color: var(--primary); cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                            <input type="checkbox" id="checkAllInvoices" checked onchange="toggleSelectAllInvoices(this.checked)">
                                            <span>Chọn tất cả</span>
                                        </label>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40px; text-align: center;">#</th>
                                                    <th>Mã hóa đơn</th>
                                                    <th>Khoản thu</th>
                                                    <th>Hạn nộp</th>
                                                    <th style="text-align: right;">Số tiền nợ</th>
                                                    <th style="text-align: center;">Trạng thái</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($unpaidInvoices as $inv): ?>
                                                    <?php $amount = (float)$inv['SoTienPhaiDong']; ?>
                                                    <tr>
                                                        <td style="text-align: center;">
                                                            <input type="checkbox" name="invoices[]" value="<?= htmlspecialchars($inv['MaHoaDon']) ?>" 
                                                                   data-amount="<?= $amount ?>" 
                                                                   class="invoice-checkbox" 
                                                                   checked 
                                                                   onchange="recalculateTotal()">
                                                        </td>
                                                        <td>
                                                            <code style="font-weight: 700; color: var(--primary); font-size: 0.9rem;">
                                                                <?= htmlspecialchars($inv['MaHoaDon']) ?>
                                                            </code>
                                                            <?php if (str_starts_with($inv['MaHoaDon'], 'HD-HS')): ?>
                                                                <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 0.7rem; margin-left: 4px;">Giữ chỗ 72h</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <b><?= htmlspecialchars($inv['LoaiHoaDon']) ?></b>
                                                            <?php if (!empty($inv['SoPhong'])): ?>
                                                                <div style="font-size: 0.75rem; color: var(--text-muted);">Phòng <?= htmlspecialchars($inv['SoPhong']) ?> (<?= htmlspecialchars($inv['TenToaNha'] ?? '') ?>)</div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?= !empty($inv['HanThanhToan']) ? date('d/m/Y', strtotime($inv['HanThanhToan'])) : '---' ?>
                                                        </td>
                                                        <td style="text-align: right;">
                                                            <b style="color: #b91c1c; font-size: 0.95rem; font-family: monospace;">
                                                                <?= number_format($amount, 0, ',', '.') ?> đ
                                                            </b>
                                                        </td>
                                                        <td style="text-align: center;">
                                                            <span class="badge badge-danger" style="font-size: 0.75rem;">
                                                                Chưa thanh toán
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- KHUNG TÍNH TIỀN TẠI QUẦY (ĐỒNG BỘ CARD FORM GHI SỐ) -->
                                <div class="card" style="margin-bottom: 24px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); padding: 20px;">
                                    <div style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                                            Thanh toán Tiền mặt & Tính tiền thối
                                        </h3>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);">Kênh tiếp nhận: <b>Tiền mặt tại quầy Kế toán</b></span>
                                    </div>

                                    <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                                        <div>
                                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px; margin-bottom: 12px;">
                                                <div style="font-size: 0.85rem; color: #166534; font-weight: 600;">Tổng tiền cần thu (đã chọn):</div>
                                                <div style="font-size: 1.8rem; font-weight: 700; color: #15803d; margin: 4px 0;" id="displayTotalSelected">
                                                    <?= number_format($totalDebt, 0, ',', '.') ?> đ
                                                </div>
                                                <div id="displayTotalWords" style="font-size: 0.8rem; color: #166534; font-style: italic;">
                                                    <?= function_exists('convert_number_to_vietnamese_words') ? convert_number_to_vietnamese_words($totalDebt) : '' ?>
                                                </div>
                                            </div>

                                            <div>
                                                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Ghi chú thu tiền mặt:</label>
                                                <input type="text" name="notes" class="btn-outline" style="width: 100%; padding: 8px 10px; border-radius: 6px; font-size: 0.85rem; height: 38px; background: #fff;" value="Thu tiền mặt tại quầy Kế toán KTX">
                                            </div>
                                        </div>

                                        <div style="display: flex; flex-direction: column; justify-content: space-between;">
                                            <div>
                                                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
                                                    Tiền khách đưa (VNĐ): <span style="color: #dc2626;">*</span>
                                                </label>
                                                <input type="text" name="cash_given" id="inputCashGiven" 
                                                       value="<?= number_format($totalDebt, 0, ',', '.') ?>" 
                                                       class="btn-outline" 
                                                       oninput="formatCashInput(this); calculateChange();"
                                                       style="width: 100%; padding: 8px 10px; border-radius: 6px; font-size: 1.25rem; font-weight: 700; height: 42px; background: #fff; font-family: monospace; color: var(--primary);" required>

                                                <div style="display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap;">
                                                    <button type="button" class="btn btn-outline btn-sm" onclick="setCashExact()" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px; color: var(--primary);">Đủ tiền</button>
                                                    <button type="button" class="btn btn-outline btn-sm" onclick="setCash(500000)" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px;">500k</button>
                                                    <button type="button" class="btn btn-outline btn-sm" onclick="setCash(1000000)" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px;">1tr</button>
                                                    <button type="button" class="btn btn-outline btn-sm" onclick="setCash(2000000)" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px;">2tr</button>
                                                    <button type="button" class="btn btn-outline btn-sm" onclick="setCash(2500000)" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px;">2.5tr</button>
                                                    <button type="button" class="btn btn-outline btn-sm" onclick="setCash(3000000)" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px;">3tr</button>
                                                </div>
                                            </div>

                                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
                                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">Tiền thối lại cho SV:</span>
                                                <strong id="displayChange" style="font-size: 1.25rem; color: #15803d; font-family: monospace;">0 đ</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 14px; flex-wrap: wrap; gap: 10px;">
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            * Hệ thống tự động gạch nợ và kích hoạt hợp đồng (nếu là lệ phí giữ chỗ 72h).
                                        </div>

                                        <button type="submit" id="btnSubmitPayment" class="btn btn-primary" style="border-radius: 6px; font-weight: 600; padding: 9px 24px; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="fa-solid fa-money-bill-wave"></i> Xác nhận Thu tiền mặt & Gạch nợ
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- EMPTY STATE KHI CHƯA TÌM KIẾM -->
            <div class="card" style="margin-bottom: 24px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03); text-align: center; padding: 48px 20px;">
                <div style="font-size: 2.5rem; margin-bottom: 8px; color: var(--primary);"><i class="fa-solid fa-cash-register"></i></div>
                <div style="font-weight: 700; font-size: 1.15rem; color: var(--text-primary); margin-bottom: 6px;">Sẵn sàng tiếp nhận thu tiền mặt tại quầy</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); max-width: 580px; margin: 0 auto 20px; line-height: 1.6;">
                    Vui lòng nhập <b>MSSV</b>, <b>Họ tên</b>, <b>CCCD</b> hoặc <b>Mã hồ sơ giữ chỗ 72h (HS...)</b> vào ô tìm kiếm phía trên để tra cứu công nợ và tiến hành gạch nợ tiền mặt.
                </div>
            </div>
        <?php endif; ?>

    <!-- ========================================================== -->
    <!-- TAB 2: SỔ QUỸ & LỊCH SỬ THU TIỀN MẶT                        -->
    <!-- ========================================================== -->
    <?php elseif ($activeTab === 'journal'): ?>
        
        <div class="card" style="margin-bottom: 24px; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span>Nhật ký Sổ quỹ Thu tiền mặt (Ngày <?= date('d/m/Y', strtotime($journalDate)) ?>)</span>
                    <span class="badge badge-info"><?= count($journalTransactions) ?> giao dịch</span>
                </div>

                <form method="GET" action="<?= url('staff/cashier') ?>" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="hidden" name="tab" value="journal">
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Xem ngày:</span>
                    <input type="date" name="date" value="<?= htmlspecialchars($journalDate) ?>" class="btn-outline" style="padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; height: 34px; background: #fff;" onchange="this.form.submit()">
                </form>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã phiếu thu</th>
                            <th>Thời gian</th>
                            <th>Sinh viên nộp tiền</th>
                            <th>Vị trí phòng</th>
                            <th>Mã hóa đơn & Khoản thu</th>
                            <th style="text-align: right;">Số tiền thu</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($journalTransactions)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px 16px; color: var(--text-muted);">
                                    <div style="font-size: 2rem; margin-bottom: 6px;">🧾</div>
                                    <div style="font-weight: 600; color: var(--text-secondary);">Chưa có giao dịch thu tiền mặt nào trong ngày <?= date('d/m/Y', strtotime($journalDate)) ?>.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($journalTransactions as $t): ?>
                                <tr>
                                    <td>
                                        <b style="font-family: monospace; color: var(--primary); font-size: 0.9rem;">
                                            <?= htmlspecialchars($t['MaThamChieuNganHang'] ?? $t['MaGiaoDich']) ?>
                                        </b>
                                    </td>
                                    <td style="font-size: 0.85rem;">
                                        <?= date('H:i:s', strtotime($t['ThoiGianGiaoDich'])) ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('d/m/Y', strtotime($t['ThoiGianGiaoDich'])) ?></div>
                                    </td>
                                    <td>
                                        <b><?= htmlspecialchars($t['HoVaTen'] ?? 'Sinh viên') ?></b>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">MSSV: <?= htmlspecialchars($t['MSSVTaiTruong'] ?? $t['MaSinhVien']) ?></div>
                                    </td>
                                    <td style="font-size: 0.85rem;">
                                        <?php if (!empty($t['SoPhong'])): ?>
                                            <b>P.<?= htmlspecialchars($t['SoPhong']) ?></b> (<?= htmlspecialchars($t['TenToaNha'] ?? '') ?>)
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">---</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <b><?= htmlspecialchars($t['LoaiHoaDon']) ?></b>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;"><?= htmlspecialchars($t['MaHoaDon']) ?></div>
                                    </td>
                                    <td style="text-align: right;">
                                        <b style="color: #15803d; font-size: 0.95rem; font-family: monospace;">
                                            <?= number_format($t['SoTien'], 0, ',', '.') ?> đ
                                        </b>
                                    </td>
                                    <td style="text-align: right;">
                                        <button type="button" class="btn btn-outline btn-sm" 
                                                onclick="openReceiptModalByRef('<?= htmlspecialchars($t['MaThamChieuNganHang'] ?? $t['MaGiaoDich']) ?>')"
                                                style="border-radius: 6px; padding: 4px 10px; font-size: 0.75rem; color: var(--primary);">
                                            <i class="fa-solid fa-print"></i> In lại phiếu
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- ========================================================== -->
<!-- MODAL IN PHIẾU THU / BIÊN LAI TIỀN MẶT CHUẨN A5/A4         -->
<!-- ========================================================== -->
<div id="receiptModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 680px; max-height: 94vh; overflow-y: auto; border-radius: 12px; padding: 0; background: #ffffff; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        
        <!-- Modal Toolbar -->
        <div class="no-print" style="background: var(--primary); color: #ffffff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; border-radius: 12px 12px 0 0;">
            <div style="font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-receipt"></i> Phiếu Thu Tiền Mặt KTX
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" onclick="printReceiptContent()" class="btn btn-sm" style="background: #ffffff; color: var(--primary); font-weight: 700; border-radius: 6px; padding: 4px 14px; border: none;">
                    <i class="fa-solid fa-print"></i> In phiếu (Ctrl+P)
                </button>
                <button type="button" onclick="closeReceiptModal()" style="background: transparent; border: none; color: #ffffff; font-size: 1.4rem; cursor: pointer; line-height: 1; padding: 0 4px;">&times;</button>
            </div>
        </div>

        <!-- Khung Nội Dung Phiếu Thu (In Chuẩn A5) -->
        <div id="receiptPrintArea" style="padding: 30px 36px; background: #ffffff; color: #1e293b; font-family: 'Times New Roman', Times, serif; font-size: 13.5pt; line-height: 1.5;">
            
            <!-- TIÊU ĐỀ ĐHQG-HCM -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000000; padding-bottom: 12px; margin-bottom: 18px;">
                <div style="text-align: center; width: 55%;">
                    <div style="font-size: 11pt; text-transform: uppercase; font-weight: bold;">ĐẠI HỌC QUỐC GIA TP. HỒ CHÍ MINH</div>
                    <div style="font-size: 11pt; text-transform: uppercase; font-weight: bold; color: var(--primary);">TRUNG TÂM QUẢN LÝ KÝ TÚC XÁ</div>
                    <div style="font-size: 9.5pt; font-style: italic; margin-top: 2px;">Văn phòng Kế toán & Tài vụ</div>
                </div>
                <div style="text-align: right; width: 42%;">
                    <div style="font-weight: bold; font-size: 11pt;">Mẫu số: 01-TT / KTX</div>
                    <div style="font-size: 10pt; margin-top: 2px;">Số: <b id="rcptCode" style="font-family: monospace; font-size: 11pt;">PT...</b></div>
                    <div style="font-size: 9.5pt; font-style: italic; margin-top: 2px;">Liên 2: Giao người nộp tiền</div>
                </div>
            </div>

            <!-- TÊN BIỂU MẪU -->
            <div style="text-align: center; margin-bottom: 18px;">
                <h2 style="margin: 0; font-size: 17pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">PHIẾU THU TIỀN MẶT</h2>
                <div style="font-style: italic; font-size: 10.5pt; margin-top: 4px;">
                    Ngày lập: <span id="rcptDate">...</span>
                </div>
            </div>

            <!-- NỘI DUNG NGƯỜI NỘP -->
            <div style="margin-bottom: 16px; font-size: 12pt; line-height: 1.8;">
                <div>- Họ và tên người nộp tiền: <b id="rcptStudentName" style="text-transform: uppercase;">...</b></div>
                <div style="display: flex; justify-content: space-between;">
                    <div>- Mã số sinh viên (MSSV): <b id="rcptMSSV">...</b></div>
                    <div>Số ĐT: <span id="rcptPhone">...</span></div>
                </div>
                <div>- Vị trí cư trú: <span id="rcptRoom">...</span></div>
                <div>- Lý do nộp: <span id="rcptNotes">Thu tiền mặt tại quầy Kế toán KTX</span></div>
            </div>

            <!-- BẢNG CÁC KHOẢN THU -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 11pt;">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th style="border: 1px solid #000; padding: 6px 8px; text-align: center; width: 36px;">STT</th>
                        <th style="border: 1px solid #000; padding: 6px 8px; text-align: left;">Khoản thu / Nội dung</th>
                        <th style="border: 1px solid #000; padding: 6px 8px; text-align: center; width: 140px;">Mã hóa đơn</th>
                        <th style="border: 1px solid #000; padding: 6px 8px; text-align: right; width: 120px;">Số tiền (VNĐ)</th>
                    </tr>
                </thead>
                <tbody id="rcptItemsBody">
                    <!-- Dòng hóa đơn chèn động bằng JS -->
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" style="border: 1px solid #000; padding: 6px 8px; text-align: right; font-weight: bold;">TỔNG CỘNG TIỀN MẶT:</th>
                        <th id="rcptTotal" style="border: 1px solid #000; padding: 6px 8px; text-align: right; font-weight: bold; font-family: monospace; font-size: 12pt;">0 đ</th>
                    </tr>
                </tfoot>
            </table>

            <!-- SỐ TIỀN BẰNG CHỮ -->
            <div style="margin-bottom: 22px; font-size: 11.5pt; font-style: italic;">
                - Số tiền viết bằng chữ: <b id="rcptWords" style="font-style: normal;">...</b>
            </div>

            <!-- CHỮ KÝ 2 BÊN -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; text-align: center; margin-top: 10px; font-size: 11pt;">
                <div>
                    <b>NGƯỜI NỘP TIỀN</b>
                    <div style="font-size: 9.5pt; font-style: italic;">(Ký, ghi rõ họ tên)</div>
                    <div style="height: 60px;"></div>
                    <div id="rcptSignStudent" style="font-weight: bold;">...</div>
                </div>
                <div>
                    <b>NGƯỜI THU TIỀN (KẾ TOÁN)</b>
                    <div style="font-size: 9.5pt; font-style: italic;">(Ký, đóng dấu xác nhận)</div>
                    <div style="height: 60px;"></div>
                    <div id="rcptSignCashier" style="font-weight: bold;">...</div>
                </div>
            </div>

            <div style="margin-top: 24px; padding-top: 8px; border-top: 1px dashed #94a3b8; font-size: 9pt; color: #64748b; font-style: italic; text-align: center;">
                * Biên lai này là căn cứ hợp lệ xác nhận sinh viên đã hoàn thành nghĩa vụ tài chính để nhận phòng tại KTX ĐHQG-HCM.
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #receiptModal, #receiptModal * {
        visibility: visible;
    }
    #receiptModal {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: auto;
        background: transparent !important;
        padding: 0 !important;
    }
    .no-print {
        display: none !important;
    }
    #receiptPrintArea {
        box-shadow: none !important;
        padding: 10mm 15mm !important;
        width: 100% !important;
    }
}
</style>

<script>
    // ==========================================
    // AUTO-SUGGEST TỨC THÌ CHO THANH TÌM KIẾM
    // ==========================================
    const searchInput = document.getElementById('cashierSearchInput');
    const suggestBox = document.getElementById('cashierSuggestDropdown');
    let suggestTimer = null;

    if (searchInput && suggestBox) {
        searchInput.addEventListener('input', function() {
            const q = this.value.trim();
            clearTimeout(suggestTimer);

            if (q.length < 2) {
                suggestBox.style.display = 'none';
                suggestBox.innerHTML = '';
                return;
            }

            suggestTimer = setTimeout(function() {
                fetch("<?= url('staff/cashier/search') ?>?q=" + encodeURIComponent(q))
                    .then(res => res.json())
                    .then(res => {
                        if (res && res.success && res.data && res.data.length > 0) {
                            let html = '';
                            res.data.forEach(item => {
                                const debtText = item.SoHoaDonNo > 0 
                                    ? `<span style="color: #dc2626; font-weight: 700;">Nợ ${new Intl.NumberFormat('vi-VN').format(item.TongNo)} đ (${item.SoHoaDonNo} HĐ)</span>`
                                    : `<span style="color: #059669; font-weight: 600;">✓ Không nợ</span>`;
                                
                                html += `
                                    <div class="suggest-item" onclick="selectSuggestItem('${item.MaSinhVien}')" style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.15s;">
                                        <div>
                                            <b style="color: var(--text-primary); font-size: 0.9rem;">${item.HoVaTen}</b>
                                            <span style="color: var(--primary); font-weight: 600; font-size: 0.82rem; margin-left: 6px;">[${item.MSSVTaiTruong || item.MaSinhVien}]</span>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">CCCD: ${item.CCCD || '---'} &bull; SĐT: ${item.SoDienThoai || '---'}</div>
                                        </div>
                                        <div style="text-align: right; font-size: 0.82rem;">
                                            ${debtText}
                                        </div>
                                    </div>
                                `;
                            });
                            suggestBox.innerHTML = html;
                            suggestBox.style.display = 'block';

                            document.querySelectorAll('.suggest-item').forEach(el => {
                                el.addEventListener('mouseenter', () => el.style.background = '#f0fdf4');
                                el.addEventListener('mouseleave', () => el.style.background = '#ffffff');
                            });
                        } else {
                            suggestBox.style.display = 'none';
                        }
                    })
                    .catch(() => {
                        suggestBox.style.display = 'none';
                    });
            }, 250);
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestBox.contains(e.target)) {
                suggestBox.style.display = 'none';
            }
        });
    }

    function selectSuggestItem(maSV) {
        if (searchInput) {
            searchInput.value = maSV;
        }
        if (suggestBox) {
            suggestBox.style.display = 'none';
        }
        document.getElementById('cashierSearchForm').submit();
    }

    // ==========================================
    // LOGIC TÍNH TOÁN CÔNG NỢ & TIỀN THỐI LẠI
    // ==========================================
    let currentSelectedTotal = <?= (float)$totalDebt ?>;

    function toggleSelectAllInvoices(isChecked) {
        document.querySelectorAll('.invoice-checkbox').forEach(cb => {
            cb.checked = isChecked;
        });
        recalculateTotal();
    }

    function recalculateTotal() {
        let total = 0;
        let anyChecked = false;

        document.querySelectorAll('.invoice-checkbox').forEach(cb => {
            if (cb.checked) {
                total += parseFloat(cb.getAttribute('data-amount') || 0);
                anyChecked = true;
            }
        });

        currentSelectedTotal = total;
        document.getElementById('displayTotalSelected').innerText = new Intl.NumberFormat('vi-VN').format(total) + ' đ';

        setCashExact();
        calculateChange();

        const btnSubmit = document.getElementById('btnSubmitPayment');
        if (btnSubmit) {
            btnSubmit.disabled = !anyChecked;
            if (!anyChecked) {
                btnSubmit.style.opacity = '0.5';
                btnSubmit.style.cursor = 'not-allowed';
            } else {
                btnSubmit.style.opacity = '1';
                btnSubmit.style.cursor = 'pointer';
            }
        }
    }

    function formatCashInput(input) {
        let val = input.value.replace(/[^0-9]/g, '');
        if (val) {
            input.value = new Intl.NumberFormat('vi-VN').format(parseInt(val, 10));
        } else {
            input.value = '0';
        }
    }

    function setCashExact() {
        const input = document.getElementById('inputCashGiven');
        if (input) {
            input.value = new Intl.NumberFormat('vi-VN').format(currentSelectedTotal);
            calculateChange();
        }
    }

    function setCash(amount) {
        const input = document.getElementById('inputCashGiven');
        if (input) {
            input.value = new Intl.NumberFormat('vi-VN').format(amount);
            calculateChange();
        }
    }

    function calculateChange() {
        const input = document.getElementById('inputCashGiven');
        const displayChange = document.getElementById('displayChange');
        if (!input || !displayChange) return;

        let cashGiven = parseFloat(input.value.replace(/\./g, '').replace(/,/g, '')) || 0;
        let change = cashGiven - currentSelectedTotal;

        if (change < 0) {
            displayChange.innerHTML = `<span style="color: #dc2626; font-size: 1rem;">Còn thiếu ${new Intl.NumberFormat('vi-VN').format(Math.abs(change))} đ</span>`;
        } else {
            displayChange.innerHTML = `<span style="color: #166534;">${new Intl.NumberFormat('vi-VN').format(change)} đ</span>`;
        }
    }

    function validateCashierPayment() {
        if (currentSelectedTotal <= 0) {
            alert('Vui lòng chọn ít nhất một hóa đơn cần thu tiền!');
            return false;
        }

        const input = document.getElementById('inputCashGiven');
        let cashGiven = parseFloat(input.value.replace(/\./g, '').replace(/,/g, '')) || 0;

        if (cashGiven < currentSelectedTotal) {
            alert('Số tiền khách đưa chưa đủ để thanh toán các hóa đơn đã chọn!');
            input.focus();
            return false;
        }

        return confirm('Xác nhận thu TIỀN MẶT ' + new Intl.NumberFormat('vi-VN').format(currentSelectedTotal) + ' đ và gạch nợ ngay lập tức?');
    }

    // ==========================================
    // LOGIC MODAL PHIẾU THU & IN ẤN A5
    // ==========================================
    function openReceiptModal(data) {
        if (!data) return;

        document.getElementById('rcptCode').innerText = data.receipt_code || 'PT...';
        document.getElementById('rcptDate').innerText = data.created_at || '<?= date('d/m/Y H:i') ?>';
        document.getElementById('rcptStudentName').innerText = data.student_name || '...';
        document.getElementById('rcptMSSV').innerText = data.mssv || '...';
        document.getElementById('rcptPhone').innerText = data.phone || '---';
        document.getElementById('rcptRoom').innerText = data.room || '<?= !empty($roomInfo) ? htmlspecialchars(($roomInfo['TenToaNha'] ?? '') . ' - P.' . ($roomInfo['SoPhong'] ?? '')) : '---' ?>';
        document.getElementById('rcptNotes').innerText = data.notes || 'Thu tiền mặt tại quầy KTX';
        document.getElementById('rcptTotal').innerText = new Intl.NumberFormat('vi-VN').format(data.total_amount || 0) + ' đ';
        document.getElementById('rcptWords').innerText = data.total_words || '...';
        document.getElementById('rcptSignStudent').innerText = data.student_name || '...';
        document.getElementById('rcptSignCashier').innerText = data.cashier_name || 'Kế toán KTX';

        const tbody = document.getElementById('rcptItemsBody');
        let itemsHtml = '';
        if (data.invoices && data.invoices.length > 0) {
            data.invoices.forEach((inv, idx) => {
                const amt = parseFloat(inv.SoTienPhaiDong || inv.SoTien || 0);
                itemsHtml += `
                    <tr>
                        <td style="border: 1px solid #000; padding: 6px 8px; text-align: center;">${idx + 1}</td>
                        <td style="border: 1px solid #000; padding: 6px 8px;">${inv.LoaiHoaDon || 'Lệ phí KTX'}</td>
                        <td style="border: 1px solid #000; padding: 6px 8px; text-align: center; font-family: monospace;">${inv.MaHoaDon || ''}</td>
                        <td style="border: 1px solid #000; padding: 6px 8px; text-align: right; font-family: monospace;">${new Intl.NumberFormat('vi-VN').format(amt)} đ</td>
                    </tr>
                `;
            });
        }
        tbody.innerHTML = itemsHtml;

        const modal = document.getElementById('receiptModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeReceiptModal() {
        const modal = document.getElementById('receiptModal');
        if (modal) modal.style.display = 'none';
    }

    function printReceiptContent() {
        window.print();
    }

    function openReceiptModalByRef(ref) {
        window.open("<?= url('staff/cashier/receipt') ?>?ref=" + encodeURIComponent(ref), '_blank');
    }

    document.addEventListener('DOMContentLoaded', function() {
        calculateChange();

        <?php if (!empty($printReceipt)): ?>
            const receiptData = <?= json_encode($printReceipt, JSON_UNESCAPED_UNICODE) ?>;
            openReceiptModal(receiptData);
        <?php endif; ?>
    });
</script>

<?php
$content = ob_get_clean();
$title = 'Quầy Thu Ngân - KTX ĐHQG-HCM';
require dirname(__DIR__) . '/layouts/main.php';
?>
