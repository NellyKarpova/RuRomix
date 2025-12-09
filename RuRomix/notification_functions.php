<?php
function createComicNotifications($pdo, $comic_id, $author_id) {
    try {
        // Получаем информацию о комиксе и авторе
        $stmt = $pdo->prepare("
            SELECT c.Title, u.Username 
            FROM Comics c 
            JOIN Users u ON c.Author_id = u.ID 
            WHERE c.ID = ?
        ");
        $stmt->execute([$comic_id]);
        $comic_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$comic_info) return false;
        
        // Получаем всех подписчиков автора
        $stmt = $pdo->prepare("
            SELECT subscriber_id 
            FROM Subscriptions 
            WHERE target_user_id = ?
        ");
        $stmt->execute([$author_id]);
        $subscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Создаем уведомления для каждого подписчика
        foreach ($subscribers as $subscriber_id) {
            $message = "Автор " . $comic_info['Username'] . " выпустил новый комикс: " . $comic_info['Title'];
            
            $stmt = $pdo->prepare("
                INSERT INTO Notifications (user_id, type, source_id, message) 
                VALUES (?, 'new_comic', ?, ?)
            ");
            $stmt->execute([$subscriber_id, $comic_id, $message]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Ошибка создания уведомлений о комиксе: " . $e->getMessage());
        return false;
    }
}

function createChapterNotifications($pdo, $chapter_id, $comic_id) {
    try {
        // Получаем информацию о главе и комиксе
        $stmt = $pdo->prepare("
            SELECT ch.Title as chapter_title, c.Title as comic_title, c.Author_id, u.Username
            FROM Chapters ch
            JOIN Comics c ON ch.Comics_id = c.ID
            JOIN Users u ON c.Author_id = u.ID
            WHERE ch.ID = ?
        ");
        $stmt->execute([$chapter_id]);
        $chapter_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$chapter_info) return false;
        
        // Получаем всех подписчиков автора
        $stmt = $pdo->prepare("
            SELECT subscriber_id 
            FROM Subscriptions 
            WHERE target_user_id = ?
        ");
        $stmt->execute([$chapter_info['Author_id']]);
        $subscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Создаем уведомления для каждого подписчика
        foreach ($subscribers as $subscriber_id) {
            $message = "Новая глава в комиксе '" . $chapter_info['comic_title'] . "': " . $chapter_info['chapter_title'];
            
            $stmt = $pdo->prepare("
                INSERT INTO Notifications (user_id, type, source_id, message) 
                VALUES (?, 'new_chapter', ?, ?)
            ");
            $stmt->execute([$subscriber_id, $chapter_id, $message]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Ошибка создания уведомлений о главе: " . $e->getMessage());
        return false;
    }
}
?>