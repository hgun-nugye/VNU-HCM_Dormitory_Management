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
        <a href="<?= url('/') ?>" class="brand">
            <span class="brand-name">KTX ĐHQG-HCM</span>
            <span class="badge-uni">VNU-HCM</span>
        </a>
        <ul class="nav-links">   
            <li><a href="<?= url('/') ?>" style="font-weight: 600; color: #2563eb;">Trang chủ</a></li>         
            <?php if (\App\Core\Auth::check()): ?>
                <?php if (\App\Core\Auth::isSinhVien()): ?>
                    <li><a href="<?= url('/') ?>" style="font-weight: 600; color: #2563eb;">Trang chủ</a></li>
                    <li><a href="<?= url('student/dashboard') ?>">Cổng sinh viên</a></li>
                    <li><a href="<?= url('student/profile') ?>">Hồ sơ sinh viên</a></li>
                    <li><a href="<?= url('student/payments') ?>">Thanh toán</a></li>
                <?php elseif (\App\Core\Auth::isNhanVien()): ?>
                    <li><a href="<?= url('/') ?>" style="font-weight: 600; color: #2563eb;">Trang chủ</a></li>
                    <li><a href="<?= url('staff/dashboard') ?>">BQL Cụm</a></li>
                    <li><a href="<?= url('staff/rooms') ?>">Sơ đồ phòng</a></li>
                    <li><a href="<?= url('staff/applications') ?>">Xét duyệt 72h</a></li>
                    <li><a href="<?= url('staff/utilities') ?>">Điện nước</a></li>
                    <li><a href="<?= url('staff/requests') ?>">Đơn 1 cửa</a></li>
                    <li><a href="<?= url('staff/maintenance') ?>">Bảo trì</a></li>
                    <li><a href="<?= url('staff/discipline') ?>">An ninh</a></li>
                <?php elseif (\App\Core\Auth::isQuanTriVien()): ?>
                    <li><a href="<?= url('/') ?>" style="font-weight: 600; color: #2563eb;">Trang chủ</a></li>
                    <li><a href="<?= url('admin/dashboard') ?>">Dashboard</a></li>
                    <li><a href="<?= url('admin/users') ?>">Cán bộ</a></li>
                    <li><a href="<?= url('admin/rooms') ?>">Phòng ở</a></li>
                    <li><a href="<?= url('admin/periods') ?>">Đợt 72h</a></li>
                    <li><a href="<?= url('admin/room-types') ?>">Loại phòng</a></li>
                    <li><a href="<?= url('admin/universities') ?>">Trường ĐH</a></li>
                <?php endif; ?>
                <li>
                    <span class="user-info">
                        <?= htmlspecialchars(\App\Core\Auth::user()['HoVaTen'] ?? \App\Core\Auth::user()['TenDangNhap']) ?>
                    </span>
                </li>
                <li><a href="<?= url('logout') ?>" class="btn btn-primary btn-sm" style="color:white">Đăng xuất</a></li>
            <?php else: ?>
                <!-- <li><a href="<?= url('student/profile') ?>">Hồ sơ SV</a></li>
                <li><a href="<?= url('student/payments') ?>">Thanh toán</a></li>
                <li><a href="<?= url('staff/dashboard') ?>" style="font-weight: 600; color: #059669;">BQL KTX</a></li>
                <li><a href="<?= url('admin/dashboard') ?>" style="font-weight: 600; color: #2563eb;">Admin KTX</a></li> -->
                <li><a href="<?= url('login') ?>" class="btn btn-primary btn-sm" style="color:white">Đăng nhập</a></li>
            <?php endif; ?>
        </ul>
    </header>

    <main>
        <?= $content ?? '' ?>
    </main>

    <footer>
        <p>© <?= date('Y') ?> Trung tâm Quản lý Ký túc xá Đại học Quốc gia TP.HCM</p>
        <p class="footer-sub">Hệ thống quản lý nội trú - Kiến trúc MVC</p>
    </footer>

    <!-- Reusable Confirmation Modal -->
    <?php require_once dirname(__DIR__) . '/components/confirm_modal.php'; ?>

    <script src="<?= asset('js/main.js') ?>?v=<?= time() ?>"></script>
</body>

</html>