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
        // Если пользователь не найден, выходим
        session_destroy();
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

// Получаем статистику пользователя
$stats = [
    'comics_read' => 0,
    'chapters_read' => 0,
    'favorites' => 0,
    'subscriptions' => 0
];

try {
    // Здесь можно добавить запросы для получения реальной статистики
    // Пока используем заглушки
} catch (PDOException $e) {
    // В случае ошибки оставляем значения по умолчанию
}

// Форматируем дату регистрации
$join_date = date('d.m.Y', strtotime($user_data['Created_at']));
$join_date_full = date('d F Y', strtotime($user_data['Created_at']));

// Определяем отображаемое имя роли
$role_display = [
    'reader' => 'Читатель',
    'author' => 'Автор',
    'moderator' => 'Модератор',
    'admin' => 'Администратор'
];

$user_role_display = $role_display[$user_data['Role']] ?? $user_data['Role'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой профиль - RuRomix</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_kabinets.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar-section">
                    <div class="profile-avatar" style="background-image: url('<?= htmlspecialchars($user_data['Avatar_path']) ?>')">
                        <?php if (empty($user_data['Avatar_path']) || $user_data['Avatar_path'] == 'umolch_avatar.jpeg'): ?>
                            <?= strtoupper(substr($user_data['Username'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-info">
                    <h1 class="profile-name"><?= htmlspecialchars($user_data['Username']) ?></h1>
                    <div class="profile-role"><?= htmlspecialchars($user_role_display) ?></div>
                    <div class="profile-join-date">На платформе с <?= htmlspecialchars($join_date_full) ?></div>
                    <a href="redact_profile.php" class="edit-profile-btn">Редактировать профиль</a>
                </div>
            </div>

            <div class="profile-stats">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['favorites'] ?></div>
                    <div class="stat-label">Избранное</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['subscriptions'] ?></div>
                    <div class="stat-label">Подписки</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['subscriptions'] ?></div>
                    <div class="stat-label">Подписчики</div>
                </div>
            </div>

            <div class="profile-details">
                <h2 class="section-title">Информация профиля</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Имя пользователя</span>
                        <span class="info-value"><?= htmlspecialchars($user_data['Username']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($user_data['Email']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Роль</span>
                        <span class="info-value"><?= htmlspecialchars($user_role_display) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Дата регистрации</span>
                        <span class="info-value"><?= htmlspecialchars($join_date) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Последний вход</span>
                        <span class="info-value">
                            <?= $user_data['Last_login'] ? date('d.m.Y', strtotime($user_data['Last_login'])) : 'Никогда' ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Статус</span>
                        <span class="info-value">
                            <?php 
                            switch($user_data['Status']) {
                                case 0: echo 'Активен'; break;
                                case 1: echo 'Заблокирован'; break;
                                case 2: echo 'Ожидает подтверждения'; break;
                                default: echo 'Неизвестно';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

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

            // Обработчик для кнопки смены аватара
            document.querySelector('.edit-avatar-btn').addEventListener('click', function() {
                alert('Функция смены аватара будет доступна в редакторе профиля');
            });

            // Обработчик для кнопки выхода
            document.getElementById('logoutBtn').addEventListener('click', function() {
                if(confirm('Вы уверены, что хотите выйти?')) {
                    window.location.href = 'logout.php';
                }
            });
        });
    </script>
</body>
</html>