<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Core\Config;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

mb_internal_encoding('UTF-8');

Env::load('../../.env');

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

Config::load();

const EXCLUDED_TABLES = [
    // 'migrations',
];

const EXCLUDED_COLUMNS = [
    'password_hash',
    'session_token',
    'payload_json',
    'metadata',
    'question_snapshot',
    'correct_answer_text',
    'secret',
    'user_agent',
    'output_file',
    'error_message',
];

const ALLOWED_TEXT_TYPES = [
    'char',
    'varchar',
    'tinytext',
    'text',
    'mediumtext',
    'longtext',
];

const MAX_RESULTS = 500;

$pdo = getPdo();
$dbName = getDatabaseName($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? 'search';
$message = null;
$error = null;
$results = [];
$tables = [];
$table = (string) ($_GET['table'] ?? $_POST['table'] ?? '');
$column = (string) ($_GET['column'] ?? $_POST['column'] ?? '');
$entityFilter = (string) ($_GET['entity'] ?? $_POST['entity'] ?? '');
$containsNbspOnly = isset($_GET['nbsp_only']) ? (int) $_GET['nbsp_only'] : (isset($_POST['nbsp_only']) ? (int) $_POST['nbsp_only'] : 0);

try {
    $tables = getTextTablesAndColumns($pdo, $dbName);

    if ($action === 'apply') {
        $selected = $_POST['selected'] ?? [];
        $replacements = $_POST['replacement'] ?? [];

        $updated = applySelectedFixes($pdo, $selected, $replacements);
        $message = $updated . ' ligne(s) mise(s) à jour.';
    }

    if ($action === 'search' || $action === 'apply') {
        if ($table !== '' && $column !== '') {
            $results = searchEntities($pdo, $dbName, $table, $column, $entityFilter, $containsNbspOnly === 1);
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function getPdo(): PDO
{
    if (method_exists(Database::class, 'connection')) {
        return Database::connection();
    }

    if (method_exists(Database::class, 'getConnection')) {
        return Database::getConnection();
    }

    if (method_exists(Database::class, 'pdo')) {
        return Database::pdo();
    }

    throw new RuntimeException('Méthode de connexion PDO introuvable dans App\Core\Database.');
}

function getDatabaseName(PDO $pdo): string
{
    $name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ($name === '') {
        throw new RuntimeException('Impossible de déterminer la base de données courante.');
    }

    return $name;
}

function getTextTablesAndColumns(PDO $pdo, string $dbName): array
{
    $sql = "
        SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :db
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['db' => $dbName]);

    $map = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $table = (string) $row['TABLE_NAME'];
        $column = (string) $row['COLUMN_NAME'];
        $type = strtolower((string) $row['DATA_TYPE']);

        if (in_array($table, EXCLUDED_TABLES, true)) {
            continue;
        }

        if (in_array($column, EXCLUDED_COLUMNS, true)) {
            continue;
        }

        if (!in_array($type, ALLOWED_TEXT_TYPES, true)) {
            continue;
        }

        $map[$table][] = $column;
    }

    ksort($map);

    return $map;
}

function getPrimaryKey(PDO $pdo, string $dbName, string $table): ?string
{
    $sql = "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = :db
          AND TABLE_NAME = :table
          AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY ORDINAL_POSITION
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'db' => $dbName,
        'table' => $table,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== 1) {
        return null;
    }

    return (string) $rows[0]['COLUMN_NAME'];
}

function searchEntities(PDO $pdo, string $dbName, string $table, string $column, string $entityFilter, bool $nbspOnly): array
{
    $pk = getPrimaryKey($pdo, $dbName, $table);

    if ($pk === null) {
        throw new RuntimeException("La table {$table} n'a pas de clé primaire simple exploitable.");
    }

    $where = [
        "`{$column}` IS NOT NULL",
        "`{$column}` <> ''",
        "("
        . "`{$column}` LIKE '%&%'"
        . " OR `{$column}` LIKE '%&#%'"
        . ")"
    ];

    $params = [];

    if ($entityFilter !== '') {
        $where[] = "`{$column}` LIKE :entity_filter";
        $params['entity_filter'] = '%' . $entityFilter . '%';
    }

    if ($nbspOnly) {
        $where[] = "`{$column}` LIKE '%&nbsp;%'";
    }

    $sql = "
        SELECT `{$pk}` AS pk, `{$column}` AS value
        FROM `{$table}`
        WHERE " . implode(' AND ', $where) . "
        ORDER BY `{$pk}` DESC
        LIMIT " . MAX_RESULTS;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $original = (string) $row['value'];
        $decoded = decodeHtmlEntitiesSafely($original);

        $rows[] = [
            'table' => $table,
            'column' => $column,
            'pk' => (string) $row['pk'],
            'original' => $original,
            'decoded' => $decoded,
            'original_visible' => makeWhitespaceVisible($original),
            'decoded_visible' => makeWhitespaceVisible($decoded),
            'html_preview_original' => $original,
            'html_preview_decoded' => nl2br(e($decoded)),
            'has_nbsp' => str_contains($original, '&nbsp;'),
        ];
    }

    return $rows;
}

