#!/usr/bin/env bash
set -euo pipefail

ILIAS_ROOT="${1:-/var/www/html/ilias}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="${SCRIPT_DIR}/../companion/IliasEventBridgeCron"
TARGET_DIR="${ILIAS_ROOT}/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron"

if [[ ! -d "${ILIAS_ROOT}/Services/Cron" ]]; then
    echo "Erreur : ${ILIAS_ROOT} ne ressemble pas à une installation ILIAS 7." >&2
    exit 1
fi

install -d -m 0755 "${TARGET_DIR}/classes" "${TARGET_DIR}/lang"
install -m 0644 "${SOURCE_DIR}/plugin.php.tpl" "${TARGET_DIR}/plugin.php"
install -m 0644 "${SOURCE_DIR}/classes/class.ilIliasEventBridgeCronPlugin.php.tpl" "${TARGET_DIR}/classes/class.ilIliasEventBridgeCronPlugin.php"
install -m 0644 "${SOURCE_DIR}/classes/class.ilIliasEventBridgeSendCron.php.tpl" "${TARGET_DIR}/classes/class.ilIliasEventBridgeSendCron.php"
install -m 0644 "${SOURCE_DIR}/lang/ilias_fr.lang.tpl" "${TARGET_DIR}/lang/ilias_fr.lang"
install -m 0644 "${SOURCE_DIR}/lang/ilias_en.lang.tpl" "${TARGET_DIR}/lang/ilias_en.lang"

echo "Compagnon installé dans ${TARGET_DIR}"
