<?php
require_once(__DIR__ . '/../config.php');
require_login();

echo "<!DOCTYPE html>
<html lang='hu'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Felhasználók listája</title>
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
    <h1>Felhasználók listája</h1>";

try {
    $users = $DB->get_records_sql("
        SELECT id, username, CONCAT(firstname, ' ', lastname) AS fullname, email
        FROM {user}
        WHERE id != 1
        ORDER BY id 
    ");

    if ($users) {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Felhasználónév</th>
                <th>Név</th>
                <th>Email</th>
            </tr>";

        foreach ($users as $user) {
            echo "<tr>
                <td>" . $user->id . "</td>
                <td>" . htmlspecialchars($user->username) . "</td>
                <td>" . htmlspecialchars($user->fullname) . "</td>
                <td>" . htmlspecialchars($user->email) . "</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nincs felhasználói adat vagy a lekérdezés nem adott eredményt.</p>";
    }

    // Debug információ
    echo "<pre>Debug:\n";
    print_r($users);
    echo "</pre>";

} catch (dml_exception $e) {
    echo "<p>Hiba történt az adatbázis lekérdezése során: " . $e->getMessage() . "</p>";
} 

echo "</body></html>";
?>