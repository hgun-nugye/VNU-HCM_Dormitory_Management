/**
 * Dormitory Management - Main JavaScript
 * KTX Đại học Quốc gia TP.HCM
 */

/**
 * Trình ẩn / hiện mật khẩu (Toggle Show/Hide Password)
 * @param {string} inputId - ID của ô input password
 * @param {HTMLElement} btn - Nút button kích hoạt sự kiện
 */
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');

    // Tìm icon bên trong button
    const eyeIcon = btn.querySelector('.eye-icon') || btn;
    if (isPassword) {
        btn.setAttribute('aria-label', 'Ẩn mật khẩu');
        btn.setAttribute('title', 'Ẩn mật khẩu');
        btn.style.color = 'var(--primary, #1e40af)';
        eyeIcon.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
        `;
    } else {
        btn.setAttribute('aria-label', 'Hiện mật khẩu');
        btn.setAttribute('title', 'Hiện mật khẩu');
        btn.style.color = 'var(--text-muted, #64748b)';
        eyeIcon.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        `;
    }
}

/**
 * ==========================================================================
 * Reusable Confirmation Modal System
 * Dùng chung cho toàn bộ các chức năng: Xóa, Khóa, Duyệt, Hủy...
 * ==========================================================================
 */
let confirmModalCallback = null;

/**
 * Mở modal xác nhận với các tùy chọn linh hoạt
 * @param {Object} options
 *   - title: Tiêu đề modal (VD: "Xác nhận xóa tài khoản")
 *   - message: Nội dung câu hỏi (VD: "Bạn có chắc chắn muốn xóa tài khoản <b>NV001</b>?")
 *   - note: Ghi chú cảnh báo nghiệp vụ nhỏ màu vàng (tùy chọn)
 *   - icon: Class icon FontAwesome (mặc định: "fa-solid fa-triangle-exclamation")
 *   - iconColor: Màu của icon (mặc định: "#dc2626")
 *   - btnText: Nhãn nút xác nhận (mặc định: "Xác nhận")
 *   - btnClass: Class của nút xác nhận (mặc định: "btn btn-danger")
 *   - btnIcon: Class icon trong nút xác nhận (mặc định: "fa-solid fa-trash-can")
 *   - action: URL đích để submit POST (tùy chọn)
 *   - method: Phương thức gửi (mặc định: "POST")
 *   - inputs: Object { key: value } chứa các hidden input gửi lên server
 *   - onConfirm: Callback function thực thi khi nhấn xác nhận (nếu không submit form)
 */
function openConfirmModal(options) {
    options = options || {};
    const modal = document.getElementById('reusableConfirmModal');
    if (!modal) return;

    const heading = document.getElementById('modalConfirmHeading');
    const icon = document.getElementById('modalConfirmIcon');
    const message = document.getElementById('modalConfirmMessage');
    const note = document.getElementById('modalConfirmNote');
    const submitBtn = document.getElementById('modalConfirmSubmitBtn');
    const form = document.getElementById('modalConfirmForm');
    const hiddenInputs = document.getElementById('modalConfirmHiddenInputs');

    // 1. Tiêu đề & Icon
    if (heading) heading.innerText = options.title || 'Xác nhận thao tác';
    if (icon) {
        icon.className = options.icon || 'fa-solid fa-triangle-exclamation';
        icon.style.color = options.iconColor || '#dc2626';
    }

    // 2. Nội dung thông điệp
    if (message) {
        message.innerHTML = options.message || 'Bạn có chắc chắn muốn thực hiện hành động này?';
    }

    // 3. Ghi chú phụ
    if (note) {
        if (options.note) {
            note.innerHTML = options.note;
            note.style.display = 'block';
        } else {
            note.style.display = 'none';
        }
    }

    // 4. Nút bấm xác nhận
    if (submitBtn) {
        submitBtn.className = options.btnClass || 'btn btn-danger';
        const btnIcon = options.btnIcon !== undefined ? options.btnIcon : 'fa-solid fa-trash-can';
        const btnText = options.btnText || 'Xác nhận';
        submitBtn.innerHTML = (btnIcon ? `<i class="${btnIcon}" style="margin-right: 6px;"></i> ` : '') + `<span>${btnText}</span>`;
    }

    // 5. Cài đặt hành động (Submit Form hoặc Callback)
    if (options.action && form && hiddenInputs) {
        form.action = options.action;
        form.method = options.method || 'POST';
        hiddenInputs.innerHTML = '';
        if (options.inputs) {
            for (const [key, val] of Object.entries(options.inputs)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = val;
                hiddenInputs.appendChild(input);
            }
        }
        confirmModalCallback = function() {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i> Đang xử lý...';
            }
            form.submit();
        };
    } else if (typeof options.onConfirm === 'function') {
        confirmModalCallback = function() {
            options.onConfirm();
            closeConfirmModal();
        };
    } else {
        confirmModalCallback = null;
    }

    // 6. Gán sự kiện cho nút Xác nhận
    if (submitBtn) {
        submitBtn.onclick = function() {
            if (typeof confirmModalCallback === 'function') {
                confirmModalCallback();
            } else {
                closeConfirmModal();
            }
        };
    }

    // 7. Kích hoạt hiển thị modal
    modal.style.display = 'flex';
    requestAnimationFrame(function() {
        modal.classList.add('active');
    });
    document.body.style.overflow = 'hidden';
}

/**
 * Đóng modal xác nhận
 */
function closeConfirmModal() {
    const modal = document.getElementById('reusableConfirmModal');
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 150);
}

// Bấm phím ESC để đóng modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
    }
});

