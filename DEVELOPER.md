# Developer Guide — ExamApp V3

## Objectif

Ce document décrit les règles de développement à respecter dans ExamApp V3.

Le but est de garder un code :

- stable
- lisible
- sécurisé
- performant
- cohérent avec l’architecture actuelle

---

## Stack cible

- PHP 8+
- MariaDB / MySQL
- PDO
- MVC léger
- Composer PSR-4
- Bootstrap local
- Bootstrap Icons local
- JavaScript vanilla

---

## Philosophie du projet

ExamApp V3 n’utilise pas de framework lourd.

Le projet privilégie :

- contrôle total du code
- simplicité d’exploitation
- rapidité de maintenance
- faible empreinte serveur

Chaque ajout doit être évalué selon ces critères.

---

## Structure à respecter

```text
src/
  Core/
  Controllers/
  Services/
  Views/
config/
database/
public/
scripts/
````

### Répartition des responsabilités

#### `Core`

Infrastructure technique :

* routing
* request / response
* DB
* CSRF
* sessions
* config

#### `Controllers`

Gestion HTTP uniquement :

* lire la requête
* vérifier les droits
* appeler un service
* rendre une vue ou rediriger

#### `Services`

Logique métier :

* règles fonctionnelles
* coordination base de données
* traitements applicatifs

#### `Views`

Rendu HTML uniquement.

---

## Règles de code

### 1. Contrôleurs légers

Un contrôleur ne doit pas contenir :

* de SQL
* de logique métier complexe
* de calculs lourds

Il doit rester lisible et court.

### 2. Services explicites

Un service doit exprimer une intention métier claire.

Exemples de bons noms :

* `toggleStudentActive`
* `allowClassLogin`
* `forceLogoutStudent`
* `paginateStudents`

### 3. SQL explicite

Les requêtes doivent être :

* lisibles
* préparées
* ciblées
* index-friendly

### 4. Pas de logique métier dans les vues

Les vues peuvent faire :

* affichage conditionnel simple
* boucles
* petits formats de rendu

Les vues ne doivent pas faire :

* de SQL
* de logique métier importante
* de recalcul lourd

---

## Standards PHP

### Typage

Utiliser des signatures explicites autant que possible.

Exemple :

```php
public function toggleStudentActive(int $userId, bool $active): int
```

### Retours

Toujours retourner un type cohérent :

* `array`
* `int`
* `bool`
* `?array`

### Nullabilité

Être explicite avec les valeurs nulles.

Ne pas faire reposer une logique métier critique sur une ambiguïté entre :

* `''`
* `0`
* `null`

---

## Conventions SQL

### Requêtes préparées

Toujours utiliser des paramètres.

### Attention PDO

Le projet utilise PDO avec prepares natives.
Ne pas réutiliser plusieurs fois le même placeholder nommé dans une même requête.

### Mauvais

```sql
u.nom LIKE :search OR u.prenom LIKE :search
```

### Bon

```sql
u.nom LIKE :search_nom OR u.prenom LIKE :search_prenom
```

Même règle dans les `CASE WHEN`.

### Mauvais

```sql
SET is_active = :is_active,
    can_login = CASE WHEN :is_active = 0 THEN 0 ELSE can_login END
```

### Bon

```sql
SET is_active = :is_active,
    can_login = CASE WHEN :force_disable_login = 0 THEN 0 ELSE can_login END
