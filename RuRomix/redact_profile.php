<?php
include 'config.php';
session_start();

// Базовая проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Получаем текущие данные пользователя
try {
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $errors[] = "Пользователь не найден";
        // Если пользователь не найден, используем тестовые данные
        $user = [
            'ID' => 1,
            'Username' => 'ivan_petrov',
            'Email' => 'Petrov@mail.ru',
            'Role' => 'reader',
            'Avatar_path' => 'umolch_avatar.jpeg',
            'Created_at' => '2024-01-15'
        ];
    }
} catch (PDOException $e) {
    $errors[] = "Ошибка базы данных: " . $e->getMessage();
    // Используем тестовые данные при ошибке БД
    $user = [
        'ID' => 1,
        'Username' => 'ivan_petrov',
        'Email' => 'Petrov@mail.ru',
        'Role' => 'reader',
        'Avatar_path' => 'umolch_avatar.jpeg',
        'Created_at' => '2024-01-15'
    ];
}

// Обработка формы редактирования профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $current_password = $_POST['currentPassword'] ?? '';
    $new_password = $_POST['newPassword'] ?? '';
    $confirm_password = $_POST['confirmPassword'] ?? '';
    
    // Валидация
    if (empty($username) || empty($email)) {
        $errors[] = "Имя пользователя и email обязательны для заполнения";
    }
    
    // Проверка уникальности email и username (кроме текущего пользователя)
    if (empty($errors)) {
        try {
            $checkStmt = $pdo->prepare("SELECT ID FROM Users WHERE (Email = ? OR Username = ?) AND ID != ?");
            $checkStmt->execute([$email, $username, $user_id]);
            if ($checkStmt->fetch()) {
                $errors[] = "Пользователь с таким email или логином уже существует";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка проверки данных: " . $e->getMessage();
        }
    }
    
    // Проверка пароля
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "Для смены пароля введите текущий пароль";
        } elseif (!empty($user['Password_hash']) && !password_verify($current_password, $user['Password_hash'])) {
            $errors[] = "Текущий пароль введен неверно";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Новый пароль и подтверждение не совпадают";
        }
    }
    
    // Обработка аватара
    $avatar_path = $user['Avatar_path']; // сохраняем текущий путь
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        // Проверка типа файла
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Можно загружать только изображения (JPEG, PNG, GIF)";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "Файл слишком большой. Максимальный размер: 5MB";
        } else {
            // Создаем уникальное имя файла
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $avatar_filename = 'avatar_' . uniqid() . '.' . $extension;
            $upload_path = 'uploads/avatars/' . $avatar_filename;
            
            // Создаем папку если не существует
            if (!is_dir('uploads/avatars')) {
                mkdir('uploads/avatars', 0777, true);
            }
            
            // Перемещаем загруженный файл
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $avatar_path = $upload_path;
                
                // Удаляем старый аватар, если он не стандартный
                if ($user['Avatar_path'] !== 'umolch_avatar.jpeg' && file_exists($user['Avatar_path'])) {
                    unlink($user['Avatar_path']);
                }
            } else {
                $errors[] = "Ошибка при загрузке аватара";
            }
        }
    }
    
    // Если ошибок нет - обновляем данные пользователя
    if (empty($errors)) {
        try {
            // Подготавливаем данные для обновления
            $update_fields = [
                'Username' => $username,
                'Email' => $email,
                'Avatar_path' => $avatar_path
            ];
            
            // Если указан новый пароль, добавляем его хэш
            if (!empty($new_password)) {
                $update_fields['Password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            // Формируем SQL запрос
            $set_parts = [];
            $params = [];
            foreach ($update_fields as $field => $value) {
                $set_parts[] = "$field = ?";
                $params[] = $value;
            }
            $params[] = $user_id;
            
            $sql = "UPDATE Users SET " . implode(', ', $set_parts) . " WHERE ID = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($params)) {
                $success = "Профиль успешно обновлен!";
                
                // Обновляем данные в сессии
                $_SESSION['username'] = $username;
                $_SESSION['avatar_path'] = $avatar_path;
                
                // Обновляем переменную $user для отображения новых данных
                $user = array_merge($user, $update_fields);
            } else {
                $errors[] = "Ошибка при обновлении профиля";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование профиля - RuRomix</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_redact_profile.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Редактирование профиля</h1>
        
        <div class="edit-container">
            <!-- Сообщения об успехе/ошибке -->
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="error-message">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form id="profileForm" method="POST" enctype="multipart/form-data">
                <div class="profile-header">
                    <div class="avatar-section">
                        <div class="avatar-preview" id="avatarPreview">
                            <?php if (!empty($user['Avatar_path']) && file_exists($user['Avatar_path'])): ?>
                                <img src="<?= htmlspecialchars($user['Avatar_path']) ?>" alt="Аватар">
                            <?php else: ?>
                                <img src="umolch_avatar.jpeg" alt="Аватар по умолчанию">
                            <?php endif; ?>
                        </div>
                        <div class="avatar-upload">
                            <input type="file" id="avatarInput" name="avatar" class="avatar-input" accept="image/*">
                            <label for="avatarInput" class="avatar-label">Изменить аватар</label>
                            <span style="font-size: 12px; color: #808367;">JPG, PNG до 5MB</span>
                        </div>
                    </div>
                    
                    <div style="flex-grow: 1;">
                        <h2 style="color: #92ad71; margin-bottom: 10px;"><?= htmlspecialchars($user['Username']) ?></h2>
                        <p style="color: #808367; margin-bottom: 5px;"><?= htmlspecialchars($user['Role']) ?></p>
                        <p style="color: #808367; font-size: 14px;">На платформе с <?= htmlspecialchars($user['Created_at']) ?></p>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Основная информация</h3>
                    
                    <div class="form-group">
                        <label class="form-label" for="username">Имя пользователя</label>
                        <input type="text" id="username" name="username" class="form-input" 
                               value="<?= htmlspecialchars($user['Username']) ?>" placeholder="Введите имя пользователя" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?= htmlspecialchars($user['Email']) ?>" placeholder="Введите email" required>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Безопасность</h3>
                    
                    <div class="form-group">
                        <label class="form-label" for="currentPassword">Текущий пароль (для смены пароля)</label>
                        <div class="password-toggle">
                            <input type="password" id="currentPassword" name="currentPassword" class="form-input" placeholder="Введите текущий пароль">
                            <button type="button" class="toggle-password" onclick="togglePassword('currentPassword')">👁️</button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="newPassword">Новый пароль</label>
                            <div class="password-toggle">
                                <input type="password" id="newPassword" name="newPassword" class="form-input" placeholder="Введите новый пароль">
                                <button type="button" class="toggle-password" onclick="togglePassword('newPassword')">👁️</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirmPassword">Подтверждение пароля</label>
                            <div class="password-toggle">
                                <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder="Повторите новый пароль">
                                <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">👁️</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Обработчик загрузки аватара
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('Файл слишком большой. Максимальный размер: 5MB');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarPreview = document.getElementById('avatarPreview');
                    avatarPreview.innerHTML = `<img src="${e.target.result}" alt="Аватар">`;
                };
                reader.readAsDataURL(file);
            }
        });

        // Переключение видимости пароля
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>