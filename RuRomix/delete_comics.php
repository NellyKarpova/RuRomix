<?php
session_start();
include 'config.php';
include 'check_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неправильный метод запроса']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID комикса не указан']);
    exit;
}

$comic_id = intval($_POST['id']);
$user_id = $_SESSION['user_id'];

try {
    // Проверяем, принадлежит ли комикс текущему пользователю
    $check_stmt = $pdo->prepare("SELECT Author_id, Cover_path FROM Comics WHERE ID = ?");
    $check_stmt->execute([$comic_id]);
    $comic = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comic) {
        echo json_encode(['success' => false, 'message' => 'Комикс не найден']);
        exit;
    }
    
    if ($comic['Author_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'У вас нет прав для удаления этого комикса']);
        exit;
    }
    
    // Удаляем связанные записи из других таблиц
    $pdo->beginTransaction();
    
    // Удаляем главы
    $delete_chapters = $pdo->prepare("DELETE FROM Chapters WHERE Comics_id = ?");
    $delete_chapters->execute([$comic_id]);
    
    // Удаляем оценки
    $delete_ratings = $pdo->prepare("DELETE FROM Comics_ratings WHERE Comics_id = ?");
    $delete_ratings->execute([$comic_id]);
    
    // Удаляем комментарии
    $delete_comments = $pdo->prepare("DELETE FROM Comment WHERE Comics_id = ?");
    $delete_comments->execute([$comic_id]);
    
    // Удаляем из избранного
    $delete_favorites = $pdo->prepare("DELETE FROM Users_favorite WHERE Comics_id = ?");
    $delete_favorites->execute([$comic_id]);
    
    // Удаляем сам комикс
    $delete_comic = $pdo->prepare("DELETE FROM Comics WHERE ID = ?");
    $delete_comic->execute([$comic_id]);
    
    // Удаляем файл обложки, если он существует
    if (!empty($comic['Cover_path']) && file_exists($comic['Cover_path'])) {
        unlink($comic['Cover_path']);
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Комикс успешно удален']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>