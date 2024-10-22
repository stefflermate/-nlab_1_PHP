<?php
require_once(__DIR__ . '/../config.php');
require_login();

echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Kurzusok listája</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
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
<body>
    <h1>Kurzusok listája</h1>";

try {
    // Kurzusok lekérdezése
    $courses = $DB->get_records_sql("
        SELECT id, fullname, shortname, category, startdate, enddate
        FROM {course}
        WHERE id != 1
        ORDER BY fullname
    ");

    if ($courses) {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Teljes név</th>
                <th>Rövid név</th>
                <th>Kategória</th>
                <th>Kezdés dátuma</th>
                <th>Befejezés dátuma</th>
            </tr>";

        foreach ($courses as $course) {
            echo "<tr>
                <td>" . $course->id . "</td>
                <td>" . htmlspecialchars($course->fullname) . "</td>
                <td>" . htmlspecialchars($course->shortname) . "</td>
                <td>" . $course->category . "</td>
                <td>" . userdate($course->startdate) . "</td>
                <td>" . userdate($course->enddate) . "</td>
            </tr>";
        }

        echo "</table>";
    } else {
        echo "<p>Nincsenek kurzusok az adatbázisban.</p>";
    }

} catch (dml_exception $e) {
    echo "<p>Hiba történt az adatbázis lekérdezése során: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>