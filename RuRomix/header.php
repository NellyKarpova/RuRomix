<?php
// header.php - универсальный header для всех страниц

// Проверяем, не начата ли уже сессия
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Определяем текущую страницу для адаптации функционала
$current_page = basename($_SERVER['PHP_SELF']);
$is_index_page = in_array($current_page, ['Index_RuRomix.php', 'index.php']);
$is_admin_page = strpos($current_page, 'admin') !== false;
$is_author_page = strpos($current_page, 'author') !== false;

// Получаем уведомления для авторизованного пользователя
$unread_notifications_count = 0;
$notifications = [];

if (isset($_SESSION['user_id'])) {
    include 'config.php';
    
    try {
        // Получаем количество непрочитанных уведомлений
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications_count = $stmt->fetchColumn();
        
        // Получаем последние уведомления
        $stmt = $pdo->prepare("
            SELECT n.*, 
                   u.Username as source_username,
                   c.Title as comic_title,
                   ch.Title as chapter_title
            FROM Notifications n
            LEFT JOIN Users u ON n.source_id = u.ID AND n.type = 'new_subscriber'
            LEFT JOIN Comics c ON n.source_id = c.ID AND n.type IN ('new_comic', 'new_chapter')
            LEFT JOIN Chapters ch ON n.source_id = ch.ID AND n.type = 'new_chapter'
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Ошибка при получении уведомлений: " . $e->getMessage());
    }
}
?>

<header class="header">
    <div class="header-left">
        <a href="Index_RuRomix.php" class="logo">RR</a>
        
        <?php if ($is_index_page): ?>
            <!-- Поиск только на главной странице -->
            <form method="GET" action="Index_RuRomix.php" class="search-form">
                <input type="text" name="search" class="search-box" placeholder="Поиск комиксов и пользователей..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
            </form>
        <?php elseif ($is_admin_page): ?>
            <!-- Заголовок для админ-панели -->
            <h1 class="page-title">Панель управления</h1>
        <?php elseif ($is_author_page): ?>
            <!-- Заголовок для кабинета автора -->
            <h1 class="page-title">Кабинет автора</h1>
        <?php else: ?>
            <!-- Кнопка назад или другой элемент для остальных страниц -->
            <button onclick="history.back()" class="back-button">← Назад</button>
        <?php endif; ?>
    </div>
    
    <div class="header-right">
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Уведомления (везде где пользователь авторизован) -->
            <div class="notifications-container" id="notificationsContainer">
                <div class="notifications-icon" id="notificationsIcon">
                    <img src="opoveshany.png" alt="Уведомления">
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?= $unread_notifications_count > 99 ? '99+' : $unread_notifications_count ?></span>
                    <?php endif; ?>
                </div>
                <div class="notifications-dropdown" id="notificationsDropdown">
                    <div class="notifications-header">
                        <h3>Уведомления</h3>
                        <?php if (!empty($notifications)): ?>
                            <button type="button" id="markAllRead" class="mark-read-btn">Прочитать все</button>
                        <?php endif; ?>
                    </div>
                    <div class="notifications-list">
                        <?php if (empty($notifications)): ?>
                            <div class="notification-item empty">
                                <p>Нет новых уведомлений</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>" data-notification-id="<?= $notification['ID'] ?>">
                                    <div class="notification-content">
                                        <?php if ($notification['type'] == 'new_subscriber'): ?>
                                            <p>
                                                <strong><?= htmlspecialchars($notification['source_username']) ?></strong> 
                                                подписался(ась) на вас
                                            </p>
                                        <?php elseif ($notification['type'] == 'new_chapter'): ?>
                                            <p>
                                                Новый раздел в комиксе 
                                                <strong>"<?= htmlspecialchars($notification['comic_title']) ?>"</strong>
                                                <?php if (!empty($notification['chapter_title'])): ?>
                                                    : <?= htmlspecialchars($notification['chapter_title']) ?>
                                                <?php endif; ?>
                                            </p>
                                        <?php elseif ($notification['type'] == 'new_comic'): ?>
                                            <p>
                                                <strong><?= htmlspecialchars($notification['source_username']) ?></strong> 
                                                выпустил(а) новый комикс: 
                                                <strong>"<?= htmlspecialchars($notification['comic_title']) ?>"</strong>
                                            </p>
                                        <?php else: ?>
                                            <p><?= htmlspecialchars($notification['message']) ?></p>
                                        <?php endif; ?>
                                        <small class="notification-time">
                                            <?= time_elapsed_string($notification['created_at']) ?>
                                        </small>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                        <button type="button" class="mark-read-individual">✓</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Меню пользователя (везде где авторизован) -->
            <div class="user-info">
                <div class="user-menu-trigger" id="userMenuTrigger">
                    <span class="username"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <div class="avatar" style="background-image: url('<?= htmlspecialchars($_SESSION['avatar_path']) ?>')">
                        <?php if (empty($_SESSION['avatar_path']) || $_SESSION['avatar_path'] == 'umolch_avatar.jpeg'): ?>
                            <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <!-- Адаптируем меню в зависимости от роли и текущей страницы -->
                    <a href="user_profile.php?id=<?= $_SESSION['user_id'] ?>" class="dropdown-item">
                        <span class="dropdown-icon">👤</span>
                        Мой профиль
                    </a>
                    
                    <?php if (!$is_admin_page): ?>
                        <a href="subscriptions.php" class="dropdown-item">
                            <span class="dropdown-icon">👥</span>
                            Мои подписки
                        </a>
                    <?php endif; ?>
                    
                    <div class="dropdown-divider"></div>

                    <?php if (!$is_admin_page && !$is_author_page): ?>
                        <a href="author_kabinet.php" class="dropdown-item">
                            <span class="dropdown-icon">👁️</span>
                            Кабинет автора
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!$is_admin_page && !$is_author_page): ?>
                        <a href="reader_kabinet.php" class="dropdown-item">
                            <span class="dropdown-icon">👁️</span>
                            Кабинет читателя
                        </a>
                    <?php endif; ?>
                    
                    <div class="dropdown-divider"></div>
                    
                    <a href="logout.php" class="dropdown-item">
                        <span class="dropdown-icon">🚪</span>
                        Выйти
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Кнопки входа/регистрации для неавторизованных -->
            <div class="auth-links">
                <a href="login.php" class="auth-link login">Войти</a>
                <a href="register.php" class="auth-link register">Регистрация</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<?php
