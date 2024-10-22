<?php
require_once(__DIR__ . '/../config.php');
require_login();

echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Kvíz válaszok diákonként és kérdésenként</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>";

try {
    echo "<div class='navigation'>";
    echo "<a href='?'>Kurzusok listája</a>";
    if (isset($_GET['course_id'])) {
        echo " | <a href='?course_id=" . intval($_GET['course_id']) . "'>Vissza a tesztekhez</a>";
    }
    echo "</div>";

    if (isset($_GET['quiz_id'])) {
        $quiz_id = intval($_GET['quiz_id']);
        
        echo "<h1>Választott teszt válaszai diákonként és kérdésenként</h1>";
        
        // Lekérdezzük az összes kérdést a tesztben
        $questions = $DB->get_records_sql("
            SELECT q.id, q.questiontext, q.qtype, qs.slot
            FROM {quiz_slots} qs
            JOIN {question} q ON qs.questionid = q.id
            WHERE qs.quizid = ?
            ORDER BY qs.slot
        ", array($quiz_id));
        
        // Lekérdezzük az összes diákot, aki kitöltötte a tesztet
        $students = $DB->get_records_sql("
            SELECT DISTINCT u.id, u.username
            FROM {quiz_attempts} qa
            JOIN {user} u ON qa.userid = u.id
            WHERE qa.quiz = ? AND qa.state = 'finished'
            ORDER BY u.id
        ", array($quiz_id));
        
        foreach ($students as $student) {
            echo "<h2>Diák: " . htmlspecialchars($student->username) . " (ID: " . $student->id . ")</h2>";
            echo "<table>
                <tr>
                    <th>Kérdés sorszáma</th>
                    <th>Kérdés</th>
                    <th>Válasz</th>
                    <th>Helyes válasz</th>
                </tr>";
            
            foreach ($questions as $question) {
                // Lekérdezzük a diák válaszát az adott kérdésre
                $response = $DB->get_record_sql("
                    SELECT qas.responsesummary, qas.rightanswer
                    FROM {quiz_attempts} qa
                    JOIN {question_attempts} qas ON qas.questionusageid = qa.uniqueid
                    WHERE qa.quiz = ? AND qa.userid = ? AND qa.state = 'finished' AND qas.slot = ?
                    ORDER BY qa.attempt DESC
                    LIMIT 1
                ", array($quiz_id, $student->id, $question->slot));
                
                echo "<tr>";
                echo "<td>" . $question->slot . "</td>";
                echo "<td>" . htmlspecialchars(strip_tags($question->questiontext)) . "</td>";
                if ($response && $response->responsesummary !== null) {
                    echo "<td>" . htmlspecialchars($response->responsesummary) . "</td>";
                } else {
                    echo "<td>Nincs válasz</td>";
                }
                echo "<td>" . htmlspecialchars($response->rightanswer ?? 'Nincs megadva') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    }
    // Tesztek listázása egy kurzushoz
    else if (isset($_GET['course_id'])) {
        $course_id = intval($_GET['course_id']);
        
        $quizzes = $DB->get_records_sql("
            SELECT q.id, q.name
            FROM {quiz} q
            WHERE q.course = ?
            ORDER BY q.id
        ", array($course_id));

        if ($quizzes) {
            echo "<h1>A kiválasztott kurzus tesztjei</h1>";
            echo "<table>
                <tr>
                    <th>ID</th>
                    <th>Teszt név</th>
                    <th>Kiválasztás</th>
                </tr>";
            foreach ($quizzes as $quiz) {
                echo "<tr>
                    <td>" . $quiz->id . "</td>
                    <td>" . htmlspecialchars($quiz->name) . "</td>
                    <td><a href='?course_id=" . $course_id . "&quiz_id=" . $quiz->id . "'>Kiválaszt</a></td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Nincsenek tesztek a kiválasztott kurzushoz.</p>";
        }
    }
    // Kurzusok listázása
    else {
        $courses = $DB->get_records_sql("
            SELECT c.id, c.fullname
            FROM {course} c
            WHERE c.id != 1
            ORDER BY c.fullname
        ");

        if ($courses) {
            echo "<h1>Kurzusok listája</h1>";
            echo "<table>
                <tr>
                    <th>ID</th>
                    <th>Kurzus név</th>
                    <th>Kiválasztás</th>
                </tr>";
            foreach ($courses as $course) {
                echo "<tr>
                    <td>" . $course->id . "</td>
                    <td>" . htmlspecialchars($course->fullname) . "</td>
                    <td><a href='?course_id=" . $course->id . "'>Kiválaszt</a></td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Nincsenek kurzusok az adatbázisban.</p>";
        }
    }

} catch (dml_exception $e) {
    echo "<p>Hiba történt az adatbázis lekérdezése során: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>