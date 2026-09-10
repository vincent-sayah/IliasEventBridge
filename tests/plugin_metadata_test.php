<?php

function failMetadataTest($message)
{
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
}

require __DIR__ . '/../plugin.php';

if ($id !== 'ileb') {
    failMetadataTest('unexpected EventHook plugin id');
}
if ($version !== '0.1.1') {
    failMetadataTest('unexpected EventHook plugin version');
}
if (version_compare($ilias_min_version, '7.30', '>')) {
    failMetadataTest('EventHook cannot be activated on ILIAS 7.30');
}

require __DIR__ . '/../companion/IliasEventBridgeCron/plugin.php.tpl';

if ($id !== 'ilec') {
    failMetadataTest('unexpected CronHook plugin id');
}
if ($version !== '0.1.1') {
    failMetadataTest('unexpected CronHook plugin version');
}
if (version_compare($ilias_min_version, '7.30', '>')) {
    failMetadataTest('CronHook cannot be activated on ILIAS 7.30');
}

fwrite(STDOUT, 'OK: plugin metadata tests passed' . PHP_EOL);