```

---

## Règles métier importantes

### Élève autorisable

Un élève peut se connecter seulement si :

```text
role = student
is_active = 1
numero > 0
can_login = 1
```

### Élève archivé

```text
numero = 0
```

signifie :

* historique conservé
* non affiché dans les écrans opérationnels
* non autorisable à la connexion

### Compte désactivé

```text
is_active = 0
```

signifie :

* jamais autorisable
* même si une action de masse “autoriser la classe” est lancée

### Conservation historique

Ne pas supprimer un élève si ses examens doivent être conservés.

Préférer :

* `is_active = 0`
* `can_login = 0`
* `numero = 0`

---

## Sécurité

### CSRF

Toute route POST doit valider un jeton CSRF.

Le champ attendu est :

```html
<input type="hidden" name="_csrf" value="...">
```

### Permissions

Toute route admin doit vérifier le rôle admin.

### SQL injection

Aucune concaténation non maîtrisée dans les requêtes.

### Validation

Toujours valider et normaliser :

* ids
* booléens
* filtres GET
* actions POST

---

## Performance

### Pagination obligatoire

Toute liste potentiellement grande doit être paginée.

### DOM léger

Éviter :

* plusieurs formulaires par ligne
* trop de boutons textuels longs
* structures HTML répétées inutilement

Préférer :

* un formulaire global
* event delegation JS
* boutons icônes compacts

### SQL

Éviter les N+1 queries.

### Sessions

La table `user_sessions` est critique.
Toute opération de monitoring ou logout forcé doit être ciblée et indexée.

---

## JavaScript

Le frontend doit rester léger.

Règles :

* pas de framework JS lourd
* JS utilitaire ciblé
* privilégier l’event delegation
* privilégier les composants Bootstrap déjà présents

Exemple : un seul listener document plutôt qu’un listener par bouton.

---

## Vues admin

Les pages admin doivent être :

* denses
* rapides
* claires
* orientées exploitation

Préférer :

* `table-sm`
* boutons icônes
* tooltips Bootstrap
* pagination visible
* filtres stables

---

## Ajout d’une nouvelle fonctionnalité

Approche recommandée :

### 1. Définir la règle métier

Exemple :

* qui a le droit ?
* quelle donnée change ?
* quelles contraintes ?

### 2. Créer / compléter le service

Le service porte la logique.

### 3. Brancher le contrôleur

Le contrôleur appelle le service.

### 4. Ajouter la vue

Seulement après les règles métier.

### 5. Vérifier sécurité et perf

Avant validation.

---

## Ajout d’une route

Pour toute nouvelle route :

1. définir le chemin
2. choisir GET ou POST correctement
3. protéger si admin
4. vérifier CSRF si POST
5. rediriger proprement après action

---

## Changement de schéma SQL

Toute modification de structure doit :

* être justifiée métier
* être pensée index/performance
* être compatible avec l’historique
* éviter de casser les données existantes

Toujours réfléchir à l’impact sur :

* élèves archivés
* historiques examens
* sessions actives
* autorisations de connexion

---

## Règles de review

Avant de valider un changement, vérifier :

### Fonctionnel

* la règle métier est correcte
* pas de régression sur les comptes off
* pas de régression sur les élèves archivés

### Sécurité

* route protégée
* CSRF si POST
* paramètres préparés

### Performance

* requête paginée si nécessaire
* pas de N+1
* DOM raisonnable

### Lisibilité

* noms clairs
* code cohérent
* pas de duplication inutile

---

## Anti-patterns à éviter

* SQL dans les vues
* logique métier dans les contrôleurs
* duplications de règles métier à plusieurs endroits
* placeholder PDO réutilisé plusieurs fois
* pages admin non paginées
* actions POST sans CSRF
* suppression d’élèves pour “nettoyer” l’historique

---

## Recommandations d’évolution

### Court terme

* stabiliser tous les écrans admin
* centraliser la règle “élève autorisable”
* fiabiliser monitoring et autorisations

### Moyen terme

* introduire des repositories sur les accès critiques
* factoriser certaines requêtes SQL
* ajouter worker PDF

### Long terme

* audit plus riche
* import massif
* optimisation multi-salles
* monitoring temps réel avancé

---

## Checklist avant commit

* code testé manuellement
* route branchée
* CSRF OK
* SQL préparé
* placeholders PDO uniques
* pagination OK si liste
* affichage admin compact et lisible
* pas de régression métier

---

## Checklist avant merge

* pas de casse sur admin/students
* pas de casse sur admin/classes
* pas de casse sur autorisations de connexion
* comptes off toujours non autorisables
* élèves archivés toujours préservés
* aucune erreur PHP/SQL visible
