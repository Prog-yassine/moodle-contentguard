# ContentGuard 0.2.0 — prototype Moodle

Plugin local pour Moodle 4.5 et versions compatibles ultérieures, sous GNU GPL v3 ou ultérieure. Vérifications effectuées sur Moodle 4.5.16 et Moodle 5.1+ (Build: 20251107).

ContentGuard propose un filigrane personnalisé et des restrictions facultatives de sélection, de copie, de clic droit et de raccourcis courants d’inspection. **Il ne peut pas garantir le blocage des captures d’écran, de l’extraction du contenu ou des outils de développement. Les NFT ne bloquent pas les captures et ce plugin n’utilise aucune blockchain.**

## Marquage destiné aux assistants IA

L’option « Marquage visible destiné aux assistants IA », désactivée par défaut sur une installation neuve, ajoute pendant les tentatives de quiz uniquement un avertissement français/anglais en haut de chaque question et un filigrane répété dans son contenu. Le message demande une aide méthodologique plutôt que les réponses et inclut le pseudonyme existant du compte/cours et la date UTC. Il n’affiche ni nom ni e-mail. Cette option est activée sur notre quiz de démonstration.

Le marquage est une information visuelle susceptible d’être lue par OCR ou par un modèle multimodal. **Ce n’est pas un mécanisme de contrôle des assistants IA** : il n’existe ici aucun accord avec leurs fournisseurs ni protocole universel obligeant au refus. Un assistant peut ignorer le message, et un recadrage, une retouche ou une transcription peut le supprimer. Le plugin ne contacte aucun service IA et ne mesure pas un taux de refus. Aucun refus automatique n’est revendiqué ou validé par les tests.

Les pages de cours, l’écran d’accueil du quiz et sa correction ne sont pas étiquetés comme une évaluation en cours. Les exemptions de rôle restent applicables. Pour l’activer sur une autre instance, activer d’abord ContentGuard, sélectionner le cours puis cocher cette nouvelle option dans ses réglages.

## Installation

1. Copier ce dossier dans `public/local/contentguard` sur Moodle 5.1, ou dans `local/contentguard` sur Moodle 4.5.
2. Lancer la mise à niveau Moodle depuis les notifications d’administration ou `php admin/cli/upgrade.php --non-interactive`.
3. Ouvrir **Administration du site → Plugins → Plugins locaux → ContentGuard**.
4. Activer le plugin (désactivé par défaut), puis choisir les cours et les options. Sans cours sélectionné, tous les cours compatibles sont inclus.
5. Ouvrir `/local/contentguard/demo.php?courseid=ID` avec un compte inscrit dans le cours.

Les réglages s’appliquent au prochain chargement de page. Aucun réglage n’est modifiable par l’URL de démonstration. L’activation sur la page de démonstration est volontaire, y compris pour l’administrateur et les enseignants, afin qu’ils puissent tester les restrictions.

## Périmètre

- Connexion obligatoire et accès au cours vérifié par Moodle.
- Sur les pages ordinaires, les administrateurs et les rôles disposant de `local/contentguard:bypass` sont exemptés. Les archétypes manager, enseignant et enseignant non éditeur disposent de cette capacité par défaut.
- Pages prises en charge : vue du cours, Page, Livre, Leçon et vues/tentatives/relectures de tests. Les autres types d’activités ne sont pas couverts dans ce prototype.
- La protection s’applique à `#region-main`. Dans la démo, seule la zone `#local-contentguard-demo` est protégée.
- L’administration, la connexion et les pages d’édition sont exclues.
- Les champs, zones modifiables, liens et boutons sont exemptés des interceptions. Les champs de réponse restent utilisables.
- Les contenus d’iframes, documents PDF, lecteurs externes, applications mobiles natives et fichiers téléchargés ne sont pas protégés par ce mécanisme.
- Aucun contrôle d’accès Moodle n’est remplacé. Les autorisations serveur restent indispensables.

## Filigrane et données

Le filigrane affiche `CG-` suivi d’un pseudonyme HMAC propre au compte et au cours, ainsi que la date UTC. Un secret aléatoire propre au plugin est généré à l’installation et conservé dans la configuration Moodle ; aucun nom ni e-mail n’est exposé. Le plugin ne crée aucune table, n’enregistre pas les événements de navigation et ne contacte aucun service externe. Le pseudonyme est calculé en mémoire. Il ne constitue pas une preuve de fuite ou de fraude et peut être retiré ou modifié côté client.

Les dates changent au prochain chargement de page. Le filigrane n’est pas incrusté dans les fichiers téléchargés. Une capture ordinaire inclut le filigrane visible, mais les modifications du DOM, l’OCR, les photographies et d’autres méthodes permettent de contourner la protection.

## Accessibilité et limites

Tester les restrictions avec les outils d’assistance utilisés par les apprenants. La restriction de sélection peut gêner les outils de lecture ou de traduction ; elle peut être désactivée indépendamment du filigrane. La navigation au clavier et la saisie ne doivent pas être bloquées.

L’interception de F12 et des raccourcis ne désactive pas les outils de développement. Les raccourcis réservés au navigateur ou au système peuvent ne pas atteindre la page. Aucun événement ne permet de détecter toutes les captures d’écran. Pour un examen contrôlé, étudier l’intégration Safe Exam Browser déjà présente dans Moodle.

## Technique

PHP natif Moodle ; JavaScript sans compilation ; aucune dépendance externe. `db/hooks.php` enregistre le hook `before_standard_head_html_generation`, qui initialise les ressources via `lib.php` et l’API `$PAGE->requires`. Le callback historique conservé dans `lib.php` est automatiquement ignoré par Moodle lorsque le hook moderne est enregistré. Le CSS est automatiquement agrégé par Moodle. Purger les caches après modification du plugin.

Ce prototype n’est pas encore une version commerciale finalisée. La compatibilité avec les versions de Moodle, les navigateurs, les thèmes, les types d’activités et les outils d’assistance doit être validée avant diffusion.

## Licence

Copyright 2026. Ce plugin est distribué sous GNU General Public License version 3 ou, à votre choix, toute version ultérieure. Il est fourni sans garantie. Voir `COPYING.txt` pour le texte de la licence.
