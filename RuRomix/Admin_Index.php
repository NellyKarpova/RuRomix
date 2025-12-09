<?php
// Подключение к базе данных
require_once 'config.php';

session_start();

// Проверяем, является ли пользователь администратором
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT Role FROM Users WHERE ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $is_admin = ($user && $user['Role'] === 'admin');
}

// Обработка SQL-запросов (только для администраторов)
$sql_result = null;
$sql_error = null;
$execution_time = 0;

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_query'])) {
    $sql_query = trim($_POST['sql_query']);
    
    if (!empty($sql_query)) {
        try {
            $start_time = microtime(true);
            
            // Запрещаем потенциально опасные операции в демонстрационных целях
            $blocked_patterns = [
                '/DROP\s+(DATABASE|TABLE|USER)/i',
                '/DELETE\s+FROM/i',
                '/TRUNCATE/i',
                '/ALTER\s+TABLE/i',
                '/CREATE\s+(DATABASE|USER)/i',
                '/GRANT/i',
                '/REVOKE/i',
                '/FLUSH/i',
                '/KILL/i',
                '/SHUTDOWN/i',
                '/--/', // комментарии SQL
                '/;/', // множественные запросы
            ];
            
            $is_blocked = false;
            foreach ($blocked_patterns as $pattern) {
                if (preg_match($pattern, $sql_query)) {
                    $is_blocked = true;
                    $sql_error = "Этот тип запроса заблокирован в демонстрационных целях.";
                    break;
                }
            }
            
            if (!$is_blocked) {
                // Проверяем, является ли запрос SELECT
                if (stripos($sql_query, 'SELECT') === 0) {
                    $stmt = $pdo->prepare($sql_query);
                    $stmt->execute();
                    $sql_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $sql_error = "Разрешены только SELECT-запросы. Для других операций используйте phpMyAdmin.";
                }
            }
            
            $end_time = microtime(true);
            $execution_time = round(($end_time - $start_time) * 1000, 2); // в миллисекундах
            
        } catch (PDOException $e) {
            $sql_error = "Ошибка SQL: " . $e->getMessage();
        } catch (Exception $e) {
            $sql_error = "Ошибка: " . $e->getMessage();
        }
    }
}

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
    <title>RuRomix - Административная панель</title>
    <link rel="stylesheet" href="style_main.css">
    <style>
        /* Компактная SQL-панель */
        .sql-toggle-btn {
            position: fixed;
            bottom: 70px;
            left: 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .sql-toggle-btn:hover {
            background: #45a049;
            transform: scale(1.1);
        }
        
        .sql-toggle-btn.minimized {
            bottom: 20px;
            left: 20px;
        }
        
        .sql-panel-compact {
            position: fixed;
            bottom: 130px;
            left: 20px;
            width: 400px;
            max-height: 500px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            z-index: 999;
            display: none;
            overflow: hidden;
            border: 2px solid #4CAF50;
        }
        
        .sql-panel-compact.active {
            display: block;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .sql-header {
            background: #4CAF50;
            color: white;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sql-header h3 {
            margin: 0;
            font-size: 16px;
        }
        
        .sql-close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .sql-close-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .sql-body {
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .sql-textarea-small {
            width: 100%;
            height: 80px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-bottom: 10px;
            resize: vertical;
        }
        
        .sql-buttons-small {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .sql-btn-small {
            padding: 8px 15px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            flex: 1;
        }
        
        .sql-btn-small:hover {
            background: #45a049;
        }
        
        .sql-btn-small.secondary {
            background: #6c757d;
        }
        
        .sql-btn-small.secondary:hover {
            background: #5a6268;
        }
        
        .sql-result-small {
            font-size: 12px;
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .sql-result-small.success {
            background: #e8f5e9;
            border-left: 3px solid #4CAF50;
        }
        
        .sql-result-small.error {
            background: #ffebee;
            border-left: 3px solid #f44336;
        }
        
        .sql-result-small.info {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }
        
        .sql-result-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 8px;
        }
        
        .sql-result-table th {
            background: #f5f5f5;
            padding: 6px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        
        .sql-result-table td {
            padding: 5px;
            border-bottom: 1px solid #eee;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .sql-meta-small {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed #ddd;
        }
        
        .sql-examples-small {
            margin-top: 15px;
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
        }
        
        .sql-examples-small h4 {
            font-size: 13px;
            margin: 0 0 8px 0;
            color: #333;
        }
        
        .sql-example-small {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            background: white;
            padding: 5px 8px;
            margin: 3px 0;
            border-radius: 3px;
            border-left: 2px solid #4CAF50;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .sql-example-small:hover {
            background: #e8f5e9;
        }
        
        /* Подложка для затемнения фона */
        .sql-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 998;
            display: none;
        }
        
        .sql-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'Admin_header.php'; ?>

    <main class="main-content">
        <h1 class="site-title">RuRomix - Административная панель</h1>
        
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

    <!-- SQL Панель (только для администраторов) -->
    <?php if ($is_admin): ?>
    <div class="sql-overlay" id="sqlOverlay"></div>
    
    <button class="sql-toggle-btn" id="sqlToggleBtn" title="Открыть SQL-панель">
        SQL
    </button>
    
    <div class="sql-panel-compact" id="sqlPanel">
        <div class="sql-header">
            <h3>SQL Панель (Админ)</h3>
            <button class="sql-close-btn" id="sqlCloseBtn" title="Закрыть">×</button>
        </div>
        <div class="sql-body">
            <form method="POST" action="" id="sqlForm">
                <textarea name="sql_query" class="sql-textarea-small" 
                          placeholder="Введите SELECT запрос..." 
                          id="sqlTextarea"><?php 
                          echo isset($_POST['sql_query']) ? htmlspecialchars($_POST['sql_query']) : ''; 
                          ?></textarea>
                
                <div class="sql-buttons-small">
                    <button type="submit" class="sql-btn-small">Выполнить</button>
                    <button type="button" class="sql-btn-small secondary" onclick="clearSql()">Очистить</button>
                </div>
            </form>
            
            <?php if ($sql_error !== null): ?>
            <div class="sql-result-small error">
                <strong>❌ Ошибка:</strong><br>
                <?php echo htmlspecialchars($sql_error); ?>
                <div class="sql-meta-small">Время: <?php echo $execution_time; ?> мс</div>
            </div>
            <?php elseif ($sql_result !== null): ?>
            <div class="sql-result-small success">
                <strong>✅ Результаты:</strong>
                <div class="sql-meta-small">
                    Записей: <?php echo count($sql_result); ?> | 
                    Время: <?php echo $execution_time; ?> мс
                </div>
                
                <?php if (!empty($sql_result)): ?>
                <div style="max-height: 150px; overflow-y: auto; margin-top: 8px;">
                    <table class="sql-result-table">
                        <thead>
                            <tr>
                                <?php 
                                // Берем только первые 5 столбцов для компактности
                                $columns = array_slice(array_keys($sql_result[0]), 0, 5);
                                foreach ($columns as $column): ?>
                                    <th><?php echo htmlspecialchars(substr($column, 0, 15)); ?></th>
                                <?php endforeach; ?>
                                <?php if (count(array_keys($sql_result[0])) > 5): ?>
                                    <th>...</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Показываем только первые 5 строк
                            $rows = array_slice($sql_result, 0, 5);
                            foreach ($rows as $row): ?>
                                <tr>
                                    <?php 
                                    $cells = array_slice(array_values($row), 0, 5);
                                    foreach ($cells as $cell): ?>
                                        <td title="<?php echo htmlspecialchars($cell ?? 'NULL'); ?>">
                                            <?php echo htmlspecialchars(substr($cell ?? 'NULL', 0, 20)); ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <?php if (count(array_values($row)) > 5): ?>
                                        <td>...</td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($sql_result) > 5): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; font-style: italic; color: #666;">
                                        ... и еще <?php echo count($sql_result) - 5; ?> записей
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p style="margin: 5px 0; color: #666;">Запрос выполнен, но не вернул данных.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="sql-examples-small">
                <h4>📋 Примеры запросов:</h4>
                <div class="sql-example-small" onclick="setSql('SELECT * FROM Users LIMIT 5')">
                    SELECT * FROM Users LIMIT 5
                </div>
                <div class="sql-example-small" onclick="setSql('SELECT Title, Username FROM Comics c INNER JOIN Users u ON c.Author_id = u.ID LIMIT 10')">
                    SELECT комиксы с авторами
                </div>
                <div class="sql-example-small" onclick="setSql('SELECT Role, COUNT(*) as count FROM Users GROUP BY Role')">
                    SELECT пользователей по ролям
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
            
            // Управление SQL-панелью
            const sqlToggleBtn = document.getElementById('sqlToggleBtn');
            const sqlCloseBtn = document.getElementById('sqlCloseBtn');
            const sqlPanel = document.getElementById('sqlPanel');
            const sqlOverlay = document.getElementById('sqlOverlay');
            
            if (sqlToggleBtn) {
                sqlToggleBtn.addEventListener('click', function() {
                    sqlPanel.classList.toggle('active');
                    sqlOverlay.classList.toggle('active');
                    sqlToggleBtn.classList.add('minimized');
                });
                
                sqlCloseBtn.addEventListener('click', function() {
                    sqlPanel.classList.remove('active');
                    sqlOverlay.classList.remove('active');
                    sqlToggleBtn.classList.remove('minimized');
                });
                
                sqlOverlay.addEventListener('click', function() {
                    sqlPanel.classList.remove('active');
                    sqlOverlay.classList.remove('active');
                    sqlToggleBtn.classList.remove('minimized');
                });
                
                // Закрытие по Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        sqlPanel.classList.remove('active');
                        sqlOverlay.classList.remove('active');
                        sqlToggleBtn.classList.remove('minimized');
                    }
                });
            }
        });
        
        function setSql(query) {
            document.getElementById('sqlTextarea').value = query;
            document.getElementById('sqlTextarea').focus();
        }
        
        function clearSql() {
            document.getElementById('sqlTextarea').value = '';
        }
    </script>
</body>
</html>