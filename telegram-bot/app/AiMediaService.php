<?php
declare(strict_types=1);

namespace Hendy;

use RuntimeException;
use Throwable;

/**
 * Small PostgreSQL-backed queue for image edits and music generation.
 * Provider secrets remain server-side in Render Environment.
 */
final class AiMediaService
{
    public static function create(string $userId, string $botKey, string $type, string $prompt, string $sourceFileId = ''): array
    {
        if (!Env::bool('AI_MEDIA_QUEUE_ENABLED', false)) {
            throw new RuntimeException('Dịch vụ AI chưa được Admin bật. Vui lòng quay lại sau.');
        }
        if (!preg_match('/\A[0-9]{5,20}\z/', $userId) || $botKey !== 'primary') {
            throw new RuntimeException('Tài khoản hoặc Bot không hợp lệ.');
        }
        if (!in_array($type, ['music', 'image'], true)) {
            throw new RuntimeException('Loại tác vụ AI không hợp lệ.');
        }
        $prompt = trim($prompt);
        $maxPrompt = $type === 'music' ? 500 : 1000;
        if (mb_strlen($prompt) < 5 || mb_strlen($prompt) > $maxPrompt) {
            throw new RuntimeException('Mô tả tác vụ AI không đúng độ dài cho phép.');
        }
        if ($type === 'music' && trim((string)Env::get('ELEVENLABS_API_KEY', '')) === '') {
            throw new RuntimeException('Admin chưa cấu hình ELEVENLABS_API_KEY trong Render Environment.');
        }
        if ($type === 'image' && (trim((string)Env::get('OPENAI_API_KEY', '')) === '' || $sourceFileId === '')) {
            throw new RuntimeException('Chỉnh sửa ảnh AI cần OPENAI_API_KEY và một ảnh đầu vào.');
        }

        $dailyLimit = max(1, min(10, Env::int('AI_MEDIA_MAX_PER_DAY', 3)));
        return Database::transaction(function () use ($userId, $botKey, $type, $prompt, $sourceFileId, $dailyLimit): array {
            $user = Database::one('SELECT user_id FROM users WHERE user_id=? FOR UPDATE', [$userId]);
            if ($user === null) {
                throw new RuntimeException('Không tìm thấy tài khoản. Hãy gửi /start trước.');
            }
            $active = (int)Database::value(
                "SELECT COUNT(*) FROM ai_media_jobs WHERE user_id=? AND status IN ('queued','processing')",
                [$userId]
            );
            if ($active >= 1) {
                throw new RuntimeException('Bạn đang có một tác vụ AI chưa hoàn tất. Vui lòng đợi kết quả trước.');
            }
            $today = (int)Database::value(
                "SELECT COUNT(*) FROM ai_media_jobs WHERE user_id=? AND created_at >= (date_trunc('day', NOW() AT TIME ZONE 'Asia/Ho_Chi_Minh') AT TIME ZONE 'Asia/Ho_Chi_Minh')",
                [$userId]
            );
            if ($today >= $dailyLimit) {
                throw new RuntimeException('Bạn đã đạt giới hạn ' . $dailyLimit . ' tác vụ AI/ngày.');
            }
            $id = self::uuid();
            $row = Database::one(
                "INSERT INTO ai_media_jobs(id,user_id,bot_key,job_type,prompt,source_file_id,status) " .
                "VALUES(?,?,?,?,?,?,'queued') RETURNING id,status,created_at",
                [$id,$userId,$botKey,$type,$prompt,$sourceFileId]
            );
            if ($row === null) throw new RuntimeException('Không thể tạo tác vụ AI.');
            return $row;
        });
    }

    /**
     * Claim and process exactly one job. The queue uses SKIP LOCKED so multiple
     * workers can safely coexist. A failed job is recorded and notified once.
     */
    public static function runOnce(): bool
    {
        $job = Database::transaction(function (): ?array {
            $next = Database::one(
                "SELECT id,user_id,bot_key,job_type,prompt,source_file_id FROM ai_media_jobs " .
                "WHERE status='queued' ORDER BY created_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED"
            );
            if ($next === null) return null;
            Database::run(
                "UPDATE ai_media_jobs SET status='processing',attempts=attempts+1,claimed_at=NOW(),updated_at=NOW() WHERE id=?",
                [$next['id']]
            );
            return $next;
        });
        if ($job === null) return false;

        $token = TelegramAuth::token('primary');
        $telegram = $token !== '' ? new TelegramApi($token) : null;
        $outputPath = null;
        $input = null;
        try {
            if ($telegram === null) throw new RuntimeException('Bot token chưa sẵn sàng.');
            if ((string)$job['job_type'] === 'music') {
                $outputPath = self::generateMusic((string)$job['prompt']);
                $telegram->sendAudioFile(
                    (string)$job['user_id'],
                    $outputPath,
                    '🎵 <b>NHẠC AI HENDY ĐÃ HOÀN TẤT</b>' . "\n" . 'Mã tác vụ: <code>' . self::escape(substr((string)$job['id'],0,8)) . '</code>'
                );
            } elseif ((string)$job['job_type'] === 'image') {
                $input = $telegram->downloadImageToTemp((string)$job['source_file_id']);
                $outputPath = self::editImage($input['path'], $input['mime'], (string)$job['prompt']);
                $telegram->sendPhotoFile(
                    (string)$job['user_id'],
                    $outputPath,
                    '🖼️ <b>ẢNH AI HENDY ĐÃ CHỈNH SỬA</b>' . "\n" . 'Mã tác vụ: <code>' . self::escape(substr((string)$job['id'],0,8)) . '</code>'
                );
            } else {
                throw new RuntimeException('Loại tác vụ AI không được hỗ trợ.');
            }
            Database::run(
                "UPDATE ai_media_jobs SET status='completed',completed_at=NOW(),updated_at=NOW(),error_message='' WHERE id=?",
                [$job['id']]
            );
        } catch (Throwable $error) {
            Database::run(
                "UPDATE ai_media_jobs SET status='failed',completed_at=NOW(),updated_at=NOW(),error_message=? WHERE id=?",
                [mb_substr($error->getMessage(),0,500),(string)$job['id']]
            );
            if ($telegram !== null) {
                try {
                    $telegram->sendMessage(
                        (string)$job['user_id'],
                        '❌ <b>TÁC VỤ AI CHƯA HOÀN TẤT</b>' . "\n" .
                        'Mã tác vụ: <code>' . self::escape(substr((string)$job['id'],0,8)) . '</code>' . "\n" .
                        'Hệ thống đã ghi nhận lỗi. Vui lòng liên hệ CSKH nếu cần kiểm tra.'
                    );
                } catch (Throwable $notifyError) {
                    error_log('AI media job notice failed: ' . $notifyError::class);
                }
            }
            error_log('AI media job failed (' . substr((string)$job['id'],0,8) . '): ' . $error->getMessage());
        } finally {
            if (is_array($input) && isset($input['path']) && is_file($input['path'])) @unlink($input['path']);
            if (is_string($outputPath) && is_file($outputPath)) @unlink($outputPath);
        }
        return true;
    }

