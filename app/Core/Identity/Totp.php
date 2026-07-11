<?php

declare(strict_types=1);

namespace App\Core\Identity;

/**
 * TOTP (RFC 6238) sem dependências externas — verificação de MFA.
 * Janela de ±1 período (30s) tolera pequenos desvios de relógio.
 */
class Totp
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    public static function verify(string $base32Secret, string $code, int $window = 1): bool
    {
        $counter = (int) floor(time() / self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::code($base32Secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function code(string $base32Secret, int $counter): string
    {
        $key = self::base32Decode($base32Secret);
        $binary = pack('N*', 0).pack('N*', $counter); // 8 bytes big-endian
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));
        $bits = '';

        foreach (str_split($input) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr((int) bindec($byte));
            }
        }

        return $output;
    }
}
