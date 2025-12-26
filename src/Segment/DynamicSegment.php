<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Segment;

/**
 * Dynamic segment based on runtime conditions.
 *
 * Use for segments where membership is determined by user attributes:
 * - Email domain matching (e.g., @company.com for internal users)
 * - Country-based segments
 * - Subscription tier segments
 * - Custom attribute matching
 *
 * Example configurations:
 * ```yaml
 * internal_team:
 *     type: 'dynamic'
 *     condition: 'email_domain'
 *     value: 'company.com'
 *
 * us_users:
 *     type: 'dynamic'
 *     condition: 'country'
 *     value: 'US'
 *
 * premium_tier:
 *     type: 'dynamic'
 *     condition: 'subscription'
 *     value: 'premium'
 * ```
 */
class DynamicSegment implements SegmentInterface
{
    private string $name;
    private string $condition;
    private mixed $value;

    /**
     * @param string $name Segment identifier
     * @param string $condition The context attribute to check
     * @param mixed $value The expected value for the condition
     */
    public function __construct(string $name, string $condition, mixed $value)
    {
        $this->name = $name;
        $this->condition = $condition;
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function contains(string|int $userId, array $context = []): bool
    {
        // Special handling for email domain
        if ($this->condition === 'email_domain' && isset($context['email'])) {
            return $this->matchEmailDomain($context['email'], $this->value);
        }

        // Direct attribute matching
        if (!isset($context[$this->condition])) {
            return false;
        }

        $contextValue = $context[$this->condition];

        // Support array values (IN operator)
        if (is_array($this->value)) {
            return in_array($contextValue, $this->value, true);
        }

        // Direct comparison
        return $contextValue === $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'dynamic';
    }

    /**
     * Get the condition attribute name.
     *
     * @return string
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Get the expected value.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Check if email matches domain.
     *
     * @param string $email The email address
     * @param string $domain The expected domain
     * @return bool
     */
    private function matchEmailDomain(string $email, string $domain): bool
    {
        $emailDomain = substr(strrchr($email, '@'), 1);
        return strcasecmp($emailDomain, $domain) === 0;
    }
}
