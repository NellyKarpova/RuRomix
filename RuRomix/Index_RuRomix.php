<?php
// Подключение к базе данных
require_once 'config.php';

session_start();

// Обработка поискового запроса
$search_term = '';
$search_results_comics = [];
$search_results_users = [];
$has_search = false;

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $has_search = true;
    
    try {
        // Поиск комиксов по названию или описанию
        $stmt_comics = $pdo->prepare("
            SELECT c.ID, c.Title, c.Description, u.Username, c.Cover_path, c.Created_at 
            FROM Comics c 
            INNER JOIN Users u ON c.Author_id = u.ID 
            WHERE c.Title LIKE :search OR c.Description LIKE :search 
            ORDER BY c.Created_at DESC
        ");
        $search_param = "%$search_term%";
        $stmt_comics->bindParam(':search', $search_param, PDO::PARAM_STR);
        $stmt_comics->execute();
        $search_results_comics = $stmt_comics->fetchAll(PDO::FETCH_ASSOC);
        
        // Поиск пользователей по имени пользователя
        $stmt_users = $pdo->prepare("
            SELECT ID, Username, Avatar_path, Role, Created_at 
            FROM Users 
            WHERE Username LIKE :search 
            ORDER BY Username
        ");
        $stmt_users->bindParam(':search', $search_param, PDO::PARAM_STR);
        $stmt_users->execute();
        $search_results_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $search_error = "Ошибка при выполнении поиска: " . $e->getMessage();
    }
}

// Получаем данные комиксов для главной страницы (если нет поиска)
$comics_data = [];
if (!$has_search) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.ID, c.Title, u.Username, c.Cover_path 
            FROM Comics c 
            INNER JOIN Users u ON c.Author_id = u.ID 
            ORDER BY c.Created_at DESC 
            LIMIT 6
        ");
        $stmt->execute();
        $comics_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $comics_data = [];
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RuRomix - Платформа для комиксов</title>
    <link rel="stylesheet" href="style_main.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <h1 class="site-title">RuRomix</h1>
        
        <div class="main-container">
            <?php if ($has_search): ?>
                <!-- Результаты поиска -->
                <div class="search-results">
                    <h2 class="section-title">Результаты поиска: "<?= htmlspecialchars($search_term) ?>"</h2>
                    
                    <?php if (isset($search_error)): ?>
                        <div class="error-message">
                            <?= htmlspecialchars($search_error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Результаты поиска комиксов -->
                    <?php if (!empty($search_results_comics)): ?>
                        <div class="search-section">
                            <h3 class="subsection-title">Комиксы (<?= count($search_results_comics) ?>)</h3>
                            <div class="comics-grid">
                                <?php foreach ($search_results_comics as $comic): ?>
                                    <a href="comic_detail.php?id=<?= $comic['ID'] ?>" class="comic-card">
                                        <div class="comic-cover">
                                            <?php if (!empty($comic['Cover_path'])): ?>
                                                <img src="<?= htmlspecialchars($comic['Cover_path']) ?>" alt="Обложка">
                                            <?php else: ?>
                                                Обложка
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="comic-title"><?= htmlspecialchars($comic['Title']) ?></h3>
                                        <p class="comic-author"><?= htmlspecialchars($comic['Username']) ?></p>
                                        <p class="comic-description"><?= htmlspecialchars(mb_substr($comic['Description'], 0, 100)) ?>...</p>
                                        <div class="comic-stats">
                                            <span>👁️ 0</span>
                                            <span>❤️ 0</span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>Комиксы по запросу "<?= htmlspecialchars($search_term) ?>" не найдены.</p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Результаты поиска пользователей -->
                    <?php if (!empty($search_results_users)): ?>
                        <div class="search-section">
                            <h3 class="subsection-title">Пользователи (<?= count($search_results_users) ?>)</h3>
                            <div class="users-grid">
                                <?php foreach ($search_results_users as $user): ?>
                                    <a href="user_profile.php?id=<?= $user['ID'] ?>" class="user-card">
                                        <div class="user-avatar">
                                            <?php if (!empty($user['Avatar_path']) && $user['Avatar_path'] != 'umolch_avatar.jpeg'): ?>
                                                <img src="<?= htmlspecialchars($user['Avatar_path']) ?>" alt="Аватар">
                                            <?php else: ?>
                                                <div class="avatar-placeholder">
                                                    <?= strtoupper(substr($user['Username'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-info">
                                            <h4 class="username"><?= htmlspecialchars($user['Username']) ?></h4>
                                            <p class="user-role"><?= htmlspecialchars($user['Role']) ?></p>
                                            <p class="user-join-date">Зарегистрирован: <?= date('d.m.Y', strtotime($user['Created_at'])) ?></p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>Пользователи по запросу "<?= htmlspecialchars($search_term) ?>" не найдены.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($search_results_comics) && empty($search_results_users)): ?>
                        <div class="no-results">
                            <p>Ничего не найдено по запросу "<?= htmlspecialchars($search_term) ?>".</p>
                            <p>Попробуйте изменить поисковый запрос или проверьте правописание.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Обычная главная страница -->
                <div class="comics-section">
                    <h2 class="section-title">Популярное</h2>
                    
                    <?php if (empty($comics_data)): ?>
                        <div class="error-message">
                            <p>В настоящее время комиксы недоступны. Пожалуйста, попробуйте позже.</p>
                            <p><small>Для администратора: проверьте логи ошибок.</small></p>
                        </div>
                    <?php else: ?>
                        <div class="comics-grid">
                            <?php foreach ($comics_data as $comic): ?>
                                <a href="comic_detail.php?id=<?= $comic['ID'] ?>" class="comic-card">
                                    <div class="comic-cover">
                                        <?php if (!empty($comic['Cover_path'])): ?>
                                            <img src="<?= htmlspecialchars($comic['Cover_path']) ?>" alt="Обложка">
                                        <?php else: ?>
                                            Обложка
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="comic-title"><?= htmlspecialchars($comic['Title']) ?></h3>
                                    <p class="comic-author"><?= htmlspecialchars($comic['Username']) ?></p>
                                    <div class="comic-stats">
                                        <span>👁️ 0</span>
                                        <span>❤️ 0</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="footer-left">
            @Copyright Карпова Нелли Константиновна<br>
            студентка гр. ИС-225.2
        </div>
    </footer>

    <script>
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
        });
    </script>
</body>
</html>