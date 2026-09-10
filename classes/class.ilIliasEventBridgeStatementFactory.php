<?php

/**
 * Builds xAPI 1.0.3 statements from the stable signals available in ILIAS 7:
 * learning-progress events for tests and read_event rows for course resources.
 */
class ilIliasEventBridgeStatementFactory
{
    /** @var ilIliasEventBridgeConfig */
    private $config;

    public function __construct(ilIliasEventBridgeConfig $config)
    {
        $this->config = $config;
    }

    /** @return array<int,array<string,mixed>> */
    public function createStatementsFromEventRecord(array $record): array
    {
        $statement = $this->createFromEventRecord($record);
        return $statement === null ? [] : [$statement];
    }

    /** @return array<string,mixed>|null */
    public function createFromEventRecord(array $record): ?array
    {
        $component = (string) ($record['component'] ?? '');
        $event = (string) ($record['event_name'] ?? '');
        $objType = (string) ($record['obj_type'] ?? '');
        $userId = (int) ($record['user_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        if ($this->isTrackingComponent($component) && $event === 'updateStatus' && $objType === 'tst') {
            return $this->createTestTrackingStatement($record);
        }

        if ($this->isReadEventComponent($component)
            && $event === 'access'
            && in_array($objType, $this->supportedReadObjectTypes(), true)
        ) {
            return $this->createAccessStatement($record);
        }

        return null;
    }

    /** @return array<int,string> */
    public function supportedReadObjectTypes(): array
    {
        return ['crs', 'file', 'blog', 'webr', 'mcst', 'frm', 'wiki', 'htlm', 'lm', 'sahs', 'exc'];
    }

    private function isTrackingComponent(string $component): bool
    {
        return $component === 'Services/Tracking'
            || $component === 'components/ILIAS/Tracking';
    }

    private function isReadEventComponent(string $component): bool
    {
        return $component === 'components/ILIAS/ReadEvent'
            || $component === 'Services/Tracking/ReadEvent';
    }

    /** @return array<string,mixed> */
    private function createAccessStatement(array $record): array
    {
        $objType = (string) ($record['obj_type'] ?? 'object');
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        $title = $this->objectTitle($record, $this->objectLabel($objType));
        $resultExtensions = [
            $this->extensionUri('read_count') => (int) ($record['read_count'] ?? 0),
            $this->extensionUri('spent_seconds') => (int) ($record['spent_seconds'] ?? 0),
            $this->extensionUri('read_event_last_access') => (int) ($record['read_event_last_access'] ?? 0),
        ];

        $result = ['extensions' => $resultExtensions];
        $duration = $this->durationFromSeconds((int) ($record['spent_seconds'] ?? 0));
        if ($duration !== '') {
            $result['duration'] = $duration;
        }

        return [
            'id' => $this->uuid4(),
            'actor' => $this->actor((int) ($record['user_id'] ?? 0)),
            'verb' => $this->accessVerb($objType),
            'object' => [
                'id' => $this->activityId($objType, $refId, $objId),
                'objectType' => 'Activity',
                'definition' => [
                    'type' => $this->activityType($objType),
                    'name' => ['fr-FR' => $title, 'en-US' => $title],
                    'description' => $this->objectDescription($objType),
                    'moreInfo' => $this->objectUrl($objType, $refId),
                ],
            ],
            'result' => $result,
            'context' => $this->context($record, 'repository_object_access'),
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];
    }

    /** @return array<string,mixed> */
    private function createTestTrackingStatement(array $record): array
    {
        $payload = $this->decodePayload((string) ($record['payload_json'] ?? ''));
        $status = (int) ($payload['status'] ?? -1);
        $oldStatus = (int) ($payload['old_status'] ?? -1);
        $percentage = (float) ($payload['percentage'] ?? 0);
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        $userId = (int) ($payload['usr_id'] ?? ($record['user_id'] ?? 0));
        $completion = $status === 2 || $status === 3;

        $result = [
            'completion' => $completion,
            'score' => [
                'scaled' => max(0, min(1, $percentage / 100)),
                'raw' => $percentage,
                'min' => 0,
                'max' => 100,
            ],
            'extensions' => [
                $this->extensionUri('ilias_status') => $status,
                $this->extensionUri('ilias_old_status') => $oldStatus,
                $this->extensionUri('ilias_percentage') => $percentage,
            ],
        ];
        if ($status === 2) {
            $result['success'] = true;
        } elseif ($status === 3) {
            $result['success'] = false;
        }

        $title = $this->objectTitle($record, 'Test ILIAS');
        return [
            'id' => $this->uuid4(),
            'actor' => $this->actor($userId),
            'verb' => $this->testVerb($status),
            'object' => [
                'id' => $this->activityId('tst', $refId, $objId),
                'objectType' => 'Activity',
                'definition' => [
                    'type' => 'http://adlnet.gov/expapi/activities/assessment',
                    'name' => ['fr-FR' => $title, 'en-US' => $title],
                    'description' => [
                        'fr-FR' => 'Test réalisé dans ILIAS 7',
                        'en-US' => 'Assessment performed in ILIAS 7',
                    ],
                    'moreInfo' => $this->objectUrl('tst', $refId),
                ],
            ],
            'result' => $result,
            'context' => $this->context($record, 'test_tracking_status'),
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];
    }

    /** @return array<string,mixed> */
    private function actor(int $userId): array
    {
        $login = 'ilias-user-' . max(0, $userId);
        if ($userId > 0 && class_exists('ilObjUser') && method_exists('ilObjUser', '_lookupLogin')) {
            try {
                $candidate = ilObjUser::_lookupLogin($userId);
                if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                    $login = trim((string) $candidate);
                }
            } catch (Throwable $ignored) {
                // Keep the stable numeric fallback.
            }
        }

        return [
            'objectType' => 'Agent',
            'account' => [
                'homePage' => $this->config->getActorHomePage(),
                'name' => $login,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function context(array $record, string $sourceEvent): array
    {
        $context = [
            'platform' => 'ILIAS 7.30',
            'extensions' => [
                $this->extensionUri('source_event') => $sourceEvent,
                $this->extensionUri('component') => (string) ($record['component'] ?? ''),
                $this->extensionUri('event_name') => (string) ($record['event_name'] ?? ''),
                $this->extensionUri('ref_id') => (int) ($record['ref_id'] ?? 0),
                $this->extensionUri('obj_id') => (int) ($record['obj_id'] ?? 0),
                $this->extensionUri('obj_type') => (string) ($record['obj_type'] ?? ''),
                $this->extensionUri('course_ref_id') => (int) ($record['course_ref_id'] ?? 0),
                $this->extensionUri('course_obj_id') => (int) ($record['course_obj_id'] ?? 0),
            ],
        ];

        $course = $this->courseActivity($record);
        if ($course !== null) {
            $context['contextActivities'] = ['parent' => [$course]];
        }
        return $context;
    }

    /** @return array<string,mixed>|null */
    private function courseActivity(array $record): ?array
    {
        $courseRefId = (int) ($record['course_ref_id'] ?? 0);
        $courseObjId = (int) ($record['course_obj_id'] ?? 0);
        if ($courseRefId <= 0) {
            return null;
        }
        if ((string) ($record['obj_type'] ?? '') === 'crs'
            && (int) ($record['ref_id'] ?? 0) === $courseRefId
        ) {
            return null;
        }

        $title = $this->lookupTitle($courseObjId);
        if ($title === '') {
            $title = 'Cours ILIAS ' . $courseRefId;
        }
        return [
            'id' => $this->activityId('crs', $courseRefId, $courseObjId),
            'objectType' => 'Activity',
            'definition' => [
                'type' => 'http://adlnet.gov/expapi/activities/course',
                'name' => ['fr-FR' => $title, 'en-US' => $title],
                'moreInfo' => $this->objectUrl('crs', $courseRefId),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function accessVerb(string $objType): array
    {
        $custom = $this->config->getIliasBaseUrl() . '/xapi/verbs/';
        $map = [
            'crs' => ['http://adlnet.gov/expapi/verbs/initialized', 'est entré dans le cours', 'entered the course'],
            'file' => [$custom . 'downloaded', 'a téléchargé le fichier', 'downloaded the file'],
            'sahs' => ['http://adlnet.gov/expapi/verbs/launched', 'a lancé le module SCORM', 'launched the SCORM module'],
            'webr' => [$custom . 'visited', 'a ouvert le lien web', 'opened the web link'],
            'mcst' => ['http://id.tincanapi.com/verb/viewed', 'a consulté le MediaCast', 'viewed the MediaCast'],
            'frm' => ['http://adlnet.gov/expapi/verbs/interacted', 'a consulté le forum', 'viewed the forum'],
            'exc' => ['http://adlnet.gov/expapi/verbs/experienced', 'a consulté l’exercice', 'viewed the exercise'],
        ];
        $verb = $map[$objType] ?? ['http://adlnet.gov/expapi/verbs/experienced', 'a consulté la ressource', 'experienced the resource'];
        return ['id' => $verb[0], 'display' => ['fr-FR' => $verb[1], 'en-US' => $verb[2]]];
    }

    /** @return array<string,mixed> */
    private function testVerb(int $status): array
    {
        if ($status === 2) {
            return ['id' => 'http://adlnet.gov/expapi/verbs/passed', 'display' => ['fr-FR' => 'a réussi', 'en-US' => 'passed']];
        }
        if ($status === 3) {
            return ['id' => 'http://adlnet.gov/expapi/verbs/failed', 'display' => ['fr-FR' => 'a échoué', 'en-US' => 'failed']];
        }
        return ['id' => 'http://adlnet.gov/expapi/verbs/attempted', 'display' => ['fr-FR' => 'a tenté', 'en-US' => 'attempted']];
    }

    private function activityType(string $objType): string
    {
        $base = $this->config->getIliasBaseUrl() . '/xapi/activity-type/';
        $map = [
            'crs' => 'http://adlnet.gov/expapi/activities/course',
            'sahs' => 'http://adlnet.gov/expapi/activities/module',
            'lm' => $base . 'ilias-learning-module',
            'htlm' => $base . 'ilias-html-learning-module',
            'file' => $base . 'ilias-file',
            'wiki' => $base . 'ilias-wiki',
            'blog' => $base . 'ilias-blog',
            'frm' => $base . 'ilias-forum',
            'mcst' => $base . 'ilias-mediacast',
            'webr' => $base . 'ilias-web-link',
            'exc' => $base . 'ilias-exercise',
        ];
        return $map[$objType] ?? $base . 'ilias-repository-object';
    }

    private function objectLabel(string $objType): string
    {
        $map = [
            'crs' => 'Cours ILIAS',
            'file' => 'Fichier ILIAS',
            'blog' => 'Blog ILIAS',
            'webr' => 'Lien web ILIAS',
            'mcst' => 'MediaCast ILIAS',
            'frm' => 'Forum ILIAS',
            'wiki' => 'Wiki ILIAS',
            'htlm' => 'Module HTML ILIAS',
            'lm' => 'Module d’apprentissage ILIAS',
            'sahs' => 'Module SCORM ILIAS',
            'exc' => 'Exercice ILIAS',
        ];
        return $map[$objType] ?? 'Objet ILIAS';
    }

    /** @return array<string,string> */
    private function objectDescription(string $objType): array
    {
        $label = $this->objectLabel($objType);
        return [
            'fr-FR' => 'Consultation de la ressource « ' . $label . ' » dans ILIAS 7',
            'en-US' => 'Access to the resource "' . $label . '" in ILIAS 7',
        ];
    }

    private function objectTitle(array $record, string $fallback): string
    {
        if (isset($record['object_title']) && is_scalar($record['object_title']) && trim((string) $record['object_title']) !== '') {
            return trim((string) $record['object_title']);
        }
        $title = $this->lookupTitle((int) ($record['obj_id'] ?? 0));
        return $title !== '' ? $title : $fallback;
    }

    private function lookupTitle(int $objId): string
    {
        if ($objId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupTitle')) {
            return '';
        }
        try {
            $title = ilObject::_lookupTitle($objId);
            return is_scalar($title) ? trim((string) $title) : '';
        } catch (Throwable $ignored) {
            return '';
        }
    }

    private function activityId(string $type, int $refId, int $objId): string
    {
        $type = preg_replace('/[^a-zA-Z0-9_-]/', '-', $type);
        $type = is_string($type) && $type !== '' ? $type : 'object';
        $suffix = $refId > 0 ? 'ref/' . $refId : 'obj/' . max(0, $objId);
        return $this->config->getIliasBaseUrl() . '/xapi/activity/' . $type . '/' . $suffix;
    }

    private function objectUrl(string $objType, int $refId): string
    {
        if ($refId <= 0) {
            return $this->config->getIliasBaseUrl();
        }
        $targetType = preg_replace('/[^a-zA-Z0-9_-]/', '', $objType);
        $targetType = is_string($targetType) && $targetType !== '' ? $targetType : 'obj';
        return $this->config->getIliasBaseUrl() . '/goto.php?target=' . $targetType . '_' . $refId;
    }

    private function extensionUri(string $name): string
    {
        return $this->config->getIliasBaseUrl() . '/xapi/extensions/' . $name;
    }

    /** @return array<string,mixed> */
    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function durationFromSeconds(int $seconds): string
    {
        return $seconds > 0 ? 'PT' . $seconds . 'S' : '';
    }

    private function isoTimestamp(string $createdAt): string
    {
        $timestamp = strtotime($createdAt);
        return gmdate('c', $timestamp === false ? time() : $timestamp);
    }

    private function uuid4(): string
    {
        try {
            $data = random_bytes(16);
            $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
            $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        } catch (Throwable $ignored) {
            return str_replace('.', '-', uniqid('ileb-', true));
        }
    }
}
