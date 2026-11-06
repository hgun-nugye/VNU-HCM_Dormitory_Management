<?php
ob_start();

$statsDefaults = [
    'total_students' => 26,
    'total_rooms' => 15,
    'total_beds' => 76,
    'available_beds' => 52,
    'occupied_beds' => 18,
    'waiting_applications' => 1,
    'hero_tabs' => [
        'all' => [
            'pct' => '23,7%',
            'sub' => '18 / 76 giường đang ở',
            'bar' => '23.7%',
            'badge' => '• 52 giường trống • Sẵn sàng tiếp nhận',
            'waiting' => '01 hồ sơ'
        ],
        'khuA' => [
            'pct' => '30,6%',
            'sub' => '11 / 36 giường đang ở',
            'bar' => '30.6%',
            'badge' => '• 20 giường trống • Sẵn sàng tiếp nhận',
            'waiting' => '00 hồ sơ'
        ],
        'khuB' => [
            'pct' => '17,5%',
            'sub' => '7 / 40 giường đang ở',
            'bar' => '17.5%',
            'badge' => '• 32 giường trống • Sẵn sàng tiếp nhận',
            'waiting' => '01 hồ sơ'
        ]
    ]
];
$homeStats = array_merge($statsDefaults, $stats ?? []);
$heroTabData = !empty($homeStats['hero_tabs']) ? $homeStats['hero_tabs'] : $statsDefaults['hero_tabs'];
$allTab = $heroTabData['all'] ?? $statsDefaults['hero_tabs']['all'];

$defaultRooms = [];
$roomLimit = 5;

if (!empty($availableRooms) && is_array($availableRooms)) {
    foreach ($availableRooms as $ar) {
        if (count($defaultRooms) >= $roomLimit) break;
        if ((int)$ar['GiuongTrong'] <= 0) continue;

        $rCode = str_replace('.', '-', $ar['MaPhong']);

        $defaultRooms[] = [
            'roomCode' => $rCode,
            'rawCode' => $ar['MaPhong'],
            'building' => $ar['MaToaNha'],
            'cum' => $ar['TenCum'],
            'area' => $ar['MaKhu'],
            'gender' => (strcasecmp($ar['GioiTinhQuyDinh'], 'Nữ') === 0 || strcasecmp($ar['GioiTinhQuyDinh'], 'Nu') === 0) ? 'Nữ' : 'Nam',
            'roomType' => $ar['TenLoaiPhong'],
            'roomTypeCode' => $ar['MaLoaiPhong'],
            'typeDesc' => 'Tiêu chuẩn',
            'floor' => (int)$ar['Tang'],
            'price' => number_format($ar['DonGiaLuuTruChuan'], 0, ',', '.') . ' đ/tháng',
            'availableBeds' => 'Trống ' . $ar['GiuongTrong'] . '/' . $ar['TongGiuong'],
            'totalBeds' => (int)$ar['TongGiuong'],
            'availableCount' => (int)$ar['GiuongTrong'],
            'schools' => ['ALL']
        ];
    }
}
?>

