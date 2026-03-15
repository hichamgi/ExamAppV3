<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');

require BASE_PATH . '/vendor/autoload.php';

Env::load(BASE_PATH . '/.env');

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

Config::load();

$databaseConfig = Config::get('database');

if (!is_array($databaseConfig)) {
    fwrite(STDERR, "Configuration database introuvable.\n");
    exit(1);
}

$driver = (string) ($databaseConfig['driver'] ?? 'mysql');
$host = (string) ($databaseConfig['host'] ?? '127.0.0.1');
$port = (int) ($databaseConfig['port'] ?? 3306);
$username = (string) ($databaseConfig['username'] ?? '');
$password = (string) ($databaseConfig['password'] ?? '');
$charset = (string) ($databaseConfig['charset'] ?? 'utf8mb4');

if ($driver !== 'mysql') {
    fwrite(STDERR, "Driver non supporté pour ce script : {$driver}\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;charset=%s',
    $host,
    $port,
    $charset
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$sqlFile = BASE_PATH . '/database/migrate_from_legacy.sql';

if (!is_file($sqlFile)) {
    fwrite(STDERR, "Fichier introuvable : {$sqlFile}\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Impossible de lire le fichier SQL ou fichier vide.\n");
    exit(1);
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);

    echo "=== Migration legacy examsapp -> examsappv3 ===\n";
    echo "Connexion OK : {$host}:{$port}\n";
    echo "Exécution du script SQL...\n";

    $pdo->exec($sql);

    echo "Migration terminée.\n\n";

    $checks = [
        'roles' => "SELECT COUNT(*) AS c FROM examsappv3.roles",
        'classes' => "SELECT COUNT(*) AS c FROM examsappv3.classes",
        'users' => "SELECT COUNT(*) AS c FROM examsappv3.users",
        'class_students' => "SELECT COUNT(*) AS c FROM examsappv3.class_students",
        'exams' => "SELECT COUNT(*) AS c FROM examsappv3.exams",
        'questions' => "SELECT COUNT(*) AS c FROM examsappv3.questions",
        'answer_options' => "SELECT COUNT(*) AS c FROM examsappv3.answer_options",
        'user_exams' => "SELECT COUNT(*) AS c FROM examsappv3.user_exams",
        'user_answers' => "SELECT COUNT(*) AS c FROM examsappv3.user_answers",
        'exam_results' => "SELECT COUNT(*) AS c FROM examsappv3.exam_results",
    ];

    echo "=== Résumé ===\n";
    foreach ($checks as $label => $query) {
        $stmt = $pdo->query($query);
        $count = (int) ($stmt->fetch()['c'] ?? 0);
        echo str_pad($label, 16, ' ') . ": {$count}\n";
    }

    echo "\n=== Contrôles conseillés ===\n";
    echo "1. Vérifier les admins/élèves migrés.\n";
    echo "2. Vérifier quelques examens et titres générés.\n";
    echo "3. Vérifier que les mots de passe legacy sont encore valides.\n";
    echo "4. Seeder lab_computers séparément.\n";
    echo "5. Ne pas migrer user_sessions legacy.\n";

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Erreur migration : " . $e->getMessage() . PHP_EOL);
    exit(1);
}
