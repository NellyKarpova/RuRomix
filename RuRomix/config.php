<?php
$servername = "134.90.167.42";
$port = 10306;
$username = "Karpova";
$password = "9TkG_K";
$dbname = "project_Karpova";

try {
    // Пробуем разные варианты подключения PDO
    $connection_strings = [
        "mysql:host=$servername;port=$port;dbname=$dbname;charset=utf8mb4",
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        "mysql:host=127.0.0.1;port=$port;dbname=$dbname;charset=utf8mb4"
    ];
    
    $pdo = null;
    $last_error = '';
    
    foreach ($connection_strings as $connection_string) {
        try {
            $pdo = new PDO($connection_string, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            break;
        } catch (PDOException $e) {
            $last_error = $e->getMessage();
            continue; 
        }
    }
    
    if ($pdo === null) {
        throw new PDOException("Не удалось подключиться к базе данных. Последняя ошибка: " . $last_error);
    }
    
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>