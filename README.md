# ExamApp V3

Platforme d’examens informatiques conçue pour les **salles informatiques scolaires**.

ExamApp V3 permet de gérer :

* les élèves
* les classes
* les postes informatiques
* les examens
* les autorisations de connexion
* la surveillance des sessions

L’application est conçue pour fonctionner **dans un réseau local d’établissement scolaire** et supporter **plusieurs centaines à milliers d’élèves simultanément**.

---

# Objectifs du projet

ExamApp V3 vise à fournir une plateforme :

* **rapide**
* **fiable**
* **simple à maintenir**
* **sécurisée pour un environnement d’examen**

Le projet privilégie :

* la performance
* la robustesse
* la simplicité d’architecture
* la transparence du code

---

# Stack technique

```
PHP 8+
MariaDB / MySQL
PDO
Bootstrap 5
Bootstrap Icons
JavaScript (Vanilla)
Architecture MVC légère
```

Aucun framework lourd n’est utilisé afin de :

* réduire la consommation mémoire
* garder le contrôle total du code
* simplifier la maintenance sur serveur scolaire

---

# Architecture du projet

```
ExamAppV3
│
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── app.js
│
├── src/
│
│   ├── Controllers/
│   │   ├── AdminComputerController.php
│   │   ├── AdminStudentController.php
│   │   ├── AdminClassController.php
│   │   ├── AdminAuthorizationController.php
│   │   ├── AdminExamController.php
│   │   └── AdminMonitoringController.php
│
│   ├── Services/
│   │   ├── StudentAdminService.php
│   │   ├── ClassAdminService.php
│   │   ├── LoginAuthorizationService.php
│   │   ├── ExamAdminService.php
│   │   └── MonitoringService.php
│
│   ├── Core/
│   │   ├── App.php
│   │   ├── Router.php
│   │   ├── Database.php
│   │   └── Csrf.php
│
│   └── Views/
│       └── admin/
│           ├── students/
│           ├── classes/
│           ├── computers/
│           ├── exams/
│           └── monitoring/
│
└── config/
```

---

# Architecture logicielle

ExamApp suit une architecture **MVC simplifiée** :

```
Controller
   ↓
Service (logique métier)
   ↓
Database (PDO)
   ↓
MariaDB
```

Principe :

* **Controllers** : gestion HTTP
* **Services** : logique métier
* **Core** : infrastructure
* **Views** : rendu HTML

Les contrôleurs doivent rester **minces**.

Toute la logique métier est placée dans les **Services**.

---

# Modèle de données principal

Tables principales :

```
users
roles
classes
class_students
exams
questions
answers
user_sessions
computers
exam_results
```

Relations principales :

```
users
 └── role_id → roles

class_students
 ├── class_id → classes
 └── user_id → users
```

---

# Gestion des élèves

Chaque élève possède plusieurs états :

| Champ     | Description                          |
| --------- | ------------------------------------ |
| is_active | compte actif dans l’établissement    |
| can_login | autorisation temporaire de connexion |
| numero    | numéro dans la classe                |

### Règles métier

```
is_active = 0 → compte administrativement désactivé
numero = 0 → élève archivé
can_login = 1 → autorisation de connexion
```

Un élève peut se connecter seulement si :

```
role = student
is_active = 1
numero > 0
can_login = 1
```

Les élèves archivés sont conservés afin de **préserver l’historique des examens**.

---

# Gestion des autorisations

L'administration peut :

* autoriser toute une classe
* bloquer toute une classe
* autoriser groupe 1
* autoriser groupe 2
* autoriser un élève individuellement
* forcer la déconnexion

Les groupes permettent d’organiser les examens en **deux vagues successives** dans une salle informatique.

---

# Gestion des sessions

Les connexions sont enregistrées dans :

```
user_sessions
```

Champs principaux :

```
id
user_id
status
created_at
closed_at
```

Statuts possibles :

```
active
closed
```

Un élève peut être **déconnecté à distance** depuis l'administration.

---

# Sécurité

ExamApp V3 inclut plusieurs protections :

## Protection CSRF

Toutes les requêtes POST utilisent :

```
App\Core\Csrf
```

Chaque formulaire contient :

```
<input type="hidden" name="_csrf">
```

---

## Protection SQL

Toutes les requêtes utilisent :

```
PDO prepared statements
```

afin d'éviter les injections SQL.

---

## Sessions contrôlées

Les sessions :

* sont enregistrées en base
* peuvent être fermées administrativement
* peuvent être surveillées en temps réel

---

# Performance

ExamApp V3 est optimisé pour les salles informatiques.

Principes utilisés :

### Pagination obligatoire

Les listes lourdes utilisent :

```
LIMIT + OFFSET
```

Exemple :

```
/admin/students?page=1
```

---

### Requêtes SQL optimisées

Pas de :

```
N+1 queries
```

Utilisation de :

```
JOIN
COUNT
subqueries contrôlées
```

---

### DOM léger

Les vues admin évitent :

* formulaires multiples
* scripts inutiles
* DOM excessif

---

# Installation

## 1. Cloner le projet

```
git clone https://github.com/hichamgi/ExamAppV3.git
```

---

## 2. Configurer la base

Créer une base :

```
examapp
```

Modifier :

```
config/database.php
```

---

## 3. Configurer le serveur web

Document root :

```
/public
```

Apache :

```
AllowOverride All
```

---

## 4. Importer la base

Importer :

```
database/schema.sql
```

---

# Accès administration

```
/admin
```

Modules disponibles :

* gestion élèves
* gestion classes
* gestion postes
* gestion examens
* monitoring

---

# Monitoring

Le module monitoring permet :

* voir les connexions actives
* fermer les sessions
* surveiller les examens en cours

---

# Bonnes pratiques du projet

## Code

* classes courtes
* logique métier dans les Services
* contrôleurs légers

## Sécurité

* validation systématique
* requêtes préparées
* protection CSRF

## Performance

* pagination
* SQL optimisé
* DOM léger

---

# Roadmap

Fonctionnalités prévues :

* gestion complète des examens
* correction automatique
* import CSV élèves
* monitoring réseau des postes
* verrouillage navigateur examen
* gestion multi-salles

---

# Déploiement en salle informatique

Configuration recommandée :

```
Serveur Linux
PHP-FPM
MariaDB
Apache ou Nginx
```

Réseau :

```
Serveur local
Salle informatique
LAN uniquement
```

---

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Developer Guide](DEVELOPER.md)

---

# Auteur

Développé par :

**Hicham**

Projet destiné aux établissements scolaires pour la gestion d’examens informatiques.

---

# Licence

Projet éducatif.

Utilisation libre dans les établissements scolaires.

---

# Contribution

Les contributions sont bienvenues.

Merci de :

* respecter l’architecture existante
* privilégier la performance
* éviter les dépendances inutiles
* garder le code simple et lisible
