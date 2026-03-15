# Base de données — ExamApp V3

## Objectif

La base de données d’ExamApp V3 doit permettre :

- la gestion des utilisateurs
- la gestion des classes
- la gestion des postes
- la gestion des examens
- la gestion des sessions
- la conservation de l’historique

Le schéma est conçu pour un usage en **environnement scolaire local**, avec une contrainte forte sur :

- fiabilité
- lisibilité
- performance SQL
- conservation de l’historique

---

## Vue d’ensemble du modèle

```text
roles
  └── users
        ├── class_students ─── classes
        ├── user_sessions
        ├── user_exams ─── exams ─── questions ─── answer_options
        │                    └── exam_results
        └── login_attempt_alerts

lab_computers
  ├── user_sessions
  └── login_attempt_alerts

pdf_jobs
````

---

## Diagramme relationnel simplifié

```text
roles (1) ────< users (N)

classes (1) ────< class_students (N) >──── (1) users

users (1) ────< user_sessions (N)
lab_computers (1) ────< user_sessions (N)

users (1) ────< user_exams (N) >──── (1) exams
classes (1) ────< user_exams (N)

exams (1) ────< questions (N)
questions (1) ────< answer_options (N)

user_exams (1) ────< user_answers (N)
questions (1) ────< user_answers (N)

user_exams (1) ──── (1) exam_results