<div class="landing-wrapper">
    <!-- HERO BANNER FULL WIDTH WITH BACKGROUND SLIDER -->
    <div class="hero-stats-section" id="heroStatsSection">
        <!-- Nền ảnh chuyển động Fade Layer (img1, img2, img4) -->
        <div class="hero-bg-slider" id="heroBgSlider">
            <div class="hero-bg-slide active" style="background-image: linear-gradient(90deg, rgba(7, 30, 61, 0.94) 0%, rgba(8, 40, 77, 0.88) 55%, rgba(6, 27, 53, 0.85) 100%), url('<?= url('assets/images/img1.jpg') ?>');"></div>
            <div class="hero-bg-slide" style="background-image: linear-gradient(90deg, rgba(7, 30, 61, 0.94) 0%, rgba(8, 40, 77, 0.88) 55%, rgba(6, 27, 53, 0.85) 100%), url('<?= url('assets/images/img2.jpg') ?>');"></div>
            <div class="hero-bg-slide" style="background-image: linear-gradient(90deg, rgba(7, 30, 61, 0.94) 0%, rgba(8, 40, 77, 0.88) 55%, rgba(6, 27, 53, 0.85) 100%), url('<?= url('assets/images/img4.jpg') ?>');"></div>
        </div>

        <div class="hero-banner" style="position: relative; z-index: 2;">
            <div class="hero-grid">

                <!-- Hero Left Column: Multi-Slide Content -->
                <div class="hero-left">
                    <div class="hero-content-slider" id="heroContentSlider">
                        <!-- Slide 1 (Nền img1) -->
                        <div class="hero-slide-item active" data-slide="0">
                            <span class="hero-tag">Đợt đăng ký lưu trú mới &bull; Row-Level Locking</span>
                            <h1 class="hero-title">
                                Không gian phòng ở chuẩn mực<br>
                                <span class="highlight-blue">Xét duyệt &amp; giữ chỗ 72 giờ</span>
                            </h1>
                            <p class="hero-desc">
                                Lọc điều kiện lưu trú, xét duyệt hồ sơ và kiểm soát thời hạn thanh toán. Minh bạch từng bước, an tâm mỗi lần giữ chỗ.
                            </p>
                        </div>

                        <!-- Slide 2 (Nền img2) -->
                        <div class="hero-slide-item" data-slide="1">
                            <span class="hero-tag">Đại học Quốc gia TP. Hồ Chí Minh &bull; Cổng Lưu trú Sinh viên</span>
                            <h1 class="hero-title">
                                Hệ thống Quản lý<br>
                                <span class="highlight-blue">Ký túc xá ĐHQG-HCM</span>
                            </h1>
                            <p class="hero-desc">
                                Giải pháp tích hợp quản lý lưu trú sinh viên, xét duyệt giữ chỗ theo hạn định 72 giờ, dồn phòng tối ưu, thanh toán điện nước bậc thang và giám sát an ninh nội trú.
                            </p>
                        </div>

                        <!-- Slide 3 (Nền img4) -->
                        <div class="hero-slide-item" data-slide="2">
                            <span class="hero-tag">An ninh Nội trú Thông minh &bull; Kiểm soát Thẻ từ 24/7</span>
                            <h1 class="hero-title">
                                An toàn Nội vụ Sinh viên &amp;<br>
                                <span class="highlight-blue">Tự động hóa Điện Nước, Bảo trì</span>
                            </h1>
                            <p class="hero-desc">
                                Đối soát dữ liệu quét thẻ ra vào cổng với đơn xin tạm vắng, tính tiền điện nước lũy tiến minh bạch và chống trùng lặp phiếu báo hỏng thiết bị trong phòng.
                            </p>
                        </div>
                    </div>

                    <div class="hero-carousel-dots" id="heroCarouselDots">
                        <span class="dot active" data-slide="0" role="button" aria-label="Chuyển đến Slide 1" title="Chuyển đến Slide 1"></span>
                        <span class="dot" data-slide="1" role="button" aria-label="Chuyển đến Slide 2" title="Chuyển đến Slide 2"></span>
                        <span class="dot" data-slide="2" role="button" aria-label="Chuyển đến Slide 3" title="Chuyển đến Slide 3"></span>
                    </div>

                    <div class="hero-cta-btns">
                        <a href="<?= url('register') ?>" class="btn-hero-primary">
                            <span class="btn-cta-sub">Dành cho sinh viên chưa vào KTX</span>
                            <span class="btn-cta-main">Đăng ký lưu trú ngay</span>
                        </a>
                        <a href="<?= url('login') ?>" class="btn-hero-secondary">
                            <span class="btn-cta-sub">Dành cho sinh viên đang ở KTX</span>
                            <span class="btn-cta-main">Đăng nhập cổng nội trú</span>
                        </a>
                    </div>
                </div>

                <!-- Hero Right: Realtime Status Card -->
                <div class="hero-right">
                    <div class="hero-status-card">
                        <div class="status-card-header">
                            <div>
                                <h3>Trạng thái Giường Nội Trú</h3>
                                <p>Hệ thống tự động &bull; Giữ chỗ thời gian thực</p>
                            </div>
                            <div class="pill-tabs" id="heroPillTabs">
                                <button type="button" class="pill-tab active" data-tab="all">Toàn KTX</button>
                                <button type="button" class="pill-tab" data-tab="khuA">Khu A</button>
                                <button type="button" class="pill-tab" data-tab="khuB">Khu B</button>
                            </div>
                        </div>

                        <div id="heroPercentNumber" class="percent-number"><?= htmlspecialchars($allTab['pct'] ?? '23,7%') ?></div>
                        <div id="heroPercentSub" class="percent-sub"><?= htmlspecialchars($allTab['sub'] ?? '18 / 76 giường đang ở') ?></div>

                        <div class="custom-progress">
                            <div id="heroProgressBar" class="progress-bar-fill" style="width: <?= htmlspecialchars($allTab['bar'] ?? '23.7%') ?>;"></div>
                        </div>

                        <div id="heroStatusBadge" class="status-alert-badge">
                            <?= htmlspecialchars($allTab['badge'] ?? '• 52 giường trống • Sẵn sàng tiếp nhận') ?>
                        </div>

                        <div class="mini-info-box-grid">
                            <div>
                                <div class="mini-box-label">HỒ SƠ CHỜ DUYỆT (24H)</div>
                                <div id="heroWaitingApps" class="mini-box-val"><?= htmlspecialchars($allTab['waiting'] ?? '01 hồ sơ') ?></div>
                            </div>
                            <div>
                                <div class="mini-box-label">CƠ CHẾ KHÓA PHÒNG</div>
                                <div class="mini-box-val">Row-Level Lock</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 4 FLOATING STATS COUNTERS -->
        <div id="stats" class="stats-float-row">
            <div class="stat-box-card">
                <div class="stat-header">
                    <span class="stat-title">SINH VIÊN ĐĂNG KÝ</span>
                    <span class="stat-icon" style="color: #0284c7;"><i class="fa-solid fa-users"></i></span>
                </div>
                <div class="stat-val"><?= number_format($homeStats['total_students']) ?></div>
                <div class="stat-note">Hồ sơ trong hệ thống</div>
            </div>

            <div class="stat-box-card">
                <div class="stat-header">
                    <span class="stat-title">TỔNG SỐ PHÒNG Ở</span>
                    <span class="stat-icon" style="color: #6366f1;"><i class="fa-solid fa-building"></i></span>
                </div>
                <div class="stat-val"><?= number_format($homeStats['total_rooms']) ?></div>
                <div class="stat-note">Khu A và Khu B</div>
            </div>

            <div class="stat-box-card">
                <div class="stat-header">
                    <span class="stat-title">TỔNG SỐ GIƯỜNG</span>
                    <span class="stat-icon" style="color: #f59e0b;"><i class="fa-solid fa-bed"></i></span>
                </div>
                <div class="stat-val"><?= number_format($homeStats['total_beds']) ?></div>
                <div class="stat-note">Công suất thiết kế</div>
            </div>

            <div class="stat-box-card">
                <div class="stat-header">
                    <span class="stat-title">GIƯỜNG CÒN TRỐNG</span>
                    <span class="stat-icon" style="color: #10b981;"><i class="fa-solid fa-circle-check"></i></span>
                </div>
                <div class="stat-val val-green"><?= number_format($homeStats['available_beds']) ?></div>
                <div class="stat-note">Sẵn sàng tiếp nhận</div>
            </div>
        </div>

    </div>

    <!-- HIGHLIGHT BANNERS (2 COLUMNS: 1 LARGE, 2 STACKED) -->
    <div id="highlights" class="highlight-banners-grid">
        <!-- Banner Lớn -->
        <div class="banner-card banner-navy">
            <img src="<?= url('assets/images/img5.png') ?>" alt="Không gian phòng ở hiện đại" class="banner-bg-image">
            <div class="banner-overlay"></div>
            <div class="banner-content">
                <span class="banner-badge">ĐỢT ĐĂNG KÝ NỘI TRÚ &bull; NĂM HỌC 2026 &ndash; 2027</span>
                <h2 class="banner-title">Không gian phòng ở hiện đại &amp; Xét duyệt tự động trong 72 giờ</h2>
                <p class="banner-desc">Lọc tòa nhà theo giới tính, kiểm soát điều kiện lưu trú và khóa dòng giữ chỗ. Bắt đầu hành trình nội trú ngay hôm nay.</p>
                <div class="banner-actions">
                    <a href="<?= url('register') ?>" class="btn-hero-primary" style="display: inline-block;">Đăng ký chọn phòng ngay</a>
                    <a href="#services" class="btn-hero-secondary" style="display: inline-block;">Xem danh sách phòng trống</a>
                </div>
            </div>
        </div>

        <!-- 2 Banners Nhỏ Xếp Chồng -->
        <div class="banner-right-stack">
            <div class="banner-card-light">
                <span class="banner-badge">AN NINH NỘI TRÚ THÔNG MINH</span>
                <h3 class="banner-title">Kiểm soát cổng thẻ từ &amp; Kết xuất điểm rèn luyện</h3>
                <p class="banner-desc">Đối soát đơn tạm vắng và cảnh báo vắng mặt quá 30 ngày không lý do.</p>
            </div>

            <div class="banner-card-light">
                <span class="banner-badge">TIỆN ÍCH SỐ HÓA SINH VIÊN</span>
                <h3 class="banner-title">Thanh toán điện nước lũy tiến &amp; Báo hỏng trực tuyến</h3>
                <p class="banner-desc">Cảnh báo chỉ số bất thường &gt; 3 lần trung bình 3 tháng và chống trùng phiếu bảo trì.</p>
            </div>
        </div>
    </div>

    <!-- 8 QUY TRÌNH NGHIỆP VỤ CỐT LÕI -->
    <div id="core-features" class="section-title-wrap">
        <div class="section-super-title">TIÊU CHUẨN VẬN HÀNH</div>
        <h2 class="section-main-title">8 quy trình nghiệp vụ cốt lõi</h2>
        <p class="section-subtitle">Một hệ thống thống nhất. Mỗi bước lưu trú đều được kiểm soát rõ ràng.</p>
    </div>

    <div class="filter-tabs-row" id="processFilterTabs">
        <button type="button" class="filter-tab-btn active" data-category="all">Tất cả (8)</button>
        <button type="button" class="filter-tab-btn" data-category="phong">Xếp chỗ &amp; Phòng ở (4)</button>
        <button type="button" class="filter-tab-btn" data-category="taichinh">Tài chính &amp; Điện nước (2)</button>
        <button type="button" class="filter-tab-btn" data-category="noivu">Bảo trì &amp; Nội vụ SV (2)</button>
    </div>

    <div class="features-card-grid" id="processGrid">
        <!-- QT 01 -->
        <div class="feature-clean-card" data-category="phong">
            <div>
                <div class="feature-top-meta">
                    <div class="feature-icon-badge"><i class="fa-solid fa-lock"></i></div>
                    <span class="feature-id">QUY TRÌNH 01</span>
                </div>
                <h4 class="card-item-title">Khóa giường &amp; Kiểm soát điều kiện lưu trú</h4>
                <p class="card-item-desc">Tự động gợi ý phòng theo giới tính, kiểm tra điều kiện đào tạo chuẩn theo trường ĐH. Giao dịch CSDL với Row-Level Locking ngăn xung đột giữ chỗ cùng lúc.</p>
            </div>
            <div class="card-bottom-row">
                <span class="card-tag-pill">Lưu trú</span>
                <a href="javascript:void(0)" class="card-link-detail" onclick="openProcessDetail(1)">Chi tiết quy trình &rarr;</a>
            </div>
        </div>

        <!-- QT 02 -->
        <div class="feature-clean-card" data-category="phong">
            <div>
                <div class="feature-top-meta">
                    <div class="feature-icon-badge"><i class="fa-solid fa-clock"></i></div>
                    <span class="feature-id">QUY TRÌNH 02</span>
                </div>
                <h4 class="card-item-title">Duyệt hồ sơ &amp; Tự động thu hồi chỗ sau 72 giờ</h4>
                <p class="card-item-desc">Thiết lập hạn thanh toán 72 giờ sau khi duyệt. Dịch vụ chạy ngầm định kỳ quét hồ sơ quá hạn, thu hồi giường về trạng thái trống.</p>
            </div>
            <div class="card-bottom-row">
                <span class="card-tag-pill">72 giờ</span>
                <a href="javascript:void(0)" class="card-link-detail" onclick="openProcessDetail(2)">Chi tiết quy trình &rarr;</a>
            </div>
        </div>

        <!-- QT 03 -->
        <div class="feature-clean-card" data-category="phong">
            <div>
                <div class="feature-top-meta">
                    <div class="feature-icon-badge"><i class="fa-solid fa-chart-pie"></i></div>
                    <span class="feature-id">QUY TRÌNH 03</span>
                </div>
                <h4 class="card-item-title">Dồn phòng &amp; Tối ưu công suất lưu trú</h4>
                <p class="card-item-desc">Thống kê phòng có tỷ lệ lấp đầy &le; 50% vào giữa năm học. Đề xuất chuyển sinh viên sang phòng cùng loại trong cụm, giải phóng phòng bảo trì.</p>
            </div>
            <div class="card-bottom-row">
                <span class="card-tag-pill">Công suất</span>
                <a href="javascript:void(0)" class="card-link-detail" onclick="openProcessDetail(3)">Chi tiết quy trình &rarr;</a>
            </div>
        </div>

        <!-- QT 04 -->
        <div class="feature-clean-card" data-category="phong">
            <div>
                <div class="feature-top-meta">
                    <div class="feature-icon-badge"><i class="fa-solid fa-arrows-rotate"></i></div>
                    <span class="feature-id">QUY TRÌNH 04</span>
                </div>
                <h4 class="card-item-title">Chuyển phòng &amp; Bù trừ chênh lệch chi phí</h4>
                <p class="card-item-desc">Xác định số tháng hợp đồng còn lại, đối chiếu đơn giá phòng mới và cũ để phát hành hóa đơn nộp bổ sung hoặc phiếu hoàn trả.</p>
            </div>
            <div class="card-bottom-row">
                <span class="card-tag-pill">Tài chính</span>
                <a href="javascript:void(0)" class="card-link-detail" onclick="openProcessDetail(4)">Chi tiết quy trình &rarr;</a>
            </div>
        </div>

        <!-- QT 05 -->
        <div class="feature-clean-card" data-category="taichinh">
            <div>
                <div class="feature-top-meta">
                    <div class="feature-icon-badge"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                    <span class="feature-id">QUY TRÌNH 05</span>
                </div>
                <h4 class="card-item-title">Xét miễn giảm theo trần &amp; Hoàn phí trước hạn</h4>
                <p class="card-item-desc">Áp đơn theo đơn giá phòng tiêu chuẩn cho diện chính sách. Xác thực tốt nghiệp, bảo lưu, du học trong 07 ngày để hoàn phí các tháng chưa ở.</p>
            </div>
            <div class="card-bottom-row">
                <span class="card-tag-pill">Chính sách</span>
                <a href="javascript:void(0)" class="card-link-detail" onclick="openProcessDetail(5)">Chi tiết quy trình &rarr;</a>
            </div>
        </div>

        <!-- QT 06 -->
        <div class="feature-clean-card" data-category="taichinh">
            <div>
                <div class="feature-top-meta">
                    <div class="feature-icon-badge"><i class="fa-solid fa-bolt"></i></div>
                    <span class="feature-id">QUY TRÌNH 06</span>
                </div>
                <h4 class="card-item-title">Tính tiền điện nước bậc thang &amp; Kiểm tra bất thường</h4>
                <p class="card-item-desc">Cảnh báo chỉ số mới &lt; chỉ số cũ hoặc mức tiêu thụ &ge; 3 lần trung bình 3 tháng. Tự động chia đều cho số lượng SV lưu trú.</p>
            </div>
            <div class="card-bottom-row">
                <span class="card-tag-pill">Điện nước</span>
                <a href="javascript:void(0)" class="card-link-detail" onclick="openProcessDetail(6)">Chi tiết quy trình &rarr;</a>
            </div>
        </div>

        <!-- QT 07 -->
        <div class="feature-clean-card" data-category="noivu">
            <div>
                <div class="feature-top-meta">
                    <div class="feature-icon-badge"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                    <span class="feature-id">QUY TRÌNH 07</span>
                </div>
                <h4 class="card-item-title">Điều phối bảo trì &amp; Chống trùng lặp phiếu</h4>
                <p class="card-item-desc">Kiểm tra phiếu báo hỏng đang xử lý trong cùng phòng. Hậu kiểm phân công đội ngũ, điều hướng theo dõi tiến độ chung để tránh trùng phiếu.</p>
            </div>
            <div class="card-bottom-row">
                <span class="card-tag-pill">Bảo trì</span>
                <a href="javascript:void(0)" class="card-link-detail" onclick="openProcessDetail(7)">Chi tiết quy trình &rarr;</a>
            </div>
        </div>

        <!-- QT 08 -->
        <div class="feature-clean-card" data-category="noivu">
            <div>
                <div class="feature-top-meta">
                    <div class="feature-icon-badge"><i class="fa-solid fa-shield-halved"></i></div>
                    <span class="feature-id">QUY TRÌNH 08</span>
                </div>
                <h4 class="card-item-title">Giám sát vắng mặt 30 ngày &amp; Báo cáo điểm rèn luyện</h4>
                <p class="card-item-desc">Đối soát quét thẻ với đơn tạm vắng đã duyệt. Cảnh báo vắng liên tục &gt; 30 ngày không lý do, kết xuất điểm rèn luyện gửi về trường thành viên.</p>
            </div>
            <div class="card-bottom-row">
                <span class="card-tag-pill">An ninh</span>
                <a href="javascript:void(0)" class="card-link-detail" onclick="openProcessDetail(8)">Chi tiết quy trình &rarr;</a>
            </div>
        </div>
    </div>

    <!-- CỔNG TIỆN ÍCH NỘI TRÚ / TRA CỨU & TỰ PHỤC VỤ SINH VIÊN -->
    <div id="services" class="self-service-box">

        <div class="service-header">
            <div class="section-super-title">CỔNG TIỆN ÍCH NỘI TRÚ</div>
            <h2 class="section-main-title">Tra cứu &amp; tự phục vụ sinh viên</h2>
            <p class="section-subtitle">Trải nghiệm mở phòng &bull; Không gửi hồ sơ, thu tiền hay giữ giường thật. Dữ liệu đặt lại khi tải lại trang.</p>
        </div>

        <!-- Chỉ giữ lại duy nhất 1 Tab Tra Cứu theo đúng yêu cầu -->
        <div class="service-nav-tabs">
            <button type="button" class="service-tab-btn active">Tra cứu phòng &amp; Khóa giường 72h</button>
        </div>

        <!-- BỐ CỤC 2 CỘT: FILTER TRÁI & KẾT QUẢ PHÒNG PHẢI -->
        <div class="service-layout-grid">

            <!-- SIDEBAR BỘ LỌC -->
            <div class="filter-sidebar">
                <h4 class="sidebar-title">Tìm phòng phù hợp</h4>

                <div class="form-group-service">
                    <label for="serviceUniversity">Trường thành viên</label>
                    <select id="serviceUniversity" class="form-control-service">
                        <option value="UIT">UIT - ĐH Công nghệ Thông tin</option>
                        <option value="BK">BK - ĐH Bách Khoa</option>
                        <option value="KHTN">KHTN - ĐH Khoa học Tự nhiên</option>
                        <option value="KHXHNV">KHXHNV - ĐH Khoa học Xã hội &amp; Nhân văn</option>
                        <option value="KTPL">KTPL - ĐH Kinh tế - Luật</option>
                        <option value="QT">QT - ĐH Quốc tế</option>
                        <option value="AGU">AGU - ĐH An Giang</option>
                        <option value="KHOAY">KHOAY - Khoa Y ĐHQG-HCM</option>
                    </select>
                </div>

                <div class="form-group-service">
                    <label for="serviceGender">Giới tính tòa nhà</label>
                    <select id="serviceGender" class="form-control-service">
                        <option value="">Tất cả</option>
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                    </select>
                </div>

                <div class="form-group-service">
                    <label for="serviceZone">Khu ký túc xá</label>
                    <select id="serviceZone" class="form-control-service">
                        <option value="">Tất cả</option>
                        <option value="A">Khu A</option>
                        <option value="B">Khu B</option>
                    </select>
                </div>

                <div class="form-group-service">
                    <label for="serviceRoomType">Loại phòng</label>
                    <select id="serviceRoomType" class="form-control-service">
                        <option value="">Tất cả loại phòng</option>
                        <option value="4">Phòng 4 SV</option>
                        <option value="6">Phòng 6 SV</option>
                        <option value="8">Phòng 8 SV</option>
                    </select>
                </div>

                <p id="serviceSchoolDesc" class="filter-description">
                    Trường UIT: Áp dụng bảng giá phòng tiêu chuẩn. Chưa có dữ liệu điều kiện đào tạo theo trường.
                </p>
            </div>

            <!-- DANH SÁCH PHÒNG PHÙ HỢP -->
            <div class="room-results-area">

                <div class="room-list" id="roomResultsList">
                    <!-- Danh sách phòng render động bằng JavaScript -->
                </div>
            </div>

        </div>

    </div>

