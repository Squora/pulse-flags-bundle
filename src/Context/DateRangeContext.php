<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

use DateTimeImmutable;

/**
 * Context for date-range-based strategy (DateRangeStrategy).
 */
final class DateRangeContext implements ContextInterface
{
    public function __construct(
        private readonly DateTimeImmutable $currentDate
    ) {
    }

    public function getCurrentDate(): DateTimeImmutable
    {
        return $this->currentDate;
    }

    public function toArray(): array
    {
        return [
            'current_date' => $this->currentDate,
        ];
    }
}
