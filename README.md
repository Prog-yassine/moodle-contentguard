# moodle-contentguard

ContentGuard — laboratoire Moodle.

Source du plugin Moodle ContentGuard et de son environnement Docker de démonstration. Lancer `./start.sh` pour générer les identifiants locaux et préparer Moodle. Les fichiers `runtime/`, `artifacts/` et `.env` sont créés localement et exclus du dépôt Git ; les résultats et captures référencés ci-dessous sont des fichiers générés. L’hébergement temporaire de la conversation n’est pas inclus dans ce dépôt.

Prototype fonctionnel de protection dissuasive des contenus, installé sur un **vrai Moodle 5.1+ (Build: 20251107)**, numéro **2025100600.06**, PHP 8.3 et PostgreSQL 16. Cette révision et le thème **Classic** correspondent aux informations publiques de `lab.e-polytechnique.ma`. L’environnement utilise ses propres comptes et un vrai quiz standard en français. Voir `SCHOOL-VERSION.md` pour les preuves et le périmètre de cette correspondance.

Un NFT atteste une propriété ou une provenance ; il ne bloque pas les captures d’écran. Ce prototype utilise un **filigrane visible pseudonymisé**, et des restrictions de copie/clic droit/raccourcis configurables. Il n’utilise ni blockchain ni DRM et ne garantit pas d’empêcher les captures, l’inspection, l’OCR ou une photo extérieure. Le filigrane reste visible sur les captures ordinaires, mais il peut être retiré avec les outils du navigateur.

## Démarrer le laboratoire

Prérequis : Docker avec Compose v2, `curl`, `tar`, Python 3 et accès à Internet. Script prévu pour Linux ou WSL2. Sur macOS, définir `CONTENTGUARD_CA_FILE` vers un fichier de certificats CA PEM disponible avant le démarrage.

Depuis le dossier du projet :

```sh
./start.sh
```

Le premier lancement télécharge le code officiel Moodle à un commit fixé, construit une petite image PHP et initialise la base. Il crée un cours de démonstration, un cours avec un quiz normal de quatre questions, un compte étudiant et des mots de passe aléatoires dans `.env` (ignoré par Git). Les lancements suivants conservent la base, les cours et les tentatives ; le compte étudiant et les réglages du cours de démonstration sont réinitialisés aux valeurs locales de `.env`.

Ouvrir **http://localhost:8080** sur l’ordinateur où tourne Docker. Cette adresse n’est pas un site public. Identifiant étudiant : **etudiant**. Le mot de passe est affiché à la fin du démarrage. Pour le réafficher :

```sh
python3 scripts/credentials.py
```

Le lien du **quiz standard** est enregistré dans `runtime/quiz.json`. Dans l’instance actuelle, il se trouve à `/mod/quiz/view.php?id=4` ; utiliser le lien généré dans le JSON sur une autre installation. Le quiz contient deux QCM, un vrai/faux et une réponse courte, sans minuterie, avec tentatives illimitées et une note sur 10 après soumission. Le banc d’essai technique précédent reste disponible ; ses liens sont dans `runtime/seed.json`.

Un événement de cours « Test de connaissances » figure aussi dans le calendrier Moodle, sur toute la journée de sa création, avec un lien direct vers le quiz. Le script `scripts/seed-calendar.php` conserve sa date sur les lancements suivants et évite les doublons. Le lien et la date sont enregistrés dans `runtime/calendar.json`. Cet événement ne fixe ni date limite ni fermeture du quiz.

Le compte étudiant permet de tester le blocage dans les zones de lecture. Pour obtenir l’accès administrateur :

```sh
python3 scripts/credentials.py --admin
```

Les réglages se trouvent dans **Administration du site → Plugins → Plugins locaux → ContentGuard**, ou à `/admin/settings.php?section=local_contentguard`. Sur les pages de cours, les administrateurs et enseignants sont exemptés. La page de démonstration applique explicitement la protection à ces comptes pour permettre le test.

## Essayer le plugin

1. Se connecter comme étudiant et ouvrir le quiz normal indiqué dans `runtime/quiz.json`.
2. Essayer de sélectionner/copier le texte et d’ouvrir son menu contextuel.
3. Donner le focus à la zone de lecture et essayer F12 ou les raccourcis d’inspection.
4. Choisir les réponses et remplir la réponse courte : les champs restent libres. Terminer la tentative et confirmer l’envoi pour voir la note et la correction.
5. Faire une capture : elle est possible et contient le filigrane. C’est la limite attendue du produit.
6. Dans les réglages administrateur, désactiver une restriction ou le plugin, enregistrer puis recharger la démo.

