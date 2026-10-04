<?php
namespace System\Broadcasting;

/**
 * WebSocket frames (RFC 6455): encode the server's frames, decode the browser's.
 */
class Frame
{
    public const TEXT = 0x1;
    public const CLOSE = 0x8;
    public const PING = 0x9;
    public const PONG = 0xA;

    /** A complete unmasked frame (server → client), or masked when $mask is true (client → server) */
    public static function encode(string $payload, int $opcode = self::TEXT, bool $mask = false): string
    {
        $length = strlen($payload);
        $head = chr(0x80 | $opcode);
        $maskBit = $mask ? 0x80 : 0;

        if ($length < 126) {
            $head .= chr($maskBit | $length);
        } elseif ($length < 65536) {
            $head .= chr($maskBit | 126) . pack('n', $length);
        } else {
            $head .= chr($maskBit | 127) . pack('J', $length);
        }

        if (!$mask) {
            return $head . $payload;
        }
        $key = random_bytes(4);
        return $head . $key . self::applyMask($payload, $key);
    }

    /**
     * Take one frame off the front of $buffer.
     * Returns [fin, opcode, payload] or null when the frame is not complete yet.
     * Throws when a frame is larger than $maxBytes.
     */
    public static function decode(string &$buffer, int $maxBytes = 65536): ?array
    {
        if (strlen($buffer) < 2) {
            return null;
        }
        $first = ord($buffer[0]);
        $second = ord($buffer[1]);
        $masked = (bool) ($second & 0x80);
        $length = $second & 0x7F;
        $offset = 2;

        if ($length === 126) {
            if (strlen($buffer) < 4) return null;
            $length = unpack('n', substr($buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            if (strlen($buffer) < 10) return null;
            $length = unpack('J', substr($buffer, 2, 8))[1];
            $offset = 10;
        }
        if ($length > $maxBytes) {
            throw new \LengthException("WebSocket frame of $length bytes is over the $maxBytes byte limit");
        }

        $key = '';
        if ($masked) {
            if (strlen($buffer) < $offset + 4) return null;
            $key = substr($buffer, $offset, 4);
            $offset += 4;
        }
        if (strlen($buffer) < $offset + $length) {
            return null;
        }

        $payload = substr($buffer, $offset, $length);
        $buffer = (string) substr($buffer, $offset + $length);

        return [(bool) ($first & 0x80), $first & 0x0F, $masked ? self::applyMask($payload, $key) : $payload];
    }

    protected static function applyMask(string $payload, string $key): string
    {
        $mask = str_repeat($key, intdiv(strlen($payload), 4) + 1);
        return $payload ^ substr($mask, 0, strlen($payload));
    }
}