function decodeHtmlEntitiesSafely(string $value): string
{
    $current = $value;

    for ($i = 0; $i < 3; $i++) {
        $decoded = html_entity_decode($current, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decoded === $current) {
            break;
        }

        $current = $decoded;
    }

    return $current;
}

function makeWhitespaceVisible(string $value): string
{
    $value = str_replace("\r\n", "\n", $value);
    $value = str_replace("\r", "\n", $value);
    $value = str_replace("\t", '⇥', $value);
    $value = str_replace("\n", "⏎\n", $value);
    $value = str_replace("\xc2\xa0", '⍽', $value);
    $value = str_replace('&nbsp;', '[NBSP]', $value);

    $value = preg_replace('/ /u', '·', $value) ?? $value;

    return $value;
}

function applySelectedFixes(PDO $pdo, array $selected, array $replacements): int
{
    $updated = 0;

    if ($selected === []) {
        return 0;
    }

    $pdo->beginTransaction();

    try {
        foreach ($selected as $token) {
            if (!isset($replacements[$token])) {
                continue;
            }

            $parts = explode('|', (string) $token, 4);

            if (count($parts) !== 4) {
                continue;
            }

            [$table, $pkColumn, $pkValue, $column] = $parts;

            $sql = "
                UPDATE `{$table}`
                SET `{$column}` = :value
                WHERE `{$pkColumn}` = :pk
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'value' => (string) $replacements[$token],
                'pk' => $pkValue,
            ]);

            $updated += $stmt->rowCount() > 0 ? 1 : 0;
        }

        $pdo->commit();

        return $updated;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Review HTML Entities</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f7f7f7;
            color: #222;
        }
        h1 {
            margin-bottom: 8px;
        }
        .muted {
            color: #666;
            margin-bottom: 20px;
        }
        .box {
            background: #fff;
            border: 1px solid #ddd;
            padding: 16px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
        }
        .field {
            display: flex;
            flex-direction: column;
            min-width: 220px;
            flex: 1 1 220px;
        }
        label {
            font-size: 13px;
            margin-bottom: 6px;
            color: #444;
        }
        select, input[type="text"], textarea {
            padding: 8px 10px;
            border: 1px solid #bbb;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
        }
        .actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        button {
            padding: 10px 14px;
            border: 0;
            border-radius: 6px;
            background: #0d6efd;
            color: #fff;
            cursor: pointer;
        }
        button.secondary {
            background: #6c757d;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .alert-success {
            background: #e9f7ef;
            border: 1px solid #b7e1c1;
            color: #1f6f3d;
        }
        .alert-error {
            background: #fdecec;
            border: 1px solid #f5b5b5;
            color: #9f2d2d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            border: 1px solid #ddd;
            vertical-align: top;
            padding: 8px;
            font-size: 13px;
        }
        th {
            background: #f0f0f0;
            text-align: left;
        }
        .mono {
            font-family: Consolas, Monaco, monospace;
            white-space: pre-wrap;
            word-break: break-word;
            background: #fafafa;
        }
        .preview {
            white-space: pre-wrap;
            word-break: break-word;
            background: #fffef6;
            min-height: 44px;
        }
        .replace-area {
            min-height: 100px;
            font-family: Consolas, Monaco, monospace;
        }
        .tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eee;
            font-size: 12px;
        }
        .tag-warn {
            background: #fff3cd;
            color: #7a5d00;
        }
        .small {
            font-size: 12px;
            color: #666;
        }
        .sticky {
            position: sticky;
            top: 0;
            z-index: 5;
        }
    </style>
</head>
<body>

<h1>Review HTML Entities</h1>
<div class="muted">
    Outil temporaire de revue visuelle des entités HTML en base.  
    Utilise-le pour décider quoi corriger, notamment pour les cas sensibles comme <code>&amp;nbsp;</code>.
</div>

