<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

use Pulse\Flags\Core\Segment\SegmentRepository;

/**
 * Validator for segment strategy configuration.
 */
class SegmentStrategyValidator implements StrategyValidatorInterface
{
    private SegmentRepository $segmentRepository;

    public function __construct(SegmentRepository $segmentRepository)
    {
        $this->segmentRepository = $segmentRepository;
    }

    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        // Must have segments
        if (!isset($config['segments']) || empty($config['segments'])) {
            $result->addError('Segment strategy requires "segments" array');
            return $result;
        }

        if (!is_array($config['segments'])) {
            $result->addError('segments must be an array');
            return $result;
        }

        // Validate each segment
        foreach ($config['segments'] as $index => $segmentName) {
            if (!is_string($segmentName)) {
                $result->addError(sprintf(
                    'segments[%d]: Segment name must be string, got %s',
                    $index,
                    gettype($segmentName)
                ));
                continue;
            }

            // Check if segment exists
            if (!$this->segmentRepository->has($segmentName)) {
                $result->addError(sprintf(
                    'Segment "%s" not found. Available segments: %s',
                    $segmentName,
                    implode(', ', $this->segmentRepository->getNames()) ?: 'none'
                ));
            }
        }

        return $result;
    }

    public function getStrategyName(): string
    {
        return 'segment';
    }
}
