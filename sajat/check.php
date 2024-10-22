<?php
require_once(__DIR__ . '/../config.php');
require_login();

global $DB, $CFG;

// Használjuk a Moodle által definiált prefixet
$table_name = $CFG->prefix . 'diak_check';

// Naplózás funkció
function log_message($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/debug.log');
}

log_message("Szkript indítása");

// Tábla létezésének ellenőrzése
if (!$DB->get_manager()->table_exists($table_name)) {
    echo "<p style='color: red;'>Hiba: A '{$table_name}' tábla nem létezik az adatbázisban.</p>";
    echo "<p>Adatbázis részletek: " . $CFG->dbtype . " @ " . $CFG->dbhost . "</p>";
    log_message("Hiba: A '{$table_name}' tábla nem létezik");
    die();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_message("POST kérés érkezett");
    
    $student_id = required_param('student_id', PARAM_INT);
    $email = required_param('email', PARAM_TEXT);
    $check_in = optional_param('check_in', 0, PARAM_INT);
    $phone_number = optional_param('phone_number', '', PARAM_TEXT);
    $megjegyzes = optional_param('megjegyzes', '', PARAM_TEXT);

    log_message("Beérkezett adatok: " . json_encode($_POST));

    $record = new stdClass();
    $record->student_id = $student_id;
    $record->email = $email;
    $record->check_in = $check_in ? 1 : 0;
    $record->phone_number = $phone_number;
    $record->megjegyzes = $megjegyzes;

    log_message("Beszúrandó rekord: " . json_encode($record));

    try {
        $insert_id = $DB->insert_record($table_name, $record);
        if ($insert_id) {
            log_message("Sikeres beszúrás, ID: " . $insert_id);
            $message = "<p style='color: green;'>Az adatok sikeresen elmentve! (ID: $insert_id)</p>";
        } else {
            log_message("Beszúrás sikertelen, nincs visszatérési érték");
            $message = "<p style='color: red;'>Hiba: Az adatok mentése sikertelen volt.</p>";
        }
    } catch (dml_exception $e) {
        log_message("Kivétel a beszúrás során: " . $e->getMessage());
        $error_details = $DB->get_last_error();
        $message = "<p style='color: red;'>Hiba történt az adatok mentése során:</p>";
        $message .= "<p>Hibaüzenet: " . $e->getMessage() . "</p>";
        $message .= "<p>SQL hiba: " . $error_details . "</p>";
    }
}

echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diák ellenőrzési űrlap</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        input[type='text'], input[type='email'], input[type='tel'], textarea {
            width: 100%; padding: 5px; box-sizing: border-box;
        }
        input[type='submit'] { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; }
        input[type='submit']:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <h1>Diák ellenőrzési űrlap</h1>
    $message
    <table>
        <tr>
            <th>ID</th>
            <th>Név</th>
            <th>E-mail</th>
            <th>Bejelentkezett</th>
            <th>Telefonszám</th>
            <th>Megjegyzés</th>
            <th>Művelet</th>
        </tr>";

// Diákok lekérdezése
$students = $DB->get_records_sql("
    SELECT id, username
    FROM {user}
    WHERE id != 1 AND deleted = 0
    ORDER BY id
");

foreach ($students as $student) {
    echo "<tr>
        <form method='post'>
            <input type='hidden' name='student_id' value='{$student->id}'>
            <td>{$student->id}</td>
            <td>" . htmlspecialchars($student->username) . "</td>
            <td><input type='email' name='email' required></td>
            <td><input type='checkbox' name='check_in' value='1'></td>
            <td><input type='tel' name='phone_number' pattern='[0-9+\s-]+' title='Adjon meg egy érvényes telefonszámot'></td>
            <td><textarea name='megjegyzes'></textarea></td>
            <td><input type='submit' value='Mentés'></td>
        </form>
    </tr>";
}

echo "</table>
</body>
</html>";

log_message("Szkript befejezése");
?>