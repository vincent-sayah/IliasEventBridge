# Installation sur ILIAS 7.30 / AlmaLinux 8

Le root ILIAS utilisé ici est `/var/www/html/ilias` et l’URL de test est `http://192.168.56.53`.

## 1. Préparer les dépendances

```bash
dnf install -y git php-curl
php -v
php -m | grep -i curl
```

ILIAS 7.30 a été publié pour PHP 7.3. Utilisez la même version PHP que celle déjà validée par votre installation ILIAS 7.

## 2. Télécharger le plugin principal

```bash
cd /var/www/html/ilias
mkdir -p Customizing/global/plugins/Services/EventHandling/EventHook
cd Customizing/global/plugins/Services/EventHandling/EventHook
git clone https://github.com/vincent-sayah/IliasEventBridge.git
```

Pour mettre à jour une installation déjà clonée :

```bash
cd /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge
git pull --ff-only origin main
```

## 3. Installer le compagnon CronHook

```bash
cd /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge
bash scripts/install_cron_companion.sh /var/www/html/ilias
```

Le script crée :

```text
/var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron
```

## 4. Appliquer les droits et le contexte SELinux

```bash
chown -R root:apache \
  /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge \
  /var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron

find /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge -type d -exec chmod 755 {} \;
find /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge -type f -exec chmod 644 {} \;
find /var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron -type d -exec chmod 755 {} \;
find /var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron -type f -exec chmod 644 {} \;

restorecon -Rv /var/www/html/ilias/Customizing/global/plugins
systemctl restart php-fpm httpd
```

Le dépôt Git doit rester possédé par `root` si les mises à jour sont exécutées avec le compte `root`. Apache peut lire le plugin grâce aux droits `755` sur les répertoires et `644` sur les fichiers.

Si SELinux interdit la connexion HTTP sortante de PHP/Apache vers TRAX :

```bash
setsebool -P httpd_can_network_connect 1
```

## 5. Vérifier la syntaxe sur la VM

```bash
cd /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge
find classes sql -type f -name '*.php' -exec php -l {} \;
php -l plugin.php
php tests/statement_factory_test.php

cd /var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron
find . -type f -name '*.php' -exec php -l {} \;
```

Tous les lints doivent afficher `No syntax errors detected`. Le test doit afficher `OK: statement factory tests passed`.

## 6. Installer et activer dans ILIAS

Dans ILIAS 7 :

1. connectez-vous avec un compte administrateur ;
2. ouvrez **Administration → Extending ILIAS → Plugins** ;
3. trouvez `IliasEventBridge` dans **Services / EventHandling / EventHook** ;
4. cliquez sur **Installer**, puis **Activer** ;
5. trouvez `IliasEventBridgeCron` dans **Services / Cron / CronHook** ;
6. cliquez sur **Installer**, puis **Activer**.

L’installation du plugin principal crée les tables :

- `evnt_evhk_ileb_log` ;
- `evnt_evhk_ileb_out` ;
- `evnt_evhk_ileb_read` ;
- `evnt_evhk_ileb_ccfg` ;
- `evnt_evhk_ileb_rcfg` ;
- `evnt_evhk_ileb_dlog`.

## 7. Configurer le plugin principal

Dans l’action **Configurer** de `IliasEventBridge` :

1. renseignez `URL de base ILIAS` avec `http://192.168.56.53` ;
2. renseignez l’endpoint TRAX, l’identifiant et le mot de passe xAPI ;
3. conservez `Version xAPI = 1.0.3` ;
4. conservez la vérification TLS activée en HTTPS ;
5. activez le plugin, la génération de statements et le compagnon cron ;
6. enregistrez ;
7. utilisez **Tester TRAX**.

Le mot de passe laissé vide lors d’une modification conserve la valeur existante.

## 8. Activer un cours

Dans la même page :

1. saisissez le `ref_id` du cours ;
2. cliquez sur **Charger le cours** ;
3. cochez **Activer les traces pour ce cours** ;
4. sélectionnez les ressources souhaitées ou cliquez sur **Tout activer**.

Le `ref_id` apparaît dans l’URL ILIAS, par exemple `ref_id=123`.

## 9. Activer le job cron ILIAS

Dans la gestion des jobs cron ILIAS :

1. recherchez `IliasEventBridge — collecte et envoi TRAX` ;
2. activez-le ;
3. choisissez un intervalle de 5 minutes pour le test.

Le cron système d’ILIAS doit lui-même être planifié. Vérifiez la commande déjà utilisée par votre installation ILIAS ; ne créez pas un second cron concurrent.

## 10. Mise à jour ultérieure

```bash
cd /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge
git pull --ff-only origin main
bash scripts/install_cron_companion.sh /var/www/html/ilias
chown -R root:apache \
  /var/www/html/ilias/Customizing/global/plugins/Services/EventHandling/EventHook/IliasEventBridge \
  /var/www/html/ilias/Customizing/global/plugins/Services/Cron/CronHook/IliasEventBridgeCron
restorecon -Rv /var/www/html/ilias/Customizing/global/plugins
systemctl restart php-fpm httpd
```

Dans ILIAS, utilisez **Mettre à jour** sur chaque plugin seulement lorsque sa version a changé.
