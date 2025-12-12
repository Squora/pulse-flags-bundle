<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

/**
 * Empty context for strategies that don't require any context data.
 *
 * Used with SimpleStrategy which always returns true regardless of context.
 */
final class EmptyContext implements ContextInterface
{
    public function toArray(): array
    {
        return [];
    }
}
