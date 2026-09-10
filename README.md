# IliasEventBridge pour ILIAS 7.30

`IliasEventBridge` est le portage ILIAS 7 du plugin `IliasTraxEventBridge` développé pour ILIAS 10. Il transforme des activités pédagogiques ILIAS en statements xAPI 1.0.3, les place dans une outbox locale, puis les envoie vers TRAX 3 LRS.

## Périmètre de la version 0.1.0

| Signal ILIAS 7 | Statement xAPI |
|---|---|
| Entrée dans un cours | `initialized` |
| Consultation d’un fichier, module, SCORM, wiki, blog, forum, MediaCast, lien web ou exercice | verbe adapté à la ressource |
| Test commencé | `attempted` |
| Test réussi | `passed` |
| Test échoué | `failed` |

Le plugin conserve les garanties du bridge d’origine :

- activation explicite par cours et par ressource ;
- journal debug optionnel ;
- journal optionnel des traces refusées ;
- outbox locale avec batch et nouvelles tentatives ;
- test de connexion et envoi manuel depuis l’administration ;
- aucune panne TRAX ne bloque la navigation dans ILIAS ;
- acteur xAPI identifié par `actor.account.name` avec le login ILIAS ;
- vérification TLS activée par défaut.

## Pourquoi deux composants ?

ILIAS 7 ne permet pas à un plugin EventHook de fournir directement un job cron. Le dépôt contient donc :

1. le plugin principal `IliasEventBridge`, installé dans `Services/EventHandling/EventHook` ;
2. le compagnon `IliasEventBridgeCron`, généré dans `Services/Cron/CronHook` par le script d’installation.

Le compagnon lit la table native `read_event`, convertit les nouvelles consultations et envoie l’outbox. Aucune modification du cœur d’ILIAS n’est nécessaire.

## Installation rapide

Sur le serveur ILIAS :

```bash
cd /var/www/html/ilias
mkdir -p Customizing/global/plugins/Services/EventHandling/EventHook
cd Customizing/global/plugins/Services/EventHandling/EventHook
git clone https://github.com/vincent-sayah/IliasEventBridge.git
cd IliasEventBridge
bash scripts/install_cron_companion.sh /var/www/html/ilias
chown -R apache:apache \
  /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge \
  /var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron
find /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge -type d -exec chmod 755 {} \;
find /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge -type f -exec chmod 644 {} \;
find /var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron -type d -exec chmod 755 {} \;
find /var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron -type f -exec chmod 644 {} \;
restorecon -Rv /var/www/html/ilias/Customizing/global/plugins
systemctl restart php-fpm httpd
```

Installez et activez ensuite les deux plugins dans l’administration ILIAS, configurez TRAX dans le plugin principal, puis activez le job `IliasEventBridge — collecte et envoi TRAX`.

La procédure détaillée est dans [docs/INSTALLATION.md](docs/INSTALLATION.md). La validation fonctionnelle est dans [docs/VALIDATION.md](docs/VALIDATION.md).

## Compatibilité

- ILIAS : 7.30.x
- PHP : 7.3, 7.4 et 8.3 vérifiés par la CI
- xAPI : 1.0.3
- LRS cible : TRAX 3
- base de données : API `ilDB` d’ILIAS, sans SQL spécifique à MariaDB/PostgreSQL

## Limites connues

- ILIAS 7 n’émet pas d’EventHook lors de chaque simple ouverture : ces accès sont récupérés via `read_event` au prochain passage du cron.
- Un même objet référencé dans plusieurs cours peut être attribué à sa première référence de dépôt ; activez la référence voulue et contrôlez le statement dans l’outbox.
- Le suivi vidéo MediaCast détaillé (lecture d’une vidéo précise) et les statements par question de test ne font pas partie de cette première version ILIAS 7 ; l’accès à l’objet MediaCast et le résultat global du test sont couverts.
- Le mot de passe TRAX est stocké dans les réglages ILIAS ; protégez l’accès à la base et réservez la configuration aux administrateurs.

## Licence

GPL-3.0-or-later.