users (1) ────< login_attempt_alerts (N)
lab_computers (1) ────< login_attempt_alerts (N)
user_sessions (1) ────< login_attempt_alerts (N)
```

---

## Tables métier

---

## `roles`

### Rôle

Référentiel des rôles applicatifs.

### Colonnes principales

* `id`
* `code`
* `name`

### Valeurs usuelles

* `admin`
* `student`

### Contraintes

* `code` unique

---

## `users`

### Rôle

Stocke les comptes administrateurs et élèves.

### Colonnes principales

* `id`
* `role_id`
* `numero`
* `code_massar`
* `password_hash`
* `secret`
* `can_login`
* `is_active`
* `nom`
* `prenom`
* `nom_ar`
* `prenom_ar`
* `last_login_at`

### Règles métier

#### Élève autorisable

Un élève ne peut se connecter que si :

```text
role = student
is_active = 1
numero > 0
can_login = 1
```

#### Élève archivé

```text
numero = 0
```

signifie :

* élève conservé pour historique
* non affiché dans les écrans opérationnels
* non autorisable à la connexion

#### Compte désactivé

```text
is_active = 0
```

signifie :

* compte inactif
* jamais autorisable
* peut être réactivé plus tard

### Index utiles

* PK `id`
* UK `code_massar`
* `idx_users_role`
* `idx_users_can_login`
* `idx_users_numero`

### Recommandations

À terme, ajouter potentiellement un index composite métier :

```sql
(role_id, is_active, numero, can_login)
```

si certains écrans deviennent très volumineux.

---

## `classes`

### Rôle

Stocke les classes scolaires.

### Colonnes principales

* `id`
* `name`
* `school_year`
* `is_active`

### Contraintes

* unicité `(name, school_year)`

### Index utiles

* `idx_classes_active`

---

## `class_students`

### Rôle

Table de liaison entre classes et utilisateurs.

### Colonnes principales

* `class_id`
* `user_id`

### Rôle métier

Permet de :

* rattacher un élève à une classe
* changer un élève de classe sans perdre son historique
* garder un même `users.id` avec nouvelle affectation

### Clé primaire

* `(class_id, user_id)`

### Index utiles

* `idx_class_students_user`

---

## `lab_computers`

### Rôle

Référentiel des postes de la salle informatique.

### Colonnes principales

* `id`
* `name`
* `hostname`
* `ip_lan`
* `ip_wifi`
* `is_active`
* `room_name`
* `description`

### Règles métier

Un élève ne doit se connecter que depuis un poste autorisé.

### Contraintes

* unicité `name`
* unicité `hostname`
* unicité `ip_lan`
* unicité `ip_wifi`

### Index utiles

* `idx_lab_computers_active`

---

## `user_sessions`

### Rôle

Journal et pilotage des sessions applicatives.

### Colonnes principales

* `id`
* `session_token`
* `user_id`
* `class_id`
* `computer_id`
* `ip_address`
* `user_agent`
* `network_type`
* `status`
* `started_at`
* `last_activity_at`
* `closed_at`

### Règles métier

Pour les élèves :

* une seule session active
* fermeture forcée possible par l’admin

Pour les admins :

* sessions multiples autorisées

### Statuts

* `active`
* `closed`
* `expired`
* `refused`

### Index utiles

* UK `session_token`
* `idx_user_sessions_user`
* `idx_user_sessions_computer`
* `idx_user_sessions_status_activity`
* `idx_user_sessions_user_status`

### Recommandations

Table critique pour :

* monitoring
* contrôle de double connexion
* audit

---

## `login_attempt_alerts`

### Rôle

Historique des tentatives suspectes ou refusées.

### Colonnes principales

* `id`
* `user_id`
* `username_attempted`
* `class_id`
* `existing_session_id`
* `existing_computer_id`
* `existing_ip`
* `attempted_computer_id`
* `attempted_ip`
* `attempted_network_type`
* `attempted_at`
* `status`
* `notes`

### Statuts

* `refused`
* `suspect`
* `validated`
* `ignored`

### Utilité

* audit sécurité
* détection de double connexion
* diagnostic d’usage réseau

### Index utiles

* `idx_alerts_user`
* `idx_alerts_attempted_at`
* `idx_alerts_status_time`

---

## `exams`

### Rôle

Définition des examens.

### Colonnes principales

* `id`
* `code`
* `title`
* `duration_minutes`
* `is_active`
* `allow_print`
* `metadata`

### Contraintes

* `code` unique

### Index utiles

* `idx_exams_active`

---

## `questions`

### Rôle

Questions d’un examen.

### Colonnes principales

* `id`
* `exam_id`
* `category_id`
* `question_text`
* `points`
* `type`
* `num`
* `is_required`
* `sort_order`

### Contraintes

* FK `exam_id` vers `exams`

### Index utiles

* `idx_questions_exam_num`
* `idx_questions_exam_sort`
* `idx_questions_category`

### Recommandation

Si la volumétrie des questions augmente fortement, veiller à systématiquement requêter par `exam_id`.

---

## `answer_options`

### Rôle

Choix de réponse pour les questions à options.

### Colonnes principales

* `id`
* `question_id`
* `answer_text`
* `is_correct`
* `explanation`
* `sort_order`

### Contraintes

* FK `question_id` vers `questions`

### Index utiles

* `idx_answer_options_question`
* `idx_answer_options_question_sort`

---

## `user_exams`

### Rôle

Affectation et suivi d’un examen pour un élève.

### Colonnes principales

* `id`
* `user_id`
* `class_id`
* `exam_id`
* `is_absent`
* `is_retake`
* `score`
* `started_at`
* `submitted_at`
* `duration_seconds`
* `status`

### Statuts

* `assigned`
* `started`
* `submitted`
* `corrected`
* `cancelled`

### Contraintes

* unique `(user_id, class_id, exam_id)`

### Index utiles

* `uk_user_exams_triplet`
* `idx_user_exams_class_exam`
* `idx_user_exams_exam_status`
* `idx_user_exams_user`

### Rôle historique

Cette table est centrale pour conserver les résultats même si l’élève change de classe ou de numéro.

---

## `user_answers`

### Rôle

Réponses enregistrées pour un élève à un examen.

### Colonnes principales

* `id`
* `user_exam_id`
* `question_id`
* `question_num`
* `awarded_points`
* `answer_text`
* `correct_answer_text`
* `question_snapshot`

### Contraintes

* unique `(user_exam_id, question_id)`

### Index utiles

* `uk_user_answers_exam_question`
* `idx_user_answers_user_exam`
* `idx_user_answers_question_num`

### Note

Le `question_snapshot` est utile pour figer le contexte de correction même si la question évolue plus tard.

---

## `exam_results`

### Rôle

Synthèse de résultat d’un `user_exam`.

### Colonnes principales

* `id`
* `user_exam_id`
* `total_questions`
* `answered_questions`
* `correct_questions`
* `wrong_questions`
* `blank_questions`
* `final_score`

### Contraintes

* unique `user_exam_id`

### Utilité

Permet d’éviter de recalculer en permanence des agrégats coûteux.

---

## `pdf_jobs`

### Rôle

File de génération PDF.

### Colonnes principales

* `id`
* `job_type`
* `reference_type`
* `reference_id`
* `payload_json`
* `output_file`
* `status`
* `attempts`
* `error_message`
* `requested_by`
* `locked_at`
* `processed_at`

### Statuts

* `pending`
* `processing`
* `done`
* `failed`

### Utilité

Déporter les générations lourdes hors du cycle HTTP.

### Index utiles

* `idx_pdf_jobs_status_created`
* `idx_pdf_jobs_ref`
* `idx_pdf_jobs_requested_by`

---

## Règles de modélisation importantes

### 1. Ne pas supprimer un élève pour conserver l’historique

Un élève ancien ou déplacé doit être :

* désactivé
* potentiellement mis avec `numero = 0`

mais pas supprimé si son historique doit être conservé.

### 2. Ne pas recréer un user pour un simple changement de classe

Le bon modèle est :

* conserver `users.id`
* changer `class_students`
* changer `numero`
* réactiver si nécessaire

### 3. Les autorisations de connexion ne remplacent pas l’état du compte

`can_login` = autorisation opérationnelle temporaire
`is_active` = état administratif du compte

Les deux ne doivent jamais être confondus.

---

## Requêtes critiques

Les requêtes les plus sensibles en perf sont :

* liste paginée des élèves
* détail d’une classe avec ses élèves
* sessions actives
* détection de double connexion
* récupération des questions d’un examen
* consultation d’historique examens élève

---

## Règles SQL de performance

### Toujours faire

* requêtes paginées
* colonnes ciblées
* `JOIN` explicites
* index adaptés aux `WHERE`, `ORDER BY`, `JOIN`

### Éviter

* `SELECT *` sur grandes tables
* sous-requêtes non bornées
* placeholders PDO nommés réutilisés plusieurs fois dans la même requête si `ATTR_EMULATE_PREPARES = false`

Exemple à éviter :

```sql
u.nom LIKE :search OR u.prenom LIKE :search
```

Préférer :

```sql
u.nom LIKE :search_nom OR u.prenom LIKE :search_prenom
```

---

## Nettoyage et maintenance

À prévoir périodiquement :

* purge / archivage éventuel des anciennes `login_attempt_alerts`
* fermeture des sessions orphelines
* supervision des `pdf_jobs` en erreur
* contrôle d’intégrité sur les comptes archivés

---

## Évolutions futures possibles

* table dédiée pour catégories de questions
* table d’affectation multi-classes si besoin futur
* historisation plus fine des changements de classe
* partitionnement ou archivage des logs si volumétrie très élevée
