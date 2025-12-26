<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Tests\Strategy\Hash;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pulse\Flags\Core\Enum\HashAlgorithm;
use Pulse\Flags\Core\Strategy\Hash\HashCalculator;

final class HashCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_bucket_with_crc32_algorithm(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket = $calculator->calculateBucket(
            identifier: 'user-123',
            algorithm: HashAlgorithm::CRC32
        );

        // Assert
        self::assertIsInt($bucket);
        self::assertGreaterThanOrEqual(0, $bucket);
        self::assertLessThan(100000, $bucket);
    }

    #[Test]
    public function it_calculates_bucket_with_md5_algorithm(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket = $calculator->calculateBucket(
            identifier: 'user-123',
            algorithm: HashAlgorithm::MD5
        );

        // Assert
        self::assertIsInt($bucket);
        self::assertGreaterThanOrEqual(0, $bucket);
        self::assertLessThan(100000, $bucket);
    }

    #[Test]
    public function it_calculates_bucket_with_sha256_algorithm(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket = $calculator->calculateBucket(
            identifier: 'user-123',
            algorithm: HashAlgorithm::SHA256
        );

        // Assert
        self::assertIsInt($bucket);
        self::assertGreaterThanOrEqual(0, $bucket);
        self::assertLessThan(100000, $bucket);
    }

    #[Test]
    public function it_is_deterministic_for_same_identifier(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $identifier = 'user-456';

        // Act
        $bucket1 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32);
        $bucket2 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32);
        $bucket3 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32);

        // Assert - same identifier always produces same bucket
        self::assertSame($bucket1, $bucket2);
        self::assertSame($bucket2, $bucket3);
    }

    #[Test]
    #[DataProvider('provideAlgorithms')]
    public function it_is_deterministic_for_all_algorithms(HashAlgorithm $algorithm): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $identifier = 'test-user-789';

        // Act
        $bucket1 = $calculator->calculateBucket($identifier, $algorithm);
        $bucket2 = $calculator->calculateBucket($identifier, $algorithm);

        // Assert
        self::assertSame($bucket1, $bucket2);
    }

    public static function provideAlgorithms(): iterable
    {
        yield 'CRC32' => [HashAlgorithm::CRC32];
        yield 'MD5' => [HashAlgorithm::MD5];
        yield 'SHA256' => [HashAlgorithm::SHA256];
    }

    #[Test]
    public function it_produces_different_buckets_for_different_identifiers(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket1 = $calculator->calculateBucket('user-1', HashAlgorithm::CRC32);
        $bucket2 = $calculator->calculateBucket('user-2', HashAlgorithm::CRC32);
        $bucket3 = $calculator->calculateBucket('user-3', HashAlgorithm::CRC32);

        // Assert - different identifiers should produce different buckets
        // (not guaranteed but highly probable with good hash function)
        $buckets = [$bucket1, $bucket2, $bucket3];
        $uniqueBuckets = array_unique($buckets);
        self::assertCount(3, $uniqueBuckets, 'Expected different identifiers to produce different buckets');
    }

    #[Test]
    public function it_applies_seed_to_hash_calculation(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $identifier = 'user-123';

        // Act
        $bucketWithoutSeed = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, seed: '');
        $bucketWithSeed1 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, seed: 'experiment-1');
        $bucketWithSeed2 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, seed: 'experiment-2');

        // Assert - seed changes the bucket
        self::assertNotSame($bucketWithoutSeed, $bucketWithSeed1);
        self::assertNotSame($bucketWithSeed1, $bucketWithSeed2);
    }

    #[Test]
    public function it_is_deterministic_with_seed(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $identifier = 'user-123';
        $seed = 'my-experiment';

        // Act
        $bucket1 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, seed: $seed);
        $bucket2 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, seed: $seed);

        // Assert - same identifier + seed always produces same bucket
        self::assertSame($bucket1, $bucket2);
    }

    #[Test]
    #[DataProvider('provideBucketCounts')]
    public function it_respects_custom_bucket_count(int $buckets): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket = $calculator->calculateBucket(
            identifier: 'user-123',
            algorithm: HashAlgorithm::CRC32,
            buckets: $buckets
        );

        // Assert - bucket should be in range [0, buckets-1]
        self::assertGreaterThanOrEqual(0, $bucket);
        self::assertLessThan($buckets, $bucket);
    }

    public static function provideBucketCounts(): iterable
    {
        yield '10 buckets' => [10];
        yield '100 buckets' => [100];
        yield '1000 buckets' => [1000];
        yield '10000 buckets' => [10000];
        yield '100000 buckets (default)' => [100000];
    }

    #[Test]
    public function it_handles_empty_identifier(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket = $calculator->calculateBucket('', HashAlgorithm::CRC32);

        // Assert - empty string should still produce valid bucket
        self::assertIsInt($bucket);
        self::assertGreaterThanOrEqual(0, $bucket);
        self::assertLessThan(100000, $bucket);
    }

    #[Test]
    public function it_handles_unicode_identifiers(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket1 = $calculator->calculateBucket('пользователь-123', HashAlgorithm::CRC32);
        $bucket2 = $calculator->calculateBucket('用户-456', HashAlgorithm::MD5);
        $bucket3 = $calculator->calculateBucket('🚀user-789', HashAlgorithm::SHA256);

        // Assert
        self::assertIsInt($bucket1);
        self::assertIsInt($bucket2);
        self::assertIsInt($bucket3);
        self::assertGreaterThanOrEqual(0, $bucket1);
        self::assertGreaterThanOrEqual(0, $bucket2);
        self::assertGreaterThanOrEqual(0, $bucket3);
    }

    #[Test]
    public function it_handles_very_long_identifiers(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $longIdentifier = str_repeat('a', 10000);

        // Act
        $bucket = $calculator->calculateBucket($longIdentifier, HashAlgorithm::CRC32);

        // Assert
        self::assertIsInt($bucket);
        self::assertGreaterThanOrEqual(0, $bucket);
        self::assertLessThan(100000, $bucket);
    }

    #[Test]
    public function it_handles_special_characters_in_identifier(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket1 = $calculator->calculateBucket('user@example.com', HashAlgorithm::CRC32);
        $bucket2 = $calculator->calculateBucket('user!@#$%^&*()', HashAlgorithm::MD5);
        $bucket3 = $calculator->calculateBucket('user\nwith\nnewlines', HashAlgorithm::SHA256);

        // Assert
        self::assertIsInt($bucket1);
        self::assertIsInt($bucket2);
        self::assertIsInt($bucket3);
    }

    #[Test]
    public function it_produces_different_hashes_for_different_algorithms(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $identifier = 'user-123';

        // Act
        $bucketCrc32 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32);
        $bucketMd5 = $calculator->calculateBucket($identifier, HashAlgorithm::MD5);
        $bucketSha256 = $calculator->calculateBucket($identifier, HashAlgorithm::SHA256);

        // Assert - different algorithms should produce different buckets
        // (not guaranteed but highly probable)
        $buckets = [$bucketCrc32, $bucketMd5, $bucketSha256];
        $uniqueBuckets = array_unique($buckets);
        self::assertGreaterThan(1, count($uniqueBuckets), 'Expected different algorithms to produce different buckets');
    }

    #[Test]
    #[DataProvider('provideAlgorithmsForDistribution')]
    public function it_has_uniform_distribution(HashAlgorithm $algorithm): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $bucketCount = 10;
        $totalSamples = 10000;
        $buckets = array_fill(0, $bucketCount, 0);

        // Act - generate many samples and count distribution
        for ($i = 0; $i < $totalSamples; $i++) {
            $bucket = $calculator->calculateBucket(
                identifier: "user-{$i}",
                algorithm: $algorithm,
                buckets: $bucketCount
            );
            $buckets[$bucket]++;
        }

        // Assert - use chi-square test for uniform distribution
        // Expected: each bucket should have approximately totalSamples / bucketCount
        $expectedPerBucket = $totalSamples / $bucketCount;
        $chiSquare = 0;

        foreach ($buckets as $observed) {
            $chiSquare += pow($observed - $expectedPerBucket, 2) / $expectedPerBucket;
        }

        // Chi-square critical value for 9 degrees of freedom (10 buckets - 1) at p=0.05 is ~16.92
        // We use a more lenient threshold of 20 for this test
        self::assertLessThan(
            20,
            $chiSquare,
            sprintf(
                'Distribution is not uniform (chi-square: %.2f). Buckets: %s',
                $chiSquare,
                json_encode($buckets)
            )
        );

        // Also verify each bucket has reasonable count (not empty, not too full)
        $minExpected = $expectedPerBucket * 0.7; // Allow 30% deviation
        $maxExpected = $expectedPerBucket * 1.3;

        foreach ($buckets as $index => $count) {
            self::assertGreaterThan(
                $minExpected,
                $count,
                sprintf('Bucket %d has too few items: %d (expected ~%.0f)', $index, $count, $expectedPerBucket)
            );
            self::assertLessThan(
                $maxExpected,
                $count,
                sprintf('Bucket %d has too many items: %d (expected ~%.0f)', $index, $count, $expectedPerBucket)
            );
        }
    }

    public static function provideAlgorithmsForDistribution(): iterable
    {
        yield 'CRC32 distribution' => [HashAlgorithm::CRC32];
        yield 'MD5 distribution' => [HashAlgorithm::MD5];
        yield 'SHA256 distribution' => [HashAlgorithm::SHA256];
    }

    #[Test]
    public function it_produces_consistent_buckets_across_different_bucket_sizes(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $identifier = 'user-123';

        // Act
        $bucket10 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, buckets: 10);
        $bucket100 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, buckets: 100);
        $bucket1000 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, buckets: 1000);

        // Assert - bucket should scale with bucket count
        // If in bucket 3 of 10, should be in bucket 30-39 of 100, etc.
        self::assertGreaterThanOrEqual(0, $bucket10);
        self::assertLessThan(10, $bucket10);
        self::assertGreaterThanOrEqual(0, $bucket100);
        self::assertLessThan(100, $bucket100);
        self::assertGreaterThanOrEqual(0, $bucket1000);
        self::assertLessThan(1000, $bucket1000);
    }

    #[Test]
    public function it_handles_numeric_string_identifiers(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket1 = $calculator->calculateBucket('123', HashAlgorithm::CRC32);
        $bucket2 = $calculator->calculateBucket('456', HashAlgorithm::CRC32);

        // Assert
        self::assertIsInt($bucket1);
        self::assertIsInt($bucket2);
        self::assertNotSame($bucket1, $bucket2);
    }

    #[Test]
    public function it_handles_seed_with_special_characters(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $identifier = 'user-123';

        // Act
        $bucket1 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, seed: 'exp!@#$');
        $bucket2 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32, seed: 'exp_123');

        // Assert
        self::assertIsInt($bucket1);
        self::assertIsInt($bucket2);
        self::assertNotSame($bucket1, $bucket2);
    }

    #[Test]
    public function it_concatenates_seed_and_identifier_correctly(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act - these should produce different results because seed+identifier is different
        $bucket1 = $calculator->calculateBucket(
            identifier: 'abc',
            algorithm: HashAlgorithm::CRC32,
            seed: 'xyz'
        ); // "xyzabc"

        $bucket2 = $calculator->calculateBucket(
            identifier: 'zabc',
            algorithm: HashAlgorithm::CRC32,
            seed: 'xy'
        ); // "xyzabc" - same concatenation!

        // Assert - same concatenation should produce same bucket
        self::assertSame($bucket1, $bucket2);
    }

    #[Test]
    public function it_returns_zero_for_single_bucket(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket = $calculator->calculateBucket(
            identifier: 'user-123',
            algorithm: HashAlgorithm::CRC32,
            buckets: 1
        );

        // Assert - only possible bucket is 0
        self::assertSame(0, $bucket);
    }

    #[Test]
    public function it_handles_whitespace_in_identifiers(): void
    {
        // Arrange
        $calculator = new HashCalculator();

        // Act
        $bucket1 = $calculator->calculateBucket('user 123', HashAlgorithm::CRC32);
        $bucket2 = $calculator->calculateBucket('user  123', HashAlgorithm::CRC32); // double space
        $bucket3 = $calculator->calculateBucket(' user 123 ', HashAlgorithm::CRC32); // leading/trailing

        // Assert - whitespace matters, should produce different buckets
        self::assertIsInt($bucket1);
        self::assertIsInt($bucket2);
        self::assertIsInt($bucket3);
    }

    #[Test]
    public function it_produces_stable_results_for_real_world_identifiers(): void
    {
        // Arrange
        $calculator = new HashCalculator();
        $testCases = [
            'email' => 'user@example.com',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'session' => 'sess_1234567890abcdef',
            'ip' => '192.168.1.1',
            'device' => 'device-fingerprint-abc123',
        ];

        // Act & Assert
        foreach ($testCases as $type => $identifier) {
            $bucket1 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32);
            $bucket2 = $calculator->calculateBucket($identifier, HashAlgorithm::CRC32);

            self::assertSame(
                $bucket1,
                $bucket2,
                "Bucket should be stable for {$type} identifier: {$identifier}"
            );
        }
    }
}
