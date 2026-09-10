<?php

$source = file_get_contents(__DIR__ . '/../classes/class.ilIliasEventBridgeConfigGUI.php');
if (!is_string($source)) {
    fwrite(STDERR, 'FAIL: unable to read ConfigGUI source' . PHP_EOL);
    exit(1);
}

$commands = [
    'saveConfig',
    'configure',
    'saveCourseTracking',
    'enableAllCourseResources',
    'disableAllCourseResources',
    'resetCourseTracking',
    'testTrax',
    'scanReadEvents',
    'sendOutbox',
    'resetFailed',
    'clearLog',
    'clearDenyLog',
    'clearOutbox',
];

foreach ($commands as $command) {
    if (strpos($source, 'name="cmd[' . $command . ']"') === false) {
        fwrite(STDERR, 'FAIL: missing ILIAS 7 POST command ' . $command . PHP_EOL);
        exit(1);
    }
}

if (strpos($source, 'name="cmd"') !== false) {
    fwrite(STDERR, 'FAIL: scalar cmd fields are ignored by the ILIAS 7 controller' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'OK: ILIAS 7 form command tests passed' . PHP_EOL);
