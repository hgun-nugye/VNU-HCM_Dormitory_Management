<?php

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\MailService;

class RequestController extends Controller {
    public function attachment(): void {
        $request = new Request();
        $kind = (string) $request->input('kind', '');
        $id = trim((string) $request->input('id', ''));
        $mssv = $this->studentId();
        $isStudent = Auth::isSinhVien();
        $isStaff = Auth::isNhanVien() || Auth::isQuanLy() || Auth::isQuanTriVien();

        if ($id === '' || (!$isStudent && !$isStaff)) {
            http_response_code(403);
            exit('Không tìm thấy tệp.');
        }

        if ($kind === 'proof') {
            $proof = $isStudent
                ? Database::fetch('SELECT TepMinhChung AS FilePath FROM DonTuMotCua WHERE MaDon = :id AND MaSinhVien = :student LIMIT 1', ['id' => $id, 'student' => $mssv])
                : Database::fetch('SELECT TepMinhChung AS FilePath FROM DonTuMotCua WHERE MaDon = :id LIMIT 1', ['id' => $id]);
            $storedPath = $proof['FilePath'] ?? null;
            $directory = 'student-requests';
            $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        } elseif ($kind === 'repair') {
            $repair = $isStudent
                ? Database::fetch('SELECT HinhAnh AS FilePath FROM PhieuBaoHong WHERE MaPhieu = :id AND MaSinhVienBao = :student LIMIT 1', ['id' => $id, 'student' => $mssv])
                : Database::fetch('SELECT HinhAnh AS FilePath FROM PhieuBaoHong WHERE MaPhieu = :id LIMIT 1', ['id' => $id]);
            $storedPath = $repair['FilePath'] ?? null;
            $directory = 'repairs';
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        } else {
            http_response_code(404);
            exit('Không tìm thấy tệp.');
        }

        $expectedPrefix = 'storage/uploads/' . $directory . '/';
        if (!is_string($storedPath) || !str_starts_with(str_replace('\\', '/', $storedPath), $expectedPrefix)) {
            http_response_code(404);
            exit('Không tìm thấy tệp.');
        }

        $root = dirname(__DIR__, 3);
        $uploadRoot = realpath($root . '/storage/uploads/' . $directory);
        $filePath = $uploadRoot ? realpath($uploadRoot . DIRECTORY_SEPARATOR . basename($storedPath)) : false;
        if (!$uploadRoot || !$filePath || !is_file($filePath) || !str_starts_with($filePath, $uploadRoot . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Không tìm thấy tệp.');
        }

        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        if (!in_array($mime, $allowedMimes, true)) {
            http_response_code(415);
            exit('Định dạng tệp không được hỗ trợ.');
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . rawurlencode(basename($filePath)) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit;
    }

    public function index(): void {
        $mssv = $this->studentId();
        $requests = $mssv ? Database::fetchAll(
            "SELECT d.*, hd.MaHopDong, g.KyHieuGiuong AS GiuongMoi, p.SoPhong AS PhongMoi
             FROM DonTuMotCua d
             LEFT JOIN HopDongNoiTru hd ON hd.MaHopDong = d.MaHopDong
             LEFT JOIN Giuong g ON d.MaGiuongChuyenDen = g.MaGiuong
             LEFT JOIN Phong p ON g.MaPhong = p.MaPhong
             WHERE d.MaSinhVien = :mssv ORDER BY d.MaDon DESC",
            ['mssv' => $mssv]
        ) : [];
        $repairs = $mssv ? Database::fetchAll(
            "SELECT pb.*, tb.TenThietBi, tb.MaSoThietBi
             FROM PhieuBaoHong pb
             LEFT JOIN ThietBiPhong tb ON pb.MaThietBi = tb.MaThietBi
             WHERE pb.MaSinhVienBao = :mssv ORDER BY pb.NgayBao DESC",
            ['mssv' => $mssv]
        ) : [];
        $beds = Database::fetchAll(
            "SELECT g.MaGiuong, g.KyHieuGiuong, g.MaPhong, p.SoPhong
             FROM Giuong g JOIN Phong p ON g.MaPhong = p.MaPhong
             WHERE g.TrangThaiGiuong IN ('Trống', 'Cho phép chuyển')
             ORDER BY g.MaPhong, g.KyHieuGiuong LIMIT 100"
        );
        $contract = Database::fetch(
            "SELECT * FROM HopDongNoiTru WHERE MaSinhVien = :mssv AND TrangThaiHopDong = 'Đang hiệu lực' LIMIT 1",
            ['mssv' => $mssv]
        );

        $this->view('student.requests', [
            'requests' => $requests,
            'repairs' => $repairs,
            'beds' => $beds,
            'contract' => $contract,
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
            'title' => 'Dịch vụ một cửa'
        ]);
    }

    public function transfer(): void { $this->showForm('Xin chuyển phòng', 'Yêu cầu chuyển sang giường/phòng khác'); }
    public function repair(): void { $this->showForm('Xin sửa chữa', 'Báo hỏng thiết bị trong phòng nội trú'); }
    public function leave(): void { $this->showForm('Xin tạm vắng', 'Khai báo thời gian tạm vắng khỏi KTX'); }
    public function returnRoom(): void { $this->showForm('Xin trả phòng', 'Kết thúc hợp đồng và trả lại chỗ ở'); }
    public function extension(): void { $this->showForm('Hồ sơ miễn giảm lệ phí', 'Nộp minh chứng để xét miễn hoặc giảm lệ phí phòng ở'); }

    private function showForm(string $type, string $description): void {
        $mssv = $this->studentId();
        $contract = Database::fetch(
            "SELECT hd.*, g.MaPhong, g.KyHieuGiuong, p.MaToaNha
             FROM HopDongNoiTru hd JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
             JOIN Phong p ON p.MaPhong = g.MaPhong
             WHERE hd.MaSinhVien = :mssv AND hd.TrangThaiHopDong = 'Đang hiệu lực' LIMIT 1",
            ['mssv' => $mssv]
        );
        $beds = $type === 'Xin chuyển phòng' ? Database::fetchAll(
            "SELECT g.MaGiuong, g.KyHieuGiuong, g.MaPhong, p.SoPhong
             FROM Giuong g JOIN Phong p ON g.MaPhong = p.MaPhong
             WHERE g.TrangThaiGiuong IN ('Trống', 'Cho phép chuyển')
             ORDER BY g.MaPhong, g.KyHieuGiuong"
        ) : [];
        $equipment = [];
        if ($type === 'Xin sửa chữa' && $contract) {
             $equipment = Database::fetchAll(
                 "SELECT MaThietBi, TenThietBi, LoaiThietBi, MaSoThietBi
                  FROM ThietBiPhong
                  WHERE MaPhong = :room
                  ORDER BY TenThietBi, MaSoThietBi",
                 ['room' => $contract['MaPhong']]
             );
        }
        $this->view('student.request-form', [
            'serviceType' => $type,
            'serviceDescription' => $description,
            'contract' => $contract,
            'beds' => $beds,
            'equipment' => $equipment,
            'error' => Session::flash('error'),
            'success' => Session::flash('success'),
            'title' => $type
        ]);
    }

    public function create(): void {
        $request = new Request();
        $mssv = $this->studentId();
        $type = trim((string) $request->input('LoaiDon', ''));
        $token = (string) $request->input('csrf_token', '');
        $sessionToken = (string) Session::get('csrf_token', '');
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            Session::flash('error', 'Phiên làm việc đã hết hạn.');
            $this->redirect($this->serviceRoute($type));
            return;
        }

        $reason = trim((string) $request->input('LyDo', ''));
        $allowed = ['Xin chuyển phòng', 'Xin sửa chữa', 'Xin tạm vắng', 'Xin trả phòng', 'Xin gia hạn', 'Hồ sơ miễn giảm lệ phí'];
        if (!in_array($type, $allowed, true)) {
            Session::flash('error', 'Vui lòng chọn loại đơn hợp lệ.');
            $this->redirect($this->serviceRoute($type));
            return;
        }

        if (mb_strlen($reason) < 10) {
            Session::flash('error', 'Vui lòng nhập lý do ít nhất 10 ký tự.');
            $this->redirect($this->serviceRoute($type));
            return;
        }

        $wordCount = count(preg_split('/\s+/u', $reason, -1, PREG_SPLIT_NO_EMPTY));
        if ($wordCount >= 100) {
            Session::flash('error', 'Lý do gửi yêu cầu phải dưới 100 từ (hiện tại có ' . $wordCount . ' từ).');
            $this->redirect($this->serviceRoute($type));
            return;
        }

        $from = trim((string) $request->input('TuNgay', ''));
        $to = trim((string) $request->input('DenNgay', ''));
        if ($type === 'Xin tạm vắng' && ($from === '' || $to === '' || $to < $from)) {
            Session::flash('error', 'Ngày tạm vắng không hợp lệ.');
            $this->redirect($this->serviceRoute($type));
            return;
        }
        if ($type === 'Xin trả phòng' && $from === '') {
            Session::flash('error', 'Vui lòng chọn ngày trả phòng.');
            $this->redirect($this->serviceRoute($type));
            return;
        }
        if ($type === 'Xin gia hạn' && $to === '') {
            Session::flash('error', 'Vui lòng chọn ngày gia hạn mong muốn.');
            $this->redirect($this->serviceRoute($type));
            return;
        }
        $proofPath = null;
        if ($type === 'Hồ sơ miễn giảm lệ phí') {
            if (!isset($_FILES['TepMinhChung']) || ($_FILES['TepMinhChung']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                Session::flash('error', 'Vui lòng tải lên tệp minh chứng miễn giảm.');
                $this->redirect($this->serviceRoute($type));
                return;
            }
            $file = $_FILES['TepMinhChung'];
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
                Session::flash('error', 'Tệp minh chứng không hợp lệ hoặc vượt quá 5 MB.');
                $this->redirect($this->serviceRoute($type));
                return;
            }
            $mime = mime_content_type($file['tmp_name']);
            $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($mime, $allowedMime, true)) {
                Session::flash('error', 'Tệp minh chứng chỉ chấp nhận JPG, PNG hoặc PDF.');
                $this->redirect($this->serviceRoute($type));
                return;
            }
            $extension = match ($mime) {
                'image/png' => 'png',
                'application/pdf' => 'pdf',
                default => 'jpg',
            };
            $uploadDir = dirname(__DIR__, 3) . '/storage/uploads/student-requests';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $filename = 'proof_' . bin2hex(random_bytes(8)) . '.' . $extension;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                Session::flash('error', 'Không thể lưu tệp minh chứng.');
                $this->redirect($this->serviceRoute($type));
                return;
            }
            $proofPath = 'storage/uploads/student-requests/' . $filename;
        }

