<?php
require_once 'config.php';
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

$subscriber_id = $_SESSION['user_id'];
$target_user_id = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($target_user_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID пользователя']);
    exit;
}

// Нельзя подписаться на себя
if ($subscriber_id === $target_user_id) {
    echo json_encode(['success' => false, 'message' => 'Нельзя подписаться на себя']);
    exit;
}

try {
    if ($action === 'subscribe') {
        // Подписываемся
        $stmt = $pdo->prepare("INSERT INTO Subscriptions (subscriber_id, target_user_id) VALUES (?, ?)");
        $stmt->execute([$subscriber_id, $target_user_id]);
        
        // Создаем уведомление для пользователя, на которого подписались
        $stmt = $pdo->prepare("
            INSERT INTO Notifications (user_id, type, source_id, message) 
            VALUES (?, 'new_subscriber', ?, ?)
        ");
        
        // Получаем имя подписчика для уведомления
        $stmt_user = $pdo->prepare("SELECT Username FROM Users WHERE ID = ?");
        $stmt_user->execute([$subscriber_id]);
        $subscriber = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        $message = "Пользователь " . $subscriber['Username'] . " подписался на вас";
        $stmt->execute([$target_user_id, $subscriber_id, $message]);
        
        echo json_encode(['success' => true, 'action' => 'subscribed', 'message' => 'Вы успешно подписались']);
    } elseif ($action === 'unsubscribe') {
        // Отписываемся
        $stmt = $pdo->prepare("DELETE FROM Subscriptions WHERE subscriber_id = ? AND target_user_id = ?");
        $stmt->execute([$subscriber_id, $target_user_id]);
        echo json_encode(['success' => true, 'action' => 'unsubscribed', 'message' => 'Вы отписались']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // duplicate entry
        echo json_encode(['success' => false, 'message' => 'Вы уже подписаны на этого пользователя']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Произошла ошибка: ' . $e->getMessage()]);
    }
}
exit;
?>