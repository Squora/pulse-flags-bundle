<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Validator for user_id strategy configuration.
 */
class UserIdStrategyValidator implements StrategyValidatorInterface
{
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        $hasWhitelist = isset($config['whitelist']) && !empty($config['whitelist']);
        $hasBlacklist = isset($config['blacklist']) && !empty($config['blacklist']);

        // Must have at least one list
        if (!$hasWhitelist && !$hasBlacklist) {
            $result->addError('User ID strategy requires either "whitelist" or "blacklist"');
        }

        // Cannot have both
        if ($hasWhitelist && $hasBlacklist) {
            $result->addError('User ID strategy cannot have both "whitelist" and "blacklist"');
        }

        // Validate whitelist format
        if ($hasWhitelist) {
            if (!is_array($config['whitelist'])) {
                $result->addError('Whitelist must be an array');
            } else {
                $this->validateUserList($config['whitelist'], 'whitelist', $result);
            }
        }

        // Validate blacklist format
        if ($hasBlacklist) {
            if (!is_array($config['blacklist'])) {
                $result->addError('Blacklist must be an array');
            } else {
                $this->validateUserList($config['blacklist'], 'blacklist', $result);
            }
        }

        return $result;
    }

    /**
     * Validate user ID list.
     *
     * @param array<mixed> $list
     * @param string $listName
     * @param ValidationResult $result
     * @return void
     */
    private function validateUserList(array $list, string $listName, ValidationResult $result): void
    {
        if (empty($list)) {
            $result->addWarning(sprintf('%s is empty', ucfirst($listName)));
            return;
        }

        foreach ($list as $index => $userId) {
            if (!is_string($userId) && !is_int($userId)) {
                $result->addError(sprintf(
                    '%s[%d]: User ID must be string or integer, got %s',
                    $listName,
                    $index,
                    gettype($userId)
                ));
            }
        }

        // Warn about large lists (performance consideration)
        if (count($list) > 10000) {
            $result->addWarning(sprintf(
                '%s contains %d users. Consider using segment strategy for better management.',
                ucfirst($listName),
                count($list)
            ));
        }
    }

    public function getStrategyName(): string
    {
        return 'user_id';
    }
}
