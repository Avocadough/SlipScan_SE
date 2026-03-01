<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthGuard;
use PDO;

class SlipController
{
    private PDO $db;

    // Allowed MIME / extensions
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/webp',
    ];
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const OCR_SERVICE    = 'http://localhost:5000/ocr';
    private const MAX_BATCH      = 20;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/slips/upload  (auth required)
    // ─────────────────────────────────────────────────────────────────────────
    public function upload(): void
    {
        $decoded = AuthGuard::verify();
        $userId  = (int) $decoded->sub;

        // ── Validate file ──
        if (empty($_FILES['file'])) {
            $this->respond(400, ['success' => false, 'message' => "No file uploaded (field: 'file')"]);
            return;
        }

        $file  = $_FILES['file'];
        $error = $this->validateFile($file);
        if ($error) {
            $this->respond(400, ['success' => false, 'message' => $error]);
            return;
        }

        // ── Save file ──
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('slip_', true) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->respond(500, ['success' => false, 'message' => 'Failed to save uploaded file']);
            return;
        }

        // ── Call OCR service ──
        [$ocrData, $warnings] = $this->callOcrService($destPath);

        if ($ocrData === null) {
            // OCR failed — still save record with empty data
            $warnings[] = 'OCR service unavailable or failed';
            $ocrData    = [];
        }

        // ── Save to DB ──
        $slipId = $this->saveSlip($userId, 'uploads/' . $filename, $ocrData);

        // ── Response ──
        $this->respond(200, [
            'success'  => true,
            'slip_id'  => $slipId,
            'data'     => [
                'sender_name'      => $ocrData['sender_name']      ?? null,
                'bank_name'        => $ocrData['bank_name']        ?? null,
                'amount'           => $ocrData['amount']           ?? null,
                'slip_date'        => $ocrData['slip_date']        ?? null,
                'slip_time'        => $ocrData['slip_time']        ?? null,
                'ref_no'           => $ocrData['ref_no']           ?? null,
                'receiver_name'    => $ocrData['receiver_name']    ?? null,
                'receiver_acct'    => $ocrData['receiver_account'] ?? null,
            ],
            'raw_ocr'  => $ocrData['raw_ocr'] ?? null,
            'warnings' => $warnings,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/slips/upload-batch  (auth required, max 20 files)
    // ─────────────────────────────────────────────────────────────────────────
    public function uploadBatch(): void
    {
        $decoded = AuthGuard::verify();
        $userId  = (int) $decoded->sub;

        if (empty($_FILES['files'])) {
            $this->respond(400, ['success' => false, 'message' => "No files uploaded (field: 'files[]')"]);
            return;
        }

        // Normalise multiple-file $_FILES array
        $files = $this->normaliseFiles($_FILES['files']);

        if (count($files) > self::MAX_BATCH) {
            $this->respond(400, [
                'success' => false,
                'message' => 'Maximum ' . self::MAX_BATCH . ' files per batch',
            ]);
            return;
        }

        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $results  = [];
        $success  = 0;
        $failed   = 0;

        foreach ($files as $index => $file) {
            // Validate
            $error = $this->validateFile($file);
            if ($error) {
                $results[] = ['index' => $index, 'success' => false, 'error' => $error];
                $failed++;
                continue;
            }

            // Save
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid('slip_', true) . '.' . $ext;
            $destPath = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $results[] = ['index' => $index, 'success' => false, 'error' => 'Failed to save file'];
                $failed++;
                continue;
            }

            // OCR
            [$ocrData, $warnings] = $this->callOcrService($destPath);
            if ($ocrData === null) {
                $warnings[] = 'OCR service unavailable';
                $ocrData    = [];
            }

            // DB
            $slipId    = $this->saveSlip($userId, 'uploads/' . $filename, $ocrData);
            $results[] = [
                'index'    => $index,
                'success'  => true,
                'slip_id'  => $slipId,
                'filename' => $file['name'],
                'data'     => [
                    'sender_name'   => $ocrData['sender_name']      ?? null,
                    'bank_name'     => $ocrData['bank_name']        ?? null,
                    'amount'        => $ocrData['amount']           ?? null,
                    'slip_date'     => $ocrData['slip_date']        ?? null,
                    'slip_time'     => $ocrData['slip_time']        ?? null,
                    'ref_no'        => $ocrData['ref_no']           ?? null,
                    'receiver_name' => $ocrData['receiver_name']    ?? null,
                    'receiver_acct' => $ocrData['receiver_account'] ?? null,
                ],
                'warnings' => $warnings,
            ];
            $success++;
        }

        $this->respond(200, [
            'success' => true,
            'total'   => count($files),
            'success_count' => $success,
            'failed_count'  => $failed,
            'items'   => $results,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/slips/:id  (auth required)
    // ─────────────────────────────────────────────────────────────────────────
    public function getById(int $id): void
    {
        $decoded = AuthGuard::verify();
        $userId  = (int) $decoded->sub;

        $stmt = $this->db->prepare(
            'SELECT * FROM slips WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $slip = $stmt->fetch();

        if (!$slip) {
            $this->respond(404, ['success' => false, 'message' => 'Slip not found']);
            return;
        }

        $slip['raw_ocr'] = $slip['raw_ocr'] ? json_decode($slip['raw_ocr'], true) : null;
        $this->respond(200, ['success' => true, 'data' => $slip]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/slips  (auth required)
    // ─────────────────────────────────────────────────────────────────────────
    public function listAll(): void
    {
        $decoded = AuthGuard::verify();
        $userId  = (int) $decoded->sub;

        $page    = max(1, (int) ($_GET['page']    ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        // total count
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM slips WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT id, image_path, sender_name, bank_name, amount, slip_date, slip_time,
                    ref_no, receiver_name, receiver_acct, is_fake, is_duplicate, created_at
             FROM slips WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $perPage, $offset]);
        $slips = $stmt->fetchAll();

        $this->respond(200, [
            'success'    => true,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'data'       => $slips,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function validateFile(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'File upload error: code ' . $file['error'];
        }
        if ($file['size'] > self::MAX_SIZE_BYTES) {
            return 'File too large (max 10MB)';
        }
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return "Unsupported file type: {$mime}. Allowed: jpg, png, webp";
        }
        return null;
    }

    private function callOcrService(string $filePath): array
    {
        $warnings = [];

        if (!function_exists('curl_init')) {
            $warnings[] = 'cURL not available';
            return [null, $warnings];
        }

        $ch = curl_init(self::OCR_SERVICE);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file' => new \CURLFile($filePath),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $warnings[] = "OCR service returned HTTP {$httpCode}";
            return [null, $warnings];
        }

        $body = json_decode($response, true);
        if (!($body['success'] ?? false)) {
            $warnings[] = $body['error'] ?? 'OCR failed';
            return [null, $warnings];
        }

        $warnings = array_merge($warnings, $body['warnings'] ?? []);
        return [$body['data'], $warnings];
    }

    private function saveSlip(int $userId, string $imagePath, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO slips
               (user_id, image_path, sender_name, bank_name, amount,
                slip_date, slip_time, ref_no, receiver_name, receiver_acct, raw_ocr)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $userId,
            $imagePath,
            $data['sender_name']      ?? null,
            $data['bank_name']        ?? null,
            $data['amount']           ?? null,
            $data['slip_date']        ?? null,
            $data['slip_time']        ?? null,
            $data['ref_no']           ?? null,
            $data['receiver_name']    ?? null,
            $data['receiver_account'] ?? null,
            isset($data['raw_ocr']) ? json_encode($data['raw_ocr'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Convert PHP's multi-file $_FILES array to array of single-file arrays */
    private function normaliseFiles(array $filesInput): array
    {
        $files = [];
        $count = count($filesInput['name']);
        for ($i = 0; $i < $count; $i++) {
            $files[] = [
                'name'     => $filesInput['name'][$i],
                'type'     => $filesInput['type'][$i],
                'tmp_name' => $filesInput['tmp_name'][$i],
                'error'    => $filesInput['error'][$i],
                'size'     => $filesInput['size'][$i],
            ];
        }
        return $files;
    }

    private function respond(int $code, array $data): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
