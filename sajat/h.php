<?php
require_once(__DIR__ . '/../config.php');
require_login();

global $DB, $CFG;

$table_name = 'diak_check';

function log_message($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/debug.log');
}

function is_valid_phone($phone) {
    return empty($phone) || preg_match('/^[0-9+\-\s()]{6,20}$/', $phone);
}

log_message("Szkript indítása");

$message = '';

// POST adatok feldolgozása csak ha van form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_button'])) {
    log_message("POST kérés érkezett");
    
    $student_id = required_param('student_id', PARAM_INT);
    $email = required_param('email', PARAM_EMAIL);
    $check_in = optional_param('check_in', 0, PARAM_INT);
    $phone_number = optional_param('phone_number', '', PARAM_TEXT);
    $megjegyzes = optional_param('megjegyzes', '', PARAM_TEXT);

    $errors = array();
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Érvénytelen email cím formátum";
    }
    
    if (!empty($phone_number) && !is_valid_phone($phone_number)) {
        $errors[] = "Érvénytelen telefonszám formátum";
    }

    if (empty($errors)) {
        $record = new stdClass();
        $record->student_id = $student_id;
        $record->email = $email;
        $record->check_in = $check_in ? 1 : 0;
        $record->phone_number = $phone_number;
        $record->megjegyzes = $megjegyzes;

        try {
            // Először ellenőrizzük, hogy van-e már rekord ehhez a student_id-hoz
            $existing_record = $DB->get_record($table_name, array('student_id' => $student_id));
            
            if ($existing_record) {
                // Ha van, akkor update
                $record->id = $existing_record->id;
                $success = $DB->update_record($table_name, $record);
                $action = "frissítve";
            } else {
                // Ha nincs, akkor insert
                $insert_id = $DB->insert_record($table_name, $record);
                $success = $insert_id ? true : false;
                $action = "mentve";
            }

            if ($success) {
                log_message("Sikeres művelet: " . $action);
                $message = "<div class='alert alert-success'>Az adatok sikeresen {$action}!</div>";
            } else {
                log_message("Művelet sikertelen");
                $message = "<div class='alert alert-danger'>Hiba: Az adatok mentése sikertelen volt.</div>";
            }
        } catch (dml_exception $e) {
            log_message("Kivétel: " . $e->getMessage());
            $message = "<div class='alert alert-danger'>Hiba történt: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>" . implode("<br>", $errors) . "</div>";
    }
}

// Meglévő rekordok lekérdezése
$existing_records = $DB->get_records($table_name, array(), '', '*');
$student_data = array();
foreach ($existing_records as $record) {
    $student_data[$record->student_id] = $record;
}

echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diák ellenőrzési űrlap</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-bottom: 20px;
            background-color: white;
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px 8px; 
            text-align: left;
        }
        th { 
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        input[type='text'], 
        input[type='email'], 
        input[type='tel'], 
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type='submit'] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type='submit']:hover {
            background-color: #45a049;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .existing-data {
            background-color: #e8f4f8;
        }
    </style>
</head>
<body>
    <div class='container'>
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
    SELECT id, username, firstname, lastname
    FROM {user}
    WHERE id != 1 
    AND deleted = 0
    ORDER BY lastname, firstname
");

foreach ($students as $student) {
    $full_name = htmlspecialchars($student->lastname . ' ' . $student->firstname);
    $existing_data = isset($student_data[$student->id]) ? $student_data[$student->id] : null;
    $row_class = $existing_data ? ' class="existing-data"' : '';
    
    echo "<tr$row_class>
        <form method='post' onsubmit='return validateForm(this);'>
            <input type='hidden' name='student_id' value='{$student->id}'>
            <td>{$student->id}</td>
            <td>$full_name</td>
            <td><input type='email' name='email' required pattern='[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$' 
                value='" . ($existing_data ? htmlspecialchars($existing_data->email) : '') . "'></td>
            <td><input type='checkbox' name='check_in' value='1'" . 
                ($existing_data && $existing_data->check_in ? ' checked' : '') . "></td>
            <td><input type='tel' name='phone_number' pattern='[0-9+\-\s()]{6,20}' 
                value='" . ($existing_data ? htmlspecialchars($existing_data->phone_number) : '') . "'></td>
            <td><textarea name='megjegyzes' maxlength='1000'>" . 
                ($existing_data ? htmlspecialchars($existing_data->megjegyzes) : '') . "</textarea></td>
            <td><input type='submit' name='submit_button' value='Mentés'></td>
        </form>
    </tr>";
}

echo "</table>
    </div>
    <script>
    function validateForm(form) {
        const email = form.email.value;
        const phone = form.phone_number.value;
        
        if (!email.match(/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/)) {
            alert('Kérjük, adjon meg egy érvényes email címet!');
            return false;
        }
        
        if (phone && !phone.match(/^[0-9+\-\s()]{6,20}$/)) {
            alert('Kérjük, adjon meg egy érvényes telefonszámot!');
            return false;
        }
        
        return true;
    }
    </script>
</body>
</html>";

log_message("Szkript befejezése");
?>