</div>

<!-- MODAL XEM CHI TIẾT QUY TRÌNH CỐT LÕI -->
<div id="processDetailModal" class="process-modal-backdrop" style="display: none;">
    <div class="process-modal-content">
        <button type="button" class="process-modal-close" onclick="closeProcessDetail()">&times;</button>
        <div id="processModalBody"></div>
    </div>
</div>

<script>
    // Dữ liệu danh sách phòng tra cứu
    const roomsDataset = <?= json_encode($defaultRooms, JSON_UNESCAPED_UNICODE) ?>;
    const registerBaseUrl = <?= json_encode(url('register'), JSON_UNESCAPED_SLASHES) ?>;

    // Thông tin chi tiết 8 Quy trình phục vụ Modal
    const processDetailsData = {
        1: {
            title: "Quy trình 01: Khóa giường & Kiểm soát điều kiện lưu trú",
            badge: "LƯU TRÚ · ROW-LEVEL LOCKING",
            desc: "Đảm bảo tính công bằng và loại bỏ hoàn toàn xung đột giữ chỗ khi nhiều sinh viên đăng ký cùng lúc.",
            steps: [
                "Hệ thống tự động lọc các tòa nhà phù hợp với giới tính sinh viên.",
                "Đối soát thời gian đào tạo chuẩn theo Trường Đại học thành viên (ví dụ: SV năm 1-4 áp dụng chính sách ưu tiên theo quy định).",
                "Khi sinh viên chọn giường, CSDL kích hoạt lệnh khóa hàng (SELECT ... FOR UPDATE) để tạm khóa vị trí giường trong phiên đăng ký.",
                "Ngăn ngừa 100% tình trạng hai sinh viên chọn trùng cùng một giường trong cùng thời điểm."
            ]
        },
        2: {
            title: "Quy trình 02: Duyệt hồ sơ & Tự động thu hồi chỗ sau 72 giờ",
            badge: "QUY CHẾ GIỮ CHỖ 72H",
            desc: "Tự động hóa toàn diện quy trình giữ chỗ, kiểm soát hạn chót nộp lệ phí nội trú.",
            steps: [
                "Sau khi Cán bộ quản lý duyệt hồ sơ, hệ thống kích hoạt đồng hồ đếm ngược 72 giờ (chính xác đến từng phút).",
                "Sinh viên nhận email thông báo kèm hạn thanh toán và hướng dẫn nộp lệ phí.",
                "Tiến trình tự động (Scheduled Cron Service) quét liên tục: nếu quá 72 giờ sinh viên chưa hoàn tất thanh toán, hệ thống tự động hủy giữ chỗ.",
                "Vị trí giường lập tức được hoàn trả về trạng thái 'Trống' để tiếp nhận sinh viên khác."
            ]
        },
        3: {
            title: "Quy trình 03: Dồn phòng & Tối ưu công suất lưu trú",
            badge: "TỐI ƯU HẠ TẦNG KTX",
            desc: "Tối ưu hóa nguồn tài nguyên phòng ốc, tiết kiệm điện nước và giải phóng phòng phục vụ bảo trì.",
            steps: [
                "Vào giữa học kỳ hoặc sau các đợt trả phòng, hệ thống tự động quét và lập danh sách các phòng có tỷ lệ lấp đầy <= 50%.",
                "Gợi ý phương án ghép phòng cùng loại trong cùng Cụm nhà cho Cán bộ Quản lý.",
                "Thông báo kế hoạch điều chuyển cho sinh viên trước 7 ngày làm việc.",
                "Sau khi dồn ghép thành công, phòng trống được đưa vào kế hoạch bảo trì, vệ sinh định kỳ."
            ]
        },
        4: {
            title: "Quy trình 04: Chuyển phòng & Bù trừ chênh lệch chi phí",
            badge: "TÀI CHÍNH & NỘI TRÚ",
            desc: "Minh bạch hóa việc điều chuyển phòng và tự động cân đối tài chính theo số tháng còn lại.",
            steps: [
                "Sinh viên nộp đơn xin chuyển phòng qua Cổng Một Cửa trực tuyến.",
                "Cán bộ quản lý kiểm tra phòng trống phù hợp và phê duyệt đơn.",
                "Hệ thống tính toán số tháng hợp đồng còn lại, so sánh đơn giá phòng cũ và phòng mới.",
                "Tự động phát hành hóa đơn nộp bổ sung (nếu chuyển sang phòng giá cao hơn) hoặc tạo phiếu hoàn trả tài chính (nếu chuyển sang phòng giá thấp hơn)."
            ]
        },
        5: {
            title: "Quy trình 05: Xét miễn giảm theo trần & Hoàn phí trước hạn",
            badge: "CHÍNH SÁCH XÃ HỘI",
            desc: "Thực hiện đúng chính sách hỗ trợ của Nhà nước và ĐHQG-HCM cho sinh viên có hoàn cảnh đặc biệt.",
            steps: [
                "Sinh viên thuộc diện chính sách nộp minh chứng (hộ nghèo, con thương binh, khuyết tật) khi làm hồ sơ.",
                "Áp dụng mức miễn giảm theo trần phòng tiêu chuẩn. Sinh viên ở phòng dịch vụ chỉ phải trả khoản chênh lệch tiện ích.",
                "Đối với các trường hợp tốt nghiệp sớm, bảo lưu hoặc du học, hệ thống xử lý hoàn phí trong vòng 7 ngày làm việc tính theo số tháng chưa ở."
            ]
        },
        6: {
            title: "Quy trình 06: Tính tiền điện nước bậc thang & Kiểm tra bất thường",
            badge: "ĐIỆN NƯỚC & CÔNG NỢ",
            desc: "Kiểm soát chỉ số tiêu thụ minh bạch, phát hiện sớm nguy cơ rò rỉ nước hoặc chập điện.",
            steps: [
                "Kỹ thuật viên ghi chỉ số qua hệ thống di động vào ngày 25-28 hàng tháng.",
                "Hệ thống kiểm tra tự động: Cảnh báo đỏ nếu chỉ số mới < chỉ số cũ hoặc mức tiêu thụ tăng đột biến >= 3 lần so với trung bình 3 tháng gần nhất.",
                "Áp dụng biểu giá bậc thang lũy tiến của Nhà nước.",
                "Hóa đơn được phát hành và tự động chia đều cho các sinh viên đang lưu trú thực tế tại phòng."
            ]
        },
        7: {
            title: "Quy trình 07: Điều phối bảo trì & Chống trùng lặp phiếu",
            badge: "BẢO TRÌ & SỬA CHỮA",
            desc: "Tiếp nhận và xử lý sự cố thiết bị nhanh chóng, tránh lãng phí nguồn lực kỹ thuật.",
            steps: [
                "Sinh viên gửi phiếu báo hỏng trực tuyến kèm hình ảnh chụp hiện trạng thiết bị.",
                "Hệ thống tự động kiểm tra xem trong phòng đó đã có phiếu báo hỏng thiết bị tương tự đang chờ xử lý hay chưa để chống tạo trùng.",
                "Tự động điều phối công việc đến Kỹ thuật viên phụ trách Cụm nhà.",
                "Kỹ thuật viên cập nhật tiến độ (Tiếp nhận -> Đang sửa -> Hoàn tất nghiệm thu) để sinh viên theo dõi trực tiếp."
            ]
        },
        8: {
            title: "Quy trình 08: Giám sát vắng mặt 30 ngày & Báo cáo điểm rèn luyện",
            badge: "AN NINH & ĐIỂM RÈN LUYỆN",
            desc: "Bảo đảm an toàn thân thể cho sinh viên và đồng bộ dữ liệu đánh giá về các trường Đại học thành viên.",
            steps: [
                "Cổng Barrier thẻ từ ghi nhận nhật ký vào/ra KTX của sinh viên 24/7.",
                "Thuật toán đối soát tự động đối chiếu dữ liệu quẹt thẻ với Đơn xin tạm vắng đã được duyệt.",
                "Phát cảnh báo đỏ khẩn cấp nếu sinh viên vắng mặt liên tục > 30 ngày mà không có đơn tạm vắng, kích hoạt liên hệ phụ huynh và nhà trường.",
                "Định kỳ cuối học kỳ, hệ thống tổng hợp điểm chấp hành nội quy và kết xuất báo cáo gửi trực tiếp về Ban Công tác Sinh viên của Trường."
            ]
        }
    };

    document.addEventListener('DOMContentLoaded', function() {

        // 0. Hero Carousel Slider (Tự động chuyển slide & ảnh nền img1, img2, img4)
        (function() {
            const bgSlides = document.querySelectorAll('#heroBgSlider .hero-bg-slide');
            const contentSlides = document.querySelectorAll('#heroContentSlider .hero-slide-item');
            const dots = document.querySelectorAll('#heroCarouselDots .dot');
            const totalSlides = contentSlides.length;
            if (totalSlides === 0) return;

            let currentSlide = 0;
            let slideTimer = null;
            const slideInterval = 5000; // Tự động chuyển sau mỗi 5 giây

            function goToSlide(index) {
                currentSlide = (index + totalSlides) % totalSlides;

                bgSlides.forEach((slide, i) => {
                    slide.classList.toggle('active', i === currentSlide);
                });

                contentSlides.forEach((slide, i) => {
                    slide.classList.toggle('active', i === currentSlide);
                });

                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === currentSlide);
                });
            }

            function startAutoSlide() {
                stopAutoSlide();
                slideTimer = setInterval(() => {
                    goToSlide(currentSlide + 1);
                }, slideInterval);
            }

            function stopAutoSlide() {
                if (slideTimer) {
                    clearInterval(slideTimer);
                    slideTimer = null;
                }
            }

            dots.forEach((dot, idx) => {
                dot.addEventListener('click', function() {
                    goToSlide(idx);
                    startAutoSlide();
                });
            });

            const bannerEl = document.querySelector('.hero-banner');
            if (bannerEl) {
                bannerEl.addEventListener('mouseenter', stopAutoSlide);
                bannerEl.addEventListener('mouseleave', startAutoSlide);
            }

            startAutoSlide();
        })();

        // 1. Chuyển đổi trạng thái Hero Status Card (Toàn KTX, Khu A, Khu B)
        const heroTabs = document.querySelectorAll('#heroPillTabs .pill-tab');
        const heroPercentNumber = document.getElementById('heroPercentNumber');
        const heroPercentSub = document.getElementById('heroPercentSub');
        const heroProgressBar = document.getElementById('heroProgressBar');
        const heroStatusBadge = document.getElementById('heroStatusBadge');
        const heroWaitingApps = document.getElementById('heroWaitingApps');

        const heroTabData = <?= json_encode($heroTabData, JSON_UNESCAPED_UNICODE) ?>;

        heroTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                heroTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const key = this.getAttribute('data-tab');
                const d = heroTabData[key] || heroTabData.all;
                if (heroPercentNumber) heroPercentNumber.textContent = d.pct;
                if (heroPercentSub) heroPercentSub.textContent = d.sub;
                if (heroProgressBar) heroProgressBar.style.width = d.bar;
                if (heroStatusBadge) heroStatusBadge.textContent = d.badge;
                if (heroWaitingApps) heroWaitingApps.textContent = d.waiting;
            });
        });

        // 2. Lọc 8 Quy trình cốt lõi
        const processButtons = document.querySelectorAll('#processFilterTabs .filter-tab-btn');
        const processCards = document.querySelectorAll('#processGrid .feature-clean-card');

        processButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                processButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const cat = this.getAttribute('data-category');

                processCards.forEach(card => {
                    if (cat === 'all') {
                        card.style.display = 'flex';
                    } else {
                        const cardCat = card.getAttribute('data-category');
                        card.style.display = (cardCat === cat) ? 'flex' : 'none';
                    }
                });
            });
        });

        // 3. Khởi tạo danh sách phòng tra cứu & bộ lọc
        const selectUni = document.getElementById('serviceUniversity');
        const selectGender = document.getElementById('serviceGender');
        const selectZone = document.getElementById('serviceZone');
        const selectRoomType = document.getElementById('serviceRoomType');
        const descSchool = document.getElementById('serviceSchoolDesc');
        const roomContainer = document.getElementById('roomResultsList');
        const countDisplay = document.getElementById('matchRoomCount');

        const schoolDescriptions = {
            'UIT': 'Trường UIT: Áp dụng bảng giá phòng tiêu chuẩn. Chưa có dữ liệu điều kiện đào tạo theo trường.',
            'BK': 'Trường Bách Khoa: Hỗ trợ ưu tiên lưu trú tại Khu A và Khu B cho sinh viên các khoa kỹ thuật.',
            'KHTN': 'Trường KHTN: Bố trí cụm phòng học tập nhóm và không gian yên tĩnh nghiên cứu khoa học.',
            'KHXHNV': 'Trường KHXH&NV: Ưu tiên phân bổ các dãy tòa nhà gần trạm xe buýt và cổng chính KTX.',
            'KTPL': 'Trường Kinh tế - Luật: Hỗ trợ xét duyệt diện con thương binh, gia đình chính sách theo trần miễn giảm.',
            'QT': 'Trường Quốc Tế: Bố trí các phòng dịch vụ máy lạnh cao cấp và tiêu chuẩn quốc tế.',
            'AGU': 'Trường ĐH An Giang: Ưu tiên lưu trú trọn khóa đối với sinh viên có hộ khẩu thường trú vùng xa.',
            'KHOAY': 'Khoa Y: Bố trí thời gian biểu ra vào linh hoạt phù hợp lịch trực lâm sàng tại bệnh viện.'
        };

        function renderRooms(rooms) {
            if (!roomContainer) return;
            roomContainer.innerHTML = '';

            if (countDisplay) {
                countDisplay.textContent = rooms.length;
            }

            if (rooms.length === 0) {
                roomContainer.innerHTML = `
                <div class="empty-room-box">
                    <i class="fa-solid fa-bed" style="font-size: 32px; color: #94a3b8; margin-bottom: 12px; display: block;"></i>
                    <h4 style="margin: 0 0 6px; color: #1e293b; font-size: 15px;">Không tìm thấy phòng phù hợp</h4>
                    <p style="margin: 0 0 16px; font-size: 13px;">Hãy thử điều chỉnh lại bộ lọc giới tính, khu KTX hoặc loại phòng để xem thêm các lựa chọn khác.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="resetRoomFilter()">Đặt lại bộ lọc</button>
                </div>
            `;
                return;
            }

            rooms.forEach(r => {
                const card = document.createElement('div');
                card.className = 'room-card-item';

                // Tạo liên kết giữ chỗ 72h dẫn đến trang đăng ký hồ sơ kèm thông tin phòng
                const targetUni = selectUni ? selectUni.value : 'UIT';
                const bookUrl = `${registerBaseUrl}?area=${encodeURIComponent(r.area)}&building=${encodeURIComponent(r.building)}&floor=${encodeURIComponent(r.floor)}&room=${encodeURIComponent(r.rawCode || r.roomCode)}&gender=${encodeURIComponent(r.gender)}&roomType=${encodeURIComponent(r.roomTypeCode || '')}&university=${encodeURIComponent(targetUni)}`;

                card.innerHTML = `
                <div class="room-information">
                    <div class="room-main-info">
                        <span class="room-code">${r.roomCode}</span>
                        <span class="room-sub-badge">Khu ${r.area} &bull; ${r.gender}</span>
                    </div>
                    <div class="room-meta-desc">${r.cum} &bull; ${r.roomType} &bull; ${r.typeDesc} &bull; Tầng ${r.floor}</div>
                    <div class="room-price-row">
                        <span class="price-bold">${r.price}</span>
                        <span class="beds-available-tag">${r.availableBeds}</span>
                    </div>
                </div>
                <a href="${bookUrl}" class="btn-book-room" title="Chuyển đến trang Đăng ký hồ sơ và điền sẵn phòng ${r.roomCode}">
                    <span>Giữ chỗ 72h</span>
                    <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
                </a>
            `;
                roomContainer.appendChild(card);
            });
        }

        function filterRooms() {
            const uni = selectUni ? selectUni.value : '';
            const gender = selectGender ? selectGender.value : '';
            const zone = selectZone ? selectZone.value : '';
            const type = selectRoomType ? selectRoomType.value : '';

            // Cập nhật mô tả trường
            if (descSchool && schoolDescriptions[uni]) {
                descSchool.textContent = schoolDescriptions[uni];
            }

            const filtered = roomsDataset.filter(r => {
                // Lọc giới tính
                if (gender && r.gender !== gender) {
                    return false;
                }
                // Lọc khu
                if (zone && r.area !== zone) {
                    return false;
                }
                // Lọc loại phòng (4, 6, 8 chỗ)
                if (type) {
                    if (type === '4' && !r.roomType.includes('4')) return false;
                    if (type === '6' && !r.roomType.includes('6')) return false;
                    if (type === '8' && !r.roomType.includes('8')) return false;
                }
                return true;
            });

            renderRooms(filtered);
        }

        window.resetRoomFilter = function() {
            if (selectGender) selectGender.value = '';
            if (selectZone) selectZone.value = '';
            if (selectRoomType) selectRoomType.value = '';
            filterRooms();
        };

        if (selectUni) selectUni.addEventListener('change', filterRooms);
        if (selectGender) selectGender.addEventListener('change', filterRooms);
        if (selectZone) selectZone.addEventListener('change', filterRooms);
        if (selectRoomType) selectRoomType.addEventListener('change', filterRooms);

        // Render sẵn danh sách phòng ban đầu khi chưa tra cứu
        renderRooms(roomsDataset);
    });

    // Modal chi tiết quy trình
    function openProcessDetail(id) {
        const data = processDetailsData[id];
        if (!data) return;

        const modal = document.getElementById('processDetailModal');
        const body = document.getElementById('processModalBody');
        if (!modal || !body) return;

        let stepsHtml = data.steps.map((s, idx) => `
        <li style="margin-bottom: 10px; display: flex; align-items: flex-start; gap: 10px;">
            <span style="background: #0284c7; color: #fff; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; margin-top: 1px;">${idx + 1}</span>
            <span style="font-size: 13px; color: #334155; line-height: 1.5;">${s}</span>
        </li>
    `).join('');

        body.innerHTML = `
        <div style="margin-bottom: 6px;">
            <span style="background: #e0f2fe; color: #0284c7; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">${data.badge}</span>
        </div>
        <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 10px;">${data.title}</h3>
        <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 20px;">${data.desc}</p>
        <h4 style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #475569; margin: 0 0 12px; letter-spacing: 0.5px;">Các bước triển khai chuẩn hóa:</h4>
        <ul style="list-style: none; padding: 0; margin: 0 0 24px;">
            ${stepsHtml}
        </ul>
        <div style="text-align: right; border-top: 1px solid #f1f5f9; padding-top: 16px;">
            <button type="button" class="btn btn-primary" onclick="closeProcessDetail()" style="padding: 7px 18px; font-size: 13px;">Đã hiểu</button>
        </div>
    `;

        modal.style.display = 'flex';
    }

    function closeProcessDetail() {
        const modal = document.getElementById('processDetailModal');
        if (modal) modal.style.display = 'none';
    }

    // Đóng modal khi bấm ra ngoài hoặc nhấn phím ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeProcessDetail();
    });
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('processDetailModal');
        if (modal && e.target === modal) closeProcessDetail();
    });
</script>

<?php
$content = ob_get_clean();
$title = "Hệ thống Quản lý Ký túc xá ĐHQG-HCM";
require __DIR__ . '/layouts/main.php';
?>