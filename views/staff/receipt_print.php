<?php
$receipt = $receipt ?? [];
$invoices = $receipt['invoices'] ?? [];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phiếu thu tiền mặt - <?= htmlspecialchars($receipt['receipt_code'] ?? 'KTX') ?></title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 13pt;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 20px;
            background: #f8fafc;
        }
        .receipt-container {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            padding: 30px 40px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .title-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .title-section h1 {
            margin: 0;
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .info-row {
            margin-bottom: 8px;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 11.5pt;
        }
        table.items-table th, table.items-table td {
            border: 1px solid #000;
            padding: 7px 10px;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            text-align: center;
            margin-top: 25px;
        }
        .btn-print {
            background: #005a3c;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 20px;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .receipt-container {
                border: none;
                box-shadow: none;
                padding: 10mm 15mm;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: center;">
        <button type="button" class="btn-print" onclick="window.print()">🖨️ In Phiếu Thu (Ctrl + P)</button>
        <button type="button" class="btn-print" style="background: #64748b;" onclick="window.close()">Đóng cửa sổ</button>
    </div>

    <div class="receipt-container">
        <!-- HEADER ĐƠN VỊ -->
        <table class="header-table">
            <tr>
                <td style="width: 58%; text-align: center; vertical-align: top;">
                    <div style="font-size: 11pt; text-transform: uppercase; font-weight: bold;">ĐẠI HỌC QUỐC GIA TP. HỒ CHÍ MINH</div>
                    <div style="font-size: 11pt; text-transform: uppercase; font-weight: bold; color: #005a3c;">TRUNG TÂM QUẢN LÝ KÝ TÚC XÁ</div>
                    <div style="font-size: 9.5pt; font-style: italic; margin-top: 2px;">Văn phòng Kế toán & Tài vụ</div>
                </td>
                <td style="width: 42%; text-align: right; vertical-align: top;">
                    <div style="font-weight: bold; font-size: 11pt;">Mẫu số: 01-TT / KTX</div>
                    <div style="font-size: 10pt; margin-top: 2px;">Số: <b style="font-family: monospace; font-size: 11pt;"><?= htmlspecialchars($receipt['receipt_code'] ?? 'PT...') ?></b></div>
                    <div style="font-size: 9.5pt; font-style: italic; margin-top: 2px;">Liên 2: Giao người nộp tiền</div>
                </td>
            </tr>
        </table>

        <!-- TIÊU ĐỀ PHIẾU THU -->
        <div class="title-section">
            <h1>PHIẾU THU TIỀN MẶT</h1>
            <div style="font-style: italic; font-size: 10.5pt; margin-top: 4px;">
                Ngày lập: <?= htmlspecialchars($receipt['created_at'] ?? date('d/m/Y H:i')) ?>
            </div>
        </div>

        <!-- THÔNG TIN NGƯỜI NỘP -->
        <div class="info-row">- Họ và tên người nộp tiền: <b style="text-transform: uppercase;"><?= htmlspecialchars($receipt['student_name'] ?? '') ?></b></div>
        <div class="info-row" style="display: flex; justify-content: space-between;">
            <div>- Mã số sinh viên (MSSV): <b><?= htmlspecialchars($receipt['mssv'] ?? '') ?></b></div>
            <div>Số ĐT: <?= htmlspecialchars($receipt['phone'] ?? '---') ?></div>
        </div>
        <div class="info-row">- Vị trí cư trú: <?= htmlspecialchars($receipt['room'] ?? '---') ?></div>
        <div class="info-row">- Lý do nộp: <?= htmlspecialchars($receipt['notes'] ?? 'Thu tiền mặt tại quầy Kế toán KTX') ?></div>

        <!-- BẢNG CÁC KHOẢN THU -->
        <table class="items-table">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="width: 40px; text-align: center;">STT</th>
                    <th style="text-align: left;">Khoản thu / Nội dung</th>
                    <th style="width: 140px; text-align: center;">Mã hóa đơn</th>
                    <th style="width: 130px; text-align: right;">Số tiền (VNĐ)</th>
                </tr>
            </thead>
            <tbody>
                <?php $stt = 1; foreach ($invoices as $inv): ?>
                    <?php $amt = (float)($inv['SoTien'] ?? $inv['SoTienPhaiDong'] ?? 0); ?>
                    <tr>
                        <td style="text-align: center;"><?= $stt++ ?></td>
                        <td><?= htmlspecialchars($inv['LoaiHoaDon'] ?? 'Lệ phí KTX') ?></td>
                        <td style="text-align: center; font-family: monospace;"><?= htmlspecialchars($inv['MaHoaDon'] ?? '') ?></td>
                        <td style="text-align: right; font-family: monospace;"><?= number_format($amt, 0, ',', '.') ?> đ</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" style="text-align: right; font-weight: bold;">TỔNG CỘNG TIỀN MẶT:</th>
                    <th style="text-align: right; font-weight: bold; font-family: monospace; font-size: 12pt;">
                        <?= number_format((float)($receipt['total_amount'] ?? 0), 0, ',', '.') ?> đ
                    </th>
                </tr>
            </tfoot>
        </table>

        <!-- SỐ TIỀN BẰNG CHỮ -->
        <div style="font-size: 11.5pt; font-style: italic; margin-bottom: 24px;">
            - Số tiền viết bằng chữ: <b style="font-style: normal;"><?= htmlspecialchars($receipt['total_words'] ?? '') ?></b>
        </div>

        <!-- CHỮ KÝ 2 BÊN -->
        <div class="signatures">
            <div>
                <b>NGƯỜI NỘP TIỀN</b>
                <div style="font-size: 9.5pt; font-style: italic;">(Ký, ghi rõ họ tên)</div>
                <div style="height: 65px;"></div>
                <b><?= htmlspecialchars($receipt['student_name'] ?? '') ?></b>
            </div>
            <div>
                <b>NGƯỜI THU TIỀN (KẾ TOÁN)</b>
                <div style="font-size: 9.5pt; font-style: italic;">(Ký, đóng dấu xác nhận)</div>
                <div style="height: 65px;"></div>
                <b><?= htmlspecialchars($receipt['cashier_name'] ?? 'Kế toán KTX') ?></b>
            </div>
        </div>

        <div style="margin-top: 30px; padding-top: 10px; border-top: 1px dashed #94a3b8; font-size: 9.5pt; color: #64748b; font-style: italic; text-align: center;">
            * Biên lai này là căn cứ hợp lệ xác nhận sinh viên đã hoàn thành nghĩa vụ tài chính để nhận phòng tại KTX ĐHQG-HCM.
        </div>
    </div>

    <script>
        // Tự động mở hộp thoại in khi tải xong trang
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>

