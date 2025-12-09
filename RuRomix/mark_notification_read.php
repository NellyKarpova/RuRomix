<?php
include 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Необходима авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неправильный метод запроса']);
    exit;
}

$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($notification_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID уведомления']);
    exit;
}

try {
    // Проверяем, что уведомление принадлежит пользователю
    $stmt = $pdo->prepare("UPDATE Notifications SET is_read = 1 WHERE ID = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Уведомление не найдено']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
?>