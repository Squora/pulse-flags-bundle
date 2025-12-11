<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\DependencyInjection\Compiler;

use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to configure logging for PulseFlags bundle.
 *
 * This pass configures the logger service based on bundle configuration:
 * - If logging is disabled: Uses NullLogger (no logging)
 * - If logging is enabled: Uses configured Monolog channel or default logger
 *
 * This allows users to control logging behavior via configuration without
 * changing service definitions.
 */
class LoggerConfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('pulse_flags.logging.enabled')) {
            return;
        }

        $loggingEnabled = $container->getParameter('pulse_flags.logging.enabled');
        $loggerChannel = $container->getParameter('pulse_flags.logging.channel');

        $loggerDefinition = $container->getDefinition('pulse_flags.logger');

        if (!$loggingEnabled) {
            // Logging disabled - use NullLogger
            $loggerDefinition->setClass(NullLogger::class);
            return;
        }

        // Try to use custom Monolog channel if configured
        $channelService = sprintf('monolog.logger.%s', $loggerChannel);
        if ($container->has($channelService)) {
            $container->setAlias('pulse_flags.logger', $channelService);
        } elseif ($container->has('logger')) {
            // Fallback to default logger
            $container->setAlias('pulse_flags.logger', 'logger');
        }
        // If no logger available, NullLogger will be used (already set in services.yaml)
    }
}
