<?php
// Параметры подключения к базе данных
$servername = "localhost"; // Имя сервера базы данных 
$username = "root";        // Имя пользователя базы данных
$password = "";            // Пароль пользователя базы данных (пустой, если не задан)
$dbname = "mybasesql";     // Имя базы данных, к которой подключаемся

try {
    // Создаем новый объект PDO для подключения к MySQL с указанными параметрами
    // Формат DSN: mysql:host=адрес_сервера;dbname=имя_базы_данных
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    
    // Устанавливаем режим обработки ошибок — исключения (PDOException)
    // Это удобно для отлова и обработки ошибок при работе с базой
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Если при подключении произошла ошибка — ловим исключение и выводим сообщение об ошибке
    echo "Connection failed: " . $e->getMessage();
}
?>
