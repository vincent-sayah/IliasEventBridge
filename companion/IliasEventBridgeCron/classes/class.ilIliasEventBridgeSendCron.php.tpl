<?php

class ilIliasEventBridgeSendCron extends ilCronJob
{
    public const JOB_ID = 'ileb_read_event_and_send';

    public function getId()
    {
        return self::JOB_ID;
    }

    public function getTitle()
    {
        return 'IliasEventBridge — collecte et envoi TRAX';
    }

    public function getDescription()
    {
        return 'Convertit les consultations read_event autorisées puis envoie l’outbox xAPI vers TRAX.';
    }

    public function getDefaultScheduleType()
    {
        return ilCronJob::SCHEDULE_TYPE_IN_MINUTES;
    }

    public function getDefaultScheduleValue()
    {
        return 5;
    }

    public function hasAutoActivation()
    {
        return false;
    }

    public function hasFlexibleSchedule()
    {
        return true;
    }

    public function run()
    {
        $result = new ilCronJobResult();
        try {
            $this->loadBridgeClasses();
            $config = new ilIliasEventBridgeConfig();
            if (!$config->isEnabled() || !$config->isCronEnabled()) {
                $message = 'IliasEventBridge ou son cron est désactivé dans la configuration du plugin principal.';
                $config->setLastCronResult(true, 0, $message);
                $result->setStatus(ilCronJobResult::STATUS_NO_ACTION);
                $result->setMessage($message);
                return $result;
            }

            $outbox = new ilIliasEventBridgeOutboxRepository();
            $outbox->resetStuckSending();
            $generated = (new ilIliasEventBridgeReadEventTracker(
                $config,
                $outbox,
                new ilIliasEventBridgeStatementFactory($config),
                new ilIliasEventBridgeCourseContextResolver(),
                new ilIliasEventBridgeCourseTrackingRepository(),
                new ilIliasEventBridgeDenyLogRepository()
            ))->scanAndEnqueue(100);

            (new ilIliasEventBridgeEventDebugRepository())->deleteOlderThanDays($config->getRetentionDays());
            $send = (new ilIliasEventBridgeOutboxSender($config, $outbox))->sendBatch();
            $message = $generated . ' consultation(s) convertie(s) ; ' . (string) $send['message'];
            $config->setLastCronResult((bool) $send['success'], (int) $send['http_status'], $message);
            $result->setStatus($send['success'] ? ilCronJobResult::STATUS_OK : ilCronJobResult::STATUS_FAIL);
            $result->setMessage($message);
        } catch (Throwable $e) {
            if (isset($config) && $config instanceof ilIliasEventBridgeConfig) {
                $config->setLastCronResult(false, 0, $e->getMessage());
            }
            $result->setStatus(ilCronJobResult::STATUS_FAIL);
            $result->setMessage($e->getMessage());
        }
        return $result;
    }

    private function loadBridgeClasses(): void
    {
        $iliasRoot = dirname(__DIR__, 8);
        $base = $iliasRoot . '/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge/classes';
        $files = [
            'class.ilIliasEventBridgeConfig.php',
            'class.ilIliasEventBridgeEventDebugRepository.php',
            'class.ilIliasEventBridgeOutboxRepository.php',
            'class.ilIliasEventBridgeOutboxSender.php',
            'class.ilIliasEventBridgeTraxClient.php',
            'class.ilIliasEventBridgeHttpResult.php',
            'class.ilIliasEventBridgeStatementFactory.php',
            'class.ilIliasEventBridgeCourseContextResolver.php',
            'class.ilIliasEventBridgeCourseTrackingRepository.php',
            'class.ilIliasEventBridgeDenyLogRepository.php',
            'class.ilIliasEventBridgeReadEventTracker.php',
        ];
        foreach ($files as $file) {
            $path = $base . '/' . $file;
            if (!is_file($path)) {
                throw new RuntimeException('Fichier du plugin principal introuvable : ' . $path);
            }
            require_once $path;
        }
    }
}
