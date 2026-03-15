SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

-- =========================================================
-- 0. Préconditions minimales
-- =========================================================

INSERT INTO examsappv3.roles (code, name, created_at, updated_at)
VALUES
('admin', 'Administrateur', NOW(), NOW()),
('student', 'Élève', NOW(), NOW())
ON DUPLICATE KEY UPDATE
name = VALUES(name),
updated_at = NOW();

-- =========================================================
-- 1. Nettoyage optionnel des tables cibles
-- =========================================================
-- L’ordre est important à cause des FK.

TRUNCATE TABLE examsappv3.exam_results;
TRUNCATE TABLE examsappv3.user_answers;
TRUNCATE TABLE examsappv3.user_exams;
TRUNCATE TABLE examsappv3.answer_options;
TRUNCATE TABLE examsappv3.questions;
TRUNCATE TABLE examsappv3.class_students;
TRUNCATE TABLE examsappv3.users;
TRUNCATE TABLE examsappv3.exams;
TRUNCATE TABLE examsappv3.classes;

-- On remet les rôles après truncate users/classes/exams si besoin
INSERT INTO examsappv3.roles (code, name, created_at, updated_at)
VALUES
('admin', 'Administrateur', NOW(), NOW()),
('student', 'Élève', NOW(), NOW())
ON DUPLICATE KEY UPDATE
name = VALUES(name),
updated_at = NOW();

-- =========================================================
-- 2. Migration des classes
-- =========================================================

INSERT INTO examsappv3.classes (
    id,
    name,
    school_year,
    is_active,
    created_at,
    updated_at
)
SELECT
    c.id,
    c.classe AS name,
    COALESCE(a.annee, '') AS school_year,
    1 AS is_active,
    c.created_at,
    c.updated_at
FROM examsapp.classes c
LEFT JOIN examsapp.annees a ON a.id = c.idannee;

-- =========================================================
-- 3. Migration des utilisateurs
-- =========================================================

INSERT INTO examsappv3.users (
    id,
    role_id,
    numero,
    code_massar,
    password_hash,
    secret,
    can_login,
    is_active,
    nom,
    prenom,
    nom_ar,
    prenom_ar,
    last_login_at,
    created_at,
    updated_at
)
SELECT
    u.id,
    CASE
        WHEN u.admin = 1 THEN (SELECT id FROM examsappv3.roles WHERE code = 'admin' LIMIT 1)
        ELSE (SELECT id FROM examsappv3.roles WHERE code = 'student' LIMIT 1)
    END AS role_id,
    u.numero,
    u.username AS code_massar,
    u.password AS password_hash,
    COALESCE(u.secret, '') AS secret,
    u.can_login,
    1 AS is_active,
    COALESCE(u.nom, '') AS nom,
    COALESCE(u.prenom, '') AS prenom,
    COALESCE(u.nomar, '') AS nom_ar,
    COALESCE(u.prenomar, '') AS prenom_ar,
    u.last_login_at,
    u.created_at,
    u.updated_at
FROM examsapp.users u;

-- =========================================================
-- 4. Migration classe <-> élèves
-- =========================================================

INSERT IGNORE INTO examsappv3.class_students (
    class_id,
    user_id,
    created_at
)
SELECT
    uc.idclasse AS class_id,
    uc.iduser AS user_id,
    uc.created_at
FROM examsapp.userclasse uc
INNER JOIN examsapp.users u ON u.id = uc.iduser
WHERE u.admin = 0
  AND uc.idclasse IS NOT NULL;

-- =========================================================
-- 5. Migration des examens
-- =========================================================

INSERT INTO examsappv3.exams (
    id,
    code,
    title,
    duration_minutes,
    is_active,
    allow_print,
    metadata,
    created_at,
    updated_at
)
SELECT
    e.id,
    CONCAT('EX', LPAD(e.id, 4, '0')) AS code,
    CONCAT(
        COALESCE(m.module, 'Examen'),
        ' - ',
        COALESCE(t.type, 'Type'),
        ' #',
        e.id
    ) AS title,
    e.temps AS duration_minutes,
    e.active AS is_active,
    e.print AS allow_print,
    JSON_OBJECT(
        'legacy_idmodule', e.idmodule,
        'legacy_idtype', e.idtype,
        'legacy_description', COALESCE(e.description, ''),
        'module', COALESCE(m.module, ''),
        'module_abrev', COALESCE(m.abrev, ''),
        'type', COALESCE(t.type, ''),
        'division_id', COALESCE(m.iddivision, 0)
    ) AS metadata,
    e.created_at,
    e.updated_at
