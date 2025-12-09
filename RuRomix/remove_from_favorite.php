<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comic_id'])) {
    $user_id = $_SESSION['user_id'];
    $comic_id = intval($_POST['comic_id']);
    $return_url = isset($_POST['return_url']) ? $_POST['return_url'] : 'reader_kabinet.php?tab=favorites';

    try {
        $stmt = $pdo->prepare("DELETE FROM Users_favorite WHERE User_id = ? AND Comics_id = ?");
        $stmt->execute([$user_id, $comic_id]);
        
        $_SESSION['message'] = "Комикс удален из избранного";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Ошибка при удалении из избранного: " . $e->getMessage();
    }

    header("Location: " . $return_url);
    exit;
} else {
    header("Location: reader_kabinet.php");
    exit;
}
?>