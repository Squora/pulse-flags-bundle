<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

/**
 * Base interface for all strategy contexts.
 *
 * Each strategy has its own specific context VO that implements this interface.
 */
interface ContextInterface
{
    /**
     * Convert context to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