FROM examsapp.exams e
LEFT JOIN examsapp.modules m ON m.id = e.idmodule
LEFT JOIN examsapp.types t ON t.id = e.idtype;

-- =========================================================
-- 6. Migration des questions
-- =========================================================
-- category_id est conservé comme référence legacy non contrainte.

INSERT INTO examsappv3.questions (
    id,
    exam_id,
    category_id,
    question_text,
    points,
    type,
    num,
    is_required,
    sort_order,
    created_at,
    updated_at
)
SELECT
    q.id,
    q.idexam AS exam_id,
    q.idcategorie AS category_id,
    COALESCE(q.question, '') AS question_text,
    q.point AS points,
    COALESCE(q.type, '') AS type,
    q.num,
    q.obligatoire AS is_required,
    q.num AS sort_order,
    q.created_at,
    q.updated_at
FROM examsapp.questions q;

-- =========================================================
-- 7. Migration des réponses
-- =========================================================

INSERT INTO examsappv3.answer_options (
    id,
    question_id,
    answer_text,
    is_correct,
    explanation,
    sort_order,
    created_at,
    updated_at
)
SELECT
    r.id_rep AS id,
    r.id_qst AS question_id,
    COALESCE(r.reponse, '') AS answer_text,
    r.juste AS is_correct,
    COALESCE(r.description, '') AS explanation,
    r.id_rep AS sort_order,
    r.created_at,
    r.updated_at
FROM examsapp.reponses r;

-- =========================================================
-- 8. Migration user_exams
-- =========================================================

INSERT INTO examsappv3.user_exams (
    id,
    user_id,
    class_id,
    exam_id,
    is_absent,
    is_retake,
    score,
    started_at,
    submitted_at,
    duration_seconds,
    status,
    created_at,
    updated_at
)
SELECT
    ue.id,
    ue.iduser AS user_id,
    ue.idclasse AS class_id,
    ue.idexam AS exam_id,
    ue.absent AS is_absent,
    ue.rattrapage AS is_retake,
    ue.note AS score,
    ue.date AS started_at,
    NULL AS submitted_at,
    ue.temps AS duration_seconds,
    CASE
        WHEN ue.absent = 1 THEN 'assigned'
        WHEN ue.temps > 0 THEN 'submitted'
        ELSE 'assigned'
    END AS status,
    ue.created_at,
    ue.updated_at
FROM examsapp.userexam ue;

-- =========================================================
-- 9. Migration user_answers
-- =========================================================

INSERT INTO examsappv3.user_answers (
    user_exam_id,
    question_id,
    question_num,
    awarded_points,
    answer_text,
    correct_answer_text,
    question_snapshot,
    created_at,
    updated_at
)
SELECT
    uq.iduserexam AS user_exam_id,
    uq.idquestion AS question_id,
    uq.numero AS question_num,
    uq.points AS awarded_points,
    uq.reponse AS answer_text,
    uq.repj AS correct_answer_text,
    uq.qst AS question_snapshot,
    uq.created_at,
    uq.updated_at
FROM examsapp.userquestion uq;

-- =========================================================
-- 10. Génération exam_results
-- =========================================================

INSERT INTO examsappv3.exam_results (
    user_exam_id,
    total_questions,
    answered_questions,
    correct_questions,
    wrong_questions,
    blank_questions,
    final_score,
    created_at,
    updated_at
)
SELECT
    ue.id AS user_exam_id,
    COUNT(ua.id) AS total_questions,
    SUM(
        CASE
            WHEN ua.answer_text IS NOT NULL AND TRIM(ua.answer_text) <> '' THEN 1
            ELSE 0
        END
    ) AS answered_questions,
    SUM(
        CASE
            WHEN ua.awarded_points > 0 THEN 1
            ELSE 0
        END
    ) AS correct_questions,
    SUM(
        CASE
            WHEN ua.awarded_points = 0
             AND ua.answer_text IS NOT NULL
             AND TRIM(ua.answer_text) <> '' THEN 1
            ELSE 0
        END
    ) AS wrong_questions,
    SUM(
        CASE
            WHEN ua.answer_text IS NULL OR TRIM(ua.answer_text) = '' THEN 1
            ELSE 0
        END
    ) AS blank_questions,
    ue.score AS final_score,
    NOW(),
    NOW()
FROM examsappv3.user_exams ue
LEFT JOIN examsappv3.user_answers ua ON ua.user_exam_id = ue.id
GROUP BY ue.id, ue.score;

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
