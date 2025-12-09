<?php
include 'config.php';
session_start();

// Генерация капчи при загрузке страницы
function generateCaptcha() {
    // Список доступных изображений капчи
    $captcha_images = ['1', '2', '3', '4'];
    shuffle($captcha_images);
    $selected_image = $captcha_images[0]; // Выбираем одно случайное изображение
    
    // Создаем правильный порядок частей (1,2,3,4)
    $correct_order = [1, 2, 3, 4];
    $shuffled_order = [1, 2, 3, 4];
    shuffle($shuffled_order);
    
    $_SESSION['captcha_answer'] = $correct_order;
    $_SESSION['captcha_shuffled'] = $shuffled_order;
    
    return [
        'correct_order' => $correct_order,
        'shuffled_order' => $shuffled_order
    ];
}

// Проверяем, нужно ли генерировать новую капчу
if (empty($_SESSION['captcha_answer']) || isset($_GET['refresh_captcha'])) {
    generateCaptcha();
}

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'reader';
    $avatar_data = $_POST['avatar_data'] ?? ''; // Данные base64 аватара
    $captcha_order = $_POST['captcha_order'] ?? '';
    
    // Валидация
    $errors = [];
    
    if (empty($username) || empty($email) || empty($password)) {
        $errors[] = "Все обязательные поля должны быть заполнены";
    }
    
    if ($password !== ($_POST['confirmPassword'] ?? '')) {
        $errors[] = "Пароли не совпадают";
    }
    
    // Проверка капчи
    if (empty($captcha_order)) {
        $errors[] = "Пожалуйста, соберите капчу";
    } else {
        $user_order = array_map('intval', explode(',', $captcha_order));
        $correct_order = $_SESSION['captcha_answer'];
        
        if ($user_order !== $correct_order) {
            $errors[] = "Капча собрана неправильно. Попробуйте еще раз.";
            // Генерируем новую капчу при ошибке
            generateCaptcha();
        }
    }
    
    // Проверка уникальности email и username
    if (empty($errors)) {
        try {
            // ИСПРАВЛЕНИЕ: используем $pdo вместо $conn
            $checkStmt = $pdo->prepare("SELECT ID FROM Users WHERE Email = ? OR Username = ?");
            $checkStmt->execute([$email, $username]);
            if ($checkStmt->fetch()) {
                $errors[] = "Пользователь с таким email или логином уже существует";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка проверки данных: " . $e->getMessage();
        }
    }
    
    // Обработка аватара
    $avatar_path = 'umolch_avatar.jpeg'; // путь по умолчанию
    
    if (!empty($avatar_data) && strpos($avatar_data, 'data:image') === 0) {
        // Извлекаем данные base64
        list($type, $avatar_data) = explode(';', $avatar_data);
        list(, $avatar_data) = explode(',', $avatar_data);
        $avatar_data = base64_decode($avatar_data);
        
        // Создаем уникальное имя файла
        $avatar_filename = 'avatar_' . uniqid() . '.jpeg';
        $avatar_path = 'uploads/avatars/' . $avatar_filename;
        
        // Создаем папку если не существует
        if (!is_dir('uploads/avatars')) {
            mkdir('uploads/avatars', 0777, true);
        }
        
        // Сохраняем файл
        if (file_put_contents($avatar_path, $avatar_data) === false) {
            $errors[] = "Ошибка при сохранении аватара";
            $avatar_path = 'umolch_avatar.jpeg';
        }
    }
    
    // Если ошибок нет - регистрируем пользователя
    if (empty($errors)) {
        try {
            // Хэшируем пароль
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Подготовленный запрос для безопасности
            // ИСПРАВЛЕНИЕ: используем $pdo вместо $conn
            $stmt = $pdo->prepare("INSERT INTO Users (Username, Email, Role, Password_hash, Created_at, Status, Last_login, Avatar_path) 
                                  VALUES (?, ?, ?, ?, CURDATE(), 0, CURDATE(), ?)");
            
            if ($stmt->execute([$username, $email, $role, $password_hash, $avatar_path])) {
                $success = "Регистрация прошла успешно!";
                // Очищаем капчу из сессии
                unset($_SESSION['captcha_answer']);
                unset($_SESSION['captcha_shuffled']);
                // Очищаем форму
                echo "<script>
                    document.getElementById('registerForm').reset();
                    document.getElementById('avatarPreview').innerHTML = '<img src=\"umolch_avatar.jpeg\" alt=\"Аватар по умолчанию\">';
                </script>";
            } else {
                $errors[] = "Ошибка при регистрации пользователя";
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
    <title>Регистрация</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_log_regis.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Регистрация</h1>
        
        <div class="register-container">
            <?php if (isset($success)): ?>
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
            
            <form id="registerForm" method="POST" action="">
                <input type="hidden" id="avatar_data" name="avatar_data" value="">
                <input type="hidden" id="captcha_order" name="captcha_order" value="">
                
                <div class="avatar-section">
                    <h2 class="section-title">Аватар</h2>
                    
                    <div class="avatar-preview" id="avatarPreview">
                        <img src="umolch_avatar.jpeg" alt="Аватар по умолчанию">
                    </div>
                    
                    <div class="avatar-upload">
                        <input type="file" id="avatarInput" class="avatar-input" accept="image/*">
                        <label for="avatarInput" class="avatar-label">Загрузить аватар</label>
                        <button type="button" class="avatar-remove" id="removeAvatar">Удалить аватар</button>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2 class="section-title">Основная информация</h2>
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Логин *</label>
                        <input type="text" id="username" name="username" class="form-input" placeholder="Введите Логин" required 
                               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Введите email" required
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group password-toggle">
                            <label for="password" class="form-label">Пароль *</label>
                            <input type="password" id="password" name="password" class="form-input" placeholder="Введите пароль" required>
                            <button type="button" class="toggle-password">👁️</button>
                        </div>
                        
                        <div class="form-group password-toggle">
                            <label for="confirmPassword" class="form-label">Подтверждение пароля *</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder="Повторите пароль" required>
                            <button type="button" class="toggle-password">👁️</button>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2 class="section-title">Роль</h2>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Выберите роль *</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="" disabled selected>Выберите роль</option>
                            <option value="reader" <?= (isset($_POST['role']) && $_POST['role'] == 'reader') ? 'selected' : '' ?>>Читатель</option>
                            <option value="author" <?= (isset($_POST['role']) && $_POST['role'] == 'author') ? 'selected' : '' ?>>Автор</option>
                        </select>
                    </div>
                </div>

                <!-- Блок капчи -->
                <div class="form-section">
                    <h2 class="section-title">Капча</h2>
                    <div class="captcha-section">
                        <p class="captcha-instructions">Соберите мозаику из частей в правильном порядке:</p>
                        
                        <div class="captcha-preview">
                            <div class="captcha-original"></div>
                            <div>Пример правильной картинки</div>
                        </div>
                        
                        <!-- Целевая область для сборки капчи - 2x2 -->
                        <div class="captcha-target" id="captchaTarget">
                            <div class="captcha-slot" data-slot="0"></div>
                            <div class="captcha-slot" data-slot="1"></div>
                            <div class="captcha-slot" data-slot="2"></div>
                            <div class="captcha-slot" data-slot="3"></div>
                        </div>
                        
                        <!-- Контейнер с перемешанными частями - 2x2 -->
                        <div class="captcha-container" id="captchaContainer">
                            <!-- Части капчи будут добавлены через JavaScript -->
                        </div>
                        
                        <div class="captcha-controls">
                            <button type="button" class="btn btn-captcha" id="resetCaptcha">Перемешать</button>
                            <button type="button" class="btn btn-captcha" id="newCaptcha">Новая капча</button>
                        </div>
                        
                        <div class="captcha-status" id="captchaStatus"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">Очистить форму</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Зарегистрироваться</button>
                </div>
            </form>
            
            <div class="guest-section">
                <div class="guest-links">
                    <p>Не хотите регистрироваться? <a href="#" class="guest-link">Гостем будешь</a></p>
                    <p>Уже есть аккаунт? <a href="login.php" class="guest-link">Войдите</a></p>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentOrder = [];
            let draggedItem = null;

            // Функция для получения URL изображения для части
            function getImageUrl(partNumber) {
                return `captcha/${partNumber}.png`;
            }

            // Функция для перемешивания массива
            function shuffleArray(array) {
                const newArray = [...array];
                for (let i = newArray.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
                }
                return newArray;
            }

            // Инициализация капчи
            function initCaptcha() {
                const captchaContainer = document.getElementById('captchaContainer');
                const captchaTarget = document.getElementById('captchaTarget');
                const captchaOrderInput = document.getElementById('captcha_order');
                const submitBtn = document.getElementById('submitBtn');
                const captchaStatus = document.getElementById('captchaStatus');
                
                // Очищаем контейнеры
                captchaContainer.innerHTML = '';
                captchaTarget.querySelectorAll('.captcha-slot').forEach(slot => {
                    slot.innerHTML = '';
                    slot.classList.remove('filled', 'over');
                    slot.style.backgroundImage = '';
                });
                
                // Получаем перемешанный порядок из PHP сессии
                const shuffledOrder = <?php echo json_encode($_SESSION['captcha_shuffled'] ?? [1,2,3,4]); ?>;
                currentOrder = new Array(4).fill(null);
                
                // Создаем перетаскиваемые элементы (части изображения)
                shuffledOrder.forEach((partNumber, index) => {
                    const item = document.createElement('div');
                    item.className = 'captcha-item';
                    item.style.backgroundImage = `url('${getImageUrl(partNumber)}')`;
                    item.setAttribute('data-part', partNumber);
                    item.setAttribute('draggable', 'true');
                    
                    item.addEventListener('dragstart', function(e) {
                        draggedItem = this;
                        this.classList.add('dragging');
                        e.dataTransfer.setData('text/plain', partNumber);
                    });
                    
                    item.addEventListener('dragend', function() {
                        this.classList.remove('dragging');
                        draggedItem = null;
                    });
                    
                    captchaContainer.appendChild(item);
                });
                
                // Настройка слотов для перетаскивания
                captchaTarget.querySelectorAll('.captcha-slot').forEach(slot => {
                    slot.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        this.classList.add('over');
                    });
                    
                    slot.addEventListener('dragleave', function() {
                        this.classList.remove('over');
                    });
                    
                    slot.addEventListener('drop', function(e) {
                        e.preventDefault();
                        this.classList.remove('over');
                        
                        if (draggedItem) {
                            const slotIndex = parseInt(this.getAttribute('data-slot'));
                            const partNumber = parseInt(draggedItem.getAttribute('data-part'));
                            
                            // Проверяем, не занят ли уже слот
                            if (currentOrder[slotIndex] === null) {
                                // Устанавливаем фон для слота
                                this.style.backgroundImage = `url('${getImageUrl(partNumber)}')`;
                                this.classList.add('filled');
                                currentOrder[slotIndex] = partNumber;
                                
                                // Удаляем перетаскиваемый элемент
                                draggedItem.remove();
                                
                                // Проверяем правильность капчи
                                checkCaptcha();
                            }
                        }
                    });
                    
                    // Двойной клик для очистки слота
                    slot.addEventListener('dblclick', function() {
                        const slotIndex = parseInt(this.getAttribute('data-slot'));
                        const partNumber = currentOrder[slotIndex];
                        
                        if (partNumber) {
                            // Возвращаем элемент в контейнер
                            const item = document.createElement('div');
                            item.className = 'captcha-item';
                            item.style.backgroundImage = `url('${getImageUrl(partNumber)}')`;
                            item.setAttribute('data-part', partNumber);
                            item.setAttribute('draggable', 'true');
                            
                            item.addEventListener('dragstart', function(e) {
                                draggedItem = this;
                                this.classList.add('dragging');
                                e.dataTransfer.setData('text/plain', partNumber);
                            });
                            
                            item.addEventListener('dragend', function() {
                                this.classList.remove('dragging');
                                draggedItem = null;
                            });
                            
                            document.getElementById('captchaContainer').appendChild(item);
                            
                            // Очищаем слот
                            this.style.backgroundImage = '';
                            this.classList.remove('filled');
                            currentOrder[slotIndex] = null;
                            
                            checkCaptcha();
                        }
                    });
                });
                
                // Сбрасываем состояние
                captchaOrderInput.value = '';
                submitBtn.disabled = true;
                captchaStatus.textContent = 'Перетащите части изображения в правильном порядке';
                captchaStatus.className = 'captcha-status';
            }
            
            // Функция для перемешивания капчи
            function shuffleCaptcha() {
                const captchaContainer = document.getElementById('captchaContainer');
                const captchaTarget = document.getElementById('captchaTarget');
                
                // Собираем все части обратно в контейнер
                captchaTarget.querySelectorAll('.captcha-slot').forEach(slot => {
                    const partNumber = currentOrder[parseInt(slot.getAttribute('data-slot'))];
                    if (partNumber) {
                        const item = document.createElement('div');
                        item.className = 'captcha-item';
                        item.style.backgroundImage = `url('${getImageUrl(partNumber)}')`;
                        item.setAttribute('data-part', partNumber);
                        item.setAttribute('draggable', 'true');
                        
                        item.addEventListener('dragstart', function(e) {
                            draggedItem = this;
                            this.classList.add('dragging');
                            e.dataTransfer.setData('text/plain', partNumber);
                        });
                        
                        item.addEventListener('dragend', function() {
                            this.classList.remove('dragging');
                            draggedItem = null;
                        });
                        
                        captchaContainer.appendChild(item);
                        
                        // Очищаем слот
                        slot.style.backgroundImage = '';
                        slot.classList.remove('filled');
                    }
                });
                
                // Перемешиваем элементы в контейнере
                const items = Array.from(captchaContainer.children);
                captchaContainer.innerHTML = '';
                shuffleArray(items).forEach(item => {
                    captchaContainer.appendChild(item);
                });
                
                // Сбрасываем текущий порядок
                currentOrder = new Array(4).fill(null);
                
                // Сбрасываем статус
                document.getElementById('captcha_order').value = '';
                document.getElementById('submitBtn').disabled = true;
                document.getElementById('captchaStatus').textContent = 'Перетащите части изображения в правильном порядке';
                document.getElementById('captchaStatus').className = 'captcha-status';
            }
            
            // Проверка капчи
            function checkCaptcha() {
                const correctAnswer = <?php echo json_encode($_SESSION['captcha_answer'] ?? [1,2,3,4]); ?>;
                const captchaOrderInput = document.getElementById('captcha_order');
                const submitBtn = document.getElementById('submitBtn');
                const captchaStatus = document.getElementById('captchaStatus');
                
                // Проверяем, все ли слоты заполнены
                const isComplete = currentOrder.every(item => item !== null);
                
                if (isComplete) {
                    // Проверяем правильность порядка
                    const isCorrect = JSON.stringify(currentOrder) === JSON.stringify(correctAnswer);
                    
                    if (isCorrect) {
                        captchaStatus.textContent = '✓ Капча пройдена!';
                        captchaStatus.className = 'captcha-status correct';
                        captchaOrderInput.value = currentOrder.join(',');
                        submitBtn.disabled = false;
                    } else {
                        captchaStatus.textContent = '✗ Неправильный порядок. Попробуйте еще раз.';
                        captchaStatus.className = 'captcha-status incorrect';
                        captchaOrderInput.value = '';
                        submitBtn.disabled = true;
                    }
                } else {
                    captchaStatus.textContent = 'Заполните все слоты';
                    captchaStatus.className = 'captcha-status';
                    captchaOrderInput.value = '';
                    submitBtn.disabled = true;
                }
            }
            
            // Инициализация капчи при загрузке
            initCaptcha();
            
            // Кнопка перемешивания
            document.getElementById('resetCaptcha').addEventListener('click', function() {
                shuffleCaptcha();
            });
            
            // Новая капча
            document.getElementById('newCaptcha').addEventListener('click', function() {
                window.location.href = '?refresh_captcha=1';
            });
            
            // Переключение видимости пароля
            const toggleButtons = document.querySelectorAll('.toggle-password');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.textContent = '🙈';
                    } else {
                        input.type = 'password';
                        this.textContent = '👁️';
                    }
                });
            });
            
            // Загрузка аватара
            const avatarInput = document.getElementById('avatarInput');
            const avatarPreview = document.getElementById('avatarPreview');
            const removeAvatarBtn = document.getElementById('removeAvatar');
            const avatarDataInput = document.getElementById('avatar_data');
            
            avatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        avatarPreview.innerHTML = '';
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        avatarPreview.appendChild(img);
                        
                        // Сохраняем данные base64 для отправки на сервер
                        avatarDataInput.value = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
            
            // Удаление аватара - возвращаем umolch_avatar.jpeg
            removeAvatarBtn.addEventListener('click', function() {
                avatarPreview.innerHTML = '<img src="umolch_avatar.jpeg" alt="Аватар по умолчанию">';
                avatarInput.value = '';
                avatarDataInput.value = '';
            });
            
            // Очистка формы
            const resetBtn = document.getElementById('resetBtn');
            resetBtn.addEventListener('click', function() {
                document.getElementById('registerForm').reset();
                avatarPreview.innerHTML = '<img src="umolch_avatar.jpeg" alt="Аватар по умолчанию">';
                avatarDataInput.value = '';
                initCaptcha();
            });
        });
    </script>
</body>
</html>