    private static function generateMusic(string $prompt): string
    {
        $apiKey = trim((string)Env::get('ELEVENLABS_API_KEY', ''));
        if ($apiKey === '') throw new RuntimeException('Thiếu ELEVENLABS_API_KEY.');
        $model = trim((string)Env::get('ELEVENLABS_MUSIC_MODEL', 'music_v2_5'));
        if (!preg_match('/\A[A-Za-z0-9_.-]{2,80}\z/', $model)) $model = 'music_v2_5';
        $body = json_encode([
            'prompt' => $prompt,
            'model_id' => $model,
            'music_length_ms' => 30_000,
            'force_instrumental' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $handle = curl_init('https://api.elevenlabs.io/v1/music?output_format=mp3_48000_192');
        if ($handle === false) throw new RuntimeException('Không khởi tạo được kết nối tạo nhạc.');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json','Accept: audio/mpeg','xi-api-key: '.$apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $bytes = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $mime = strtolower(trim(explode(';',(string)curl_getinfo($handle,CURLINFO_CONTENT_TYPE))[0]));
        curl_close($handle);
        if ($bytes === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Nhà cung cấp âm nhạc AI trả về HTTP '.$status.'.');
        }
        if (!is_string($bytes) || strlen($bytes) < 1024 || strlen($bytes) > 25_000_000
            || !in_array($mime,['audio/mpeg','audio/mp3','application/octet-stream'],true)) {
            throw new RuntimeException('Nhà cung cấp trả về tệp âm thanh không hợp lệ hoặc vượt 25 MB.');
        }
        $path = tempnam(sys_get_temp_dir(),'hendy-ai-music-');
        if ($path === false || file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)) {
            if (is_string($path) && is_file($path)) @unlink($path);
            throw new RuntimeException('Không lưu được tệp nhạc tạm.');
        }
        return $path;
    }

    private static function editImage(string $inputPath, string $mime, string $prompt): string
    {
        $apiKey = trim((string)Env::get('OPENAI_API_KEY', ''));
        if ($apiKey === '') throw new RuntimeException('Thiếu OPENAI_API_KEY.');
        $model = trim((string)Env::get('OPENAI_IMAGE_MODEL', 'gpt-image-2.5-sunburst'));
        if (!preg_match('/\A[A-Za-z0-9_.-]{3,100}\z/', $model)) $model = 'gpt-image-2.5-sunburst';
        $fields = [
            'model' => $model,
            'prompt' => $prompt,
            'image' => new \CURLFile($inputPath,$mime,basename($inputPath)),
            'size' => '1024x1024',
            'quality' => 'medium',
            'output_format' => 'png',
            'n' => '1',
        ];
        $handle = curl_init('https://api.openai.com/v1/images/edits');
        if ($handle === false) throw new RuntimeException('Không khởi tạo được kết nối chỉnh ảnh AI.');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$apiKey,'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Nhà cung cấp chỉnh ảnh AI trả về HTTP '.$status.'.');
        }
        if (!is_string($response) || strlen($response)>40_000_000) throw new RuntimeException('Phản hồi ảnh AI vượt giới hạn.');
        $decoded = json_decode($response,true);
        $encoded = is_array($decoded) ? (string)($decoded['data'][0]['b64_json'] ?? '') : '';
        $bytes = $encoded !== '' ? base64_decode($encoded,true) : false;
        if (!is_string($bytes) || strlen($bytes)<16 || strlen($bytes)>30_000_000) {
            throw new RuntimeException('Nhà cung cấp không trả về ảnh PNG hợp lệ.');
        }
        $info = @getimagesizefromstring($bytes);
        if ($info===false || ($info['mime']??'')!=='image/png') throw new RuntimeException('Kết quả chỉnh ảnh không phải PNG hợp lệ.');
        $path = tempnam(sys_get_temp_dir(),'hendy-ai-image-');
        if ($path===false || file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)) {
            if(is_string($path)&&is_file($path))@unlink($path);
            throw new RuntimeException('Không lưu được ảnh AI tạm.');
        }
        return $path;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
