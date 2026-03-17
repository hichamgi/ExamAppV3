cd# Architecture — ExamApp V3

## Vue d’ensemble

ExamApp V3 est une plateforme d’examens informatiques conçue pour un **réseau local scolaire**, avec une architecture :

- simple
- maintenable
- performante
- sécurisée

Le projet repose sur une architecture **MVC légère sans framework**, avec séparation claire entre :

- HTTP / routing
- logique métier
- accès base de données
- rendu HTML

---

## Objectifs d’architecture

L’architecture doit permettre :

- de supporter **1000 à 2000 élèves simultanément**
- de fonctionner **sans Internet**
- de limiter les dépendances externes
- de garder un code lisible et maîtrisable
- d’éviter les refontes coûteuses

---

## Vue logique

```text
Navigateur
   ↓
public/index.php
   ↓
Core\App
   ↓
Core\Router
   ↓
Controller
   ↓
Service
   ↓
Core\Database (PDO)
   ↓
MariaDB
   ↓
View PHP
   ↓
Réponse HTML
````

---

## Composants principaux

### 1. Front controller

Fichier :

```text
public/index.php
```

Rôle :

* point d’entrée unique
* bootstrap de l’application
* chargement de l’autoload
* démarrage de l’application

---

### 2. Noyau applicatif

Dossier :

```text
src/Core/
```

Classes principales :

* `App.php`
* `Router.php`
* `Request.php`
* `Response.php`
* `Controller.php`
* `Database.php`
* `Env.php`
* `Config.php`
* `Csrf.php`
* `Middleware.php`
* `AuthMiddleware.php`
* `SessionManager.php`

Responsabilités :

#### `App`

* démarre l’application
* charge la config
* orchestre le cycle de requête

#### `Router`

* associe URL + méthode HTTP à un handler
* dispatch vers le bon contrôleur

#### `Request`

* lecture sécurisée des entrées GET / POST / SERVER
* normalisation des paramètres

#### `Response`

* encapsulation des réponses HTTP

#### `Controller`

* base commune des contrôleurs
* rendu des vues
* helpers partagés

#### `Database`

* accès PDO centralisé
* exécution des requêtes
* helpers `fetchAll`, `fetchOne`, `fetchValue`, `execute`

#### `Csrf`

* protection des formulaires POST
* génération et validation des jetons

#### `SessionManager`

* gestion des sessions applicatives
* coordination avec `user_sessions`

---

## Couche contrôleurs

Dossier :

```text
src/Controllers/
```

Principe :

* un contrôleur ne doit pas contenir de logique métier lourde
* il valide l’intention HTTP
* il appelle un service
* il prépare la vue ou la redirection

Contrôleurs principaux :

* `AuthController`
* `AdminController`
* `StudentController`
* `AdminComputerController`
* `AdminStudentController`
* `AdminClassController`
* `AdminExamController`
* `AdminMonitoringController`

Exemple de responsabilité :

### `AdminStudentController`

* liste paginée des élèves
* filtres admin
* activation / désactivation
* autorisation / blocage login
* déconnexion forcée

---

## Couche services

Dossier :

```text
src/Services/
```

Principe :

* toute la logique métier va ici
* les services doivent être indépendants du rendu HTML
* ils manipulent les règles fonctionnelles du domaine

Services actuels :

* `AuthService`
* `NetworkComputerService`
* `StudentAdminService`
* `ClassAdminService`
* `LoginAuthorizationService`
* `ExamAdminService`
* `MonitoringService`
* `AlertService`

Exemples :

### `AuthService`

* authentification admin / élève
* vérification des droits de connexion
* création de session

### `StudentAdminService`

* pagination des élèves
* recherche
* activation / désactivation
* fermeture de sessions

### `ClassAdminService`

* chargement des classes
* détail d’une classe
* récupération des élèves actifs d’une classe

### `LoginAuthorizationService`

* autoriser toute une classe
* bloquer toute une classe
* autoriser groupe 1 / groupe 2
* garantir qu’un compte off ne soit jamais autorisé

### `MonitoringService`

* suivi des sessions actives
* visibilité admin sur les connexions

---

## Couche vues

Dossier :

```text
src/Views/
```

Organisation :

```text
layouts/
partials/
auth/
admin/
student/
```

Règles :

* une vue ne doit pas contenir de logique métier
* la vue affiche seulement des données déjà préparées
* éviter les requêtes SQL en vue
* éviter les calculs lourds en vue

---

## Flux d’une requête admin

Exemple : liste des élèves

```text
GET /admin/students
   ↓
