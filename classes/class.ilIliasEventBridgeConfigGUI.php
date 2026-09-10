<?php
/**
 * @ilCtrl_IsCalledBy ilIliasEventBridgeConfigGUI: ilObjComponentSettingsGUI
 */

require_once __DIR__ . '/class.ilIliasEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasEventBridgeEventDebugRepository.php';
require_once __DIR__ . '/class.ilIliasEventBridgeOutboxRepository.php';
require_once __DIR__ . '/class.ilIliasEventBridgeOutboxSender.php';
require_once __DIR__ . '/class.ilIliasEventBridgeTraxClient.php';
require_once __DIR__ . '/class.ilIliasEventBridgeDenyLogRepository.php';
require_once __DIR__ . '/class.ilIliasEventBridgeCourseTrackingRepository.php';
require_once __DIR__ . '/class.ilIliasEventBridgeCourseResourceResolver.php';

class ilIliasEventBridgeConfigGUI extends ilPluginConfigGUI
{
    /** @var mixed */
    private $ctrl;

    /** @var mixed */
    private $tpl;

    /** @var ilIliasEventBridgeConfig */
    private $config;

    /** @var ilIliasEventBridgeEventDebugRepository */
    private $events;

    /** @var ilIliasEventBridgeOutboxRepository */
    private $outbox;

    /** @var ilIliasEventBridgeDenyLogRepository */
    private $denyLog;

    public function __construct()
    {
        global $DIC, $ilCtrl, $tpl;
        $this->ctrl = isset($DIC) && is_object($DIC) && method_exists($DIC, 'ctrl') ? $DIC->ctrl() : $ilCtrl;
        $this->tpl = isset($DIC) && (is_array($DIC) || $DIC instanceof ArrayAccess) && isset($DIC['tpl'])
            ? $DIC['tpl']
            : $tpl;
    }

    /** ILIAS 7 parent method deliberately has no scalar type declaration. */
    public function performCommand($cmd)
    {
        $this->init();
        $command = is_scalar($cmd) ? (string) $cmd : 'configure';
        switch ($command) {
            case 'saveConfig':
                $this->requirePost();
                $this->saveConfig();
                break;
            case 'testTrax':
                $this->requirePost();
                $this->testTrax();
                break;
            case 'scanReadEvents':
                $this->requirePost();
                $this->scanReadEvents();
                break;
            case 'sendOutbox':
                $this->requirePost();
                $this->sendOutbox();
                break;
            case 'resetFailed':
                $this->requirePost();
                $this->success($this->outbox->resetFailedToGenerated() . ' statement(s) réinitialisé(s).');
                $this->redirect();
                break;
            case 'clearLog':
                $this->requirePost();
                $this->events->clear();
                $this->success('Journal des événements vidé.');
                $this->redirect();
                break;
            case 'clearDenyLog':
                $this->requirePost();
                $this->success($this->denyLog->clear() . ' refus supprimé(s).');
                $this->redirect();
                break;
            case 'clearOutbox':
                $this->requirePost();
                $this->outbox->clear();
                $this->success('Outbox vidée.');
                $this->redirect();
                break;
            case 'saveCourseTracking':
                $this->requirePost();
                $this->saveCourseTracking();
                break;
            case 'enableAllCourseResources':
                $this->requirePost();
                $this->setAllCourseResources(true);
                break;
            case 'disableAllCourseResources':
                $this->requirePost();
                $this->setAllCourseResources(false);
                break;
            case 'resetCourseTracking':
                $this->requirePost();
                $this->resetCourseTracking();
                break;
            case 'configure':
            default:
                $this->configure();
                break;
        }
    }

    private function init(): void
    {
        $this->config = new ilIliasEventBridgeConfig();
        $this->events = new ilIliasEventBridgeEventDebugRepository();
        $this->outbox = new ilIliasEventBridgeOutboxRepository();
        $this->denyLog = new ilIliasEventBridgeDenyLogRepository();
        $this->outbox->resetStuckSending();
    }

