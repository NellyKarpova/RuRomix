<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'subscriptions';

// Получаем подписчиков и подписки
$subscribers = [];
$subscriptions = [];

try {
    // Подписчики (кто подписан на меня)
    $stmt = $pdo->prepare("
        SELECT u.ID, u.Username, u.Avatar_path, u.Role, s.created_at 
        FROM Subscriptions s 
        INNER JOIN Users u ON s.subscriber_id = u.ID 
        WHERE s.target_user_id = ? 
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Подписки (на кого подписан я)
    $stmt = $pdo->prepare("
        SELECT u.ID, u.Username, u.Avatar_path, u.Role, s.created_at 
        FROM Subscriptions s 
        INNER JOIN Users u ON s.target_user_id = u.ID 
        WHERE s.subscriber_id = ? 
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке подписок: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои подписки - RuRomix</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_profile.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="main-container">
            <h1 class="section-title">Мои подписки</h1>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <div class="subscription-tabs">
                <button class="tab-button <?= $active_tab === 'subscriptions' ? 'active' : '' ?>" 
                        onclick="switchTab('subscriptions')">
                    👥 Мои подписки (<?= count($subscriptions) ?>)
                </button>
                <button class="tab-button <?= $active_tab === 'subscribers' ? 'active' : '' ?>" 
                        onclick="switchTab('subscribers')">
                    ❤️ Мои подписчики (<?= count($subscribers) ?>)
                </button>
            </div>

            <!-- Вкладка подписок -->
            <div id="subscriptions-tab" class="tab-content <?= $active_tab === 'subscriptions' ? 'active' : '' ?>">
                <?php if (empty($subscriptions)): ?>
                    <div class="no-content">
                        <p>Вы еще ни на кого не подписаны</p>
                        <p><a href="authors.php" class="btn btn-subscribe" style="margin-top: 15px;">Найти авторов</a></p>
                    </div>
                <?php else: ?>
                    <div class="users-grid">
                        <?php foreach ($subscriptions as $user): ?>
                            <div class="user-card">
                                <a href="user_profile.php?id=<?= $user['ID'] ?>" class="user-card-link">
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
                                        <p class="user-join-date">Подписан: <?= date('d.m.Y', strtotime($user['created_at'])) ?></p>
                                    </div>
                                </a>
                                <button type="button" 
                                        class="btn btn-unsubscribe unsubscribe-btn"
                                        data-user-id="<?= $user['ID'] ?>"
                                        style="margin-top: 10px; width: 100%;">
                                    Отписаться
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Вкладка подписчиков -->
            <div id="subscribers-tab" class="tab-content <?= $active_tab === 'subscribers' ? 'active' : '' ?>">
                <?php if (empty($subscribers)): ?>
                    <div class="no-content">
                        <p>У вас пока нет подписчиков</p>
                        <p>Публикуйте комиксы, чтобы привлечь внимание!</p>
                    </div>
                <?php else: ?>
                    <div class="users-grid">
                        <?php foreach ($subscribers as $user): ?>
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
                                    <p class="user-join-date">Подписан: <?= date('d.m.Y', strtotime($user['created_at'])) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        function switchTab(tabName) {
            // Обновляем URL без перезагрузки страницы
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
            
            // Переключаем активные вкладки
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.querySelector(`.tab-button[onclick="switchTab('${tabName}')"]`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }

        // Обработка отписки на странице подписок
        document.addEventListener('DOMContentLoaded', function() {
            const unsubscribeButtons = document.querySelectorAll('.unsubscribe-btn');
            
            unsubscribeButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const userId = this.getAttribute('data-user-id');
                    const userCard = this.closest('.user-card');
                    
                    // Показываем индикатор загрузки
                    const originalText = this.textContent;
                    this.textContent = '...';
                    this.disabled = true;

                    // Отправляем AJAX запрос
                    fetch('subscribe_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `target_user_id=${userId}&action=unsubscribe`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Анимация удаления карточки
                            userCard.style.opacity = '0';
                            userCard.style.transform = 'translateX(100%)';
                            setTimeout(() => {
                                userCard.remove();
                                // Обновляем счетчик во вкладке
                                const tabButton = document.querySelector('.tab-button[onclick="switchTab(\'subscriptions\')"]');
                                const currentCount = parseInt(tabButton.textContent.match(/\((\d+)\)/)[1]);
                                tabButton.textContent = tabButton.textContent.replace(/\(\d+\)/, `(${currentCount - 1})`);
                            }, 300);
                        } else {
                            alert(data.message);
                            this.textContent = originalText;
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Произошла ошибка при выполнении запроса');
                        this.textContent = originalText;
                        this.disabled = false;
                    });
                });
            });
        });
    </script>
</body>
</html>