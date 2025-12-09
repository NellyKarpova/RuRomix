<?php
require_once 'config.php';
session_start();

// Получаем ID пользователя из GET параметра
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id === 0) {
    header('Location: Index_RuRomix.php');
    exit;
}

// Получаем информацию о пользователе
$user = [];
$subscribers_count = 0;
$subscriptions_count = 0;
$is_subscribed = false;
$user_comics = [];

try {
    // Информация о пользователе
    $stmt = $pdo->prepare("SELECT ID, Username, Avatar_path, Role, Created_at FROM Users WHERE ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die('Пользователь не найден');
    }

    // Количество подписчиков
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Subscriptions WHERE target_user_id = ?");
    $stmt->execute([$user_id]);
    $subscribers_count = $stmt->fetchColumn();

    // Количество подписок (на кого подписан)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Subscriptions WHERE subscriber_id = ?");
    $stmt->execute([$user_id]);
    $subscriptions_count = $stmt->fetchColumn();

    // Проверяем, подписан ли текущий пользователь на этого пользователя
    if (isset($_SESSION['user_id'])) {
        $current_user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Subscriptions WHERE subscriber_id = ? AND target_user_id = ?");
        $stmt->execute([$current_user_id, $user_id]);
        $is_subscribed = $stmt->fetchColumn() > 0;
    }

    // Получаем комиксы пользователя
    $stmt = $pdo->prepare("
        SELECT ID, Title, Cover_path, Created_at, Status 
        FROM Comics 
        WHERE Author_id = ? 
        ORDER BY Created_at DESC
    ");
    $stmt->execute([$user_id]);
    $user_comics = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка при получении данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль пользователя - <?= htmlspecialchars($user['Username']) ?> | RuRomix</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_profile.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="main-container">
            <div id="message-container"></div>

            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if (!empty($user['Avatar_path']) && $user['Avatar_path'] != 'umolch_avatar.jpeg'): ?>
                        <img src="<?= htmlspecialchars($user['Avatar_path']) ?>" alt="Аватар <?= htmlspecialchars($user['Username']) ?>">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder">
                            <?= strtoupper(substr($user['Username'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h1 class="profile-username"><?= htmlspecialchars($user['Username']) ?></h1>
                    <p class="profile-role">Роль: <span style="text-transform: capitalize;"><?= htmlspecialchars($user['Role']) ?></span></p>
                    <p class="profile-join-date">Зарегистрирован: <?= date('d.m.Y', strtotime($user['Created_at'])) ?></p>
                    <div class="profile-stats">
                        <div class="stat">
                            <span class="stat-count" id="subscribers-count"><?= $subscribers_count ?></span>
                            <span class="stat-label">Подписчиков</span>
                        </div>
                        <div class="stat">
                            <span class="stat-count"><?= $subscriptions_count ?></span>
                            <span class="stat-label">Подписок</span>
                        </div>
                        <div class="stat">
                            <span class="stat-count"><?= count($user_comics) ?></span>
                            <span class="stat-label">Комиксов</span>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user_id): ?>
                        <button type="button" 
                                id="subscribe-btn" 
                                class="btn <?= $is_subscribed ? 'btn-unsubscribe' : 'btn-subscribe' ?>" 
                                data-user-id="<?= $user_id ?>"
                                data-action="<?= $is_subscribed ? 'unsubscribe' : 'subscribe' ?>">
                            <?php if ($is_subscribed): ?>
                                ✓ Подписан
                            <?php else: ?>
                                + Подписаться
                            <?php endif; ?>
                        </button>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn btn-subscribe">+ Подписаться</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($user_comics)): ?>
                <div class="user-comics-section">
                    <h2 class="section-title">Комиксы пользователя</h2>
                    <div class="comics-grid">
                        <?php foreach ($user_comics as $comic): ?>
                            <a href="comic_detail.php?id=<?= $comic['ID'] ?>" class="comic-card">
                                <div class="comic-cover">
                                    <?php if (!empty($comic['Cover_path'])): ?>
                                        <img src="<?= htmlspecialchars($comic['Cover_path']) ?>" alt="Обложка комикса <?= htmlspecialchars($comic['Title']) ?>">
                                    <?php else: ?>
                                        Обложка
                                    <?php endif; ?>
                                </div>
                                <h3 class="comic-title"><?= htmlspecialchars($comic['Title']) ?></h3>
                                <div class="comic-stats">
                                    <span class="comic-status"><?= $comic['Status'] == '1' ? 'Активен' : 'Черновик' ?></span>
                                    <span><?= date('d.m.Y', strtotime($comic['Created_at'])) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-content">
                    <p>У пользователя пока нет комиксов</p>
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
            const subscribeBtn = document.getElementById('subscribe-btn');
            const messageContainer = document.getElementById('message-container');
            const subscribersCount = document.getElementById('subscribers-count');

            if (subscribeBtn) {
                subscribeBtn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const currentAction = this.getAttribute('data-action');
                    const newAction = currentAction === 'subscribe' ? 'unsubscribe' : 'subscribe';

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
                        body: `target_user_id=${userId}&action=${currentAction}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Обновляем кнопку
                            if (data.action === 'subscribed') {
                                this.textContent = '✓ Подписан';
                                this.classList.remove('btn-subscribe');
                                this.classList.add('btn-unsubscribe');
                                this.setAttribute('data-action', 'unsubscribe');
                                
                                // Увеличиваем счетчик подписчиков
                                if (subscribersCount) {
                                    subscribersCount.textContent = parseInt(subscribersCount.textContent) + 1;
                                }
                            } else {
                                this.textContent = '+ Подписаться';
                                this.classList.remove('btn-unsubscribe');
                                this.classList.add('btn-subscribe');
                                this.setAttribute('data-action', 'subscribe');
                                
                                // Уменьшаем счетчик подписчиков
                                if (subscribersCount) {
                                    subscribersCount.textContent = parseInt(subscribersCount.textContent) - 1;
                                }
                            }

                            // Показываем сообщение об успехе
                            showMessage(data.message, 'success');
                        } else {
                            // Показываем сообщение об ошибке
                            showMessage(data.message, 'error');
                            // Возвращаем оригинальный текст кнопки
                            this.textContent = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Произошла ошибка при выполнении запроса', 'error');
                        this.textContent = originalText;
                    })
                    .finally(() => {
                        this.disabled = false;
                    });
                });
            }

            function showMessage(message, type) {
                messageContainer.innerHTML = `
                    <div class="${type === 'success' ? 'success-message' : 'error-message'}">
                        ${message}
                    </div>
                `;
                
                // Автоматически скрываем сообщение через 3 секунды
                setTimeout(() => {
                    messageContainer.innerHTML = '';
                }, 3000);
            }
        });
    </script>
</body>
</html>