# Fonctionnalité : Expense Tracker API

## Reformulation
Créer une API REST (Laravel) qui permet à un utilisateur d'enregistrer ses dépenses,
de les consulter (avec filtres par catégorie et par période) et de voir des statistiques
(total dépensé, nombre de dépenses, répartition par catégorie).

## Endpoints



1 GET     | `/api/expenses`       | Lister les dépenses (plus récentes d'abord) | `category`, `from`, `to` (optionnels)
2  POST    | `/api/expenses`       | Ajouter une dépense (réponse 201)      | `title`, `amount`, `category`, `date` (requis)
3.GET     | `/api/expenses/stats` | Statistiques globales et par catégorie | `category`, `from`, `to` (optionnels)

Exemple de réponse de `/api/expenses/stats` :

```json
{
  "total": 35.75,
  "count": 3,
  "by_category": [
    { "category": "food", "total": 30.25, "count": 2 },
    { "category": "transport", "total": 5.5, "count": 1 }
  ]
}
```

## Questions / hypothèses
- Pas d'authentification : l'API est mono-utilisateur pour cette première version.
- Un montant doit être strictement positif (min 0.01), avec 2 décimales max en base (`decimal(10,2)`).
- La catégorie est une chaîne libre (pas de table `categories` pour l'instant).
- Les filtres `from` / `to` sont inclusifs, et `to` doit être après ou égal à `from` (sinon 422).
- Les statistiques acceptent les mêmes filtres que la liste (ex : stats du mois en cours).


## Plan
1. `bootstrap/app.php` : enregistrer `routes/api.php` (sinon aucune route `/api/*` n'existe).
2. Migration `create_expenses_table` (avec un préfixe de date pour que Laravel l'ordonne correctement).
3. Modèle `Expense` + `ExpenseFactory` (pour les tests).
4. Service `App\Services\ExpenseTracker` : toute la logique (liste filtrée, création, stats).
   Je mets la logique dans un service plutôt que dans le contrôleur parce qu'une autre
   ressource (un autre contrôleur, une commande, un job…) peut avoir besoin de l'interroger.
5. Contrôleurs fins qui valident la requête puis appellent le service :
   - `ExpenseController` : `index` (liste) et `store` (création).
   - `StatisticController` : `index` (stats).
6. `routes/api.php` : 3 routes, dont un POST parce qu'on crée la ressource.
   La route `/expenses/stats` est déclarée avant les autres pour éviter un conflit
   le jour où on ajoutera `/expenses/{expense}`.
7. Tests PHPUnit (`tests/Feature/`) :
   - `ExpenseApiTest.php` : teste chaque endpoint par HTTP (création, validation, tri, filtres, stats).
   - `ExpenseTrackerTest.php` : valide la méthode choisie (logique dans le service, contrôleurs fins).
     - Test 1, le service étape par étape : base vide -> création -> tri -> filtre catégorie
       -> filtre période (bornes incluses) -> filtres combinés -> stats globales
       -> stats filtrées cohérentes avec la liste filtrée.
       Chaque étape a son propre message d'erreur pour savoir tout de suite laquelle échoue.
     - Test 2, les contrôleurs délèguent au service : le service est remplacé par un mock PHPUnit,
       on vérifie que les routes l'appellent avec les filtres validés, et qu'une requête
       invalide (422) n'appelle jamais `create()`.

## Lancer les tests

```bash
./vendor/bin/sail artisan test                              # tous les tests
./vendor/bin/sail artisan test --filter=ExpenseTrackerTest  # seulement le test étape par étape
```

## Difficultés
- La config de base de Laravel, pas trop comprise au départ -> j'ai pris le temps de comprendre.
- Les routes `/api/...` renvoyaient 404 -> depuis Laravel 11, `routes/api.php` n'est plus chargé
  par défaut : il faut l'ajouter dans `bootstrap/app.php` (ou lancer `php artisan install:api`).
- La route de stats pointait vers un contrôleur vide et une méthode qui n'existait pas
  (`stat` vs `stats`) -> la logique a été déplacée dans le service et `StatisticController` l'appelle.
- Migration sans date dans son nom -> renommée en `2026_10_22_000000_create_expenses_table.php`.
- `SUM()` renvoie une chaîne avec MySQL -> les totaux sont convertis en nombre dans le service.
- Premier lancement du test étape par étape : l'étape 8 échouait parce que le total attendu
  écrit dans le test était faux (755.5 au lieu de 798) -> c'est le test qu'il fallait corriger,
  pas le service. Leçon : toujours recalculer à la main les valeurs attendues.
- `ExampleTest` (`GET /`) échoue à cause des permissions sur `storage/` : dans le conteneur,
  l'utilisateur `sail` a l'UID 1337 alors que les fichiers appartiennent à l'UID 1000
  -> ajouter `WWWUSER=1000` et `WWWGROUP=1000` dans `.env`, puis `sail build --no-cache`.

## Bilan
- OK : création, liste filtrée et statistiques fonctionnent ; validation des entrées (erreurs 422 en JSON) ;
  9 tests PHPUnit (51 assertions) qui passent : 7 dans `ExpenseApiTest`, 2 dans `ExpenseTrackerTest`.
- Manque : modification / suppression d'une dépense (`PUT` / `DELETE`), authentification, pagination.
- Avec plus de temps : authentification avec Sanctum (une liste de dépenses par utilisateur),
  `apiResource` complet, pagination de la liste, table `categories`, stats par mois,
  API Resources (`ExpenseResource`) pour contrôler le format JSON.