Les comportements des raccourcis varient selon le navigateur et le système. Ces restrictions JavaScript sont contournables. Les documents téléchargés, contenus dans des iframes et clients Moodle mobiles natifs ne sont pas protégés par cette version.

## Plugin installable séparément

Le code est dans `plugin/local/contentguard/`. L’archive `contentguard-plugin.zip` contient le dossier `contentguard/` attendu par Moodle. Sur une autre installation compatible, installer ce ZIP depuis l’administration ou copier ce dossier dans **`public/local/contentguard` pour Moodle 5.1**, puis lancer la mise à niveau Moodle. Sur Moodle 4.5, le dossier reste `local/contentguard`. Le serveur web de Moodle 5.1 doit servir le sous-dossier `public/`, ce que fait cette configuration Docker.

Le plugin est **désactivé par défaut** sur une installation neuve. Activer ensuite ses options et sélectionner les cours à protéger. Une sélection de cours vide signifie tous les cours, pas zéro cours.

Le filigrane utilise un HMAC fondé sur l’utilisateur, le cours et un secret aléatoire propre au plugin. Il n’affiche ni nom ni email. Il reste une donnée pseudonymisée ; ce prototype n’ajoute pas de journal de surveillance ni de table de suivi. Moodle conserve ses propres données et journaux habituels.

## Vérification navigateur

Les tests dans `scripts/browser-test.cjs` se connectent à Moodle, exercent les restrictions, vérifient les exceptions de saisie et le rôle administrateur, et enregistrent des captures grand écran/mobile dans `artifacts/`.

`scripts/check-quiz.cjs` vérifie le quiz standard sur ordinateur et mobile : connexion, saisie, choix des réponses, soumission et calcul de la note. Les résultats sont dans `artifacts/quiz-results.json`. Le script utilise des tentatives de test réelles ; leurs IDs sont conservés pour permettre leur suppression ciblée via l’API Moodle avant une nouvelle démonstration.

Pour les relancer avec Node et Playwright installés localement :

```sh
npm install --no-save --package-lock=false playwright
CONTENTGUARD_CHROMIUM=/chemin/vers/chromium node scripts/browser-test.cjs
```

Le navigateur Chromium doit déjà être installé. Les mots de passe sont lus dans `.env` et masqués dans les résultats. Les événements simulés vérifient les gestionnaires du plugin ; ils ne prouvent pas qu’un raccourci est interceptable sur tous les systèmes.

## Arrêter ou reprendre

```sh
docker compose stop
docker compose up -d
```

Les données sont conservées dans les volumes `postgres_data` et `moodle_data`. Garder ensemble la base et le dossier de données pour une sauvegarde. Ne pas employer `docker compose down -v` sans vouloir supprimer les données.

## Hébergement permanent

Ce laboratoire écoute uniquement sur `127.0.0.1:8080` et utilise le serveur de développement PHP. Ce n’est pas une configuration de production. Un hébergement permanent nécessitera un VPS ou un hébergement Moodle permettant les plugins, HTTPS, un serveur web de production, une tâche cron Moodle et des sauvegardes. L’hébergement Sites disponible dans cette session exécute des Workers JavaScript et ne peut pas héberger directement ce Moodle PHP.

Avant une vente : définir les versions et navigateurs pris en charge, tester l’accessibilité et les mises à jour, fixer le support et rédiger des conditions correspondant aux limites réelles. Pour des examens verrouillés, examiner l’intégration Safe Exam Browser déjà disponible dans Moodle.

## Licence et versions

Plugin et scripts originaux : **GPL-3.0-or-later**, voir `plugin/local/contentguard/COPYING.txt`. La vente est permise ; les destinataires conservent leurs droits GPL. Moodle et les images tierces gardent leurs licences respectives.

- Moodle : 5.1+ (Build: 20251107), `2025100600.06`, commit officiel `41a6ad6cd21cc2932b832de940a35830a975c6c8`.
- Plugin : 0.2.0, `2026111302`.
- PHP : paquets PHP 8.3 d’Alpine 3.22 ; les correctifs exacts sont ceux disponibles à la construction.
- PostgreSQL : branche 16 Alpine.

Ce dépôt ne contient pas les images Docker, le code Moodle téléchargé ni les identifiants. Le démarrage les prépare localement.
