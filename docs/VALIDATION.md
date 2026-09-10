# Validation fonctionnelle

## Test minimal

1. Activez le journal debug temporairement.
2. Activez un cours de test et toutes ses ressources.
3. Connectez-vous avec un compte apprenant, pas avec `anonymous`.
4. Entrez dans le cours.
5. Ouvrez un fichier ou un module.
6. Commencez un test puis terminez-le avec succès ou échec.
7. Revenez dans la configuration du plugin.
8. Cliquez sur **Collecter read_event**.
9. Contrôlez les statements `generated` dans l’outbox.
10. Cliquez sur **Envoyer l’outbox** et vérifiez le statut `sent`.
11. Contrôlez les mêmes statements dans TRAX.

Résultat attendu :

| Action | Verb xAPI attendu |
|---|---|
| entrée dans le cours | `initialized` |
| fichier ouvert/téléchargé | verbe personnalisé `downloaded` |
| module ouvert | `experienced` ou `launched` pour SCORM |
| test commencé | `attempted` |
| test réussi | `passed` avec `success=true` |
| test échoué | `failed` avec `success=false` |

## Point d’attention pour les tests ILIAS

L’événement `Services/Tracking/updateStatus` est émis lors d’un changement de statut. Si le même apprenant a déjà terminé le test, réinitialisez son résultat ou utilisez un nouvel apprenant avant de recommencer le scénario.

## Diagnostic

Si aucune consultation n’apparaît :

```bash
cd /var/www/html/ilias
grep -R "IliasEventBridge" data/*/logs 2>/dev/null | tail -50
journalctl -u php-fpm -n 100 --no-pager
journalctl -u httpd -n 100 --no-pager
```

Contrôlez ensuite :

- le plugin principal est installé, actif et activé dans sa configuration ;
- le cours et la ressource sont activés ;
- le compagnon CronHook et son job sont actifs ;
- TRAX répond au bouton **Tester TRAX** ;
- la table native `read_event` contient une ligne récente pour l’utilisateur et l’objet ;
- SELinux autorise la connexion réseau sortante d’Apache/PHP.

Après validation, désactivez le journal debug pour limiter le volume de données.
