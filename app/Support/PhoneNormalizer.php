<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Trả về số đã chuẩn hoá ('+33612345678'), hoặc null nếu không hợp lệ.
     */
    public static function normalize(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $s = trim($raw);

        // Số ẩn / private: iOS đôi khi đẩy lên các chuỗi này.
        $lower = strtolower($s);
        if (in_array($lower, ['anonymous', 'private', 'unknown', 'masqué', 'masque'], true)) {
            return null;
        }

        // Giữ lại + đầu (nếu có), bỏ tất cả ký tự không phải số.
        $hasPlus = str_starts_with($s, '+');
        $digits  = preg_replace('/\D+/', '', $s);

        if ($digits === '') {
            return null;
        }

        // Số rất ngắn -> không phải số điện thoại thật (số ngắn nội bộ, extension).
        if (strlen($digits) < 8) {
            return null;
        }

        // Trường hợp Pháp: số bắt đầu 0 (national format) + đủ 10 chữ số -> +33XXXXXXXXX
        // (bỏ số 0 đầu, prefix +33).
        if (!$hasPlus && strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '+33' . substr($digits, 1);
        }

        // Trường hợp đã có dấu + ở đầu: trả lại '+' + digits (đã clean space/dấu).
        if ($hasPlus) {
            return '+' . $digits;
        }

        // Trường hợp prefix "00" thay cho "+" (chuẩn châu Âu cũ): 0033... -> +33...
        if (str_starts_with($digits, '00') && strlen($digits) >= 11) {
            return '+' . substr($digits, 2);
        }

        // Còn lại: số không xác định được country -> trả về dạng số thô (digits only).
        // Vẫn unique được trong danh bạ, chỉ không gộp được với format khác.
        return $digits;
    }

    /**
     * True nếu hai số raw biểu diễn cùng một số (sau khi chuẩn hoá).
     * Tiện cho việc so sánh nhanh trong code.
     */
    public static function equals(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);
        return $na !== null && $nb !== null && $na === $nb;
    }
}