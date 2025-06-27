<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Support;

/**
 * UUID generation utilities
 * 
 * Provides utility functions for generating UUIDs.
 * 
 * @package LengthOfRope\TreeHouse\Support
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Uuid
{
    /**
     * Generate a version 4 (random) UUID
     * 
     * @return string
     */
    public static function uuid4(): string
    {
        $data = random_bytes(16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate a version 1 (time-based) UUID
     * 
     * @return string
     */
    public static function uuid1(): string
    {
        // Get current time in 100-nanosecond intervals since UUID epoch (1582-10-15)
        $time = microtime(true);
        $uuidTime = ($time + 12219292800) * 10000000; // UUID epoch offset

        // Split time into low, mid, and high parts
        $timeLow = $uuidTime & 0xffffffff;
        $timeMid = ($uuidTime >> 32) & 0xffff;
        $timeHi = (($uuidTime >> 48) & 0x0fff) | 0x1000; // Version 1

        // Generate random clock sequence and node
        $clockSeq = random_int(0, 0x3fff) | 0x8000; // Variant bits
        $node = random_bytes(6);
        $node[0] = chr(ord($node[0]) | 0x01); // Set multicast bit

        return sprintf(
            '%08x-%04x-%04x-%04x-%s',
            $timeLow,
            $timeMid,
            $timeHi,
            $clockSeq,
            bin2hex($node)
        );
    }

    /**
     * Generate a version 3 (name-based MD5) UUID
     * 
     * @param string $namespace
     * @param string $name
     * @return string
     */
    public static function uuid3(string $namespace, string $name): string
    {
        if (!static::isValid($namespace)) {
            throw new \InvalidArgumentException('Invalid namespace UUID');
        }

        // Convert namespace UUID to binary
        $nhex = str_replace(['-', '{', '}'], '', $namespace);
        $nstr = '';
        for ($i = 0; $i < strlen($nhex); $i += 2) {
            $nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
        }

        // Calculate hash
        $hash = md5($nstr . $name);

        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    /**
     * Generate a version 5 (name-based SHA1) UUID
     * 
     * @param string $namespace
     * @param string $name
     * @return string
     */
    public static function uuid5(string $namespace, string $name): string
    {
        if (!static::isValid($namespace)) {
            throw new \InvalidArgumentException('Invalid namespace UUID');
        }

        // Convert namespace UUID to binary
        $nhex = str_replace(['-', '{', '}'], '', $namespace);
        $nstr = '';
        for ($i = 0; $i < strlen($nhex); $i += 2) {
            $nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
        }

        // Calculate hash
        $hash = sha1($nstr . $name);

        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    /**
     * Validate a UUID string
     * 
     * @param string $uuid
     * @return bool
     */
    public static function isValid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Generate a nil UUID (all zeros)
     * 
     * @return string
     */
    public static function nil(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Generate a max UUID (all ones)
     * 
     * @return string
     */
    public static function max(): string
    {
        return 'ffffffff-ffff-ffff-ffff-ffffffffffff';
    }

    /**
     * Convert a UUID to its binary representation
     * 
     * @param string $uuid
     * @return string
     */
    public static function toBinary(string $uuid): string
    {
        if (!static::isValid($uuid)) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }

        $hex = str_replace('-', '', $uuid);
        $binary = '';

        for ($i = 0; $i < strlen($hex); $i += 2) {
            $binary .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }

        return $binary;
    }

    /**
     * Convert binary data to UUID string
     * 
     * @param string $binary
     * @return string
     */
    public static function fromBinary(string $binary): string
    {
        if (strlen($binary) !== 16) {
            throw new \InvalidArgumentException('Binary data must be exactly 16 bytes');
        }

        $hex = bin2hex($binary);

        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Get the version of a UUID
     * 
     * @param string $uuid
     * @return int
     */
    public static function getVersion(string $uuid): int
    {
        if (!static::isValid($uuid)) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }

        return (int) $uuid[14];
    }

    /**
     * Get the variant of a UUID
     * 
     * @param string $uuid
     * @return string
     */
    public static function getVariant(string $uuid): string
    {
        if (!static::isValid($uuid)) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }

        $char = $uuid[19];
        $dec = hexdec($char);

        if (($dec & 0x8) === 0) {
            return 'NCS';
        } elseif (($dec & 0xC) === 0x8) {
            return 'RFC4122';
        } elseif (($dec & 0xE) === 0xC) {
            return 'Microsoft';
        } else {
            return 'Reserved';
        }
    }

    /**
     * Compare two UUIDs
     * 
     * @param string $uuid1
     * @param string $uuid2
     * @return int
     */
    public static function compare(string $uuid1, string $uuid2): int
    {
        if (!static::isValid($uuid1) || !static::isValid($uuid2)) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }

        return strcmp(strtolower($uuid1), strtolower($uuid2));
    }

    /**
     * Check if two UUIDs are equal
     * 
     * @param string $uuid1
     * @param string $uuid2
     * @return bool
     */
    public static function equals(string $uuid1, string $uuid2): bool
    {
        return static::compare($uuid1, $uuid2) === 0;
    }

    /**
     * Generate a short UUID (base62 encoded)
     *
     * @return string
     */
    public static function short(): string
    {
        $uuid = static::uuid4();
        $binary = static::toBinary($uuid);
        
        // Convert to base62
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $base = strlen($chars);
        
        // Use bcmath for arbitrary precision arithmetic if available
        if (extension_loaded('bcmath')) {
            // Convert binary to decimal string
            $number = '0';
            for ($i = 0; $i < 16; $i++) {
                $number = bcadd(bcmul($number, '256'), (string) ord($binary[$i]));
            }
            
            // Convert to base62
            $result = '';
            while (bccomp($number, '0') > 0) {
                $remainder = bcmod($number, (string) $base);
                $result = $chars[(int) $remainder] . $result;
                $number = bcdiv($number, (string) $base, 0);
            }
            
            return $result ?: '0';
        }
        
        // Fallback: use only the first 8 bytes to avoid float precision issues
        $number = 0;
        for ($i = 0; $i < 8; $i++) {
            $number = $number * 256 + ord($binary[$i]);
        }
        
        // Convert to base62
        $result = '';
        while ($number > 0) {
            $remainder = $number % $base;
            $result = $chars[$remainder] . $result;
            $number = (int) ($number / $base);
        }
        
        return $result ?: '0';
    }

    /**
     * Predefined namespace UUIDs
     */
    public const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';
}