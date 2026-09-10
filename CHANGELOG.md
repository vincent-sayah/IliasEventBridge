# Changelog

## 0.1.2 — 2026-09-10

- Corrige les commandes POST des formulaires pour le format attendu par le contrôleur ILIAS 7 (`cmd[action]`).
- Rétablit l’enregistrement de la configuration, le chargement des cours et toutes les actions de diagnostic/outbox.

## 0.1.1 — 2026-09-10

- Corrige la version minimale ILIAS de `7.30.0` vers `7.30` afin qu’ILIAS 7.30 autorise l’activation du plugin principal et du compagnon cron.

## 0.1.0 — 2026-09-10

- Premier portage du plugin vers ILIAS 7.30.
- EventHook compatible avec les signatures non typées d’ILIAS 7.
- Statements xAPI pour l’entrée dans un cours, la consultation des ressources et les tests (`attempted`, `passed`, `failed`).
- Acteur xAPI identifié par le login ILIAS avec repli sur l’identifiant numérique.
- Outbox locale, reprise sur erreur, envoi manuel et diagnostic TRAX.
- Activation explicite par cours et par ressource.
- Compagnon CronHook pour collecter `read_event` et envoyer l’outbox automatiquement.
- Vérification TLS active par défaut avec bundle CA personnalisable.