// Функция для форматирования времени
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $components = [
        'y' => ['value' => $diff->y, 'text' => 'год'],
        'm' => ['value' => $diff->m, 'text' => 'месяц'],
        'w' => ['value' => $weeks, 'text' => 'неделя'],
        'd' => ['value' => $days, 'text' => 'день'],
        'h' => ['value' => $diff->h, 'text' => 'час'],
        'i' => ['value' => $diff->i, 'text' => 'минута'],
        's' => ['value' => $diff->s, 'text' => 'секунда'],
    ];

    $result = [];
    foreach ($components as $key => $component) {
        if ($component['value'] > 0) {
            $text = $component['value'] . ' ' . $component['text'];
            if ($component['value'] > 1 && $key != 'm') {
                $text .= ($key == 'y') ? 'а' : (($key == 'h') ? 'ов' : (($key == 'd') ? 'ей' : 'ы'));
            }
            $result[] = $text;
        }
    }

    if (!$full && !empty($result)) {
        $result = [reset($result)];
    }
    
    return !empty($result) ? implode(', ', $result) . ' назад' : 'только что';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationsIcon = document.getElementById('notificationsIcon');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const userMenuTrigger = document.getElementById('userMenuTrigger');
    const dropdownMenu = document.getElementById('dropdownMenu');

    // Управление уведомлениями
    if (notificationsIcon) {
        notificationsIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('show');
            if (dropdownMenu) dropdownMenu.classList.remove('show');
        });
    }

    // Управление меню пользователя
    if (userMenuTrigger) {
        userMenuTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
            if (notificationsDropdown) notificationsDropdown.classList.remove('show');
        });
    }

    // Закрытие меню при клике вне их
    document.addEventListener('click', function() {
        if (notificationsDropdown) notificationsDropdown.classList.remove('show');
        if (dropdownMenu) dropdownMenu.classList.remove('show');
    });

    // Останавливаем всплытие при клике внутри меню
    if (notificationsDropdown) {
        notificationsDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    if (dropdownMenu) {
        dropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Обработка уведомлений
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            markAllNotificationsAsRead();
        });
    }

    const markReadButtons = document.querySelectorAll('.mark-read-individual');
    markReadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const notificationItem = this.closest('.notification-item');
            const notificationId = notificationItem.getAttribute('data-notification-id');
            markNotificationAsRead(notificationId, notificationItem);
        });
    });

    function markNotificationAsRead(notificationId, notificationElement) {
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                notificationElement.classList.remove('unread');
                notificationElement.classList.add('read');
                notificationElement.querySelector('.mark-read-individual').remove();
                updateNotificationBadge();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function markAllNotificationsAsRead() {
        fetch('mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    const markBtn = item.querySelector('.mark-read-individual');
                    if (markBtn) markBtn.remove();
                });
                updateNotificationBadge();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function updateNotificationBadge() {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            const currentCount = parseInt(badge.textContent);
            if (currentCount > 1) {
                badge.textContent = currentCount - 1;
            } else {
                badge.remove();
            }
        }
    }
});
</script>