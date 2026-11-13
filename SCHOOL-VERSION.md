# Version de référence et correspondance

Observation publique du 13 novembre 2026 : le [catalogue de cours de lab.e-polytechnique.ma](https://lab.e-polytechnique.ma/course/index.php?lang=en) expose le numéro **2025100600.06** dans le paramètre `version` du lien « Get the mobile app ». La configuration JavaScript de cette même page indique le thème **classic**.

Le [fichier officiel public/version.php](https://github.com/moodle/moodle/blob/41a6ad6cd21cc2932b832de940a35830a975c6c8/public/version.php) du commit `41a6ad6cd21cc2932b832de940a35830a975c6c8` contient exactement :

```php
$version = 2025100600.06;
$release = '5.1+ (Build: 20251107)';
$branch = '501';
```

Ce commit officiel est celui installé dans notre environnement. Le suffixe `.06` représente un incrément de développement de la branche 5.1+, et ne signifie pas Moodle 5.1.6.

La version précédente de notre démonstration était Moodle 4.5.16. Sa base et son dossier de données ont été sauvegardés avant la mise à niveau, puis la migration vers 5.1+ a été exécutée avec l’outil CLI officiel de Moodle. L’environnement conserve son compte étudiant et utilise maintenant le thème Classic. Un quiz Moodle standard est installé dans un cours distinct.

La vérification de l’école s’est limitée à des pages et documents publics. Aucun compte de l’école n’a été utilisé et aucune modification n’y a été effectuée. Le numéro exposé n’identifie pas les plugins privés, les correctifs locaux éventuels, l’authentification, les contenus de cours ou les autres paramètres de l’établissement ; ils ne sont pas reproduits ici.

Les [prérequis officiels de Moodle 5.1](https://moodledev.io/general/releases/5.1) acceptent PHP 8.3 et PostgreSQL 16. Moodle 5.1 sert le dossier `public/` ; le plugin est donc monté dans `public/local/contentguard` et le fichier de configuration reste en dehors du document root.