    private function configure(): void
    {
        $html = $this->styles()
            . '<div class="ileb-page"><h1>IliasEventBridge — ILIAS 7.30</h1>'
            . '<p class="ileb-intro">Transformation des consultations et résultats de tests ILIAS en statements xAPI 1.0.3 envoyés à TRAX 3.</p>'
            . $this->renderStatus()
            . $this->renderConfigForm()
            . $this->renderCourseTracking()
            . $this->renderActions()
            . $this->renderOutbox()
            . $this->renderEvents()
            . '</div>';
        $this->setContent($html);
    }

    private function renderStatus(): string
    {
        $lastTest = $this->config->getLastResult('last_trax_test');
        $lastSend = $this->config->getLastResult('last_trax_send');
        $lastCron = $this->config->getLastResult('last_cron');
        $rows = '';
        $rows .= $this->statusRow('Plugin', $this->config->isEnabled() ? 'activé' : 'désactivé');
        $rows .= $this->statusRow('TRAX', $this->config->isTraxConfigured() ? 'configuré' : 'configuration incomplète');
        $rows .= $this->statusRow('Outbox', $this->outbox->countByStatus('generated') . ' generated ; ' . $this->outbox->countByStatus('failed') . ' failed ; ' . $this->outbox->countByStatus('sent') . ' sent');
        $rows .= $this->statusRow('Dernier test', $this->formatResult($lastTest));
        $rows .= $this->statusRow('Dernier envoi', $this->formatResult($lastSend));
        $rows .= $this->statusRow('Dernier cron', $this->formatResult($lastCron));
        return '<section><h2>État</h2><table class="std"><tbody>' . $rows . '</tbody></table></section>';
    }

    private function renderConfigForm(): string
    {
        $action = $this->formAction();
        return '<section><h2>Configuration générale et TRAX</h2>'
            . '<form method="post" action="' . $this->esc($action) . '"><table class="std ileb-form"><tbody>'
            . $this->checkRow('Activer IliasEventBridge', 'enabled', $this->config->isEnabled(), 'Le traitement des événements reste non bloquant.')
            . $this->checkRow('Générer les statements', 'local_xapi_generation_enabled', $this->config->isLocalXapiGenerationEnabled(), 'Ajoute les statements acceptés dans l’outbox locale.')
            . $this->checkRow('Activer le compagnon cron', 'cron_enabled', $this->config->isCronEnabled(), 'Doit aussi être activé dans la gestion des jobs cron ILIAS.')
            . $this->checkRow('Journal debug', 'debug_enabled', $this->config->isDebugEnabled(), 'À activer temporairement : tous les événements ILIAS reçus sont enregistrés.')
            . $this->checkRow('Journal des refus', 'deny_log_enabled', $this->config->isDenyLogEnabled(), 'Diagnostic des ressources non autorisées ou non prises en charge.')
            . $this->inputRow('URL de base ILIAS', 'ilias_base_url', $this->config->getIliasBaseUrl(), 'Exemple : http://192.168.56.53')
            . $this->inputRow('Endpoint TRAX/LRS', 'trax_endpoint', $this->config->getTraxEndpoint(), 'URL xAPI de base ou URL terminant par /statements.')
            . $this->inputRow('Identifiant TRAX', 'trax_username', $this->config->getTraxUsername(), 'Client/key xAPI TRAX.')
            . $this->passwordRow('Mot de passe TRAX', 'trax_password', $this->config->getTraxPassword() !== '' ? 'Un mot de passe est enregistré. Laisser vide pour le conserver.' : 'Aucun mot de passe enregistré.')
            . $this->checkRow('Supprimer le mot de passe enregistré', 'clear_trax_password', false, 'Cochez puis enregistrez pour effacer le secret.')
            . $this->inputRow('Version xAPI', 'xapi_version', $this->config->getXapiVersion(), 'Valeur recommandée : 1.0.3')
            . $this->numberRow('Timeout HTTP (secondes)', 'http_timeout', $this->config->getHttpTimeout(), 2, 120)
            . $this->numberRow('Taille du batch', 'batch_size', $this->config->getBatchSize(), 1, 100)
            . $this->numberRow('Nombre maximal de tentatives', 'max_retry', $this->config->getMaxRetry(), 0, 50)
            . $this->numberRow('Rétention journal (jours)', 'retention_days', $this->config->getRetentionDays(), 1, 365)
            . $this->numberRow('Payload debug maximal', 'max_payload_chars', $this->config->getMaxPayloadChars(), 500, 30000)
            . $this->checkRow('Vérifier le certificat TLS', 'tls_verify', $this->config->isTlsVerificationEnabled(), 'À conserver activé hors test local.')
            . $this->inputRow('Bundle CA personnalisé', 'ca_bundle_path', $this->config->getCaBundlePath(), 'Optionnel, exemple : /etc/pki/tls/certs/ca-bundle.crt')
            . '</tbody></table><p><button class="btn btn-primary" name="cmd[saveConfig]" value="1" type="submit">Enregistrer</button></p></form></section>';
    }