<?php if ($message !== null): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error !== null): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="box">
    <form method="GET">
        <input type="hidden" name="action" value="search">

        <div class="row">
            <div class="field">
                <label>Table</label>
                <select name="table" id="table-select" required>
                    <option value="">-- choisir --</option>
                    <?php foreach ($tables as $tableName => $columns): ?>
                        <option value="<?= e($tableName) ?>" <?= $table === $tableName ? 'selected' : '' ?>>
                            <?= e($tableName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Colonne</label>
                <select name="column" id="column-select" required></select>
            </div>

            <div class="field">
                <label>Filtre entité (optionnel)</label>
                <input type="text" name="entity" value="<?= e($entityFilter) ?>" placeholder="ex: &nbsp; ou &eacute;">
            </div>

            <div class="field" style="justify-content: flex-end;">
                <label>
                    <input type="checkbox" name="nbsp_only" value="1" <?= $containsNbspOnly === 1 ? 'checked' : '' ?>>
                    Seulement les lignes contenant &amp;nbsp;
                </label>
            </div>
        </div>

        <div class="actions">
            <button type="submit">Analyser</button>
        </div>
    </form>
</div>

<?php if ($results !== []): ?>
    <form method="POST">
        <input type="hidden" name="action" value="apply">
        <input type="hidden" name="table" value="<?= e($table) ?>">
        <input type="hidden" name="column" value="<?= e($column) ?>">
        <input type="hidden" name="entity" value="<?= e($entityFilter) ?>">
        <input type="hidden" name="nbsp_only" value="<?= $containsNbspOnly ?>">

        <div class="box">
            <div class="actions">
                <button type="button" class="secondary" onclick="toggleAll(true)">Tout cocher</button>
                <button type="button" class="secondary" onclick="toggleAll(false)">Tout décocher</button>
                <button type="submit">Appliquer les lignes cochées</button>
            </div>
        </div>

        <table>
            <thead class="sticky">
                <tr>
                    <th style="width: 60px;">OK</th>
                    <th style="width: 120px;">PK</th>
                    <th style="width: 140px;">Infos</th>
                    <th>Valeur brute</th>
                    <th>Brute visible</th>
                    <th>Rendu HTML actuel</th>
                    <th>Version décodée</th>
                    <th>Décodée visible</th>
                    <th>Valeur à enregistrer</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $row): ?>
                <?php
                $token = $row['table'] . '|id|' . $row['pk'] . '|' . $row['column'];
                ?>
                <tr>
                    <td>
                        <input type="checkbox" name="selected[]" value="<?= e($token) ?>">
                    </td>
                    <td class="mono"><?= e($row['pk']) ?></td>
                    <td>
                        <div><strong><?= e($row['table']) ?></strong></div>
                        <div class="small"><?= e($row['column']) ?></div>
                        <?php if ($row['has_nbsp']): ?>
                            <div class="tag tag-warn">contient &nbsp;</div>
                        <?php endif; ?>
                    </td>
                    <td class="mono"><?= e($row['original']) ?></td>
                    <td class="mono"><?= e($row['original_visible']) ?></td>
                    <td class="preview"><?= $row['original'] ?></td>
                    <td class="mono"><?= e($row['decoded']) ?></td>
                    <td class="mono"><?= e($row['decoded_visible']) ?></td>
                    <td>
                        <textarea class="replace-area" name="replacement[<?= e($token) ?>]"><?= e($row['decoded']) ?></textarea>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form>
<?php elseif ($table !== '' && $column !== '' && $error === null): ?>
    <div class="box">
        Aucun résultat trouvé.
    </div>
<?php endif; ?>

<script>
    const tableColumns = <?= json_encode($tables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const selectedTable = <?= json_encode($table, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const selectedColumn = <?= json_encode($column, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function fillColumns(tableName, selected) {
        const select = document.getElementById('column-select');
        select.innerHTML = '<option value="">-- choisir --</option>';

        if (!tableName || !tableColumns[tableName]) {
            return;
        }

        tableColumns[tableName].forEach(function (col) {
            const option = document.createElement('option');
            option.value = col;
            option.textContent = col;

            if (selected === col) {
                option.selected = true;
            }

            select.appendChild(option);
        });
    }

    document.getElementById('table-select').addEventListener('change', function () {
        fillColumns(this.value, '');
    });

    fillColumns(selectedTable, selectedColumn);

    function toggleAll(state) {
        document.querySelectorAll('input[name="selected[]"]').forEach(function (checkbox) {
            checkbox.checked = state;
        });
    }
</script>

</body>
</html>