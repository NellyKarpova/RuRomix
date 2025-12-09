<?php
include 'config.php';
session_start();

// Если пользователь уже авторизован, перенаправляем в соответствующий профиль
if (isset($_SESSION['user_id'])) {
    // Проверяем роль пользователя и перенаправляем на соответствующую страницу
    if ($_SESSION['role'] == 'admin') {
        header("Location: Admin_Index.php");
    } else {
        header("Location: Index_RuRomix.php");
    }
    exit();
}

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
    
    $_SESSION['captcha_image'] = $selected_image;
    $_SESSION['captcha_answer'] = $correct_order;
    $_SESSION['captcha_shuffled'] = $shuffled_order;
    
    return [
        'image' => $selected_image,
        'correct_order' => $correct_order,
        'shuffled_order' => $shuffled_order
    ];
}

// Проверяем, нужно ли генерировать новую капчу
if (empty($_SESSION['captcha_answer']) || isset($_GET['refresh_captcha'])) {
    generateCaptcha();
}

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    $captcha_order = $_POST['captcha_order'] ?? '';
    
    $errors = [];
    
    if (empty($login) || empty($password)) {
        $errors[] = "Все поля должны быть заполнены";
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
    
    if (empty($errors)) {
        try {
            // Ищем пользователя по email или username
            $stmt = $pdo->prepare("SELECT * FROM Users WHERE Email = ? OR Username = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['Password_hash'])) {
                // Успешный вход
                
                // Сохраняем данные пользователя в сессии
                $_SESSION['user_id'] = $user['ID'];
                $_SESSION['username'] = $user['Username'];
                $_SESSION['email'] = $user['Email'];
                $_SESSION['role'] = $user['Role'];
                $_SESSION['avatar_path'] = $user['Avatar_path'];
                
                // Обновляем Last_login
                $updateStmt = $pdo->prepare("UPDATE Users SET Last_login = CURDATE() WHERE ID = ?");
                $updateStmt->execute([$user['ID']]);
                
                // Очищаем капчу из сессии
                unset($_SESSION['captcha_answer']);
                unset($_SESSION['captcha_shuffled']);
                unset($_SESSION['captcha_image']);
                
                // Перенаправляем в зависимости от роли пользователя
                if ($user['Role'] == 'admin') {
                    header("Location: Admin_Index.php");
                } else {
                    header("Location: my_profile.php");
                }
                exit();
            } else {
                $errors[] = "Неверный логин или пароль";
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
    <title>Вход</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_log_regis.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Вход</h1>
        
        <div class="login-container">
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
            
            <form id="loginForm" method="POST" action="">
                <div class="form-section">
                    <h2 class="section-title">Вход в аккаунт</h2>
                    
                    <div class="form-group">
                        <label for="login" class="form-label">Email или Логин *</label>
                        <input type="text" id="login" name="login" class="form-input" placeholder="Введите email или логин" required 
                               value="<?= isset($_POST['login']) ? htmlspecialchars($_POST['login']) : '' ?>">
                    </div>
                    
                    <div class="form-group password-toggle">
                        <label for="password" class="form-label">Пароль *</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Введите пароль" required>
                        <button type="button" class="toggle-password">👁️</button>
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
                    <input type="hidden" id="captcha_order" name="captcha_order" value="">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">Очистить форму</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Войти</button>
                </div>
            </form>
            
            <div class="register-section">
                <p>Еще нет аккаунта? <a href="register.php" class="register-link">Зарегистрируйтесь</a></p>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentCaptchaImage = '<?= $_SESSION['captcha_image'] ?? '1' ?>';
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
            
            // Очистка формы
            const resetBtn = document.getElementById('resetBtn');
            resetBtn.addEventListener('click', function() {
                document.getElementById('loginForm').reset();
                initCaptcha();
            });
        });
    </script>
</body>
</html>