    private function renderCourseTracking(): string
    {
        $courseRefId = $this->requestInt('course_ref_id');
        $html = '<section><h2>Cours et ressources suivis</h2>'
            . '<form method="post" action="' . $this->esc($this->formAction()) . '">'
            . '<input type="hidden" name="cmd[configure]" value="1">'
            . '<label for="course_ref_id">Ref-ID du cours : </label> '
            . '<input id="course_ref_id" name="course_ref_id" type="number" min="1" value="' . ($courseRefId > 0 ? $courseRefId : '') . '"> '
            . '<button class="btn btn-default" type="submit">Charger le cours</button></form>';
        if ($courseRefId <= 0) {
            return $html . '<p>Indiquez le <code>ref_id</code> d’un cours, puis activez les ressources à tracer.</p></section>';
        }

        $repository = new ilIliasEventBridgeCourseTrackingRepository();
        $resolver = new ilIliasEventBridgeCourseResourceResolver($repository);
        $course = $resolver->resolveCourse($courseRefId);
        if ((int) ($course['course_obj_id'] ?? 0) <= 0) {
            return $html . '<p class="alert alert-danger">Le ref_id ' . $courseRefId . ' ne correspond pas à un cours ILIAS.</p></section>';
        }

        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        $html .= '<h3>' . $this->esc((string) ($course['course_title'] ?? 'Cours')) . '</h3>'
            . '<form method="post" action="' . $this->esc($this->formAction()) . '">'
            . '<input type="hidden" name="course_ref_id" value="' . $courseRefId . '">'
            . '<p><label><input name="course_enabled" value="1" type="checkbox"' . (!empty($course['course_enabled']) ? ' checked="checked"' : '') . '> Activer les traces pour ce cours</label></p>'
            . '<table class="std"><thead><tr><th>xAPI</th><th>Type</th><th>Ressource</th><th>ref_id</th></tr></thead><tbody>';
        foreach ($resources as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            $html .= '<tr><td><input name="enabled_resources[]" value="' . $refId . '" type="checkbox"' . (!empty($resource['enabled']) ? ' checked="checked"' : '') . '></td>'
                . '<td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($resource['title'] ?? '')) . '</td>'
                . '<td>' . $refId . '</td></tr>';
        }
        $html .= '</tbody></table><p class="ileb-actions">'
            . '<button class="btn btn-primary" name="cmd[saveCourseTracking]" value="1" type="submit">Enregistrer ce cours</button> '
            . '<button class="btn btn-default" name="cmd[enableAllCourseResources]" value="1" type="submit">Tout activer</button> '
            . '<button class="btn btn-default" name="cmd[disableAllCourseResources]" value="1" type="submit">Tout désactiver</button> '
            . '<button class="btn btn-default" name="cmd[resetCourseTracking]" value="1" type="submit" onclick="return confirm(\'Réinitialiser ce cours ?\')">Réinitialiser</button>'
            . '</p></form></section>';
        return $html;
    }

