<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

/**
 * Context for user-based strategies (UserIdStrategy, PercentageStrategy).
 */
final class UserContext implements ContextInterface
{
    public function __construct(
        private readonly string $userId,
        private readonly ?string $sessionId = null,
        private readonly ?string $companyId = null
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function toArray(): array
    {
        $result = ['user_id' => $this->userId];

        if ($this->sessionId !== null) {
            $result['session_id'] = $this->sessionId;
        }

        if ($this->companyId !== null) {
            $result['company_id'] = $this->companyId;
        }

        return $result;
    }
}
