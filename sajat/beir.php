<?php
require_once(__DIR__ . '/../config.php');
require_login();

global $DB;

// Beiratkozási teszt azonosítójának lekérdezése a 6-os kurzushoz
$enrollment_quiz = $DB->get_record_sql("
    SELECT q.id
    FROM {quiz} q
    WHERE q.course = ? AND q.name LIKE '%beiratkoz%'
    LIMIT 1
", array(6));

// Fix kurzus ID-k meghatározása
$course_ids = [
    'Bsc' => 7,  // 7-es ID a Bsc kurzushoz
    'Msc' => 8,  // 8-as ID az Msc kurzushoz
    'Phd' => 9   // 9-es ID a Phd kurzushoz
];

// Csak a diákok lekérdezése
$students = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.firstname, u.lastname 
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {context} ctx ON ctx.id = ra.contextid
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname = 'student'
    AND u.deleted = 0
    AND u.id != 1
    ORDER BY u.lastname, u.firstname
");

// POST kérések kezelése
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = '';
    
    // Függvény a beiratkozás kezelésére
    function handle_enrollment($student_id, $response) {
        global $DB, $course_ids;
        
        if (empty($response) || $response === 'Nincs válasz') {
            return "Nincs mentés, mert nincs válasz!";
        }
        
        // Az első három karakter kinyerése
        $program = substr(trim($response), 0, 3);
        
        // Ellenőrizzük, hogy érvényes program-e
        if (!array_key_exists($program, $course_ids)) {
            return "Érvénytelen válasz formátum! A válasznak 'Bsc', 'Msc' vagy 'Phd'-vel kell kezdődnie.";
        }
        
        $course_id = $course_ids[$program];
        
        // Ellenőrizzük, hogy már be van-e iratkozva az adott kurzusra
        $is_enrolled = $DB->record_exists_sql("
            SELECT 1 
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE ue.userid = ?
            AND e.courseid = ?
        ", array($student_id, $course_id));
        
        if ($is_enrolled) {
            return "A hallgató már be van iratkozva a $program kurzusra!";
        }
        
        // Beiratkoztatás
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course_id, 'enrol' => 'manual']);
        if ($instance) {
            $enrol->enrol_user($instance, $student_id, 5); // 5 = student role
            return "Sikeres beiratkoztatás a $program kurzusra!";
        }
        
        return "Hiba: A beiratkoztatás sikertelen!";
    }
    
    if (isset($_POST['save_all'])) {
        // Összes mentése
        foreach ($students as $student) {
            $response = $DB->get_field_sql("
                SELECT qas.responsesummary
                FROM {quiz_attempts} qa
                JOIN {question_attempts} qas ON qas.questionusageid = qa.uniqueid
                WHERE qa.quiz = ? AND qa.userid = ? AND qa.state = 'finished'
                ORDER BY qa.attempt DESC
                LIMIT 1
            ", array($enrollment_quiz->id, $student->id));
            
            $message .= htmlspecialchars($student->lastname . ' ' . $student->firstname) . ': ' 
                     . handle_enrollment($student->id, $response) . "<br>";
        }
    } elseif (isset($_POST['save_single'])) {
        // Egyedi mentés
        $student_id = key($_POST['save_single']);
        $response = $DB->get_field_sql("
            SELECT qas.responsesummary
            FROM {quiz_attempts} qa
            JOIN {question_attempts} qas ON qas.questionusageid = qa.uniqueid
            WHERE qa.quiz = ? AND qa.userid = ? AND qa.state = 'finished'
            ORDER BY qa.attempt DESC
            LIMIT 1
        ", array($enrollment_quiz->id, $student_id));
        
        $student = $students[$student_id];
        $message = htmlspecialchars($student->lastname . ' ' . $student->firstname) . ': ' 
                . handle_enrollment($student_id, $response);
    }
}

// HTML rész
echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Beiratkozás ellenőrzés</title>
    <link rel='stylesheet' href='css.css'>
    <style>
        .status-icon {
            font-weight: bold;
            font-size: 18px;
        }
        .enrolled {
            color: #4CAF50;
        }
        .not-enrolled {
            color: #dc3545;
        }
        .save-all-container {
            text-align: center;
            margin-top: 20px;
        }
        .save-all-btn {
            padding: 12px 24px;
            font-size: 16px;
        }
        .message {
            background-color: #f8f9fa;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class='container'>";

// Üzenet megjelenítése, ha van
if (!empty($message)) {
    echo "<div class='message'>$message</div>";
}

echo "<h1>Beiratkozás ellenőrzés</h1>
        <form id='mainForm' method='post'>
            <table>
                <tr>
                    <th>Név</th>
                    <th>Beiratkozott</th>
                    <th>Beiratkozási teszt válasza</th>
                    <th>Műveletek</th>
                </tr>";

foreach ($students as $student) {
    // Beiratkozási teszt válaszának lekérdezése
    $test_response = '';
    if ($enrollment_quiz) {
        $response = $DB->get_record_sql("
            SELECT qas.responsesummary
            FROM {quiz_attempts} qa
            JOIN {question_attempts} qas ON qas.questionusageid = qa.uniqueid
            WHERE qa.quiz = ? AND qa.userid = ? AND qa.state = 'finished'
            ORDER BY qa.attempt DESC
            LIMIT 1
        ", array($enrollment_quiz->id, $student->id));
        
        $test_response = $response ? $response->responsesummary : 'Nincs válasz';
    }
    
    // Alapértelmezetten X (nincs válasz vagy érvénytelen válasz)
    $is_enrolled = false;
    
    // Ha van válasz, ellenőrizzük a megfelelő kurzust
    if ($test_response && $test_response !== 'Nincs válasz') {
        $program = substr(trim($test_response), 0, 3);
        if (array_key_exists($program, $course_ids)) {
            $course_id = $course_ids[$program];
            // Javított lekérdezés a beiratkozás ellenőrzéséhez
            $is_enrolled = $DB->record_exists_sql("
                SELECT 1 
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.userid = ?
                AND e.courseid = ?
            ", array($student->id, $course_id));
        }
    }
    
    $status_class = $is_enrolled ? 'enrolled' : 'not-enrolled';
    $status_icon = $is_enrolled ? '✓' : '✗';
    
    echo "<tr>
        <td>
            <input type='hidden' name='student_ids[]' value='" . $student->id . "'>
            " . htmlspecialchars($student->lastname . ' ' . $student->firstname) . "
        </td>
        <td class='status-icon $status_class'>" . $status_icon . "</td>
        <td>" . htmlspecialchars($test_response) . "</td>
        <td>
            <input type='submit' 
                   name='save_single[" . $student->id . "]' 
                   value='Mentés' 
                   onclick='return confirmSave(\"" . htmlspecialchars($student->lastname . ' ' . $student->firstname) . "\");'>
        </td>
    </tr>";
}

echo "</table>
            <div class='save-all-container'>
                <input type='submit' 
                       name='save_all' 
                       value='Összes mentése' 
                       class='save-all-btn'
                       onclick='return confirmSaveAll();'>
            </div>
        </form>
    </div>

    <script>
    function confirmSave(studentName) {
        return confirm('Biztosan menti ' + studentName + ' adatait?');
    }

    function confirmSaveAll() {
        return confirm('Biztosan menti az összes diák adatait?');
    }
    </script>
</body>
</html>";
?>