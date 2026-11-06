<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? config('app.name')) ?></title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- App Styles -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= time() ?>">
</head>

<body>
    <header class="navbar">
        <a href="<?= url('/') ?>" class="brand" style="display: flex; align-items: center; text-decoration: none;">
            <img src="<?= url('assets/images/Logo_DHQGHCM.png') ?>" alt="Đại học Quốc gia TP. Hồ Chí Minh - Hệ thống Quản lý Ký túc xá Sinh viên" style="height: 55px; width: auto; margin-left: 40px; margin-top: 4px; object-fit: contain;">
        </a>

        <ul class="nav-links">
            <li><a href="<?= url('/') ?>" class="<?= nav_active_class('') ?>">Trang chủ</a></li>

            <?php if (\App\Core\Auth::check()): ?>
                <?php if (\App\Core\Auth::isSinhVien()): ?>
                    <li><a href="<?= url('student/dashboard') ?>" class="<?= nav_active_class('student/dashboard') ?>">Cổng sinh viên</a></li>
                    <li><a href="<?= url('student/profile') ?>" class="<?= nav_active_class('student/profile') ?>">Hồ sơ sinh viên</a></li>
                    <li><a href="<?= url('student/payments') ?>" class="<?= nav_active_class('student/payments') ?>">Thanh toán</a></li>
                    <li><a href="<?= url('student/requests') ?>" class="<?= nav_active_class('student/requests') ?>">Dịch vụ một cửa</a></li>

                <?php elseif (\App\Core\Auth::isNhanVien()): ?>
                    <li><a href="<?= url('staff/dashboard') ?>" class="<?= nav_active_class('staff/dashboard') ?>">BQL Cụm</a></li>
                    <li><a href="<?= url('staff/profile') ?>" class="<?= nav_active_class('staff/profile') ?>">Hồ sơ cán bộ</a></li>

                    <?php if (\App\Core\Auth::isQuanLy()): ?>
                        <li><a href="<?= url('staff/zones') ?>" class="<?= nav_active_class('staff/zones') ?>">Khu KTX</a></li>
                        <li><a href="<?= url('staff/rooms') ?>" class="<?= nav_active_class('staff/rooms') ?>">Sơ đồ phòng</a></li>
                        <li><a href="<?= url('staff/applications') ?>" class="<?= nav_active_class('staff/applications') ?>">Xét duyệt 72h</a></li>
                        <li><a href="<?= url('staff/utilities') ?>" class="<?= nav_active_class('staff/utilities') ?>">Điện nước</a></li>
                        <li><a href="<?= url('staff/requests') ?>" class="<?= nav_active_class('staff/requests') ?>">Đơn 1 cửa</a></li>
                        <li><a href="<?= url('staff/maintenance') ?>" class="<?= nav_active_class('staff/maintenance') ?>">Bảo trì</a></li>
                        <li><a href="<?= url('staff/discipline') ?>" class="<?= nav_active_class('staff/discipline') ?>">An ninh</a></li>
                    <?php elseif (\App\Core\Auth::isKeToan()): ?>
                        <li><a href="<?= url('staff/utilities') ?>" class="<?= nav_active_class('staff/utilities') ?>">Điện nước & Công nợ</a></li>
                    <?php else: ?>
                        <li><a href="<?= url('staff/maintenance') ?>" class="<?= nav_active_class('staff/maintenance') ?>">Bảo trì</a></li>
                    <?php endif; ?>

                <?php elseif (\App\Core\Auth::isQuanTriVien()): ?>
                    <li><a href="<?= url('admin/dashboard') ?>" class="<?= nav_active_class('admin/dashboard') ?>">Dashboard</a></li>
                    <li><a href="<?= url('admin/users') ?>" class="<?= nav_active_class('admin/users') ?>">Cán bộ</a></li>
                    <li><a href="<?= url('admin/students') ?>" class="<?= nav_active_class('admin/students') ?>">Sinh viên</a></li>
                    <li><a href="<?= url('admin/universities') ?>" class="<?= nav_active_class('admin/universities') ?>">Trường ĐH</a></li>
                <?php endif; ?>

                <li><span class="user-info"><?= htmlspecialchars(\App\Core\Auth::user()['HoVaTen'] ?? \App\Core\Auth::user()['TenDangNhap'], ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><a href="<?= url('logout') ?>" class="btn btn-primary btn-sm" style="color: white;">Đăng xuất</a></li>
            <?php else: ?>
                <li><a href="<?= url('register') ?>" class="<?= nav_active_class('register') ?>">Đăng ký hồ sơ</a></li>
                <li><a href="<?= url('login') ?>" class="btn btn-primary btn-sm" style="color: white;">Đăng nhập</a></li>
            <?php endif; ?>
        </ul>
    </header>

    <main>
        <?= $content ?? '' ?>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-main">
                <section class="footer-brand" aria-label="Thông tin hệ thống">
                    <div class="brand">
                        <div class="brand-logo" aria-hidden="true">
                            <img src="<?= url('assets/images/logo_Small.png') ?>"
                                alt="Logo"
                                width="36"
                                height="36"
                                style="object-fit: contain;">
                        </div>
                        <div>
                            <h2 class="brand-title">HỆ THỐNG QUẢN LÝ KÝ TÚC XÁ</h2>
                            <p class="brand-subtitle">Đại học Quốc gia Thành phố Hồ Chí Minh</p>
                        </div>
                    </div>

                    <p class="footer-description">
                        Nền tảng số hóa chuyên biệt phục vụ công tác quản lý lưu trú sinh viên nội trú,
                        tối ưu công suất phòng ở, minh bạch tài chính điện nước và đảm bảo an ninh nội vụ sinh viên.
                    </p>
                </section>

                <section aria-label="Địa chỉ các khu ký túc xá">
                    <h3 class="footer-heading">ĐỊA CHỈ CÁC KHU KÝ TÚC XÁ</h3>
                    <ul class="footer-list">
                        <li>
                            <svg class="footer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />
                                <circle cx="12" cy="10" r="2.5" />
                            </svg>
                            <span><strong>Ký túc xá Khu A:</strong> Đường Tạ Quang Bửu, Khu phố 33, Phường Linh Xuân, TP. Hồ Chí Minh.</span>
                        </li>
                        <li>
                            <svg class="footer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />
                                <circle cx="12" cy="10" r="2.5" />
                            </svg>
                            <span><strong>Ký túc xá Khu B:</strong> Đường Mạc Đĩnh Chi, Khu phố Tân Hòa, Phường Đông Hòa, TP. Hồ Chí Minh.</span>
                        </li>
                    </ul>
                </section>

                <section aria-label="Thông tin hỗ trợ">
                    <h3 class="footer-heading">TỔNG ĐÀI HỖ TRỢ NỘI TRÚ</h3>
                    <ul class="footer-list">
                        <li>
                            <svg class="footer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 11.2 19a19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z" />
                            </svg>
                            <span>Hotline: <strong>1900.055.559</strong></span>
                        </li>
                        <li style="align-items: flex-start;">
                            <svg class="footer-icon footer-icon--green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-top: 3px; flex-shrink: 0;">
                                <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
                                <path d="M12 8v4m0 4h.01" />
                            </svg>
                            <span style="white-space: nowrap; margin-right: 6px;">Trực an ninh:</span>
                            <span style="display: flex; flex-direction: column; gap: 4px; text-align: left; white-space: nowrap;">
                                <span><strong>0983509807</strong> (Khu A)</span>
                                <span><strong>0372702334</strong> (Khu B)</span>
                            </span>
                        </li>
                        <li>
                            <svg class="footer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="m3 7 9 6 9-6" />
                            </svg>
                            <span>Email: <a href="mailto:info@ktxhcm.edu.vn" style="color: inherit; text-decoration: none;">info@ktxhcm.edu.vn</a></span>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="footer-bottom">
                <p>© 2026 Hệ thống Quản lý Ký túc xá Sinh viên ĐHQG-HCM.</p>
                <p>Cổng thông tin Lưu trú Sinh viên Nội trú</p>
            </div>
        </div>
    </footer>

    <!-- Reusable Confirmation Modal -->
    <?php require_once dirname(__DIR__) . '/components/confirm_modal.php'; ?>

    <script src="<?= asset('js/main.js') ?>?v=<?= time() ?>"></script>
    <script>
        // Tự động kiểm tra và đánh dấu thẻ đang active trên thanh điều hướng
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
            const navLinks = document.querySelectorAll('.nav-links a:not(.btn)');
            let matched = false;

            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (!href) return;
                try {
                    const url = new URL(href, window.location.origin);
                    const linkPath = url.pathname.replace(/\/+$/, '') || '/';

                    if (linkPath === currentPath) {
                        link.classList.add('active');
                        matched = true;
                    }
                } catch (e) {}
            });
            
            // Nếu chưa khớp chính xác, khớp theo tiền tố đường dẫn (trừ trang chủ '/')
            if (!matched && currentPath !== '/') {
                navLinks.forEach(link => {
                    const href = link.getAttribute('href');
                    if (!href) return;
                    try {
                        const url = new URL(href, window.location.origin);
                        const linkPath = url.pathname.replace(/\/+$/, '') || '/';
                        if (linkPath !== '/' && currentPath.startsWith(linkPath)) {
                            link.classList.add('active');
                        }
                    } catch (e) {}
                });
            }
        });
    </script>
</body>

</html>