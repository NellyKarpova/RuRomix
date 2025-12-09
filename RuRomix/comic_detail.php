<?php
include 'config.php';
session_start();

// Получаем ID комикса из GET-параметра
$comic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($comic_id === 0) {
    header("Location: Index_RuRomix.php");
    exit;
}

// Обработка добавления/удаления из избранного
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite_action'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Для добавления в избранное необходимо авторизоваться";
        header("Location: login.php");
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    if ($_POST['favorite_action'] === 'add') {
        // Добавляем в избранное
        try {
            $check_stmt = $pdo->prepare("SELECT ID FROM Users_favorite WHERE User_id = ? AND Comics_id = ?");
            $check_stmt->execute([$user_id, $comic_id]);
            
            if ($check_stmt->fetch()) {
                $_SESSION['message'] = "Комикс уже в избранном";
            } else {
                $insert_stmt = $pdo->prepare("INSERT INTO Users_favorite (User_id, Comics_id) VALUES (?, ?)");
                $insert_stmt->execute([$user_id, $comic_id]);
                $_SESSION['message'] = "Комикс добавлен в избранное!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Ошибка при добавлении в избранное: " . $e->getMessage();
        }
    } 
    elseif ($_POST['favorite_action'] === 'remove') {
        // Удаляем из избранного
        try {
            $delete_stmt = $pdo->prepare("DELETE FROM Users_favorite WHERE User_id = ? AND Comics_id = ?");
            $delete_stmt->execute([$user_id, $comic_id]);
            $_SESSION['message'] = "Комикс удален из избранного";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Ошибка при удалении из избранного: " . $e->getMessage();
        }
    }
    
    // Редирект обратно на страницу комикса
    header("Location: comic_detail.php?id=" . $comic_id);
    exit;
}

// Получаем данные комикса из БД
$comic_data = [];
$chapters = [];
$is_favorite = false;

try {
    // Получаем основную информацию о комиксе
    $stmt = $pdo->prepare("
        SELECT c.*, u.Username as author_name, g.Name as genre_name
        FROM Comics c 
        INNER JOIN Users u ON c.Author_id = u.ID 
        INNER JOIN Genres g ON c.Genres_id = g.ID 
        WHERE c.ID = ?
    ");
    $stmt->execute([$comic_id]);
    $comic_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comic_data) {
        header("Location: Index_RuRomix.php");
        exit;
    }
    
    // Проверяем, находится ли комикс в избранном у пользователя
    if (isset($_SESSION['user_id'])) {
        $favorite_stmt = $pdo->prepare("
            SELECT ID FROM Users_favorite 
            WHERE User_id = ? AND Comics_id = ?
        ");
        $favorite_stmt->execute([$_SESSION['user_id'], $comic_id]);
        $is_favorite = $favorite_stmt->fetch() ? true : false;
    }
    
    // Получаем главы комикса
    $chapters_stmt = $pdo->prepare("
        SELECT * FROM Chapters 
        WHERE Comics_id = ? 
        ORDER BY Order_number ASC
    ");
    $chapters_stmt->execute([$comic_id]);
    $chapters = $chapters_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Получаем статистику комикса
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT cr.ID) as ratings_count,
            COUNT(DISTINCT cm.ID) as comments_count
        FROM Comics c
        LEFT JOIN Comics_ratings cr ON c.ID = cr.Comics_id 
        LEFT JOIN Comment cm ON c.ID = cm.Comics_id 
        WHERE c.ID = ?
    ");
    $stats_stmt->execute([$comic_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

// Форматируем статус комикса
$status_text = '';
switch($comic_data['Status']) {
    case '1': $status_text = 'Продолжается'; break;
    case '2': $status_text = 'Завершен'; break;
    case '3': $status_text = 'Заморожен'; break;
    default: $status_text = 'Неизвестно';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($comic_data['Title']) ?> - RuRomix</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_comic_detail.css">
</head>
<body>

   <?php include 'header.php'; ?>

    <main class="main-content">
        <!-- Вывод сообщений -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?= htmlspecialchars($_SESSION['message']) ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="comic-detail-container">
            <div class="comic-header">
                <div class="comic-cover">
                    <?php if (!empty($comic_data['Cover_path'])): ?>
                        <img src="<?= htmlspecialchars($comic_data['Cover_path']) ?>" alt="Обложка комикса">
                    <?php else: ?>
                        Обложка комикса
                    <?php endif; ?>
                </div>
                
                <div class="comic-info">
                    <h1 class="comic-title"><?= htmlspecialchars($comic_data['Title']) ?></h1>
                    <p class="comic-author">Автор: <?= htmlspecialchars($comic_data['author_name']) ?></p>
                    
                    <div class="comic-meta">
                        <span class="meta-item"><?= htmlspecialchars($comic_data['genre_name']) ?></span>
                        <span class="meta-item"><?= $status_text ?></span>
                    </div>
                    
                    <div class="comic-stats">
                        <div class="stat">
                            <div class="stat-value"><?= $stats['ratings_count'] ?? 0 ?></div>
                            <div class="stat-label">Оценок</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= $stats['comments_count'] ?? 0 ?></div>
                            <div class="stat-label">Комментариев</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= count($chapters) ?></div>
                            <div class="stat-label">Глав</div>
                        </div>
                    </div>
                    
                    <div class="comic-actions">
                        <?php if (!empty($chapters)): ?>
                            <button class="action-btn" onclick="startReading()">Начать чтение</button>
                        <?php endif; ?>
                        
                        <!-- Кнопка избранного -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($is_favorite): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="favorite_action" value="remove">
                                    <button type="submit" class="action-btn favorite">❤️ В избранном</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="favorite_action" value="add">
                                    <button type="submit" class="action-btn secondary">🤍 В избранное</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="action-btn secondary">🤍 В избранное</a>
                        <?php endif; ?>   
                    </div>
                </div>
            </div>
            
            <div class="comic-description">
                <h3>Описание</h3>
                <p><?= htmlspecialchars($comic_data['Description']) ?></p>
            </div>
            
            <div class="chapters-section">
                <h2 class="section-title">Главы</h2>
                <div class="chapters-list">
                    <?php if (empty($chapters)): ?>
                        <div class="chapter-item">
                            <div class="chapter-info">
                                <h3 class="chapter-title">Глав пока нет</h3>
                                <div class="chapter-meta">Автор еще не добавил главы к этому комиксу</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chapters as $chapter): ?>
                            <div class="chapter-item">
                                <div class="chapter-info">
                                    <h3 class="chapter-title"><?= htmlspecialchars($chapter['Title']) ?></h3>
                                    <div class="chapter-meta">
                                        Глава <?= $chapter['Order_number'] ?> • Опубликовано <?= date('d.m.Y', strtotime($chapter['Created_at'])) ?>
                                    </div>
                                </div>
                                <a href="chapters.php?chapter_id=<?= $chapter['ID'] ?>" class="read-btn">Читать</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function startReading() {
            // Находим первую главу и переходим к ней
            const firstChapter = document.querySelector('.read-btn');
            if (firstChapter) {
                window.location.href = firstChapter.href;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.getElementById('userMenu');
            if (userMenu) {
                const dropdownMenu = document.getElementById('dropdownMenu');

                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });

                document.addEventListener('click', function() {
                    dropdownMenu.classList.remove('show');
                });

                dropdownMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            // Обработчик для кнопки "Начать чтение"
            const startReadingBtn = document.querySelector('.action-btn');
            if (startReadingBtn && !startReadingBtn.classList.contains('secondary')) {
                startReadingBtn.addEventListener('click', startReading);
            }
        });
    </script>
</body>
</html>