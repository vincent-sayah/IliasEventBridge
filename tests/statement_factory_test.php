<?php

$_SERVER['HTTP_HOST'] = '192.168.56.53';

class ilObjUser
{
    public static function _lookupLogin($userId)
    {
        return (int) $userId === 42 ? 'stagiaire.test' : '';
    }
}

class ilObject
{
    public static function _lookupTitle($objId)
    {
        return (int) $objId === 200 ? 'Test de mathématiques' : 'Cours de démonstration';
    }
}

require_once __DIR__ . '/../classes/class.ilIliasEventBridgeConfig.php';
require_once __DIR__ . '/../classes/class.ilIliasEventBridgeStatementFactory.php';

function expectTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$factory = new ilIliasEventBridgeStatementFactory(new ilIliasEventBridgeConfig());

$courseStatement = $factory->createFromEventRecord([
    'component' => 'components/ILIAS/ReadEvent',
    'event_name' => 'access',
    'user_id' => 42,
    'ref_id' => 100,
    'obj_id' => 101,
    'obj_type' => 'crs',
    'course_ref_id' => 100,
    'course_obj_id' => 101,
    'read_count' => 1,
    'spent_seconds' => 30,
    'created_at' => '2026-09-10 12:00:00',
]);

expectTrue(is_array($courseStatement), 'course access must create a statement');
expectTrue($courseStatement['actor']['account']['name'] === 'stagiaire.test', 'actor account must use the ILIAS login');
expectTrue($courseStatement['verb']['id'] === 'http://adlnet.gov/expapi/verbs/initialized', 'course access verb');
expectTrue(!isset($courseStatement['context']['contextActivities']), 'a course must not be its own parent');

$baseTestRecord = [
    'component' => 'Services/Tracking',
    'event_name' => 'updateStatus',
    'user_id' => 42,
    'ref_id' => 201,
    'obj_id' => 200,
    'obj_type' => 'tst',
    'course_ref_id' => 100,
    'course_obj_id' => 101,
    'created_at' => '2026-09-10 12:05:00',
];

$attempt = $baseTestRecord;
$attempt['payload_json'] = json_encode(['usr_id' => 42, 'status' => 1, 'old_status' => 0, 'percentage' => 10]);
$attemptStatement = $factory->createFromEventRecord($attempt);
expectTrue($attemptStatement['verb']['id'] === 'http://adlnet.gov/expapi/verbs/attempted', 'status 1 must map to attempted');

$passed = $baseTestRecord;
$passed['payload_json'] = json_encode(['usr_id' => 42, 'status' => 2, 'old_status' => 1, 'percentage' => 100]);
$passedStatement = $factory->createFromEventRecord($passed);
expectTrue($passedStatement['verb']['id'] === 'http://adlnet.gov/expapi/verbs/passed', 'status 2 must map to passed');
expectTrue($passedStatement['result']['success'] === true, 'passed result must be successful');
expectTrue(count($passedStatement['context']['contextActivities']['parent']) === 1, 'test must reference its parent course');

$failed = $baseTestRecord;
$failed['payload_json'] = json_encode(['usr_id' => 42, 'status' => 3, 'old_status' => 1, 'percentage' => 45]);
$failedStatement = $factory->createFromEventRecord($failed);
expectTrue($failedStatement['verb']['id'] === 'http://adlnet.gov/expapi/verbs/failed', 'status 3 must map to failed');
expectTrue($failedStatement['result']['success'] === false, 'failed result must be unsuccessful');

fwrite(STDOUT, "OK: statement factory tests passed" . PHP_EOL);