    private function renderActions(): string
    {
        $action = $this->formAction();
        return '<section><h2>Actions et diagnostic</h2><form method="post" action="' . $this->esc($action) . '" class="ileb-actions">'
            . '<button class="btn btn-default" name="cmd[testTrax]" value="1" type="submit">Tester TRAX</button> '
            . '<button class="btn btn-default" name="cmd[scanReadEvents]" value="1" type="submit">Collecter read_event</button> '
            . '<button class="btn btn-primary" name="cmd[sendOutbox]" value="1" type="submit">Envoyer l’outbox</button> '
            . '<button class="btn btn-default" name="cmd[resetFailed]" value="1" type="submit">Réinitialiser les échecs</button> '
            . '<button class="btn btn-default" name="cmd[clearLog]" value="1" type="submit" onclick="return confirm(\'Vider le journal ?\')">Vider le journal</button> '
            . '<button class="btn btn-default" name="cmd[clearDenyLog]" value="1" type="submit" onclick="return confirm(\'Vider les refus ?\')">Vider les refus</button> '
            . '<button class="btn btn-default" name="cmd[clearOutbox]" value="1" type="submit" onclick="return confirm(\'Vider toute l’outbox ?\')">Vider l’outbox</button>'
            . '</form></section>';
    }

    private function renderOutbox(): string
    {
        $rows = $this->outbox->findRecent(30);
        $html = '<section><h2>Derniers statements de l’outbox</h2>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun statement.</em></p></section>';
        }
        $html .= '<div class="table-responsive"><table class="std"><thead><tr><th>ID/date</th><th>Statut</th><th>Verb</th><th>Objet</th><th>Détail</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $detail = trim((string) ($row['last_error'] ?? ''));
            $json = (string) ($row['statement_json'] ?? '');
            $html .= '<tr><td>#' . (int) ($row['id'] ?? 0) . '<br><small>' . $this->esc((string) ($row['created_at'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($row['status'] ?? '')) . '<br>' . (int) ($row['retry_count'] ?? 0) . ' tentative(s)</td>'
                . '<td><code>' . $this->esc((string) ($row['verb_id'] ?? '')) . '</code></td>'
                . '<td>' . $this->esc((string) ($row['obj_type'] ?? '')) . ' / ref ' . (int) ($row['ref_id'] ?? 0) . '</td>'
                . '<td>' . ($detail !== '' ? $this->esc($detail) . '<br>' : '') . '<details><summary>JSON</summary><pre>' . $this->esc($this->prettyJson($json)) . '</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    private function renderEvents(): string
    {
        if (!$this->config->isDebugEnabled()) {
            return '<section><h2>Événements ILIAS</h2><p>Le journal debug est désactivé.</p></section>';
        }
        $rows = $this->events->findRecent(30);
        $html = '<section><h2>Derniers événements ILIAS reçus</h2>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun événement.</em></p></section>';
        }
        $html .= '<div class="table-responsive"><table class="std"><thead><tr><th>ID/date</th><th>Événement</th><th>Objet</th><th>Payload</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>#' . (int) ($row['id'] ?? 0) . '<br><small>' . $this->esc((string) ($row['created_at'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($row['component'] ?? '')) . '<br><strong>' . $this->esc((string) ($row['event_name'] ?? '')) . '</strong></td>'
                . '<td>' . $this->esc((string) ($row['obj_type'] ?? '')) . ' / ref ' . (int) ($row['ref_id'] ?? 0) . ' / obj ' . (int) ($row['obj_id'] ?? 0) . '</td>'
                . '<td><details><summary>JSON</summary><pre>' . $this->esc($this->prettyJson((string) ($row['payload_json'] ?? ''))) . '</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    private function saveConfig(): void
    {
        $endpoint = $this->postString('trax_endpoint');
        $baseUrl = $this->postString('ilias_base_url');
        if ($endpoint !== '' && !preg_match('~^https?://~i', $endpoint)) {
            $this->failure('L’endpoint TRAX doit commencer par http:// ou https://.');
            $this->redirect();
            return;
        }
        if ($baseUrl !== '' && !preg_match('~^https?://~i', $baseUrl)) {
            $this->failure('L’URL de base ILIAS doit commencer par http:// ou https://.');
            $this->redirect();
            return;
        }

        $this->config->setEnabled($this->postChecked('enabled'));
        $this->config->setLocalXapiGenerationEnabled($this->postChecked('local_xapi_generation_enabled'));
        $this->config->setCronEnabled($this->postChecked('cron_enabled'));
        $this->config->setDebugEnabled($this->postChecked('debug_enabled'));
        $this->config->setDenyLogEnabled($this->postChecked('deny_log_enabled'));
        $this->config->setTlsVerificationEnabled($this->postChecked('tls_verify'));
        $this->config->setIliasBaseUrl($baseUrl);
        $this->config->setTraxEndpoint($endpoint);
        $this->config->setTraxUsername($this->postString('trax_username'));
        $this->config->setXapiVersion($this->postString('xapi_version'));
        $this->config->setHttpTimeout((int) $this->postString('http_timeout'));
        $this->config->setBatchSize((int) $this->postString('batch_size'));
        $this->config->setMaxRetry((int) $this->postString('max_retry'));
        $this->config->setRetentionDays((int) $this->postString('retention_days'));
        $this->config->setMaxPayloadChars((int) $this->postString('max_payload_chars'));
        $this->config->setCaBundlePath($this->postString('ca_bundle_path'));
        if ($this->postChecked('clear_trax_password')) {
            $this->config->clearTraxPassword();
        }
        if ($this->postString('trax_password') !== '') {
            $this->config->setTraxPassword($this->postString('trax_password'));
        }
        $this->success('Configuration enregistrée.');
        $this->redirect();
    }

    private function testTrax(): void
    {
        $result = (new ilIliasEventBridgeTraxClient($this->config))->testConnection();
        $this->config->setLastTraxTestResult($result->isSuccess(), $result->getHttpStatus(), $result->getShortMessage());
        if ($result->isSuccess()) {
            $this->success('Connexion TRAX réussie : ' . $result->getShortMessage());
        } else {
            $this->failure('Connexion TRAX échouée : ' . $result->getShortMessage());
        }
        $this->redirect();
    }

    private function scanReadEvents(): void
    {
        require_once __DIR__ . '/class.ilIliasEventBridgeStatementFactory.php';
        require_once __DIR__ . '/class.ilIliasEventBridgeCourseContextResolver.php';
        require_once __DIR__ . '/class.ilIliasEventBridgeReadEventTracker.php';
        $count = (new ilIliasEventBridgeReadEventTracker(
            $this->config,
            $this->outbox,
            new ilIliasEventBridgeStatementFactory($this->config),
            new ilIliasEventBridgeCourseContextResolver(),
            new ilIliasEventBridgeCourseTrackingRepository(),
            $this->denyLog
        ))->scanAndEnqueue(100);
        $this->success($count . ' consultation(s) convertie(s) en statement(s).');
        $this->redirect();
    }

    private function sendOutbox(): void
    {
        $result = (new ilIliasEventBridgeOutboxSender($this->config, $this->outbox))->sendBatch();
        $this->config->setLastTraxSendResult((bool) $result['success'], (int) $result['http_status'], (string) $result['message']);
        if ($result['success']) {
            $this->success((string) $result['message']);
        } else {
            $this->failure((string) $result['message']);
        }
        $this->redirect();
    }

    private function saveCourseTracking(): void
    {
        $courseRefId = $this->postInt('course_ref_id');
        $repository = new ilIliasEventBridgeCourseTrackingRepository();
        $resolver = new ilIliasEventBridgeCourseResourceResolver($repository);
        $course = $resolver->resolveCourse($courseRefId);
        if ((int) ($course['course_obj_id'] ?? 0) <= 0) {
            $this->failure('Cours introuvable.');
            $this->redirect($courseRefId);
            return;
        }
        $enabled = array_fill_keys($this->postIntArray('enabled_resources'), true);
        $userId = $this->currentUserId();
        $repository->setCourseEnabled($courseRefId, (int) $course['course_obj_id'], $this->postChecked('course_enabled'), $userId);
        foreach ((array) ($course['resources'] ?? []) as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            $repository->setResourceEnabled($courseRefId, $refId, (int) ($resource['obj_id'] ?? 0), (string) ($resource['obj_type'] ?? ''), isset($enabled[$refId]), $userId);
        }
        $this->success('Configuration xAPI du cours enregistrée.');
        $this->redirect($courseRefId);
    }

    private function setAllCourseResources(bool $enabled): void
    {
        $courseRefId = $this->postInt('course_ref_id');
        $repository = new ilIliasEventBridgeCourseTrackingRepository();
        $resolver = new ilIliasEventBridgeCourseResourceResolver($repository);
        $course = $resolver->resolveCourse($courseRefId);
        if ((int) ($course['course_obj_id'] ?? 0) <= 0) {
            $this->failure('Cours introuvable.');
            $this->redirect($courseRefId);
            return;
        }
        $userId = $this->currentUserId();
        $repository->setCourseEnabled($courseRefId, (int) $course['course_obj_id'], $enabled, $userId);
        foreach ((array) ($course['resources'] ?? []) as $resource) {
            $repository->setResourceEnabled($courseRefId, (int) ($resource['ref_id'] ?? 0), (int) ($resource['obj_id'] ?? 0), (string) ($resource['obj_type'] ?? ''), $enabled, $userId);
        }
        $this->success($enabled ? 'Cours et ressources activés.' : 'Cours et ressources désactivés.');
        $this->redirect($courseRefId);
    }

    private function resetCourseTracking(): void
    {
        $courseRefId = $this->postInt('course_ref_id');
        (new ilIliasEventBridgeCourseTrackingRepository())->deleteCourseConfig($courseRefId);
        $this->success('Configuration du cours réinitialisée.');
        $this->redirect($courseRefId);
    }

    private function requirePost(): void
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
            throw new RuntimeException('Cette action nécessite une requête POST.');
        }
    }

    private function redirect(int $courseRefId = 0): void
    {
        if ($courseRefId > 0) {
            $this->ctrl->setParameter($this, 'course_ref_id', $courseRefId);
        }
        $this->ctrl->redirect($this, 'configure');
    }

    private function formAction(): string
    {
        return is_object($this->ctrl) && method_exists($this->ctrl, 'getFormAction')
            ? (string) $this->ctrl->getFormAction($this, 'configure')
            : '';
    }

    private function currentUserId(): int
    {
        if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'user')) {
            return (int) $GLOBALS['DIC']->user()->getId();
        }
        return isset($GLOBALS['ilUser']) && is_object($GLOBALS['ilUser']) ? (int) $GLOBALS['ilUser']->getId() : 0;
    }

