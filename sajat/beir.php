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

// Csoport hozzárendelések definiálása
$group_mappings = [
    '1' => ['course_id' => 7, 'group_id' => 6],  // Mérnőkinfo BSc
    '2' => ['course_id' => 7, 'group_id' => 7],  // Villamosmérnök BSc
    '3' => ['course_id' => 8, 'group_id' => 8],  // Mérnökinfo MSc
    '4' => ['course_id' => 8, 'group_id' => 9],  // Gazdinfo MSc
    '5' => ['course_id' => 8, 'group_id' => 10], // Űrmérnök MSc
    '6' => ['course_id' => 9, 'group_id' => 12], // Mérnökinfo PhD
    '7' => ['course_id' => 9, 'group_id' => 13], // Villamosmérnök PhD
    '8' => ['course_id' => 9, 'group_id' => 14]  // Gazdinfo PhD
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
    
    // Függvény a beiratkozás és csoportba sorolás kezelésére
    function handle_enrollment($student_id, $response) {
        global $DB, $course_ids, $group_mappings;
        
        if (empty($response) || $response === 'Nincs válasz') {
            return "Nincs mentés, mert nincs válasz!";
        }
        
        // Az első három karakter kinyerése a program típushoz
        $program = substr(trim($response), 0, 3);
        
        // Az 5. karakter kinyerése a csoporthoz
        $group_number = substr(trim($response), 4, 1);
        
        // Ellenőrizzük, hogy érvényes program-e
        if (!array_key_exists($program, $course_ids)) {
            return "Érvénytelen válasz formátum! A válasznak 'Bsc', 'Msc' vagy 'Phd'-vel kell kezdődnie.";
        }
        
        // Ellenőrizzük, hogy érvényes csoport szám-e
        if (!array_key_exists($group_number, $group_mappings)) {
            return "Érvénytelen csoport szám!";
        }
        
        $course_id = $course_ids[$program];
        $group_info = $group_mappings[$group_number];
        
        // Ellenőrizzük, hogy a kurzus és csoport egyezik-e
        if ($course_id !== $group_info['course_id']) {
            return "A választott csoport nem tartozik a megjelölt képzési szinthez!";
        }
        
        // Ellenőrizzük, hogy már be van-e iratkozva az adott kurzusra
        $is_enrolled = $DB->record_exists_sql("
            SELECT 1 
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE ue.userid = ?
            AND e.courseid = ?
        ", array($student_id, $course_id));
        
        if (!$is_enrolled) {
            // Beiratkoztatás a kurzusra
            $enrol = enrol_get_plugin('manual');
            $instance = $DB->get_record('enrol', ['courseid' => $course_id, 'enrol' => 'manual']);
            if ($instance) {
                $enrol->enrol_user($instance, $student_id, 5); // 5 = student role
            } else {
                return "Hiba: A kurzusra történő beiratkoztatás sikertelen!";
            }
        }
        
        // Csoportba sorolás
        try {
            // Ellenőrizzük, hogy már tagja-e a csoportnak
            $is_group_member = $DB->record_exists('groups_members', [
                'groupid' => $group_info['group_id'],
                'userid' => $student_id
            ]);
            
            if (!$is_group_member) {
                // Hozzáadjuk a csoporthoz
                $group_member = new stdClass();
                $group_member->groupid = $group_info['group_id'];
                $group_member->userid = $student_id;
                $group_member->timeadded = time();
                
                $DB->insert_record('groups_members', $group_member);
                return "Sikeres beiratkoztatás és csoportba sorolás!";
            } else {
                return "A hallgató már tagja a kiválasztott csoportnak!";
            }
        } catch (Exception $e) {
            return "Hiba: A csoportba sorolás sikertelen! " . $e->getMessage();
        }
    }
    
    if (isset($_POST['save_all'])) {
        // Kijelölt hallgatók mentése
        if (isset($_POST['selected_students'])) {
            foreach ($_POST['selected_students'] as $student_id) {
                if (isset($students[$student_id])) {
                    $response = $DB->get_field_sql("
                        SELECT qas.responsesummary
                        FROM {quiz_attempts} qa
                        JOIN {question_attempts} qas ON qas.questionusageid = qa.uniqueid
                        WHERE qa.quiz = ? AND qa.userid = ? AND qa.state = 'finished'
                        ORDER BY qa.attempt DESC
                        LIMIT 1
                    ", array($enrollment_quiz->id, $student_id));
                    
                    $student = $students[$student_id];
                    $message .= htmlspecialchars($student->lastname . ' ' . $student->firstname) . ': ' 
                             . handle_enrollment($student_id, $response) . "<br>";
                }
            }
        } else {
            $message = "Kérem válasszon ki legalább egy hallgatót!";
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
        .select-all-container {
            margin: 10px 0;
        }
        .single-save-btn {
            padding: 5px 10px;
            margin-left: 10px;
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
            <div class='select-all-container'>
                <label>
                    <input type='checkbox' id='selectAll' onclick='toggleAll(this);'> 
                    Összes kijelölése
                </label>
            </div>
            <table>
                <tr>
                    <th>Kijelölés</th>
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
            <input type='checkbox' name='selected_students[]' value='" . $student->id . "' class='student-checkbox'>
        </td>
        <td>" . htmlspecialchars($student->lastname . ' ' . $student->firstname) . "</td>
        <td class='status-icon $status_class'>" . $status_icon . "</td>
        <td>" . htmlspecialchars($test_response) . "</td>
        <td>
            <input type='submit' 
                   name='save_single[" . $student->id . "]' 
                   value='Mentés' 
                   class='single-save-btn'
                   onclick='return confirmSave(\"" . htmlspecialchars($student->lastname . ' ' . $student->firstname) . "\");'>
        </td>
    </tr>";
}

echo "</table>
            <div class='save-all-container'>
                <input type='submit' 
                       name='save_all' 
                       value='Kijelöltek mentése' 
                       class='save-all-btn'
                       onclick='return confirmSaveSelected();'>
            </div>
        </form>
    </div>

    <script>
    function confirmSave(studentName) {
        return confirm('Biztosan menti ' + studentName + ' adatait?');
    }

    function confirmSaveSelected() {
        var checkedBoxes = document.querySelectorAll('input[name=\"selected_students[]\"]');
        var checkedCount = 0;
        for (var i = 0; i < checkedBoxes.length; i++) {
            if (checkedBoxes[i].checked) checkedCount++;
        }
        if (checkedCount === 0) {
            alert('Kérem válasszon ki legalább egy hallgatót!');
            return false;
        }
        return confirm('Biztosan menti a kijelölt ' + checkedCount + ' hallgató adatait?');
    }

    function toggleAll(source) {
        var checkboxes = document.getElementsByClassName('student-checkbox');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }
    </script>
</body>
</html>";
?>