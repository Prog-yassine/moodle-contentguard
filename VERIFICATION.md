# Vérification du prototype

Vérifié le 13 novembre 2026 sur un vrai Moodle 4.5.16, PHP 8.3.36, PostgreSQL 16 et Chromium.

- Construction de l’image PHP légère : réussie.
- Installation initiale du schéma Moodle dans une base vide : réussie via `./start.sh`.
- Deuxième exécution du script : réussie, un seul cours GUARD-DEMO, aucun doublon.
- Syntaxe des 12 fichiers PHP du plugin : valide.
- Syntaxe du JavaScript : valide.
- Configuration Compose et syntaxe du script shell : valides.
- Tests navigateur sur cette installation neuve : **32/32 réussis**, aucune erreur JavaScript non gérée.
- Affichage grand écran (1440 px) et mobile (390 px) : pas de débordement horizontal.

Les tests couvrent le filigrane, les gestionnaires de copie/menu contextuel/raccourcis, la saisie au clavier, les champs éditables imbriqués, l’absence de protection sur le login et l’administration, les exceptions administrateur, et la désactivation/réactivation depuis les réglages Moodle.

Les résultats détaillés sont dans `artifacts/browser-results.json`. Les captures `demo-desktop.png` et `demo-mobile.png` montrent le filigrane sur la leçon. La capture d’écran elle-même reste possible, conformément aux limites documentées.

Les événements simulés ne prouvent pas l’interception de toutes les commandes système. Safari, Firefox, les autres versions de Moodle, les technologies d’assistance et les clients mobiles natifs n’ont pas été validés dans cette session. Aucun hébergement public permanent n’a été créé.

## Accès public temporaire

Le même Moodle a ensuite été exposé via HTTPS. Les 32 contrôles navigateur ont de nouveau réussi sur l’adresse publique, y compris la connexion, la copie, le filigrane et les réglages administrateur. Les cookies de session portent les attributs Secure et HttpOnly. Le lien temporaire figure dans le fichier local PUBLIC-DEMO.md, exclu du dépôt Git.

## Mise à niveau vers la version de référence de l’école

Version installée : **Moodle 5.1+ (Build: 20251107), 2025100600.06**, thème Classic. Le numéro correspond à celui exposé par le catalogue public de l’école ; voir SCHOOL-VERSION.md. Sauvegardes réalisées avant migration, puis mise à niveau officielle réussie depuis 4.5.16.

Quiz natif : deux QCM, un vrai/faux et une réponse courte, note sur 10, tentatives illimitées. Le seed a été exécuté deux fois sans doublon. Le parcours complet a passé **17/17 contrôles locaux** dans Chromium, avec émulation mobile puis fenêtre ordinateur : connexion, champs natifs, filigrane, exceptions de saisie, soumission, état terminé et note 10/10. Les deux tentatives QA ont été supprimées via quiz_delete_attempt() ; le compte étudiant commence sans tentative.

Un ajustement SCSS local du thème Classic masque les débordements des panneaux hors écran sur les petits écrans et contraint la barre fixe à la largeur du viewport. Le code officiel Moodle n’a pas été modifié. Les captures sont dans artifacts/quiz-mobile.png et artifacts/quiz-desktop.png ; résultats dans artifacts/quiz-results.json. L’émulation ne constitue pas une validation native de Safari sur iPhone.

L’ancien tunnel a cessé de répondre. Une nouvelle adresse HTTPS avait été communiquée dans la conversation ; les liens temporaires ne sont pas publiés dans le dépôt Git. Une connexion étudiant neuve vers le quiz y a été validée, avec cookie Secure/HttpOnly et filigrane visible ; voir artifacts/mobile-auth-results.json. Le parcours complet de notation a été validé localement, et non répété sur le nouveau tunnel.

## Calendrier Moodle

Un événement de cours a été créé pour le 13 novembre 2026, toute la journée (UTC), avec un lien vers le quiz. Deux exécutions du seed ont conservé le même ID sans doublon. Une connexion étudiant neuve en mode mobile a confirmé la visibilité du lien « Ouvrir le test » dans le calendrier public HTTPS. Les dates d’ouverture et fermeture du quiz restent à zéro (aucune restriction). Résultat : artifacts/calendar-results.json.

## Marquage indicatif pour assistants IA — version 0.2.0

La nouvelle option a été activée sur la démo. Les parcours public HTTPS mobile émulé et ordinateur ont passé 21/21 contrôles : quatre avertissements visibles, quatre filigranes répétés contenant le pseudonyme, champs de réponse utilisables, note 10/10, absence du marquage sur la page de présentation et la correction. Les seules tentatives QA créées pour cette vérification (IDs 4 et 5) ont été supprimées via l’API Moodle ; les autres tentatives sont conservées. Les fichiers PHP et JavaScript passent les vérifications de syntaxe. Aucun service IA n’a été appelé ; ces contrôles ne prouvent pas qu’un assistant externe refuse de répondre à une capture.
