<?php

/**
 * ILIAS 7 settings wrapper for IliasEventBridge.
 */
class ilIliasEventBridgeConfig
{
    private const MODULE = 'ileb';

    /** @var ilSetting|null */
    private $settings;

    public function __construct()
    {
        $this->settings = class_exists('ilSetting') ? new ilSetting(self::MODULE) : null;
    }

    public function isEnabled(): bool
    {
        return $this->getBool('enabled', false);
    }

    public function setEnabled(bool $enabled): void
    {
        $this->setBool('enabled', $enabled);
    }

    public function isDebugEnabled(): bool
    {
        return $this->getBool('debug_enabled', false);
    }

    public function setDebugEnabled(bool $enabled): void
    {
        $this->setBool('debug_enabled', $enabled);
    }

    public function isLocalXapiGenerationEnabled(): bool
    {
        return $this->getBool('local_xapi_generation_enabled', true);
    }

    public function setLocalXapiGenerationEnabled(bool $enabled): void
    {
        $this->setBool('local_xapi_generation_enabled', $enabled);
    }

    public function isDenyLogEnabled(): bool
    {
        return $this->getBool('deny_log_enabled', false);
    }

    public function setDenyLogEnabled(bool $enabled): void
    {
        $this->setBool('deny_log_enabled', $enabled);
    }

    public function isCronEnabled(): bool
    {
        return $this->getBool('cron_enabled', false);
    }

    public function setCronEnabled(bool $enabled): void
    {
        $this->setBool('cron_enabled', $enabled);
    }

    public function getMaxPayloadChars(): int
    {
        return max(500, min(30000, (int) $this->get('max_payload_chars', '10000')));
    }

    public function setMaxPayloadChars(int $value): void
    {
        $this->set('max_payload_chars', (string) max(500, min(30000, $value)));
    }

    public function getRetentionDays(): int
    {
        return max(1, min(365, (int) $this->get('retention_days', '30')));
    }

    public function setRetentionDays(int $days): void
    {
        $this->set('retention_days', (string) max(1, min(365, $days)));
    }

    public function getIliasBaseUrl(): string
    {
        $configured = trim($this->get('ilias_base_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        $host = 'ilias.local';
        if (isset($_SERVER['HTTP_HOST']) && is_scalar($_SERVER['HTTP_HOST']) && trim((string) $_SERVER['HTTP_HOST']) !== '') {
            $host = trim((string) $_SERVER['HTTP_HOST']);
        } elseif (isset($_SERVER['SERVER_NAME']) && is_scalar($_SERVER['SERVER_NAME']) && trim((string) $_SERVER['SERVER_NAME']) !== '') {
            $host = trim((string) $_SERVER['SERVER_NAME']);
        }

        return $scheme . '://' . $host;
    }

    public function setIliasBaseUrl(string $url): void
    {
        $this->set('ilias_base_url', rtrim(trim($url), '/'));
    }

    public function getActorHomePage(): string
    {
        return $this->getIliasBaseUrl();
    }

    public function getTraxEndpoint(): string
    {
        return rtrim(trim($this->get('trax_endpoint', '')), '/');
    }

    public function setTraxEndpoint(string $endpoint): void
    {
        $this->set('trax_endpoint', rtrim(trim($endpoint), '/'));
    }

    public function getTraxUsername(): string
    {
        return trim($this->get('trax_username', ''));
    }

    public function setTraxUsername(string $username): void
    {
        $this->set('trax_username', trim($username));
    }

    public function getTraxPassword(): string
    {
        return $this->get('trax_password', '');
    }

    public function setTraxPassword(string $password): void
    {
        $this->set('trax_password', $password);
    }

    public function clearTraxPassword(): void
    {
        $this->set('trax_password', '');
    }

    public function getXapiVersion(): string
    {
        $version = trim($this->get('xapi_version', '1.0.3'));
        return $version !== '' ? $version : '1.0.3';
    }

    public function setXapiVersion(string $version): void
    {
        $version = trim($version);
        $this->set('xapi_version', $version !== '' ? $version : '1.0.3');
    }

    public function getHttpTimeout(): int
    {
        return max(2, min(120, (int) $this->get('http_timeout', '15')));
    }

    public function setHttpTimeout(int $seconds): void
    {
        $this->set('http_timeout', (string) max(2, min(120, $seconds)));
    }

    public function getBatchSize(): int
    {
        return max(1, min(100, (int) $this->get('batch_size', '25')));
    }

    public function setBatchSize(int $size): void
    {
        $this->set('batch_size', (string) max(1, min(100, $size)));
    }

    public function getMaxRetry(): int
    {
        return max(0, min(50, (int) $this->get('max_retry', '5')));
    }

    public function setMaxRetry(int $maxRetry): void
    {
        $this->set('max_retry', (string) max(0, min(50, $maxRetry)));
    }

    public function isTlsVerificationEnabled(): bool
    {
        return $this->getBool('tls_verify', true);
    }

    public function setTlsVerificationEnabled(bool $enabled): void
    {
        $this->setBool('tls_verify', $enabled);
    }

    public function getCaBundlePath(): string
    {
        return trim($this->get('ca_bundle_path', ''));
    }

    public function setCaBundlePath(string $path): void
    {
        $this->set('ca_bundle_path', trim($path));
    }

    public function isTraxConfigured(): bool
    {
        return $this->getTraxEndpoint() !== ''
            && $this->getTraxUsername() !== ''
            && $this->getTraxPassword() !== '';
    }

    public function getStatementsEndpoint(): string
    {
        $endpoint = $this->getTraxEndpoint();
        if ($endpoint === '') {
            return '';
        }
        if (preg_match('~/statements/?$~', $endpoint)) {
            return rtrim($endpoint, '/');
        }
        return $endpoint . '/statements';
    }

    public function setLastTraxTestResult(bool $success, int $httpStatus, string $message): void
    {
        $this->storeResult('last_trax_test', $success, $httpStatus, $message);
    }

    public function setLastTraxSendResult(bool $success, int $httpStatus, string $message): void
    {
        $this->storeResult('last_trax_send', $success, $httpStatus, $message);
    }

    public function setLastCronResult(bool $success, int $httpStatus, string $message): void
    {
        $this->storeResult('last_cron', $success, $httpStatus, $message);
    }

    /** @return array{at:string,success:string,http_status:string,message:string} */
    public function getLastResult(string $prefix): array
    {
        return [
            'at' => $this->get($prefix . '_at', ''),
            'success' => $this->get($prefix . '_success', ''),
            'http_status' => $this->get($prefix . '_http_status', ''),
            'message' => $this->get($prefix . '_message', ''),
        ];
    }

    private function storeResult(string $prefix, bool $success, int $httpStatus, string $message): void
    {
        $this->set($prefix . '_at', date('Y-m-d H:i:s'));
        $this->set($prefix . '_success', $success ? '1' : '0');
        $this->set($prefix . '_http_status', (string) $httpStatus);
        $this->set($prefix . '_message', substr($message, 0, 2000));
    }

    private function getBool(string $key, bool $default): bool
    {
        return $this->get($key, $default ? '1' : '0') === '1';
    }

    private function setBool(string $key, bool $value): void
    {
        $this->set($key, $value ? '1' : '0');
    }

    private function get(string $key, string $default): string
    {
        if ($this->settings === null) {
            return $default;
        }
        $value = $this->settings->get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    private function set(string $key, string $value): void
    {
        if ($this->settings !== null) {
            $this->settings->set($key, $value);
        }
    }
}
