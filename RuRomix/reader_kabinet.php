<?php
include 'config.php';
include 'check_auth.php';

// Получаем данные пользователя из базы данных
$user_id = $_SESSION['user_id'];
$user_data = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE ID = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

// Определяем активную вкладку
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'reading';

// Получаем статистику пользователя
$user_stats = [
    'read_count' => 0,
    'favorites_count' => 0,
    'subscriptions_count' => 0
];

// Получаем количество избранных комиксов
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Users_favorite WHERE User_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_stats['favorites_count'] = $result['count'];
} catch (PDOException $e) {
    // Оставляем 0 в случае ошибки
}

// Получаем комиксы для разных вкладок
$reading_comics = [];
$favorite_comics = [];
$history_comics = [];

try {
    // Избранные комиксы
    if ($active_tab == 'favorites') {
        $stmt = $pdo->prepare("
            SELECT c.ID, c.Title, c.Description, u.Username as author_name, c.Cover_path, 
                   c.Status, c.Created_at, g.Name as genre_name
            FROM Comics c 
            INNER JOIN Users_favorite uf ON c.ID = uf.Comics_id 
            INNER JOIN Users u ON c.Author_id = u.ID 
            INNER JOIN Genres g ON c.Genres_id = g.ID 
            WHERE uf.User_id = ?
            ORDER BY uf.Created_at DESC
        ");
        $stmt->execute([$user_id]);
        $favorite_comics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Для вкладки "Читаю сейчас" (заглушка)
    if ($active_tab == 'reading') {
        // Здесь можно добавить логику для комиксов, которые пользователь читает
        $reading_comics = []; // Пока оставляем пустым
    }
    
    // Рекомендованные комиксы (всегда загружаем)
    $stmt = $pdo->prepare("
        SELECT c.ID, c.Title, u.Username as author_name, c.Cover_path, g.Name as genre_name
        FROM Comics c 
        INNER JOIN Users u ON c.Author_id = u.ID 
        INNER JOIN Genres g ON c.Genres_id = g.ID 
        WHERE c.Status = '1' 
        ORDER BY c.Created_at DESC 
        LIMIT 4
    ");
    $stmt->execute();
    $recommended_comics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // В случае ошибки оставляем пустые массивы
}

// Форматируем дату регистрации
$join_date = date('d.m.Y', strtotime($user_data['Created_at']));
$join_date_full = date('d F Y', strtotime($user_data['Created_at']));

// Функция для форматирования статуса комикса
function formatComicStatus($status) {
    switch($status) {
        case '1': return 'Продолжается';
        case '2': return 'Завершен';
        case '3': return 'Заморожен';
        default: return 'Неизвестно';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - RuRomix</title>
    <link rel="stylesheet" href="style_kabinets.css">
    <link rel="stylesheet" href="style_main.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="cabinet-container">
            <div class="profile-header">
                <div class="profile-avatar" style="background-image: url('<?= htmlspecialchars($user_data['Avatar_path']) ?>')">
                    <?php if (empty($user_data['Avatar_path']) || $user_data['Avatar_path'] == 'umolch_avatar.jpeg'): ?>
                        <?= strtoupper(substr($user_data['Username'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name"><?= htmlspecialchars($user_data['Username']) ?></h1>
                    <p>Читатель • На платформе с <?= htmlspecialchars($join_date_full) ?></p>
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $user_stats['read_count'] ?></div>
                            <div class="stat-label">Прочитано</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $user_stats['favorites_count'] ?></div>
                            <div class="stat-label">В избранном</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $user_stats['subscriptions_count'] ?></div>
                            <div class="stat-label">Подписок</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">Мои комиксы</h2>
                <div class="tabs">
                    <a href="reader_kabinet.php?tab=reading" class="tab <?= $active_tab == 'reading' ? 'active' : '' ?>">Читаю сейчас</a>
                    <a href="reader_kabinet.php?tab=favorites" class="tab <?= $active_tab == 'favorites' ? 'active' : '' ?>">Избранное</a>
                    <a href="reader_kabinet.php?tab=history" class="tab <?= $active_tab == 'history' ? 'active' : '' ?>">История</a>
                </div>

                <div class="comics-grid">
                    <?php if ($active_tab == 'reading'): ?>
                        <?php if (count($reading_comics) > 0): ?>
                            <?php foreach ($reading_comics as $comic): ?>
                                <a href="comic_detail.php?id=<?= $comic['ID'] ?>" class="comic-card">
                                    <div class="comic-cover">
                                        <?php if (!empty($comic['Cover_path'])): ?>
                                            <img src="<?= htmlspecialchars($comic['Cover_path']) ?>" alt="Обложка">
                                        <?php else: ?>
                                            Обложка
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="comic-title"><?= htmlspecialchars($comic['Title']) ?></h3>
                                    <p class="comic-author"><?= htmlspecialchars($comic['author_name']) ?></p>
                                    <div class="comic-progress">
                                        <div class="progress-bar" style="width: <?= $comic['progress'] ?>%"></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📚</div>
                                <p>Вы еще не начали читать ни одного комикса</p>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($active_tab == 'favorites'): ?>
                        <?php if (count($favorite_comics) > 0): ?>
                            <?php foreach ($favorite_comics as $comic): ?>
                                <div class="comic-card">
                                    <a href="comic_detail.php?id=<?= $comic['ID'] ?>" style="text-decoration: none; color: inherit;">
                                        <div class="comic-cover">
                                            <?php if (!empty($comic['Cover_path'])): ?>
                                                <img src="<?= htmlspecialchars($comic['Cover_path']) ?>" alt="Обложка">
                                            <?php else: ?>
                                                Обложка
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="comic-title"><?= htmlspecialchars($comic['Title']) ?></h3>
                                        <p class="comic-author"><?= htmlspecialchars($comic['author_name']) ?></p>
                                        <span class="comic-genre"><?= htmlspecialchars($comic['genre_name']) ?></span>
                                        <p class="comic-status"><?= formatComicStatus($comic['Status']) ?></p>
                                    </a>
                                    <form method="post" action="remove_from_favorite.php" onsubmit="return confirm('Удалить из избранного?')">
                                        <input type="hidden" name="comic_id" value="<?= $comic['ID'] ?>">
                                        <input type="hidden" name="return_url" value="reader_kabinet.php?tab=favorites">
                                        <button type="submit" class="remove-favorite">❌ Удалить из избранного</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">⭐</div>
                                <p>У вас пока нет избранных комиксов</p>
                                <p><a href="Index_RuRomix.php" style="color: #92ad71;">Найдите интересные комиксы</a> и добавьте их в избранное!</p>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($active_tab == 'history'): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📖</div>
                            <p>История чтения пока пуста</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">Рекомендации для вас</h2>
                <div class="comics-grid">
                    <?php if (count($recommended_comics) > 0): ?>
                        <?php foreach ($recommended_comics as $comic): ?>
                            <a href="comic_detail.php?id=<?= $comic['ID'] ?>" class="comic-card">
                                <div class="comic-cover">
                                    <?php if (!empty($comic['Cover_path'])): ?>
                                        <img src="<?= htmlspecialchars($comic['Cover_path']) ?>" alt="Обложка">
                                    <?php else: ?>
                                        Обложка
                                    <?php endif; ?>
                                </div>
                                <h3 class="comic-title"><?= htmlspecialchars($comic['Title']) ?></h3>
                                <p class="comic-author"><?= htmlspecialchars($comic['author_name']) ?></p>
                                <span class="comic-genre"><?= htmlspecialchars($comic['genre_name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🎨</div>
                            <p>Пока нет рекомендаций</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Обработчик для выпадающего меню пользователя
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.getElementById('userMenu');
            if (userMenu) {
                const dropdownMenu = document.getElementById('dropdownMenu');

                // Открытие/закрытие меню при клике на аватар или никнейм
                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });

                // Закрытие меню при клике вне его
                document.addEventListener('click', function() {
                    dropdownMenu.classList.remove('show');
                });

                // Предотвращение закрытия меню при клике внутри него
                dropdownMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });
    </script>
</body>
</html>