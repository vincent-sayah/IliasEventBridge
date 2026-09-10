<?php

/**
 * ILIAS 7.30 EventHook plugin forwarding selected learning events to TRAX 3.
 * Errors are deliberately swallowed and logged so that the bridge can never
 * interrupt a learner's navigation.
 */
class ilIliasEventBridgePlugin extends ilEventHookPlugin
{
    public const PLUGIN_NAME = 'IliasEventBridge';

    public function getPluginName()
    {
        return self::PLUGIN_NAME;
    }

    public function handleEvent($a_component, $a_event, $a_parameter)
    {
        try {
            require_once __DIR__ . '/class.ilIliasEventBridgeConfig.php';
            $config = new ilIliasEventBridgeConfig();
            if (!$config->isEnabled()) {
                return;
            }

            require_once __DIR__ . '/class.ilIliasEventBridgeEventDebugRepository.php';
            require_once __DIR__ . '/class.ilIliasEventBridgeStatementFactory.php';
            require_once __DIR__ . '/class.ilIliasEventBridgeOutboxRepository.php';
            require_once __DIR__ . '/class.ilIliasEventBridgeCourseContextResolver.php';
            require_once __DIR__ . '/class.ilIliasEventBridgeCourseTrackingRepository.php';
            require_once __DIR__ . '/class.ilIliasEventBridgeDenyLogRepository.php';
            require_once __DIR__ . '/class.ilIliasEventBridgeEventRouter.php';

            $parameters = is_array($a_parameter) ? $a_parameter : [];
            $router = new ilIliasEventBridgeEventRouter(
                $config,
                new ilIliasEventBridgeEventDebugRepository(),
                $this,
                new ilIliasEventBridgeStatementFactory($config),
                new ilIliasEventBridgeOutboxRepository(),
                new ilIliasEventBridgeCourseContextResolver(),
                new ilIliasEventBridgeCourseTrackingRepository(),
                new ilIliasEventBridgeDenyLogRepository()
            );
            $router->handle((string) $a_component, (string) $a_event, $parameters);
        } catch (Throwable $e) {
            $this->logNonBlockingError($e);
        }
    }

    protected function afterUninstall()
    {
        if (class_exists('ilSetting')) {
            $settings = new ilSetting('ileb');
            $settings->deleteAll();
        }
    }

    private function logNonBlockingError(Throwable $e): void
    {
        if (class_exists('ilLoggerFactory')) {
            try {
                ilLoggerFactory::getLogger('ileb')->error($e->getMessage());
                return;
            } catch (Throwable $ignored) {
                // Use PHP's error log below.
            }
        }
        error_log('[IliasEventBridge] ' . $e->getMessage());
    }
}
