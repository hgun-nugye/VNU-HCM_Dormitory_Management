<?php
ob_start();
?>

<div class="container">
    <!-- Breadcrumb & Header -->
    <div style="margin-bottom: 20px;">
        <div style="display: flex; gap: 8px; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">
            <a href="<?= url('admin/dashboard') ?>" style="color: var(--text-muted); text-decoration: none;">Quản trị hệ thống</a>
            <span>/</span>
            <span style="color: var(--text-primary); font-weight: 500;">Quản lý & Phân quyền cán bộ</span>
        </div>

        <div class="page-header" style="align-items: center;">
            <div>
                <h1 class="page-title">Quản lý Tài khoản & Phân quyền Cán bộ</h1>
                <p class="page-subtitle">
                    <strong>Chức năng file:</strong> CRUD tài khoản cán bộ BQL, phân công vai trò (Trưởng khu / Trưởng cụm / Kỹ thuật) và gán phạm vi quản lý.
                </p>
            </div>
            <div>
                <a href="<?= url('admin/dashboard') ?>" class="btn btn-outline" style="border-radius: 8px;">
                    &larr; Về trang Quản trị trung tâm
                </a>
            </div>
        </div>

    </div>

    <!-- THÔNG BÁO FLASH MESSAGE -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
            <b>Thành công:</b> <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashWarning)): ?>
        <div class="alert alert-warning" style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
            <b>Cảnh báo:</b> <?= htmlspecialchars($flashWarning) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
            <b>Lỗi:</b> <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- BIỂU MẪU CRUD: THÊM / CẬP NHẬT TÀI KHOẢN CÁN BỘ -->
    <div class="card" id="userFormCard" style="margin-bottom: 24px;">
        <div style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 id="formTitle" style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-user-plus" style="color: var(--primary); margin-right: 8px;"></i>
                <span>Thêm mới hoặc Điều chỉnh phân quyền Cán bộ</span>
            </h3>
        </div>

        <form id="userActionForm" action="<?= url('admin/users/save') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" id="editMaTaiKhoan" name="maTaiKhoan" value="">

            <!-- Datalist gợi ý họ và tên cán bộ khi nhập tay -->
            <datalist id="staffSuggestions">
                <?php foreach ($allStaff as $st): ?>
                    <option value="<?= htmlspecialchars($st['HoVaTen']) ?>">
                        <?= htmlspecialchars($st['HoVaTen']) ?> (SĐT: <?= htmlspecialchars($st['SoDienThoai'] ?? '') ?> &bull; <?= htmlspecialchars($st['ChucVu'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </datalist>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
                        Họ và tên cán bộ <span style="color:#dc2626;">*</span>

                    </label>
                    <input type="text" list="staffSuggestions" id="hoVaTen" name="hoVaTen" class="btn-outline" style="width: 100%; padding: 10px; border-radius: 6px; font-size: 0.9rem;" placeholder="Gõ tên cán bộ (VD: Trần Văn Minh, Lê Hoàng Nam...)" required autocomplete="off">
                </div>
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
                        Số điện thoại liên hệ
                    </label>
                    <input type="tel" id="soDienThoai" name="soDienThoai" class="btn-outline" style="width: 100%; padding: 10px; border-radius: 6px; font-size: 0.9rem;" placeholder="SĐT sẽ tự động hiển thị sau khi nhập/chọn họ tên">
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
                        Tên đăng nhập (Mã nhân viên) <span style="color:#dc2626;">*</span>
                        <span id="usernameLockNotice" style="display: none; font-size: 0.775rem; color: #b45309; font-weight: normal; margin-left: 6px;">
                            <i class="fa-solid fa-lock" style="margin-right: 4px;"></i>
                        </span>
                    </label>
                    <input type="text" id="tenDangNhap" name="tenDangNhap" class="btn-outline" style="width: 100%; padding: 10px; border-radius: 6px; font-size: 0.9rem;" placeholder="Ví dụ: NV003, TK002, admin..." required>
                </div>
                <div>
                    <!-- CHẾ ĐỘ THÊM MỚI: NHẬP MẬT KHẨU KHỞI TẠO -->
                    <div id="createPasswordBox">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
                            Mật khẩu khởi tạo <span style="color:#dc2626;">*</span>
                        </label>
                        <?= \App\Core\Auth::passwordInput('matKhau', 'matKhau', 'Nhập mật khẩu khởi tạo cho cán bộ', true, 'btn-outline') ?>
                        <div id="passwordHelp" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                            * Bắt buộc khi tạo mới. Gợi ý chuẩn: <code>Demo@123</code>.
                        </div>
                    </div>

                    <!-- CHẾ ĐỘ SỬA: KHÔNG CHO XEM MẬT KHẨU, CHỈ CHO RESET VỀ Demo@123 -->
                    <div id="editPasswordBox" style="display: none;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text-secondary);">
                            Mật khẩu tài khoản
                        </label>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-outline btn-sm" id="btnQuickResetPassword" style="border-radius: 6px; font-size: 0.8rem; color: #b45309; border-color: #fde68a; background: #fffbeb;" onclick="confirmQuickResetPassword()">
                                    <i class="fa-solid fa-key" style="margin-right: 4px;"></i> Reset ngay
                                </button>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Email công vụ / liên hệ</label>
                    <input type="email" id="email" name="email" class="btn-outline" style="width: 100%; padding: 10px; border-radius: 6px; font-size: 0.9rem;" placeholder="Ví dụ: canbo@qlktx.vn">
                </div>
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
                        Vai trò hệ thống <span style="color:#dc2626;">*</span>
                    </label>
                    <select id="maVaiTro" name="maVaiTro" class="btn-outline" style="width: 100%; padding: 10px; border-radius: 6px; font-size: 0.9rem;" required>
                        <option value="">-- Chọn vai trò phân quyền --</option>
                        <?php if (!empty($vaitroList)): ?>
                            <?php foreach ($vaitroList as $vt): ?>
                                <option value="<?= htmlspecialchars($vt['MaVaiTro']) ?>">
                                    <?= htmlspecialchars($vt['TenVaiTro']) ?> (<?= htmlspecialchars($vt['MaVaiTro']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="TRUONG_CUM">Trưởng cụm nhà (TRUONG_CUM)</option>
                            <option value="TRUONG_KHU">Trưởng khu KTX (TRUONG_KHU)</option>
                            <option value="NV_THUONG">Nhân viên kỹ thuật / hành chính (NV_THUONG)</option>
                            <option value="NV_QUAN_LY_KTX">Nhân viên quản lý chung KTX (NV_QUAN_LY_KTX)</option>
                            <option value="ADMIN">Quản trị viên hệ thống (ADMIN)</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

          <div class="grid grid-cols-2" style="gap: 16px; margin-bottom: 20px; align-items: start;">
    <!-- CỘT TRÁI: PHẠM VI QUẢN LÝ -->
    <div>
        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
            Phạm vi được giao quản lý (Cụm nhà / Khu vực phụ trách)
        </label>

        <select id="maCumQuanLy" name="maCumQuanLy" class="btn-outline"
            style="width: 100%; padding: 10px; border-radius: 6px; font-size: 0.9rem;">
            <option value="">-- Toàn hệ thống (Dành cho Admin / Quản lý chung) --</option>
            <option value="KHU_A">Toàn bộ Khu A (Dành cho Trưởng khu A)</option>
            <option value="KHU_B">Toàn bộ Khu B (Dành cho Trưởng khu B)</option>

            <?php foreach ($cumNhaList as $cn): ?>
                <option value="<?= htmlspecialchars($cn['MaCum']) ?>">
                    Cụm nhà <?= htmlspecialchars($cn['TenCum']) ?>
                    (<?= htmlspecialchars($cn['TenKhu'] ?? ('Khu ' . $cn['MaKhu'])) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
            * Trưởng cụm chỉ có quyền trên Cụm nhà được gán.
        </div>
    </div>

    <!-- CỘT PHẢI: CÁC NÚT CHỨC NĂNG -->
    <div style="display: flex; align-items: center; gap: 10px; padding-top: 28px; flex-wrap: wrap;">

        <button type="submit" id="btnSubmitForm" class="btn btn-primary"
            style="border-radius: 6px; padding: 10px 22px; font-weight: 600;">
            <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i>
            Lưu tài khoản & Cấp quyền
        </button>

        <button type="button" id="btnCancelEdit" class="btn btn-outline"
            style="border-radius: 6px; padding: 10px 18px;"
            onclick="resetUserForm()">
            <i class="fa-solid fa-rotate-left" style="margin-right: 6px;"></i>
            Làm mới / Thêm mới
        </button>

    </div>
</div>
        </form>
    </div>

    <!-- BẢNG DANH SÁCH TÀI KHOẢN CÁN BỘ ĐANG QUẢN LÝ (CRUD TABLE) -->
    <div class="card">
        <div class="card-title">
            <span>Danh sách tài khoản cán bộ đang quản lý trong hệ thống</span>
            <span class="badge badge-info"><?= count($userList ?? []) ?> tài khoản</span>
        </div>

        <?php if (!empty($userList)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tên đăng nhập</th>
                            <th>Họ và tên cán bộ</th>
                            <th>Vai trò hệ thống</th>
                            <th>Phạm vi phụ trách</th>
                            <th>Số điện thoại & Email</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userList as $u): ?>
                            <?php
                            $isLocked = ((int)($u['DangHoatDong'] ?? 1)) === 0;
                            $scopeLabel = 'Toàn hệ thống KTX';
                            if (!empty($u['TenCum'])) {
                                $scopeLabel = $u['TenCum'] . ' (' . ($u['TenKhu'] ?? 'KTX') . ')';
                            } elseif (!empty($u['MaCumQuanLy'])) {
                                $scopeLabel = 'Cụm ' . $u['MaCumQuanLy'];
                            } elseif ($u['MaVaiTro'] === 'TRUONG_KHU') {
                                $scopeLabel = 'Toàn bộ Khu KTX';
                            }
                            ?>
                            <tr style="<?= $isLocked ? 'background: #fef2f2;' : '' ?>">
                                <td>
                                    <b style="color: var(--primary); font-size: 0.95rem;"><?= htmlspecialchars($u['TenDangNhap']) ?></b>
                                </td>
                                <td>
                                    <b><?= htmlspecialchars($u['HoVaTen'] ?? 'Cán bộ BQL') ?></b>
                                    <?php if (!empty($u['ChucVu'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($u['ChucVu']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-info';
                                    if ($u['MaVaiTro'] === 'ADMIN') $badgeClass = 'badge-danger';
                                    elseif ($u['MaVaiTro'] === 'TRUONG_KHU') $badgeClass = 'badge-warning';
                                    elseif ($u['MaVaiTro'] === 'TRUONG_CUM') $badgeClass = 'badge-success';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($u['TenVaiTro'] ?? $u['MaVaiTro']) ?>
                                    </span>
                                </td>
                                <td>
                                    <b><?= htmlspecialchars($scopeLabel) ?></b>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($u['SoDienThoai'] ?? '—') ?></div>
                                    <?php if (!empty($u['Email'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($u['Email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isLocked): ?>
                                        <span class="badge badge-danger">Đã khóa</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Hoạt động</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= !empty($u['NgayTao']) ? date('d/m/Y', strtotime($u['NgayTao'])) : '—' ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <!-- NÚT SỬA (UPDATE) -->
                                    <!-- NÚT SỬA -->
                                    <button type="button" class="btn btn-outline btn-sm"
                                        style="border-radius: 6px; margin-right: 4px;"
                                        onclick='editUser(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                        title="Chỉnh sửa thông tin & phân quyền">
                                        <i class="fa-solid fa-pen-to-square" style="margin-right: 6px;"></i> Sửa
                                    </button>

                                    <!-- NÚT KHÓA / MỞ KHÓA -->
                                    <button type="button" class="btn btn-outline btn-sm btn-toggle-user"
                                        style="border-radius: 6px; margin-right: 4px; <?= $isLocked ? 'color: #15803d; border-color: #86efac;' : 'color: #b45309; border-color: #fde68a;' ?>"
                                        data-id="<?= (int)$u['MaTaiKhoan'] ?>"
                                        data-username="<?= htmlspecialchars($u['TenDangNhap'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-locked="<?= $isLocked ? '1' : '0' ?>"
                                        onclick="handleToggleUser(this)"
                                        title="<?= $isLocked ? 'Mở khóa tài khoản' : 'Khóa tài khoản' ?>">
                                        <i class="fa-solid <?= $isLocked ? 'fa-lock-open' : 'fa-lock' ?>"
                                            style="margin-right: 6px;"></i> <?= $isLocked ? 'Mở khóa' : 'Khóa' ?>
                                    </button>

                                    <!-- NÚT XÓA -->
                                    <button type="button" class="btn btn-outline btn-sm btn-delete-user"
                                        style="border-radius: 6px; color: #dc2626; border-color: #fecaca; background: #fff;"
                                        data-id="<?= (int)$u['MaTaiKhoan'] ?>"
                                        data-username="<?= htmlspecialchars($u['TenDangNhap'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-fullname="<?= htmlspecialchars($u['HoVaTen'] ?? $u['TenDangNhap'], ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="handleDeleteUser(this)"
                                        title="Xóa tài khoản">
                                        <i class="fa-solid fa-trash-can" style="margin-right: 6px;"></i> Xóa
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted" style="font-size: 0.9rem; padding: 20px 0;">Chưa có tài khoản cán bộ nào trong hệ thống.</p>
        <?php endif; ?>
    </div>
</div>

<!-- JAVASCRIPT XỬ LÝ GỢI Ý HỌ TÊN, TỰ ĐỘNG ĐIỀN SĐT VÀ CRUD FORM -->
<script>
    // Dữ liệu cán bộ có sẵn để tra cứu & gợi ý
    const staffPool = <?= json_encode($allStaff ?? [], JSON_UNESCAPED_UNICODE) ?>;

    // 1. Khi nhập hoặc chọn Họ và tên cán bộ từ ô gợi ý: Tự động lấy Số điện thoại
    const hoVaTenInput = document.getElementById('hoVaTen');
    const soDienThoaiInput = document.getElementById('soDienThoai');
    const tenDangNhapInput = document.getElementById('tenDangNhap');
    const emailInput = document.getElementById('email');
    const maVaiTroSelect = document.getElementById('maVaiTro');
    const maCumQuanLySelect = document.getElementById('maCumQuanLy');
    const editMaTaiKhoanInput = document.getElementById('editMaTaiKhoan');

    function findStaffByName(name) {
        if (!name) return null;
        const cleanName = name.trim().toLowerCase();
        return staffPool.find(s => s.HoVaTen && s.HoVaTen.trim().toLowerCase() === cleanName);
    }

    // Lắng nghe sự kiện gõ hoặc chọn gợi ý từ datalist
    hoVaTenInput.addEventListener('input', function() {
        const matched = findStaffByName(this.value);
        if (matched) {
            // Tự động điền số điện thoại
            if (matched.SoDienThoai) {
                soDienThoaiInput.value = matched.SoDienThoai;
            }
            // Điền email nếu có và ô email đang trống
            if (matched.Email && !emailInput.value) {
                emailInput.value = matched.Email;
            }
            // Nếu đang ở chế độ thêm mới và chưa nhập tên đăng nhập: tự động gợi ý mã nhân viên
            if (!editMaTaiKhoanInput.value && !tenDangNhapInput.value && matched.MaNhanVien) {
                tenDangNhapInput.value = matched.MaNhanVien;
            }
            // Gợi ý vai trò và cụm quản lý phù hợp
            if (!editMaTaiKhoanInput.value && matched.MaVaiTro && maVaiTroSelect) {
                maVaiTroSelect.value = matched.MaVaiTro;
            }
            if (!editMaTaiKhoanInput.value && matched.MaCumQuanLy && maCumQuanLySelect) {
                maCumQuanLySelect.value = matched.MaCumQuanLy;
            }
        }
    });

    // 2. Chức năng Sửa (Update) - Đưa thông tin lên Form
    function editUser(user) {
        document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-user-pen" style="color: var(--primary); margin-right: 8px;"></i> <span>Chỉnh sửa thông tin cán bộ: ' + (user.HoVaTen || user.TenDangNhap) + '</span>';
        document.getElementById('editMaTaiKhoan').value = user.MaTaiKhoan;

        // Cố định tên đăng nhập khi sửa: không cho phép thay đổi
        const tenDangNhapInput = document.getElementById('tenDangNhap');
        tenDangNhapInput.value = user.TenDangNhap;
        tenDangNhapInput.readOnly = true;
        tenDangNhapInput.style.backgroundColor = '#f1f5f9';
        tenDangNhapInput.style.cursor = 'not-allowed';
        const lockNotice = document.getElementById('usernameLockNotice');
        if (lockNotice) lockNotice.style.display = 'inline';

        document.getElementById('hoVaTen').value = user.HoVaTen || '';
        document.getElementById('soDienThoai').value = user.SoDienThoai || '';
        document.getElementById('email').value = user.Email || '';

        if (maVaiTroSelect) {
            maVaiTroSelect.value = user.MaVaiTro || '';
        }
        if (maCumQuanLySelect) {
            maCumQuanLySelect.value = user.MaCumQuanLy || '';
        }

        // Chuyển vùng mật khẩu: Không cho xem mật khẩu cũ, chỉ cho reset về Demo@123
        const createBox = document.getElementById('createPasswordBox');
        const editBox = document.getElementById('editPasswordBox');
        const resetCb = document.getElementById('resetPasswordCheckbox');
        if (createBox) createBox.style.display = 'none';
        if (editBox) editBox.style.display = 'block';
        if (resetCb) resetCb.checked = false;

        const matKhauInput = document.getElementById('matKhau');
        if (matKhauInput) {
            matKhauInput.removeAttribute('required');
            matKhauInput.value = '';
        }

        document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> Cập nhật tài khoản';

        // Cuộn mượt tới form
        document.getElementById('userFormCard').scrollIntoView({
            behavior: 'smooth'
        });
    }

    // 3. Làm mới Form (Reset về chế độ Thêm mới)
    function resetUserForm() {
        document.getElementById('userActionForm').reset();
        document.getElementById('editMaTaiKhoan').value = '';

        // Mở lại ô tên đăng nhập cho phép nhập
        const tenDangNhapInput = document.getElementById('tenDangNhap');
        tenDangNhapInput.readOnly = false;
        tenDangNhapInput.style.backgroundColor = '';
        tenDangNhapInput.style.cursor = '';
        const lockNotice = document.getElementById('usernameLockNotice');
        if (lockNotice) lockNotice.style.display = 'none';

        // Trả lại vùng mật khẩu về chế độ Tạo mới
        const createBox = document.getElementById('createPasswordBox');
        const editBox = document.getElementById('editPasswordBox');
        const resetCb = document.getElementById('resetPasswordCheckbox');
        if (createBox) createBox.style.display = 'block';
        if (editBox) editBox.style.display = 'none';
        if (resetCb) resetCb.checked = false;

        const matKhauInput = document.getElementById('matKhau');
        if (matKhauInput) {
            matKhauInput.setAttribute('required', 'required');
            matKhauInput.value = '';
            matKhauInput.placeholder = 'Nhập mật khẩu khởi tạo cho cán bộ';
        }

        document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-user-plus" style="color: var(--primary); margin-right: 8px;"></i> <span>Thêm mới hoặc Điều chỉnh phân quyền Cán bộ</span>';
        document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> Lưu tài khoản & Cấp quyền';
    }

    // Đặt lại mật khẩu nhanh về Demo@123 từ biểu mẫu sửa
    function confirmQuickResetPassword() {
        const maTaiKhoan = document.getElementById('editMaTaiKhoan').value;
        const tenDangNhap = document.getElementById('tenDangNhap').value;
        const hoVaTen = document.getElementById('hoVaTen').value || tenDangNhap;

        if (!maTaiKhoan) return;

        openConfirmModal({
            title: 'Đặt lại mật khẩu về mặc định',
            message: `Bạn có chắc chắn muốn đặt lại mật khẩu của cán bộ <b>${hoVaTen}</b> (<code style="color:var(--primary); font-weight:700;">${tenDangNhap}</code>) về mật khẩu chuẩn <code style="color:#dc2626; font-weight:700;">Demo@123</code>?`,
            note: '<i class="fa-solid fa-shield-halved" style="margin-right: 6px;"></i> Sau khi đặt lại, cán bộ hãy sử dụng mật khẩu <b>Demo@123</b> để đăng nhập vào hệ thống.',
            btnText: 'Đặt lại mật khẩu',
            btnClass: 'btn btn-warning',
            btnIcon: 'fa-solid fa-key',
            icon: 'fa-solid fa-key',
            iconColor: '#d97706',
            action: '<?= url("admin/users/reset-password") ?>',
            inputs: {
                maTaiKhoan: maTaiKhoan
            }
        });
    }

    // 4. Modal xác nhận Xóa tài khoản (Tái sử dụng Component confirm_modal)
    function handleDeleteUser(btn) {
        const id = btn.getAttribute('data-id');
        const username = btn.getAttribute('data-username');
        const fullname = btn.getAttribute('data-fullname') || username;
        confirmDeleteUser(id, username, fullname);
    }

    function confirmDeleteUser(maTaiKhoan, username, fullName) {
        if (typeof openConfirmModal !== 'function') {
            if (confirm(`Bạn có chắc chắn muốn xóa tài khoản cán bộ ${fullName} (${username})?`)) {
                submitActionForm('<?= url("admin/users/delete") ?>', {
                    maTaiKhoan: maTaiKhoan
                });
            }
            return;
        }

        openConfirmModal({
            title: 'Xác nhận xóa tài khoản cán bộ',
            message: `Bạn có chắc chắn muốn xóa tài khoản của cán bộ <b>${fullName}</b> (<code style="color:var(--primary); font-weight:700;">${username}</code>)?`,
            note: '<i class="fa-solid fa-shield-halved" style="margin-right: 6px;"></i> <b>Lưu ý:</b> Nếu tài khoản này đã từng duyệt hồ sơ hoặc tiếp nhận bảo trì thiết bị, hệ thống sẽ tự động chuyển sang trạng thái <b>KHÓA</b> để bảo toàn lịch sử dữ liệu.',
            btnText: 'Xác nhận xóa',
            btnClass: 'btn btn-danger',
            btnIcon: 'fa-solid fa-trash-can',
            action: '<?= url("admin/users/delete") ?>',
            inputs: {
                maTaiKhoan: maTaiKhoan
            }
        });
    }

    // 5. Modal xác nhận Khóa / Mở khóa tài khoản (Tái sử dụng Component confirm_modal)
    function handleToggleUser(btn) {
        const id = btn.getAttribute('data-id');
        const username = btn.getAttribute('data-username');
        const isLocked = btn.getAttribute('data-locked') === '1';
        confirmToggleStatus(id, username, isLocked);
    }

    function confirmToggleStatus(maTaiKhoan, username, isLocked) {
        const actionText = isLocked ? 'mở khóa' : 'tạm khóa';
        if (typeof openConfirmModal !== 'function') {
            if (confirm(`Bạn có chắc chắn muốn ${actionText} tài khoản cán bộ ${username}?`)) {
                submitActionForm('<?= url("admin/users/toggle-status") ?>', {
                    maTaiKhoan: maTaiKhoan
                });
            }
            return;
        }

        openConfirmModal({
            title: isLocked ? 'Xác nhận mở khóa tài khoản' : 'Xác nhận tạm khóa tài khoản',
            message: `Bạn có chắc chắn muốn <b>${actionText}</b> tài khoản cán bộ <code style="color:var(--primary); font-weight:700;">${username}</code>?`,
            note: isLocked ?
                '<i class="fa-solid fa-circle-check" style="color:#15803d; margin-right: 6px;"></i> Sau khi mở khóa, cán bộ sẽ có thể đăng nhập và tiếp tục thực hiện nghiệp vụ bình thường.' : '<i class="fa-solid fa-triangle-exclamation" style="color:#b45309; margin-right: 6px;"></i> Sau khi khóa, tài khoản này sẽ bị từ chối đăng nhập vào hệ thống.',
            btnText: isLocked ? 'Mở khóa ngay' : 'Khóa tài khoản',
            btnClass: isLocked ? 'btn btn-success' : 'btn btn-warning',
            btnIcon: isLocked ? 'fa-solid fa-lock-open' : 'fa-solid fa-lock',
            icon: isLocked ? 'fa-solid fa-lock-open' : 'fa-solid fa-lock',
            iconColor: isLocked ? '#15803d' : '#d97706',
            action: '<?= url("admin/users/toggle-status") ?>',
            inputs: {
                maTaiKhoan: maTaiKhoan
            }
        });
    }

    function submitActionForm(action, data) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = action;
        f.style.display = 'none';
        f.innerHTML = '<?= csrf_field() ?>';
        for (const [k, v] of Object.entries(data)) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = k;
            inp.value = v;
            f.appendChild(inp);
        }
        document.body.appendChild(f);
        f.submit();
    }
</script>

<?php
$content = ob_get_clean();
$title = "Quản lý Cán bộ & Phân quyền - KTX ĐHQG-HCM";
require dirname(__DIR__) . '/layouts/main.php';
?>