    private function requestInt(string $key): int
    {
        if (isset($_REQUEST[$key]) && is_scalar($_REQUEST[$key])) {
            return max(0, (int) $_REQUEST[$key]);
        }
        return 0;
    }

    private function postInt(string $key): int
    {
        return isset($_POST[$key]) && is_scalar($_POST[$key]) ? max(0, (int) $_POST[$key]) : 0;
    }

    /** @return array<int,int> */
    private function postIntArray(string $key): array
    {
        $values = isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
        $ids = [];
        foreach ($values as $value) {
            if (is_scalar($value) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }
        return array_values($ids);
    }

    private function postString(string $key): string
    {
        return isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    }

    private function postChecked(string $key): bool
    {
        return isset($_POST[$key]) && (string) $_POST[$key] === '1';
    }

    private function formatResult(array $result): string
    {
        if ((string) ($result['at'] ?? '') === '') {
            return 'jamais';
        }
        $status = (string) ($result['success'] ?? '') === '1' ? 'OK' : 'ERREUR';
        return $status . ' — ' . (string) $result['at'] . ' — HTTP ' . (string) ($result['http_status'] ?? '') . ' — ' . (string) ($result['message'] ?? '');
    }

    private function statusRow(string $label, string $value): string
    {
        return '<tr><th>' . $this->esc($label) . '</th><td>' . $this->esc($value) . '</td></tr>';
    }

    private function inputRow(string $label, string $name, string $value, string $help): string
    {
        return '<tr><th><label for="' . $this->esc($name) . '">' . $this->esc($label) . '</label></th><td><input class="form-control" id="' . $this->esc($name) . '" name="' . $this->esc($name) . '" type="text" value="' . $this->esc($value) . '"><small>' . $this->esc($help) . '</small></td></tr>';
    }

    private function passwordRow(string $label, string $name, string $help): string
    {
        return '<tr><th><label for="' . $this->esc($name) . '">' . $this->esc($label) . '</label></th><td><input class="form-control" autocomplete="new-password" id="' . $this->esc($name) . '" name="' . $this->esc($name) . '" type="password" value=""><small>' . $this->esc($help) . '</small></td></tr>';
    }

    private function numberRow(string $label, string $name, int $value, int $min, int $max): string
    {
        return '<tr><th><label for="' . $this->esc($name) . '">' . $this->esc($label) . '</label></th><td><input id="' . $this->esc($name) . '" name="' . $this->esc($name) . '" type="number" min="' . $min . '" max="' . $max . '" value="' . $value . '"></td></tr>';
    }

    private function checkRow(string $label, string $name, bool $checked, string $help): string
    {
        return '<tr><th>' . $this->esc($label) . '</th><td><label><input name="' . $this->esc($name) . '" type="checkbox" value="1"' . ($checked ? ' checked="checked"' : '') . '> oui</label><br><small>' . $this->esc($help) . '</small></td></tr>';
    }

    private function prettyJson(string $json): string
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        $pretty = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return is_string($pretty) ? $pretty : $json;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function success(string $message): void
    {
        if (class_exists('ilUtil')) {
            ilUtil::sendSuccess($message, true);
        }
    }

    private function failure(string $message): void
    {
        if (class_exists('ilUtil')) {
            ilUtil::sendFailure($message, true);
        }
    }

    private function setContent(string $html): void
    {
        if (is_object($this->tpl) && method_exists($this->tpl, 'setContent')) {
            $this->tpl->setContent($html);
        }
    }

    private function styles(): string
    {
        return '<style>'
            . '.ileb-page{max-width:1280px}.ileb-page section{margin:1.5rem 0}.ileb-page h1{font-size:28px}.ileb-intro{padding:12px;background:#f2f7fb;border-left:4px solid #4c7ea5}'
            . '.ileb-page table.std{width:100%;border-collapse:collapse}.ileb-page table.std th,.ileb-page table.std td{padding:8px;vertical-align:top;border:1px solid #ddd}'
            . '.ileb-page table.std th{background:#f7f7f7}.ileb-form th{width:280px}.ileb-form input.form-control{width:100%;max-width:800px}.ileb-page small{display:block;color:#666;margin-top:4px}'
            . '.ileb-actions{display:flex;flex-wrap:wrap;gap:8px}.ileb-page pre{max-height:280px;overflow:auto;white-space:pre-wrap;word-break:break-word}'
            . '</style>';
    }
}