        try {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            $contract = Database::fetch(
                "SELECT MaHopDong FROM HopDongNoiTru WHERE MaSinhVien = :mssv AND TrangThaiHopDong = 'Đang hiệu lực' LIMIT 1",
                ['mssv' => $mssv]
            );
            if (!$contract) {
                throw new \RuntimeException('student_contract_missing');
            }

            if ($type === 'Xin sửa chữa') {
                $duplicate = Database::fetch(
                    "SELECT MaPhieu FROM PhieuBaoHong
                     WHERE MaSinhVienBao = :mssv AND TrangThaiSua IN ('Chờ xử lý', 'Đang xử lý')
                       AND MoTaHuHong = :reason LIMIT 1",
                    ['mssv' => $mssv, 'reason' => $reason]
                );
                if ($duplicate) {
                    throw new \RuntimeException('duplicate_request');
                }
            } else {
                $duplicate = Database::fetch(
                    "SELECT MaDon FROM DonTuMotCua
                     WHERE MaSinhVien = :mssv AND LoaiDon = :type AND TrangThaiDuyet = 'Chờ duyệt'
                     LIMIT 1",
                    ['mssv' => $mssv, 'type' => $type]
                );
                if ($duplicate) {
                    throw new \RuntimeException('duplicate_request');
                }
            }

            $createdRequestId = null;
            if ($type === 'Xin sửa chữa') {
                $room = Database::fetch(
                    "SELECT g.MaPhong, p.MaToaNha FROM HopDongNoiTru hd
                     JOIN Giuong g ON hd.MaGiuong = g.MaGiuong
                     JOIN Phong p ON p.MaPhong = g.MaPhong
                     WHERE hd.MaSinhVien = :mssv AND hd.TrangThaiHopDong = 'Đang hiệu lực' LIMIT 1",
                    ['mssv' => $mssv]
                );
                if (!$room) throw new \RuntimeException('room_missing');
                $equipmentId = (int) $request->input('MaThietBi', 0);
                if ($equipmentId <= 0) {
                    throw new \RuntimeException('equipment_missing');
                }
                $equipment = Database::fetch(
                    "SELECT MaThietBi FROM ThietBiPhong WHERE MaThietBi = :equipment AND MaPhong = :room LIMIT 1",
                    ['equipment' => $equipmentId, 'room' => $room['MaPhong']]
                );
                if (!$equipment) {
                    throw new \RuntimeException('equipment_missing');
                }
                $imagePath = null;
                if (isset($_FILES['HinhAnh']) && ($_FILES['HinhAnh']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $image = $_FILES['HinhAnh'];
                    if (($image['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($image['size'] ?? 0) > 5 * 1024 * 1024) throw new \RuntimeException('Ảnh không hợp lệ hoặc vượt quá 5 MB.');
                    $mime = mime_content_type($image['tmp_name']);
                    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    if (!isset($extensions[$mime])) throw new \RuntimeException('Chỉ nhận ảnh JPG, PNG hoặc WEBP.');
                    $uploadDir = dirname(__DIR__, 3) . '/storage/uploads/repairs';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                    $filename = 'repair_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                    if (!move_uploaded_file($image['tmp_name'], $uploadDir . '/' . $filename)) throw new \RuntimeException('Không thể lưu ảnh minh họa.');
                    $imagePath = 'storage/uploads/repairs/' . $filename;
                }
                $createdRequestId = $this->newRequestId('BH');
                Database::execute(
                    "INSERT INTO PhieuBaoHong
                     (MaPhieu,MaPhong,MaToaNha,MaThietBi,MaSinhVienBao,LoaiSuCo,MoTaHuHong,HinhAnh,TrangThaiSua,NgayBao)
                     VALUES (:id,:room,:building,:equipment,:mssv,'PHONG',:reason,:image,'Chờ xử lý',NOW())",
                    [
                        'id' => $createdRequestId,
                        'room' => $room['MaPhong'],
                        'building' => $room['MaToaNha'],
                        'equipment' => $equipmentId,
                        'mssv' => $mssv,
                        'reason' => $reason,
                        'image' => $imagePath
                    ]
                );
            } else {
                $bed = trim((string) $request->input('MaGiuongChuyenDen', ''));
                if ($type !== 'Xin chuyển phòng') $bed = '';
                if ($type === 'Xin chuyển phòng') {
                    $available = Database::fetch(
                        "SELECT g.MaGiuong FROM Giuong g
                         WHERE g.MaGiuong = :bed
                           AND g.TrangThaiGiuong IN ('Trống', 'Cho phép chuyển')",
                        ['bed' => $bed]
                    );
                    if (!$available) throw new \RuntimeException('bed_unavailable');
                }
                $createdRequestId = $this->newRequestId('DON');
                Database::execute(
                    "INSERT INTO DonTuMotCua
                     (MaDon,MaSinhVien,MaHopDong,LoaiDon,MaGiuongChuyenDen,TuNgay,DenNgay,LyDo,TepMinhChung,TrangThaiDuyet)
                     VALUES (:id,:mssv,:contract,:type,:bed,:from,:to,:reason,:proof,'Chờ duyệt')",
                    [
                        'id' => $createdRequestId,
                        'mssv' => $mssv,
                        'contract' => $contract['MaHopDong'],
                        'type' => $type,
                        'bed' => $bed !== '' ? $bed : null,
                        'from' => $request->input('TuNgay') ?: null,
                        'to' => $request->input('DenNgay') ?: null,
                        'reason' => $reason,
                        'proof' => $proofPath
                    ]
                );
            }
            $pdo->commit();

            // Gửi thông báo đến Cán bộ Quản lý liên quan phụ trách địa bàn để giải quyết
            try {
                if ($type === 'Xin sửa chữa') {
                    MailService::notifyManagersNewRepairTicket($createdRequestId);
                } else {
                    MailService::notifyManagersNewRequest($createdRequestId);
                }
            } catch (\Throwable $e) {
                error_log('Lỗi gửi email thông báo cán bộ quản lý (đơn từ/báo hỏng): ' . $e->getMessage());
            }

            Session::flash('success', 'Đã gửi yêu cầu thành công. Cán bộ quản lý phụ trách địa bàn đã nhận được thông báo để giải quyết.');
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = match (($e instanceof \RuntimeException) ? $e->getMessage() : '') {
                'student_contract_missing' => 'Bạn cần có hợp đồng nội trú đang hiệu lực để gửi yêu cầu.',
                'duplicate_request' => 'Bạn đã gửi yêu cầu tương tự đang chờ duyệt. Không cần gửi lại.',
                'bed_unavailable' => 'Giường chuyển đến đã được giữ hoặc không còn khả dụng.',
                'equipment_missing' => 'Vui lòng chọn thiết bị thuộc phòng đang lưu trú.',
                default => 'Không thể gửi yêu cầu. Vui lòng kiểm tra lại thông tin.',
            };
            Session::flash('error', $message);
        }
        $this->redirect($this->serviceRoute($type));
    }

    private function studentId(): string {
        $user = Auth::user();
        return (string) ($user['profile']['MaSinhVien'] ?? $user['TenDangNhap'] ?? '');
    }

    private function serviceRoute(string $type): string {
        return match ($type) {
            'Xin chuyển phòng' => 'student/requests/transfer',
            'Xin sửa chữa' => 'student/requests/repair',
            'Xin tạm vắng' => 'student/requests/leave',
            'Xin trả phòng' => 'student/requests/return',
            'Xin gia hạn' => 'student/requests/extension',
            'Hồ sơ miễn giảm lệ phí' => 'student/requests/extension',
            default => 'student/requests'
        };
    }

    private function newRequestId(string $prefix): string {
        return $prefix . date('YmdHis') . bin2hex(random_bytes(2));
    }
}
