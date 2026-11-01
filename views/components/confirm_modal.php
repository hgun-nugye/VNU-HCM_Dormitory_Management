<?php
/**
 * Reusable Confirmation Modal Component - KTX ĐHQG-HCM
 * Component modal xác nhận dùng chung cho toàn hệ thống:
 * - Tự đóng gói CSS & JS (Self-contained), đảm bảo không bao giờ bị vỡ layout do cache CSS/JS.
 * - Hỗ trợ submit Form an toàn (kèm CSRF token) hoặc gọi Callback JavaScript linh hoạt.
 * - Tùy biến: Tiêu đề, Nội dung, Ghi chú cảnh báo nghiệp vụ, Icon, Màu sắc, Nút xác nhận.
 */
?>
<style>
/* ==========================================================================
   CSS CHO REUSABLE CONFIRM MODAL (SELF-CONTAINED)
   ========================================================================== */
#reusableConfirmModal.modal-backdrop {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(15, 23, 42, 0.65) !important;
    backdrop-filter: blur(4px) !important;
    -webkit-backdrop-filter: blur(4px) !important;
    z-index: 999999 !important;
    display: none; /* Mặc định luôn ẩn */
    align-items: center !important;
    justify-content: center !important;
    padding: 16px !important;
    box-sizing: border-box !important;
    margin: 0 !important;
}

#reusableConfirmModal.modal-backdrop.active {
    display: flex !important; /* Khi active chuyển thành flex căn giữa */
}

#reusableConfirmModal .modal-dialog {
    background: #ffffff !important;
    border-radius: 12px !important;
    width: 100% !important;
    max-width: 480px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35) !important;
    border: 1px solid #e2e8f0 !important;
    overflow: hidden !important;
    margin: auto !important;
    transform: scale(0.96);
    opacity: 0;
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
    box-sizing: border-box !important;
}

#reusableConfirmModal.modal-backdrop.active .modal-dialog {
    transform: scale(1) !important;
    opacity: 1 !important;
}

#reusableConfirmModal .modal-header {
    padding: 16px 20px !important;
    border-bottom: 1px solid #e2e8f0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    background: #f8fafc !important;
}

#reusableConfirmModal .modal-title {
    font-size: 1.05rem !important;
    font-weight: 700 !important;
    color: #1e293b !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin: 0 !important;
}

#reusableConfirmModal .modal-title i,
#reusableConfirmModal button i:not(:only-child),
#reusableConfirmModal .modal-body i {
    margin-right: 6px !important;
}

#reusableConfirmModal .modal-close {
    background: transparent !important;
    border: none !important;
    font-size: 1.25rem !important;
    color: #64748b !important;
    cursor: pointer !important;
    padding: 4px 8px !important;
    border-radius: 6px !important;
    line-height: 1 !important;
    transition: background 0.15s ease, color 0.15s ease;
}

#reusableConfirmModal .modal-close:hover {
    background: #e2e8f0 !important;
    color: #0f172a !important;
}

#reusableConfirmModal .modal-body {
    padding: 22px 20px !important;
    font-size: 0.925rem !important;
    color: #334155 !important;
    line-height: 1.55 !important;
}

#reusableConfirmModal .modal-footer {
    padding: 14px 20px !important;
    border-top: 1px solid #e2e8f0 !important;
    background: #f8fafc !important;
    display: flex !important;
    justify-content: flex-end !important;
    gap: 10px !important;
}
</style>

<!-- MODAL MARKUP -->
<div id="reusableConfirmModal" class="modal-backdrop" style="display: none;" onclick="if(event.target === this) closeConfirmModal()">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modalConfirmHeading">
        <div class="modal-header">
            <h3 class="modal-title">
                <i id="modalConfirmIcon" class="fa-solid fa-triangle-exclamation" style="color: #dc2626; margin-right: 8px;"></i>
                <span id="modalConfirmHeading">Xác nhận thao tác</span>
            </h3>
            <button type="button" class="modal-close" onclick="closeConfirmModal()" aria-label="Đóng modal" title="Đóng">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div id="modalConfirmMessage" style="margin-bottom: 12px; font-weight: 500; color: #1e293b; font-size: 0.95rem;">
                Bạn có chắc chắn muốn thực hiện hành động này?
            </div>
            <div id="modalConfirmNote" style="font-size: 0.825rem; color: #78350f; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 14px; line-height: 1.55; display: none;">
                <!-- Ghi chú cảnh báo nghiệp vụ -->
            </div>
        </div>

        <div class="modal-footer">
            <!-- Form ẩn để submit POST an toàn khi được truyền tham số action & inputs -->
            <form id="modalConfirmForm" method="POST" style="display: none;">
                <?= csrf_field() ?>
                <div id="modalConfirmHiddenInputs"></div>
            </form>

            <button type="button" class="btn btn-outline" onclick="closeConfirmModal()" style="border-radius: 6px; padding: 8px 18px; cursor: pointer;">
                Hủy bỏ
            </button>
            <button type="button" id="modalConfirmSubmitBtn" class="btn btn-danger" style="border-radius: 6px; padding: 8px 20px; font-weight: 600; cursor: pointer;">
                <i class="fa-solid fa-trash-can" style="margin-right: 6px;"></i> <span>Xác nhận</span>
            </button>
        </div>
    </div>
</div>

<!-- MODAL SCRIPT (GLOBAL FUNCTIONS) -->
<script>
(function() {
    let confirmModalCallback = null;

    /**
     * Mở modal xác nhận với các tùy chọn
     * @param {Object} options
     */
    window.openConfirmModal = function(options) {
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

        // 3. Ghi chú cảnh báo
        if (note) {
            if (options.note) {
                note.innerHTML = options.note;
                note.style.display = 'block';
            } else {
                note.style.display = 'none';
            }
        }

        // 4. Định dạng nút bấm xác nhận
        if (submitBtn) {
            submitBtn.className = options.btnClass || 'btn btn-danger';
            submitBtn.disabled = false;
            const btnIcon = options.btnIcon !== undefined ? options.btnIcon : 'fa-solid fa-trash-can';
            const btnText = options.btnText || 'Xác nhận';
            submitBtn.innerHTML = (btnIcon ? `<i class="${btnIcon}" style="margin-right: 6px;"></i> ` : '') + `<span>${btnText}</span>`;
        }

        // 5. Cài đặt hành động (Form submit hoặc Callback)
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

        // 6. Gán sự kiện click cho nút xác nhận
        if (submitBtn) {
            submitBtn.onclick = function() {
                if (typeof confirmModalCallback === 'function') {
                    confirmModalCallback();
                } else {
                    closeConfirmModal();
                }
            };
        }

        // 7. Hiển thị modal
        modal.style.display = 'flex';
        // Cho một frame ngắn để animation transform chạy mượt
        requestAnimationFrame(function() {
            modal.classList.add('active');
        });
        document.body.style.overflow = 'hidden';
    };

    /**
     * Đóng modal xác nhận
     */
    window.closeConfirmModal = function() {
        const modal = document.getElementById('reusableConfirmModal');
        if (!modal) return;
        modal.classList.remove('active');
        setTimeout(function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 150);
    };

    // Đóng khi nhấn phím ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeConfirmModal();
        }
    });
})();
</script>
