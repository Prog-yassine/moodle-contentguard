# Revue de Screenshot-PrivacyProtectionWeb

Revue du 13 novembre 2026, dépôt demandé par l’utilisateur : https://github.com/dxvzz/Screenshot-PrivacyProtectionWeb

Révision observée : `cf4e93ca7ff67097bfc4929fed83ee93b30cde88`.

## Conclusion

Le projet propose un écran de masquage, des interceptions de raccourcis et des heuristiques de focus/visibilité. Il n’apporte pas de blocage général des captures système iPhone/Android et ne contraint pas les assistants IA recevant une image. Son intégration telle quelle dans le quiz n’est pas recommandée : elle peut interrompre des actions normales et multiplier les timers. Aucune dépendance issue de ce dépôt n’a été ajoutée au plugin Moodle.

## Code examiné

- [app/layout.tsx](https://github.com/dxvzz/Screenshot-PrivacyProtectionWeb/blob/cf4e93ca7ff67097bfc4929fed83ee93b30cde88/app/layout.tsx) : interception de touches, `preventDefault()`, écran noir, écoute de `blur` et `visibilitychange`. Ces événements ne prouvent pas une capture. Un raccourci intercepté par le système peut ne jamais arriver à la page.
- Le même fichier enregistre des contrôles à 1, 10 et 100 ms, initialise plusieurs fois le système et réinitialise encore lors des mutations du DOM. Les timers et certains gestionnaires anonymes peuvent s’accumuler. La réécriture répétée de `window.onkeydown` enveloppe aussi les gestionnaires précédents. Les durées demandées restent soumises aux limitations du navigateur.
- [hooks/use-robust-protection.ts](https://github.com/dxvzz/Screenshot-PrivacyProtectionWeb/blob/cf4e93ca7ff67097bfc4929fed83ee93b30cde88/hooks/use-robust-protection.ts) : tentative de lecture du presse-papiers toutes les deux secondes. Elle dépend des permissions et restrictions du navigateur. La présence d’une image ne prouve ni sa provenance ni une capture du quiz ; elle ne permet pas d’annuler une capture déjà faite.
- [app/globals.css](https://github.com/dxvzz/Screenshot-PrivacyProtectionWeb/blob/cf4e93ca7ff67097bfc4929fed83ee93b30cde88/app/globals.css) : sélection désactivée globalement et styles `@media print`. Ces styles affectent la sélection et l’impression, sans empêcher une capture système. Leur portée globale gênerait certains usages et outils d’assistance du quiz.
- Les messages « screenshot blocked », « recorded and reported » et les compteurs de confiance ne constituent pas une preuve de blocage ou de signalement effectif. Le code examiné utilise notamment des états locaux et la console.

## Test isolé réalisé

Le script inline exact de `app/layout.tsx` a été chargé dans une page locale contenant un titre et un champ de réponse fictifs, avec un contexte Chromium neuf. La démo Next/React complète n’a pas été installée. Ce n’est pas un test natif Safari, Android ou iOS, ni une simulation des touches physiques du téléphone.

- Page au premier plan : contenu visible, aucun écran noir.
- Capture automatisée du navigateur : réussie ; le contenu reste visible et aucun écran noir n’est déclenché.
- Événement `blur` ordinaire simulé : écran noir affiché, sans prise de capture.
- Au relevé initial : 18 enregistrements de timers périodiques, dont 6 demandant 1 ms ; après le masquage, 21 et 7 respectivement. Ce sont des enregistrements observés, pas une mesure de fréquence réelle ou de consommation CPU.

Les éléments de test restent dans `runtime/library-review/`, exclus des archives de distribution. Le Moodle public et ses données n’ont pas été modifiés pour ce test.

## Distribution et licence

Le README présente une API `PrivacyProtect.enable()` et des fichiers `privacy-protect.js`/`index.html`. L’arborescence examinée est une démo Next/React et ne contient pas ces fichiers ni la distribution CDN annoncée. `package.json` est nommé `my-v0-project` et porte `private: true`. La requête publique `npm view screenshot-privacy-protect` a renvoyé `E404` pendant cette revue. Cela décrit l’état observé, pas une garantie sur une publication future.

La [licence MIT](https://github.com/dxvzz/Screenshot-PrivacyProtectionWeb/blob/cf4e93ca7ff67097bfc4929fed83ee93b30cde88/LICENSE) autorise la réutilisation et la vente, à condition de conserver la notice de copyright et la licence pour les copies ou portions substantielles réutilisées. Elle peut être respectée dans une distribution GPL de notre plugin.

Une adaptation ciblée du masquage de confidentialité ou des styles d’impression pourrait être envisagée séparément, avec des options, des exemptions pour les champs de réponse et des libellés factuels. Elle ne devrait pas être commercialisée comme une garantie anti-capture ou anti-réponse par IA.
