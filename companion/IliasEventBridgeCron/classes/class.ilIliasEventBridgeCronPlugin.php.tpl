<?php

class ilIliasEventBridgeCronPlugin extends ilCronHookPlugin
{
    public function getPluginName()
    {
        return 'IliasEventBridgeCron';
    }

    public function getCronJobInstances()
    {
        require_once __DIR__ . '/class.ilIliasEventBridgeSendCron.php';
        return [new ilIliasEventBridgeSendCron()];
    }

    public function getCronJobInstance($a_job_id)
    {
        require_once __DIR__ . '/class.ilIliasEventBridgeSendCron.php';
        if ((string) $a_job_id === ilIliasEventBridgeSendCron::JOB_ID) {
            return new ilIliasEventBridgeSendCron();
        }
        throw new OutOfBoundsException('Unknown IliasEventBridge cron job: ' . (string) $a_job_id);
    }
}