Router
   ↓
AdminStudentController::index()
   ↓
StudentAdminService::paginateStudents()
   ↓
Database::fetchAll() + Database::fetchValue()
   ↓
render('admin.students.index', ...)
```

---

## Flux d’une action POST sécurisée

Exemple : désactivation d’un élève

```text
POST /admin/students/toggle-active
   ↓
Router
   ↓
AdminStudentController::toggleActive()
   ↓
Csrf::assertRequest('...')
   ↓
StudentAdminService::toggleStudentActive(...)
   ↓
Database::execute(...)
   ↓
redirect('/admin/students')
```

---

## Architecture des données

Les entités centrales sont :

* `users`
* `roles`
* `classes`
* `class_students`
* `lab_computers`
* `user_sessions`
* `exams`
* `questions`
* `answer_options`
* `user_exams`
* `user_answers`
* `exam_results`
* `login_attempt_alerts`
* `pdf_jobs`

---

## Principes métier structurants

### 1. Unicité de session élève

Un élève ne doit avoir qu’une seule session active.

### 2. Admin multi-session

Un administrateur peut se connecter depuis plusieurs postes.

### 3. Comptes archivés

Un élève avec :

```text
numero = 0
```

est considéré comme archivé / historique :

* il reste en base
* il garde ses examens passés
* il ne doit pas pouvoir se connecter
* il ne doit pas apparaître dans les écrans opérationnels de classe
* il peut être réactivé plus tard

### 4. Compte désactivé

Un élève avec :

```text
is_active = 0
```

ne doit jamais être autorisé à se connecter, même si une action “autoriser classe” est lancée.

---

## Performance

Pour tenir la charge en environnement scolaire, l’architecture doit respecter :

### Pagination

Toutes les listes volumineuses doivent être paginées.

### Requêtes SQL ciblées

Éviter :

* les `SELECT *` inutiles
* les N+1 queries
* les sous-requêtes répétées non maîtrisées

### DOM léger

Éviter :

* un formulaire par ligne si un formulaire global suffit
* trop de nœuds HTML pour les grandes tables admin

### Sessions et monitoring

La table `user_sessions` doit être correctement indexée pour :

* recherche des sessions actives
* fermeture ciblée
* audit

### PDF

La génération PDF doit être déportée dans `pdf_jobs` et traitée par worker pour éviter de bloquer les requêtes web.

---

## Sécurité

### Authentification

* séparation admin / élève
* validation forte des droits

### CSRF

Toutes les actions POST doivent passer par `Csrf`.

### SQL

Toujours utiliser des requêtes préparées.

### Permissions

Chaque contrôleur admin doit protéger l’accès.

### Réseau

Les élèves doivent se connecter uniquement depuis des postes autorisés.

---

## Architecture réseau salle informatique

Hypothèse :

```text
posteprof
poste01 → poste20
```

Détection basée sur :

* hostname
* IP LAN
* IP Wi-Fi éventuelle

Les règles réseau doivent permettre :

* validation du poste autorisé
* refus de double connexion
* journalisation dans `login_attempt_alerts`

---

## Limites actuelles connues

À surveiller :

* homogénéité des règles métier entre les différents écrans
* duplication potentielle de règles dans plusieurs services
* nécessité future d’introduire des repositories si la couche service grossit trop
* coût de certaines sous-requêtes de comptage si le volume augmente fortement

---

## Direction recommandée

L’évolution saine du projet est :

### Court terme

* stabiliser les écrans admin
* centraliser les règles “élève autorisable”
* fiabiliser monitoring et autorisations

### Moyen terme

* introduire `Repository` pour les accès SQL critiques
* worker PDF dédié
* import massif d’élèves
* gestion complète des examens et corrections

### Long terme

* monitoring temps réel plus riche
* optimisation multi-salles
* mode examen plus verrouillé
* outils d’audit et statistiques avancées

---

## Règles d’évolution du code

Toute évolution doit respecter :

1. contrôleurs courts
2. logique métier dans les services
3. requêtes SQL explicites et indexées
4. pas de logique métier en vue
5. sécurité systématique
6. optimisation avant complexification
