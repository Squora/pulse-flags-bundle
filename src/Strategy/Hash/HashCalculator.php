<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Hash;

use Pulse\Flags\Core\Enum\HashAlgorithm;

/**
 * Hash calculator for percentage strategy bucketing.
 *
 * Provides multiple hashing algorithms for consistent user bucketing.
 * Supports hash seeds for re-randomizing experiments.
 */
class HashCalculator
{
    /**
     * Calculate hash bucket for a given identifier and algorithm.
     *
     * @param string $identifier The user/session identifier
     * @param HashAlgorithm $algorithm The hashing algorithm to use
     * @param string $seed Optional seed for hash diversification
     * @param int $buckets Total number of buckets (default 100,000)
     * @return int Bucket number (0 to buckets-1)
     */
    public function calculateBucket(
        string $identifier,
        HashAlgorithm $algorithm,
        string $seed = '',
        int $buckets = 100000
    ): int {
        $hashInput = $seed . $identifier;

        $hash = match ($algorithm) {
            HashAlgorithm::CRC32 => $this->crc32Hash($hashInput),
            HashAlgorithm::MD5 => $this->md5Hash($hashInput),
            HashAlgorithm::SHA256 => $this->sha256Hash($hashInput),
            HashAlgorithm::MURMUR3 => $this->murmur3Hash($hashInput),
        };

        return $hash % $buckets;
    }

    /**
     * CRC32 hash implementation.
     *
     * @param string $input The input string to hash
     * @return int The hash value
     */
    private function crc32Hash(string $input): int
    {
        return abs(crc32($input));
    }

    /**
     * MD5 hash implementation.
     * Converts first 8 characters of hex digest to integer.
     *
     * @param string $input The input string to hash
     * @return int The hash value
     */
    private function md5Hash(string $input): int
    {
        $hexHash = md5($input);
        // Use first 8 hex characters (32 bits)
        return abs((int) hexdec(substr($hexHash, 0, 8)));
    }

    /**
     * SHA256 hash implementation.
     * Converts first 8 characters of hex digest to integer.
     *
     * @param string $input The input string to hash
     * @return int The hash value
     */
    private function sha256Hash(string $input): int
    {
        $hexHash = hash('sha256', $input);
        // Use first 8 hex characters (32 bits)
        return abs((int) hexdec(substr($hexHash, 0, 8)));
    }

    /**
     * MurmurHash3 approximation using MD5.
     * PHP doesn't have native MurmurHash3, so we use MD5 as a substitute.
     * Provides similar distribution characteristics.
     *
     * @param string $input The input string to hash
     * @return int The hash value
     */
    private function murmur3Hash(string $input): int
    {
        // Use MD5 as MurmurHash3 approximation
        // In production, consider using pecl-hash extension for true MurmurHash3
        return $this->md5Hash($input);
    }
}
