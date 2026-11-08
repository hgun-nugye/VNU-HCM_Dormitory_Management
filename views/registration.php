<?php
ob_start();
$studentPrefill = $studentPrefill ?? null;
$old = $old ?? [];
?>
<div class="register-page">
  <div class="register-hero">
    <div class="register-eyebrow">Cổng đăng ký nội trú · ĐHQG-HCM</div>
    <h1>Đăng ký hồ sơ nội trú</h1>
    <p>Tự tạo tài khoản, hoàn thiện hồ sơ sinh viên, chọn chỗ ở phù hợp và xác thực email bằng mã OTP.</p>
  </div>

  <div class="register-card">
    <div class="register-progress" aria-label="Các phần của biểu mẫu">
      <span class="progress-step"><span class="progress-dot">01</span> Thông tin</span>
      <span class="progress-line"></span>
      <span class="progress-step"><span class="progress-dot">02</span> Chỗ ở</span>
      <span class="progress-line"></span>
      <span class="progress-step"><span class="progress-dot">03</span> Giấy tờ</span>
    </div>

    <p class="register-note register-note-intro">
      Tên đăng nhập do bạn tự chọn. Email được dùng để nhận thông báo hồ sơ và hỗ trợ khôi phục tài khoản.
    </p>

    <?php if (!empty($success)): ?>
      <div class="alert-success" id="serverSuccessAlert"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert-danger" id="serverErrorAlert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($isOpen)): ?>
      <section class="register-closed-box" role="status">
        <h2 class="register-closed-title">Cổng đăng ký nội trú hiện đang đóng</h2>
        <p class="register-closed-desc">
          Hệ thống sẽ tiếp nhận hồ sơ trong thời gian của đợt đăng ký. Bạn có thể xem lịch đợt gần nhất bên dưới.
        </p>
        <?php if (!empty($upcomingDot)): ?>
          <div class="register-dot-card">
            <strong><?= htmlspecialchars($upcomingDot['TenDot']) ?></strong>
            <div>Năm học: <?= htmlspecialchars($upcomingDot['NamHoc']) ?></div>
            <div>Mở cổng: <?= date('H:i d/m/Y', strtotime($upcomingDot['ThoiGianMoCong'])) ?></div>
            <div>Đóng cổng: <?= date('H:i d/m/Y', strtotime($upcomingDot['ThoiGianDongCong'])) ?></div>
          </div>
        <?php elseif (!empty($latestClosedDot)): ?>
          <p class="register-dot-closed-text">
            Đợt gần nhất <strong><?= htmlspecialchars($latestClosedDot['TenDot']) ?></strong> đã đóng vào <?= date('H:i d/m/Y', strtotime($latestClosedDot['ThoiGianDongCong'])) ?>.
          </p>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= url('student/dashboard') ?>">Quay lại cổng sinh viên</a>
      </section>
    <?php else: ?>
      <div class="register-active-dot">
        <strong><?= htmlspecialchars($activeDot['TenDot']) ?></strong> · <?= htmlspecialchars($activeDot['NamHoc']) ?>
        <span>— Nhận hồ sơ đến <?= date('H:i d/m/Y', strtotime($activeDot['ThoiGianDongCong'])) ?></span>
      </div>

      <?php if (is_array($studentPrefill)): ?>
        <div class="register-note register-prefill-note">
          Thông tin hồ sơ đã được điền từ tài khoản của bạn. Hãy xác thực email, chọn chỗ ở và gửi hồ sơ giữ chỗ.
        </div>
      <?php endif; ?>

      <form id="registrationForm" action="<?= htmlspecialchars(url('register'), ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <!-- SECTION 01: TÀI KHOẢN & THÔNG TIN CÁ NHÂN -->
        <div class="register-section register-section-first">
          <div class="section-heading">
            <span class="section-number">01</span>
            <div>
              <h3>Tài khoản & thông tin cá nhân</h3>
              <p>Chọn tên đăng nhập, mật khẩu và cung cấp thông tin định danh</p>
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group" id="grp-username">
              <label for="username">Tên đăng nhập</label>
              <input name="username" id="username" class="form-control" maxlength="50" autocomplete="username" placeholder="Tên đăng nhập bạn muốn dùng" value="<?= htmlspecialchars($old['username'] ?? ($studentPrefill['TenDangNhap'] ?? '')) ?>" required>
              <div class="field-error-msg" id="err-username"></div>
            </div>
            <div class="form-group" id="grp-password">
              <label for="password">Mật khẩu</label>
              <input type="password" name="password" id="password" class="form-control" minlength="6" autocomplete="new-password" placeholder="Tối thiểu 6 ký tự" required>
              <div class="field-error-msg" id="err-password"></div>
            </div>
            <div class="form-group" id="grp-confirm_password">
              <label for="confirm_password">Nhập lại mật khẩu</label>
              <input type="password" name="confirm_password" id="confirm_password" class="form-control" minlength="6" autocomplete="new-password" placeholder="Nhập lại mật khẩu" required>
              <div class="field-error-msg" id="err-confirm_password"></div>
            </div>
            <div class="form-group" id="grp-registrationType">
              <label for="registrationType">Loại đăng ký</label>
              <select name="registrationType" id="registrationType" class="form-control">
                <option value="Đăng ký thường" <?= (($old['registrationType'] ?? '') === 'Đăng ký thường') ? 'selected' : '' ?>>Đăng ký thường</option>
                <option value="Đăng ký diện miễn giảm lệ phí phòng ở" <?= (($old['registrationType'] ?? '') === 'Đăng ký diện miễn giảm lệ phí phòng ở') ? 'selected' : '' ?>>Đăng ký diện miễn giảm lệ phí phòng ở</option>
              </select>
              <div class="field-error-msg" id="err-registrationType"></div>
            </div>
            <div class="form-group" id="grp-university">
              <label for="university">Trường đại học</label>
              <select name="university" id="university" class="form-control" required>
                <option value="">-- Chọn trường --</option>
                <?php if (!empty($universities) && is_array($universities)): ?>
                  <?php foreach ($universities as $u): ?>
                    <option value="<?= htmlspecialchars($u['MaTruong']) ?>" <?= (($old['university'] ?? ($studentPrefill['MaTruong'] ?? '')) == $u['MaTruong']) ? 'selected' : '' ?>><?= htmlspecialchars($u['TenTruong']) ?></option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
              <div class="field-error-msg" id="err-university"></div>
            </div>
            <div class="form-group" id="grp-hoTen">
              <label for="hoTen">Họ và tên</label>
              <input name="hoTen" id="hoTen" class="form-control" value="<?= htmlspecialchars($old['hoTen'] ?? ($studentPrefill['HoVaTen'] ?? '')) ?>" placeholder="Nguyễn Văn A" required>
              <div class="field-error-msg" id="err-hoTen"></div>
            </div>
            <div class="form-group" id="grp-mssv">
              <label for="mssv">MSSV</label>
              <input name="mssv" id="mssv" class="form-control" maxlength="30" pattern="[A-Za-z0-9]+" title="MSSV chỉ gồm chữ cái không dấu và chữ số" placeholder="MSSV do trường cấp" value="<?= htmlspecialchars($old['mssv'] ?? ($studentPrefill['MaSinhVien'] ?? '')) ?>" required>
              <div class="field-error-msg" id="err-mssv"></div>
            </div>
            <div class="form-group" id="grp-cccd">
              <label for="cccd">Số CCCD</label>
              <input name="cccd" id="cccd" class="form-control" inputmode="numeric" maxlength="12" minlength="12" pattern="[0-9]{12}" title="CCCD phải gồm đủ 12 chữ số" placeholder="12 chữ số trên thẻ CCCD" value="<?= htmlspecialchars($old['cccd'] ?? ($studentPrefill['SoCCCD'] ?? '')) ?>" required>
              <div class="field-error-msg" id="err-cccd"></div>
            </div>
            <div class="form-group" id="grp-birthday">
              <label for="birthday">Ngày sinh</label>
              <input type="date" name="birthday" id="birthday" class="form-control" value="<?= htmlspecialchars($old['birthday'] ?? ($studentPrefill['NgaySinh'] ?? '')) ?>">
              <div class="field-error-msg" id="err-birthday"></div>
            </div>

            <!-- Giới tính Radio Button -->
            <div class="form-group" id="grp-gender">
              <label>Giới tính</label>
              <div class="radio-group register-radio-group" id="genderRadioGroup">
                <label class="register-radio-label">
                  <input type="radio" name="gender" id="genderNam" value="Nam" <?= (($old['gender'] ?? ($studentPrefill['GioiTinh'] ?? '')) === 'Nam') ? 'checked' : '' ?> required> Nam
                </label>
                <label class="register-radio-label">
                  <input type="radio" name="gender" id="genderNu" value="Nữ" <?= (($old['gender'] ?? ($studentPrefill['GioiTinh'] ?? '')) === 'Nữ') ? 'checked' : '' ?>> Nữ
                </label>
              </div>
              <div class="field-error-msg" id="err-gender"></div>
            </div>

            <div class="form-group" id="grp-phone">
              <label for="phone">Số điện thoại</label>
              <input name="phone" id="phone" class="form-control" inputmode="numeric" maxlength="11" minlength="9" pattern="[0-9]{9,11}" title="Số điện thoại gồm từ 9 đến 11 chữ số" placeholder="Ví dụ: 0912345678" value="<?= htmlspecialchars($old['phone'] ?? ($studentPrefill['SoDienThoai'] ?? '')) ?>" required>
              <div class="field-error-msg" id="err-phone"></div>
            </div>
            <div class="form-group" id="grp-bhyt">
              <label for="bhyt">Số BHYT</label>
              <input name="bhyt" id="bhyt" class="form-control" placeholder="Mã số BHYT (nếu có)" value="<?= htmlspecialchars($old['bhyt'] ?? ($studentPrefill['MaSoBHYT'] ?? '')) ?>">
              <div class="field-error-msg" id="err-bhyt"></div>
            </div>
            <div class="form-group full" id="grp-email">
              <label for="registerEmail">Email cá nhân</label>
              <div class="email-otp-row">
                <input id="registerEmail" type="email" name="email" class="form-control" maxlength="100" autocomplete="email" placeholder="email@example.com" value="<?= htmlspecialchars($old['email'] ?? ($studentPrefill['Email'] ?? '')) ?>" required>
                <button type="button" class="email-otp-send" id="sendEmailOtp">Gửi mã OTP</button>
              </div>
              <p class="email-otp-status" id="emailOtpStatus" aria-live="polite">Gửi mã xác thực đến email rồi nhập mã bên dưới.</p>
              <div class="field-error-msg" id="err-email"></div>
            </div>
            <div class="form-group" id="grp-email_otp">
              <label for="emailOtp">Mã xác thực email</label>
              <input id="emailOtp" type="text" name="email_otp" class="form-control" inputmode="numeric" maxlength="6" minlength="6" pattern="[0-9]{6}" placeholder="Nhập mã 6 chữ số" value="<?= htmlspecialchars($old['email_otp'] ?? '') ?>" required>
              <div class="field-error-msg" id="err-email_otp"></div>
            </div>
          </div>
        </div>

        <!-- SECTION 02: CHỌN CHỖ Ở -->
        <div class="register-section">
          <div class="section-heading">
            <span class="section-number">02</span>
            <div>
              <h3>Chọn chỗ ở</h3>
              <p>Chọn khu và loại phòng để tìm giường còn trống</p>
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group" id="grp-area">
              <label for="area">Khu</label>
              <select id="area" name="area" class="form-control" required>
                <option value="">-- Chọn khu --</option>
                <?php foreach (($areas ?? []) as $a): ?>
                  <option value="<?= htmlspecialchars($a['MaKhu']) ?>" <?= (($old['area'] ?? '') == $a['MaKhu']) ? 'selected' : '' ?>><?= htmlspecialchars($a['TenKhu']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="field-error-msg" id="err-area"></div>
            </div>
            <div class="form-group" id="grp-roomType">
              <label for="roomType">Loại phòng</label>
              <select id="roomType" name="roomType" class="form-control">
                <option value="">-- Tất cả loại phòng --</option>
                <?php if (!empty($roomTypes) && is_array($roomTypes)): ?>
                  <?php foreach ($roomTypes as $r): ?>
                    <option value="<?= htmlspecialchars($r['MaLoaiPhong']) ?>" <?= (($old['roomType'] ?? '') == $r['MaLoaiPhong']) ? 'selected' : '' ?>><?= htmlspecialchars($r['TenLoaiPhong']) ?></option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
              <div class="field-error-msg" id="err-roomType"></div>
            </div>
            <div class="form-group" id="grp-building">
              <label for="building">Tòa nhà</label>
              <select id="building" name="building" class="form-control" required>
                <option value="">-- Chọn tòa --</option>
              </select>
              <div class="field-error-msg" id="err-building"></div>
            </div>
            <div class="form-group" id="grp-floor">
              <label for="floor">Tầng</label>
              <select id="floor" name="floor" class="form-control" required>
                <option value="">-- Chọn tầng --</option>
              </select>
              <div class="field-error-msg" id="err-floor"></div>
            </div>
            <div class="form-group" id="grp-room">
              <label for="room">Phòng</label>
              <select id="room" name="room" class="form-control" required>
                <option value="">-- Chọn phòng --</option>
              </select>
              <div class="field-error-msg" id="err-room"></div>
            </div>
            <div class="form-group" id="grp-bed">
              <label for="bed">Giường trống</label>
              <select name="bed" id="bed" class="form-control" required>
                <option value="">-- Chọn giường --</option>
              </select>
              <div class="field-error-msg" id="err-bed"></div>
            </div>
          </div>
        </div>

        <p id="accommodationHint" class="accommodation-hint" aria-live="polite"></p>

        <!-- SECTION 03: HỒ SƠ GIẤY TỜ -->
        <div class="register-section">
          <div class="section-heading">
            <span class="section-number">03</span>
            <div>
              <h3>Hồ sơ giấy tờ</h3>
              <p>Tải lên ảnh rõ nét để hồ sơ được xét duyệt nhanh hơn</p>
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="cccdFront">Ảnh CCCD mặt trước</label>
              <input type="file" name="cccdFront" id="cccdFront" accept="image/jpeg,image/png,image/webp" class="form-control">
              <div class="field-error-msg" id="err-cccdFront"></div>
            </div>
            <div class="form-group">
              <label for="cccdBack">Ảnh CCCD mặt sau</label>
              <input type="file" name="cccdBack" id="cccdBack" accept="image/jpeg,image/png,image/webp" class="form-control">
              <div class="field-error-msg" id="err-cccdBack"></div>
            </div>
            <div class="form-group">
              <label for="portrait">Ảnh thẻ 4x6</label>
              <input type="file" name="portrait" id="portrait" accept="image/jpeg,image/png,image/webp" class="form-control">
              <div class="field-error-msg" id="err-portrait"></div>
            </div>
            <div class="form-group">
              <label for="bhytImage">Thẻ BHYT</label>
              <input type="file" name="bhytImage" id="bhytImage" accept="image/jpeg,image/png,image/webp" class="form-control">
              <div class="field-error-msg" id="err-bhytImage"></div>
            </div>
            <div class="form-group full">
              <label for="admissionLetter">Giấy xác nhận nhập học</label>
              <input type="file" name="admissionLetter" id="admissionLetter" accept="image/jpeg,image/png,image/webp" class="form-control">
              <div class="field-error-msg" id="err-admissionLetter"></div>
            </div>
          </div>
        </div>

        <div class="register-submit">
          <div>
            <p class="register-submit-hint">Vui lòng kiểm tra lại thông tin trước khi gửi hồ sơ.</p>
            <button type="button" id="btnResetDraft" class="btn-reset-draft" title="Xóa dữ liệu nháp">
              <i class="fa-solid fa-rotate-right"></i> Nhập lại từ đầu
            </button>
          </div>
          <button class="btn btn-primary" type="submit" id="btnSubmitRegistration">Gửi hồ sơ đăng ký <span aria-hidden="true">→</span></button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($isOpen)): ?>
  <script>
    (function() {
      const studentPrefill = <?= json_encode($studentPrefill, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: 'null' ?>;
      const oldInput = <?= json_encode($old ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
      const DRAFT_KEY = 'ktx_registration_form_draft_v2';
      const optionsUrl = <?= json_encode(url('register/options'), JSON_UNESCAPED_SLASHES) ?>;
      const otpUrl = <?= json_encode(url('register/send-otp'), JSON_UNESCAPED_SLASHES) ?>;

      const $ = id => document.getElementById(id);
      const form = $('registrationForm');

      // Helper lấy giá trị giới tính đang chọn
      function getSelectedGender() {
        const checked = document.querySelector('input[name="gender"]:checked');
        return checked ? checked.value : '';
      }

      // Xử lý studentPrefill nếu sinh viên đã có tài khoản
      if (studentPrefill) {
        const map = {
          hoTen: 'HoVaTen',
          mssv: 'MaSinhVien',
          cccd: 'SoCCCD',
          birthday: 'NgaySinh',
          phone: 'SoDienThoai',
          bhyt: 'MaSoBHYT',
          registerEmail: 'Email'
        };

        Object.entries(map).forEach(([id, key]) => {
          const el = $(id) || document.querySelector(`[name="${id}"]`);
          if (el && studentPrefill[key]) {
            el.value = studentPrefill[key];
            if (el.tagName === 'INPUT') el.readOnly = true;
            else el.disabled = true;
          }
        });

        if (studentPrefill.GioiTinh) {
          const genderRadio = document.querySelector(`input[name="gender"][value="${studentPrefill.GioiTinh}"]`);
          if (genderRadio) {
            genderRadio.checked = true;
            document.querySelectorAll('input[name="gender"]').forEach(r => r.disabled = true);
          }
        }

        const university = document.querySelector('[name="university"]');
        if (university && studentPrefill.MaTruong) {
          university.value = studentPrefill.MaTruong;
          university.disabled = true;
        }

        ['username', 'password', 'confirm_password'].forEach(name => {
          const el = document.querySelector(`[name="${name}"]`);
          if (el) {
            el.required = false;
            const grp = el.closest('.form-group');
            if (grp) grp.hidden = true;
          }
        });
      }

      /* ==========================================================
         BỘ QUY TẮC VALIDATION VÀ HIỂN THỊ LỖI TRỰC TIẾP (INLINE)
         ========================================================== */
      const validators = {
        username: function(val, el) {
          if (studentPrefill || !el || el.closest('.form-group')?.hidden) return '';
          val = (val || '').trim();
          if (!val) return 'Vui lòng nhập tên đăng nhập.';
          if (val.length < 3) return 'Tên đăng nhập phải có ít nhất 3 ký tự.';
          if (val.length > 50) return 'Tên đăng nhập không được dài quá 50 ký tự.';
          if (!/^[A-Za-z0-9_.-]+$/.test(val)) return 'Tên đăng nhập chỉ gồm chữ cái không dấu, số, dấu gạch dưới (_) hoặc gạch ngang (-).';
          return '';
        },
        password: function(val, el) {
          if (studentPrefill || !el || el.closest('.form-group')?.hidden) return '';
          if (!val) return 'Vui lòng nhập mật khẩu.';
          if (val.length < 6) return 'Mật khẩu phải có ít nhất 6 ký tự.';
          return '';
        },
        confirm_password: function(val, el) {
          if (studentPrefill || !el || el.closest('.form-group')?.hidden) return '';
          if (!val) return 'Vui lòng nhập lại mật khẩu xác nhận.';
          const pwdEl = $('password');
          const pwdVal = pwdEl ? pwdEl.value : '';
          if (val !== pwdVal) return 'Mật khẩu nhập lại chưa khớp với mật khẩu đã chọn.';
          return '';
        },
        registrationType: function(val) {
          return '';
        },
        university: function(val, el) {
          if (studentPrefill || (el && el.disabled)) return '';
          if (!val || val === '') return 'Vui lòng chọn trường đại học.';
          return '';
        },
        hoTen: function(val, el) {
          if (studentPrefill || (el && el.readOnly)) return '';
          val = (val || '').trim();
          if (!val) return 'Vui lòng nhập họ và tên.';
          if (val.length < 2) return 'Họ và tên quá ngắn.';
          if (/[0-9]/.test(val)) return 'Họ và tên không được chứa chữ số.';
          return '';
        },
        mssv: function(val, el) {
          if (studentPrefill || (el && el.readOnly)) return '';
          val = (val || '').trim();
          if (!val) return 'Vui lòng nhập mã số sinh viên (MSSV).';
          if (!/^[A-Za-z0-9]+$/.test(val)) return 'MSSV chỉ được chứa chữ cái không dấu và chữ số.';
          if (val.length > 30) return 'MSSV không được vượt quá 30 ký tự.';
          return '';
        },
        cccd: function(val, el) {
          if (studentPrefill || (el && el.readOnly)) return '';
          val = (val || '').trim();
          if (!val) return 'Vui lòng nhập số CCCD.';
          if (!/^[0-9]+$/.test(val)) return 'Số CCCD chỉ được chứa các chữ số.';
          if (val.length !== 12) return 'Số CCCD phải gồm đúng 12 chữ số (hiện có ' + val.length + ' số).';
          return '';
        },
        birthday: function(val, el) {
          if (studentPrefill || (el && el.readOnly)) return '';
          val = (val || '').trim();
          if (!val) return 'Vui lòng chọn ngày sinh.';
          const d = new Date(val);
          if (isNaN(d.getTime())) return 'Ngày sinh không hợp lệ.';
          const today = new Date();
          if (d > today) return 'Ngày sinh không được ở thời điểm tương lai.';
          const age = (today - d) / (365.25 * 24 * 3600 * 1000);
          if (age < 15) return 'Sinh viên đăng ký phải từ 15 tuổi trở lên.';
          if (age > 80) return 'Ngày sinh không hợp lệ.';
          return '';
        },
        gender: function() {
          if (studentPrefill) return '';
          const g = getSelectedGender();
          if (!g) return 'Vui lòng chọn giới tính (Nam hoặc Nữ).';
          return '';
        },
        phone: function(val, el) {
          if (studentPrefill || (el && el.readOnly)) return '';
          val = (val || '').trim();
          if (!val) return 'Vui lòng nhập số điện thoại.';
          if (!/^[0-9]+$/.test(val)) return 'Số điện thoại chỉ được chứa các chữ số.';
          if (!/^0[0-9]{8,10}$/.test(val)) return 'Số điện thoại phải gồm 9-11 chữ số và bắt đầu bằng số 0.';
          return '';
        },
        bhyt: function(val) {
          val = (val || '').trim();
          if (val && !/^[A-Za-z0-9]{10,15}$/.test(val)) return 'Mã thẻ BHYT phải gồm từ 10 đến 15 ký tự chữ và số.';
          return '';
        },
        email: function(val, el) {
          if (studentPrefill || (el && el.readOnly)) return '';
          val = (val || '').trim();
          if (!val) return 'Vui lòng nhập email cá nhân.';
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return 'Email không đúng định dạng (ví dụ: nguyenvana@gmail.com).';
          if (val.length > 100) return 'Email không được dài quá 100 ký tự.';
          return '';
        },
        email_otp: function(val) {
          val = (val || '').trim();
          if (!val) return 'Vui lòng gửi và nhập mã xác thực OTP từ email.';
          if (!/^[0-9]{6}$/.test(val)) return 'Mã OTP phải gồm đúng 6 chữ số.';
          return '';
        },
        area: function(val) {
          if (!val) return 'Vui lòng chọn khu KTX.';
          return '';
        },
        building: function(val) {
          if (!val) return 'Vui lòng chọn tòa nhà.';
          return '';
        },
        floor: function(val) {
          if (!val) return 'Vui lòng chọn tầng.';
          return '';
        },
        room: function(val) {
          if (!val) return 'Vui lòng chọn phòng.';
          return '';
        },
        bed: function(val) {
          if (!val) return 'Vui lòng chọn giường trống.';
          return '';
        }
      };

      // Hàm kiểm tra và cập nhật giao diện lỗi trực tiếp cho 1 trường
      function validateField(name, showErrorImmediate = true) {
        const validator = validators[name];
        if (!validator) return true;

        if (name === 'gender') {
          const errorMsg = validator();
          const group = $('genderRadioGroup');
          const errEl = $('err-gender');
          if (errEl) {
            if (errorMsg && showErrorImmediate) {
              errEl.textContent = errorMsg;
              errEl.classList.add('show');
              if (group) group.classList.add('is-invalid');
            } else if (!errorMsg) {
              errEl.textContent = '';
              errEl.classList.remove('show');
              if (group) group.classList.remove('is-invalid');
            }
          }
          return !errorMsg;
        }

        const el = document.querySelector(`[name="${name}"]`);
        if (!el) return true;
        if (el.disabled || el.readOnly || el.closest('.form-group')?.hidden) {
          const errEl = $('err-' + name);
          if (errEl) {
            errEl.textContent = '';
            errEl.classList.remove('show');
          }
          el.classList.remove('is-invalid');
          return true;
        }

        const val = el.value;
        const errorMsg = validator(val, el);
        const errEl = $('err-' + name);

        if (errEl) {
          if (errorMsg && showErrorImmediate) {
            errEl.textContent = errorMsg;
            errEl.classList.add('show');
            el.classList.add('is-invalid');
            el.classList.remove('is-valid');
          } else if (!errorMsg) {
            errEl.textContent = '';
            errEl.classList.remove('show');
            el.classList.remove('is-invalid');
            if (val && val.trim() !== '') {
              el.classList.add('is-valid');
            } else {
              el.classList.remove('is-valid');
            }
          }
        }

        return !errorMsg;
      }

      // Kiểm tra file upload hợp lệ
      function validateFileInput(input) {
        if (!input) return true;
        const errEl = $('err-' + input.name);
        if (!input.files || input.files.length === 0) {
          if (errEl) {
            errEl.textContent = '';
            errEl.classList.remove('show');
          }
          input.classList.remove('is-invalid');
          return true;
        }
        const file = input.files[0];
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
          if (errEl) {
            errEl.textContent = 'Chỉ chấp nhận tệp ảnh định dạng JPG, PNG hoặc WEBP.';
            errEl.classList.add('show');
          }
          input.classList.add('is-invalid');
          return false;
        }
        if (file.size > 5 * 1024 * 1024) {
          if (errEl) {
            errEl.textContent = 'Dung lượng ảnh không được vượt quá 5 MB (tệp hiện tại: ' + (file.size / (1024 * 1024)).toFixed(1) + ' MB).';
            errEl.classList.add('show');
          }
          input.classList.add('is-invalid');
          return false;
        }
        if (errEl) {
          errEl.textContent = '';
          errEl.classList.remove('show');
        }
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        return true;
      }

      // Gắn sự kiện live validation cho các trường text, date, password
      const textFields = [
        'username', 'password', 'confirm_password', 'hoTen',
        'mssv', 'cccd', 'birthday', 'phone', 'bhyt', 'email', 'email_otp'
      ];

      textFields.forEach(name => {
        const el = document.querySelector(`[name="${name}"]`);
        if (!el) return;

        // Khi gõ phím: nếu đang có lỗi hoặc hợp lệ thì cập nhật ngay
        el.addEventListener('input', function() {
          const errEl = $('err-' + name);
          const hasErrorCurrently = errEl && errEl.classList.contains('show');
          validateField(name, hasErrorCurrently);

          // Nếu đổi password thì đồng thời kiểm tra lại confirm_password nếu đã nhập
          if (name === 'password') {
            const confirmEl = $('confirm_password');
            if (confirmEl && confirmEl.value) {
              validateField('confirm_password', true);
            }
          }
        });

        // Khi rời khỏi ô nhập (blur): luôn hiển thị lỗi nếu chưa hợp lệ
        el.addEventListener('blur', function() {
          if (el.value.trim() !== '') {
            validateField(name, true);
          }
        });
      });

      // Giới tính Radio
      document.querySelectorAll('input[name="gender"]').forEach(radio => {
        radio.addEventListener('change', function() {
          validateField('gender', true);
        });
      });

      // Các select dropdown
      ['university', 'area', 'building', 'floor', 'room', 'bed'].forEach(name => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el) {
          el.addEventListener('change', function() {
            validateField(name, true);
          });
        }
      });

      // File inputs
      ['cccdFront', 'cccdBack', 'portrait', 'bhytImage', 'admissionLetter'].forEach(name => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el) {
          el.addEventListener('change', function() {
            validateFileInput(el);
          });
        }
      });

      /* ==========================================================
         CƠ CHẾ STICKY FORM: LƯU & PHỤC HỒI DỮ LIỆU KHI RELOAD
         ========================================================== */
      let saveDraftTimer = null;

      function saveDraft() {
        if (!form) return;
        const draft = {
          username: form.querySelector('[name="username"]')?.value || '',
          registrationType: form.querySelector('[name="registrationType"]')?.value || '',
          university: form.querySelector('[name="university"]')?.value || '',
          hoTen: form.querySelector('[name="hoTen"]')?.value || '',
          mssv: form.querySelector('[name="mssv"]')?.value || '',
          cccd: form.querySelector('[name="cccd"]')?.value || '',
          birthday: form.querySelector('[name="birthday"]')?.value || '',
          gender: getSelectedGender(),
          phone: form.querySelector('[name="phone"]')?.value || '',
          bhyt: form.querySelector('[name="bhyt"]')?.value || '',
          email: form.querySelector('[name="email"]')?.value || '',
          email_otp: form.querySelector('[name="email_otp"]')?.value || '',
          area: form.querySelector('[name="area"]')?.value || '',
          roomType: form.querySelector('[name="roomType"]')?.value || '',
          building: form.querySelector('[name="building"]')?.value || '',
          floor: form.querySelector('[name="floor"]')?.value || '',
          room: form.querySelector('[name="room"]')?.value || '',
          bed: form.querySelector('[name="bed"]')?.value || ''
        };
        try {
          sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
        } catch (e) {}
      }

      function debouncedSaveDraft() {
        clearTimeout(saveDraftTimer);
        saveDraftTimer = setTimeout(saveDraft, 250);
      }

      if (form) {
        form.addEventListener('input', debouncedSaveDraft);
        form.addEventListener('change', debouncedSaveDraft);
      }

      // Xử lý nút "Nhập lại từ đầu"
      const btnResetDraft = $('btnResetDraft');
      if (btnResetDraft) {
        btnResetDraft.addEventListener('click', function() {
          if (confirm('Bạn có chắc chắn muốn xóa toàn bộ thông tin đã nhập trên biểu mẫu này không?')) {
            try {
              sessionStorage.removeItem(DRAFT_KEY);
            } catch (e) {}
            window.location.href = window.location.pathname;
          }
        });
      }

      // Nếu có thông báo thành công từ máy chủ, xóa dữ liệu nháp
      if ($('serverSuccessAlert')) {
        try {
          sessionStorage.removeItem(DRAFT_KEY);
        } catch (e) {}
      }

      /* ==========================================================
         LOGIC TẢI CÁC TÙY CHỌN CHỖ Ở DẠNG BẬC THANG (CASCADE)
         ========================================================== */
      const requestVersions = {};

      function setHint(message = '', isError = false) {
        const hint = $('accommodationHint');
        if (!hint) return;
        hint.textContent = message;
        hint.style.color = isError ? '#b91c1c' : '#64748b';
        hint.style.fontWeight = isError ? 'bold' : 'normal';
      }

      function resetSelect(select, placeholder) {
        if (!select) return;
        select.innerHTML = '';
        select.add(new Option(placeholder, ''));
      }

      function setLoading(select, loading) {
        if (!select) return;
        select.disabled = loading;
        select.setAttribute('aria-busy', loading ? 'true' : 'false');
      }

      function invalidate(type) {
        requestVersions[type] = (requestVersions[type] || 0) + 1;
      }

      async function load(type, params, target, field, placeholder, getLabel) {
        const version = requestVersions[type] = (requestVersions[type] || 0) + 1;
        resetSelect(target, placeholder);
        setLoading(target, true);

        const csrfInput = document.querySelector('input[name="csrf_token"]') || document.querySelector('input[name="_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';

        params = Object.assign({
          type: type,
          csrf_token: csrfToken
        }, params);

        const body = new URLSearchParams();
        Object.keys(params).forEach(key => body.append(key, params[key] ?? ''));

        try {
          const response = await fetch(optionsUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString(),
            credentials: 'same-origin',
            cache: 'no-store'
          });

          if (!response.ok) throw new Error('HTTP ' + response.status);
          const data = await response.json();

          if (!Array.isArray(data.values)) throw new Error(data.error || 'Phản hồi dữ liệu không hợp lệ');
          if (version !== requestVersions[type]) return [];

          if (data.values.length === 0) {
            target.add(new Option('-- Không có lựa chọn phù hợp --', ''));
          }

          data.values.forEach(row => {
            const value = row[field];
            const label = getLabel ? getLabel(row, value) : String(value);
            target.add(new Option(label, String(value)));
          });

          return data.values;
        } catch (error) {
          console.error(error);
          if (version === requestVersions[type]) {
            target.add(new Option('-- Không tải được dữ liệu, vui lòng thử lại --', ''));
          }
          return [];
        } finally {
          if (version === requestVersions[type]) setLoading(target, false);
        }
      }

      async function loadBuilding(targetBuilding = null) {
        invalidate('floors');
        invalidate('rooms');
        invalidate('beds');
        resetSelect($('floor'), '-- Chọn tầng --');
        resetSelect($('room'), '-- Chọn phòng --');
        resetSelect($('bed'), '-- Chọn giường --');
        setHint('');

        const genderVal = getSelectedGender();
        if (!$('area').value || !genderVal) {
          resetSelect($('building'), '-- Chọn tòa --');
          if (!genderVal && !$('area').value) {
            setHint('Vui lòng chọn giới tính (ở mục thông tin phía trên) và khu KTX để tải danh sách tòa nhà.');
          } else if (!genderVal) {
            setHint('Vui lòng chọn giới tính ở mục thông tin cá nhân phía trên.');
          } else {
            setHint('Vui lòng chọn khu KTX (Khu A hoặc Khu B).');
          }
          return [];
        }

        const rows = await load('buildings', {
          area: $('area').value,
          gender: genderVal,
          roomType: $('roomType').value
        }, $('building'), 'MaToaNha', '-- Chọn tòa --', (row) => row.TenToaNha);

        if (rows.length === 0) {
          setHint('Không có tòa nhà phù hợp với khu, giới tính hoặc loại phòng đã chọn.', true);
          return rows;
        }

        if (targetBuilding) {
          for (let opt of $('building').options) {
            if (opt.value === targetBuilding || opt.text === targetBuilding || targetBuilding.includes(opt.value)) {
              $('building').value = opt.value;
              break;
            }
          }
        } else if (rows.length === 1) {
          $('building').value = rows[0].MaToaNha;
          setHint('Đã tự chọn tòa duy nhất phù hợp: ' + rows[0].TenToaNha);
        } else {
          setHint('Đã tìm thấy ' + rows.length + ' tòa nhà phù hợp. Vui lòng chọn một tòa nhà.');
        }

        return rows;
      }

      async function loadFloor(targetFloor = null) {
        invalidate('rooms');
        invalidate('beds');
        resetSelect($('room'), '-- Chọn phòng --');
        resetSelect($('bed'), '-- Chọn giường --');

        if (!$('building').value) return [];

        const rows = await load('floors', {
          building: $('building').value,
          roomType: $('roomType').value
        }, $('floor'), 'Tang', '-- Chọn tầng --', (row) => 'Tầng ' + row.Tang);

        if (rows.length === 0) {
          setHint('Tòa này hiện không còn tầng/phòng có giường trống phù hợp.', true);
          return rows;
        }

        if (targetFloor !== null && targetFloor !== undefined && targetFloor !== '') {
          for (let opt of $('floor').options) {
            if (String(opt.value) === String(targetFloor)) {
              $('floor').value = String(targetFloor);
              break;
            }
          }
        } else if (rows.length === 1) {
          $('floor').value = String(rows[0].Tang);
        }

        return rows;
      }

      async function loadRoom(targetRoom = null) {
        invalidate('beds');
        resetSelect($('bed'), '-- Chọn giường --');

        if (!$('building').value || !$('floor').value) return [];

        const rows = await load('rooms', {
          building: $('building').value,
          floor: $('floor').value,
          roomType: $('roomType').value
        }, $('room'), 'MaPhong', '-- Chọn phòng --', (row) => row.SoPhong);

        if (rows.length === 0) {
          setHint('Không có phòng trống phù hợp ở tầng đã chọn.', true);
          return rows;
        }

        if (targetRoom) {
          for (let opt of $('room').options) {
            const normOpt = opt.value.replace('.', '-');
            const normTarget = targetRoom.replace('.', '-');
            if (opt.value === targetRoom || opt.text === targetRoom || normOpt === normTarget || opt.text.includes(targetRoom)) {
              $('room').value = opt.value;
              break;
            }
          }
        } else if (rows.length === 1) {
          $('room').value = rows[0].MaPhong;
        }

        return rows;
      }

      async function loadBed(targetBed = null) {
        if (!$('room').value) {
          invalidate('beds');
          resetSelect($('bed'), '-- Chọn giường --');
          return [];
        }

        const rows = await load('beds', {
          room: $('room').value
        }, $('bed'), 'MaGiuong', '-- Chọn giường --', (row) => row.KyHieuGiuong);

        if (rows.length === 0) {
          setHint('Phòng này không còn giường trống. Vui lòng chọn phòng khác.', true);
          return rows;
        }

        if (targetBed) {
          for (let opt of $('bed').options) {
            if (opt.value === targetBed || opt.text === targetBed) {
              $('bed').value = opt.value;
              break;
            }
          }
        } else if (rows.length === 1) {
          $('bed').value = rows[0].MaGiuong;
        }
        setHint(rows.length > 1 ? 'Đã tải danh sách giường trống. Vui lòng chọn một giường.' : 'Đã tự chọn giường trống duy nhất.');
        return rows;
      }

      // Sự kiện thay đổi của người dùng trên giao diện
      $('area').addEventListener('change', async function() {
        await loadBuilding();
        saveDraft();
      });

      document.querySelectorAll('input[name="gender"]').forEach(radio => {
        radio.addEventListener('change', async function() {
          await loadBuilding();
          saveDraft();
        });
      });

      $('roomType').addEventListener('change', async function() {
        if (!$('building').value) {
          await loadBuilding();
        } else {
          await loadFloor();
          if ($('floor').value) {
            await loadRoom();
            if ($('room').value) {
              await loadBed();
            }
          }
        }
        saveDraft();
      });

      $('building').addEventListener('change', async function() {
        await loadFloor();
        if ($('floor').value) {
          await loadRoom();
          if ($('room').value) {
            await loadBed();
          }
        }
        saveDraft();
      });

      $('floor').addEventListener('change', async function() {
        await loadRoom();
        if ($('room').value) {
          await loadBed();
        }
        saveDraft();
      });

      $('room').addEventListener('change', async function() {
        await loadBed();
        saveDraft();
      });

      $('bed').addEventListener('change', function() {
        saveDraft();
      });

      /* ==========================================================
         KHÔI PHỤC TOÀN DIỆN DỮ LIỆU KHI TẢI TRANG (RELOAD / REDIRECT)
         ========================================================== */
      (async function restoreFormState() {
        let draft = {};
        try {
          draft = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || '{}');
        } catch (e) {}

        // Kết hợp: URL Params (ưu tiên phòng giữ chỗ) > Server Old Input > Local Draft
        const urlParams = new URLSearchParams(window.location.search);
        const merged = Object.assign({}, draft, oldInput);

        if (urlParams.get('area')) merged.area = urlParams.get('area');
        if (urlParams.get('building')) merged.building = urlParams.get('building');
        if (urlParams.get('floor')) merged.floor = urlParams.get('floor');
        if (urlParams.get('room') || urlParams.get('maPhong')) merged.room = urlParams.get('room') || urlParams.get('maPhong');
        if (urlParams.get('bed')) merged.bed = urlParams.get('bed');
        if (urlParams.get('gender')) merged.gender = urlParams.get('gender');
        if (urlParams.get('university')) merged.university = urlParams.get('university');
        if (urlParams.get('roomType')) merged.roomType = urlParams.get('roomType');

        // Phục hồi các trường thông tin cá nhân nếu chưa được prefill
        const directFields = [
          'username', 'registrationType', 'university', 'hoTen',
          'mssv', 'cccd', 'birthday', 'phone', 'bhyt', 'email_otp'
        ];

        directFields.forEach(name => {
          const el = document.querySelector(`[name="${name}"]`);
          if (el && !el.readOnly && !el.disabled && merged[name] !== undefined && merged[name] !== '') {
            el.value = merged[name];
          }
        });

        // Email phục hồi
        const emailEl = $('registerEmail');
        if (emailEl && !emailEl.readOnly && merged.email) {
          emailEl.value = merged.email;
        }

        // Giới tính
        if (merged.gender) {
          const genderRadio = document.querySelector(`input[name="gender"][value="${merged.gender}"]`);
          if (genderRadio && !genderRadio.disabled) {
            genderRadio.checked = true;
          }
        }

        // Khu & Loại phòng
        if (merged.area && $('area')) {
          for (let opt of $('area').options) {
            if (opt.value === merged.area || opt.text === merged.area) {
              $('area').value = opt.value;
              break;
            }
          }
        }

        if (merged.roomType && $('roomType')) {
          for (let opt of $('roomType').options) {
            if (opt.value === merged.roomType || opt.text === merged.roomType) {
              $('roomType').value = opt.value;
              break;
            }
          }
        }

        // Tải tuần tự các cấp chỗ ở và khôi phục lựa chọn
        if ($('area').value && getSelectedGender()) {
          const bldRows = await loadBuilding(merged.building);
          if ($('building').value) {
            const flrRows = await loadFloor(merged.floor);
            if ($('floor').value) {
              const rmRows = await loadRoom(merged.room);
              if ($('room').value) {
                await loadBed(merged.bed);
              }
            }
          }
        }

        // Nếu có thông tin phòng giữ chỗ 72h từ URL, hiển thị lời nhắc
        const targetRoom = urlParams.get('room') || urlParams.get('maPhong');
        if (targetRoom && $('room').value) {
          setHint('✓ Đã tự động chọn phòng ' + targetRoom + ' theo yêu cầu giữ chỗ 72h. Vui lòng hoàn tất thông tin cá nhân và gửi hồ sơ.');
          const hintEl = $('accommodationHint');
          if (hintEl) {
            hintEl.style.color = '#0284c7';
            hintEl.style.fontWeight = 'bold';
          }
        }
      })();

      /* ==========================================================
         XỬ LÝ GỬI FORM VÀ CHẶN SUBMIT NẾU CÓ LỖI (CLIENT VALIDATION)
         ========================================================== */
      if (form) {
        form.addEventListener('submit', function(e) {
          let isValid = true;
          let firstInvalidEl = null;

          const fieldsToValidate = [
            'username', 'password', 'confirm_password', 'university',
            'hoTen', 'mssv', 'cccd', 'birthday', 'gender', 'phone',
            'bhyt', 'email', 'email_otp',
            'area', 'building', 'floor', 'room', 'bed'
          ];

          fieldsToValidate.forEach(name => {
            const ok = validateField(name, true);
            if (!ok) {
              isValid = false;
              if (!firstInvalidEl) {
                if (name === 'gender') {
                  firstInvalidEl = $('genderRadioGroup') || document.querySelector('input[name="gender"]');
                } else {
                  firstInvalidEl = document.querySelector(`[name="${name}"]`);
                }
              }
            }
          });

          // Kiểm tra tệp đính kèm
          ['cccdFront', 'cccdBack', 'portrait', 'bhytImage', 'admissionLetter'].forEach(name => {
            const fileEl = document.querySelector(`[name="${name}"]`);
            if (fileEl && !validateFileInput(fileEl)) {
              isValid = false;
              if (!firstInvalidEl) firstInvalidEl = fileEl;
            }
          });

          if (!isValid) {
            e.preventDefault();
            if (firstInvalidEl) {
              firstInvalidEl.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
              });
              if (typeof firstInvalidEl.focus === 'function') {
                firstInvalidEl.focus();
              }
            }
            return false;
          }

          // Biểu mẫu hợp lệ: lưu trạng thái cuối cùng trước khi submit
          saveDraft();
        });
      }

      /* ==========================================================
         XỬ LÝ GỬI MÃ XÁC THỰC EMAIL (OTP)
         ========================================================== */
      const otpButton = $('sendEmailOtp');
      const emailInput = $('registerEmail');
      const otpInput = $('emailOtp');
      const otpStatus = $('emailOtpStatus');
      let otpSentEmail = '';

      emailInput.addEventListener('input', function() {
        if (emailInput.value.trim().toLowerCase() !== otpSentEmail) {
          otpInput.value = '';
          if (otpSentEmail) {
            otpStatus.textContent = 'Email đã thay đổi. Hãy gửi mã mới đến email hiện tại.';
            otpStatus.style.color = '#b42318';
          }
        }
      });

      otpButton.addEventListener('click', async function() {
        // Kiểm tra email trực tiếp trước khi gửi
        const emailValid = validateField('email', true);
        if (!emailValid) {
          emailInput.focus();
          return;
        }

        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const body = new URLSearchParams({
          email: emailInput.value.trim(),
          csrf_token: csrfInput ? csrfInput.value : ''
        });

        otpButton.disabled = true;
        otpButton.textContent = 'Đang gửi mã...';
        otpStatus.textContent = 'Đang kết nối máy chủ email...';
        otpStatus.style.color = '#475569';

        try {
          const response = await fetch(otpUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString(),
            credentials: 'same-origin',
            cache: 'no-store'
          });

          const result = await response.json();
          otpStatus.textContent = result.message || 'Không gửi được mã xác thực.';
          otpStatus.style.color = result.success ? '#047857' : '#b42318';

          if (!result.success) {
            otpButton.disabled = false;
            otpButton.textContent = 'Gửi mã OTP';
            return;
          }

          otpSentEmail = emailInput.value.trim().toLowerCase();
          otpInput.value = '';
          validateField('email_otp', false);
          let remaining = 60;
          otpButton.textContent = 'Gửi lại sau ' + remaining + ' giây';

          const timer = setInterval(function() {
            remaining--;
            if (remaining <= 0) {
              clearInterval(timer);
              otpButton.disabled = false;
              otpButton.textContent = 'Gửi lại mã OTP';
            } else {
              otpButton.textContent = 'Gửi lại sau ' + remaining + ' giây';
            }
          }, 1000);
        } catch (error) {
          otpStatus.textContent = 'Không kết nối được máy chủ email. Vui lòng thử lại.';
          otpStatus.style.color = '#b42318';
          otpButton.disabled = false;
          otpButton.textContent = 'Gửi mã OTP';
        }
      });
    })();
  </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = 'Đăng ký hồ sơ nội trú';
require __DIR__ . '/layouts/main.php';
?>