<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Enum;

/**
 * Supported hash algorithms for percentage strategy bucketing.
 *
 * Different algorithms provide different characteristics:
 * - CRC32: Fast, good distribution, default choice
 * - MD5: Better distribution, slightly slower
 * - SHA256: Cryptographic hash, slowest but most secure
 * - MURMUR3: Best distribution, fastest, recommended for high-traffic
 *
 * Use hash seeds to re-randomize experiments without changing user IDs.
 */
enum HashAlgorithm: string
{
    /**
     * CRC32 hashing (default).
     * Fast and provides good distribution for most use cases.
     */
    case CRC32 = 'crc32';

    /**
     * MD5 hashing.
     * Better distribution than CRC32, slightly slower.
     * Good for experiments requiring more uniform distribution.
     */
    case MD5 = 'md5';

    /**
     * SHA256 hashing.
     * Cryptographic hash with excellent distribution.
     * Use for security-sensitive feature flags.
     */
    case SHA256 = 'sha256';

    /**
     * MurmurHash3 simulation using MD5.
     * Excellent distribution and performance.
     * Recommended for high-traffic applications.
     * Note: PHP doesn't have native MurmurHash3, using MD5 as approximation.
     */
    case MURMUR3 = 'murmur3';
}
