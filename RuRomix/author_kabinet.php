<?php
include 'config.php';
include 'check_auth.php';

// Получаем данные автора из базы данных
$user_id = $_SESSION['user_id'];
$author_data = [];
$author_comics = [];

try {
    // Получаем данные пользователя
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE ID = ?");
    $stmt->execute([$user_id]);
    $author_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$author_data) {
        die("Пользователь не найден");
    }
    
    // Получаем комиксы автора
    $comics_stmt = $pdo->prepare("
        SELECT c.*, g.Name as Genre_name, 
               COUNT(DISTINCT ch.ID) as chapters_count,
               COUNT(DISTINCT cr.ID) as ratings_count,
               COUNT(DISTINCT cm.ID) as comments_count
        FROM Comics c 
        LEFT JOIN Genres g ON c.Genres_id = g.ID 
        LEFT JOIN Chapters ch ON c.ID = ch.Comics_id 
        LEFT JOIN Comics_ratings cr ON c.ID = cr.Comics_id 
        LEFT JOIN Comment cm ON c.ID = cm.Comics_id 
        WHERE c.Author_id = ? 
        GROUP BY c.ID
        ORDER BY c.Created_at DESC
    ");
    $comics_stmt->execute([$user_id]);
    $author_comics = $comics_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Получаем статистику автора
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_comics,
            SUM(CASE WHEN c.Status = '1' THEN 1 ELSE 0 END) as published_comics,
            SUM(CASE WHEN c.Status = '2' THEN 1 ELSE 0 END) as draft_comics,
            SUM(CASE WHEN c.Status = '3' THEN 1 ELSE 0 END) as archived_comics
        FROM Comics c 
        WHERE c.Author_id = ?
    ");
    $stats_stmt->execute([$user_id]);
    $author_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Получаем жанры для формы создания комикса
    $genres_stmt = $pdo->prepare("SELECT * FROM Genres ORDER BY Name");
    $genres_stmt->execute();
    $genres = $genres_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

// Обработка создания нового комикса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_comic'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $genre_id = $_POST['genre_id'] ?? '';
    $status = $_POST['status'] ?? '1';
    
    // Данные для главы
    $chapter_title = $_POST['chapter_title'] ?? 'Глава 1';
    $chapter_order = $_POST['chapter_order'] ?? 1;
    
    $errors = [];
    
    if (empty($title) || empty($description) || empty($genre_id)) {
        $errors[] = "Все обязательные поля должны быть заполнены";
    }
    
    if (empty($errors)) {
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();
            
            // Обработка загрузки обложки
            $cover_path = null;
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['cover'];
                
                // Проверка типа файла
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "Можно загружать только изображения (JPEG, PNG, GIF, WebP)";
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $errors[] = "Файл слишком большой. Максимальный размер: 5MB";
                } else {
                    // Создаем уникальное имя файла
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $cover_filename = 'cover_' . uniqid() . '.' . $extension;
                    $upload_path = 'covers/' . $cover_filename;
                    
                    // Создаем папку если не существует
                    if (!is_dir('covers')) {
                        mkdir('covers', 0777, true);
                    }
                    
                    // Перемещаем загруженный файл
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $cover_path = $upload_path;
                    } else {
                        $errors[] = "Ошибка при загрузке обложки";
                    }
                }
            }
            
            // Обработка загрузки файла главы
            $chapter_content = null;
            if (isset($_FILES['chapter_file']) && $_FILES['chapter_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['chapter_file'];
                
                // Проверка типа файла
                $allowed_chapter_types = [
                    'image/jpeg', 
                    'image/png', 
                    'image/gif',
                    'image/webp',
                    'image/svg+xml',
                    'application/pdf'
                ];
                
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_chapter_types)) {
                    $errors[] = "Можно загружать только изображения (JPG, PNG, GIF, WebP, SVG) или PDF";
                } elseif ($file['size'] > 10 * 1024 * 1024) {
                    $errors[] = "Файл слишком большой. Максимальный размер: 10MB";
                } else {
                    // Создаем уникальное имя файла
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $chapter_filename = 'chapter_' . uniqid() . '.' . $extension;
                    $chapter_upload_path = 'chapters/' . $chapter_filename;
                    
                    // Создаем папку если не существует
                    if (!is_dir('chapters')) {
                        mkdir('chapters', 0777, true);
                    }
                    
                    // Перемещаем загруженный файл
                    if (move_uploaded_file($file['tmp_name'], $chapter_upload_path)) {
                        $chapter_content = $chapter_upload_path;
                    } else {
                        $errors[] = "Ошибка при загрузке файла главы";
                    }
                }
            }
            
            if (empty($errors)) {
                // Создаем комикс в базе данных
                $insert_stmt = $pdo->prepare("
                    INSERT INTO Comics (Title, Description, Author_id, Status, Genres_id, Cover_path, Created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                ");
                
                if ($insert_stmt->execute([$title, $description, $user_id, $status, $genre_id, $cover_path])) {
                    $comic_id = $pdo->lastInsertId();
                    
                    // Создаем главу
                    if ($chapter_content) {
                        // Создаем главу с файлом
                        $chapter_stmt = $pdo->prepare("
                            INSERT INTO Chapters (Comics_id, Title, Order_number, Content, Created_at) 
                            VALUES (?, ?, ?, ?, CURDATE())
                        ");
                        $chapter_stmt->execute([$comic_id, $chapter_title, $chapter_order, $chapter_content]);
                    } else {
                        // Создаем главу без файла
                        $chapter_stmt = $pdo->prepare("
                            INSERT INTO Chapters (Comics_id, Title, Order_number, Created_at) 
                            VALUES (?, ?, ?, CURDATE())
                        ");
                        $chapter_stmt->execute([$comic_id, $chapter_title, $chapter_order]);
                    }
                    
                    // Фиксируем транзакцию
                    $pdo->commit();
                    
                    $success_message = "Комикс успешно создан!" . ($chapter_content ? " Глава добавлена." : "");
                    // Обновляем список комиксов
                    header("Location: author_kabinet.php?success=1");
                    exit();
                } else {
                    $pdo->rollBack();
                    $errors[] = "Ошибка при создании комикса";
                }
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}

// Форматируем дату регистрации
$join_date = date('d.m.Y', strtotime($author_data['Created_at']));
$join_date_full = date('d F Y', strtotime($author_data['Created_at']));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кабинет автора - RuRomix</title>
    <link rel="stylesheet" href="style_kabinets.css">
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_create_comics.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Кабинет автора</h1>
        
        <!-- Вывод сообщений об ошибках и успехе -->
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="success-message">
                <p>Комикс успешно создан!</p>
            </div>
        <?php endif; ?>
        
        <div class="author-container">
            <div class="author-header">
                <div class="author-avatar" style="background-image: url('<?= htmlspecialchars($author_data['Avatar_path']) ?>')">
                    <?php if (empty($author_data['Avatar_path']) || $author_data['Avatar_path'] == 'umolch_avatar.jpeg'): ?>
                        <?= strtoupper(substr($author_data['Username'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="author-info">
                    <h2 class="author-name"><?= htmlspecialchars($author_data['Username']) ?></h2>
                    <p>Автор комиксов • На платформе с <?= htmlspecialchars($join_date_full) ?></p>
                    <div class="author-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $author_stats['total_comics'] ?? 0 ?></div>
                            <div class="stat-label">Комиксов</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $author_stats['published_comics'] ?? 0 ?></div>
                            <div class="stat-label">Опубликовано</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $author_stats['draft_comics'] ?? 0 ?></div>
                            <div class="stat-label">Черновиков</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $author_stats['archived_comics'] ?? 0 ?></div>
                            <div class="stat-label">В архиве</div>
                        </div>
                    </div>
                </div>
                <button class="create-comic-btn" onclick="openCreateModal()">+ Создать комикс</button>
            </div>

            <div class="comics-section">
                <div class="section-header">
                    <h3 class="section-title">Мои комиксы</h3>
                </div>

                <div class="tabs">
                    <button class="tab active" onclick="filterComics('all')">Все</button>
                    <button class="tab" onclick="filterComics('1')">Опубликованные</button>
                    <button class="tab" onclick="filterComics('2')">Черновики</button>
                    <button class="tab" onclick="filterComics('3')">Архив</button>
                </div>

                <div class="comics-grid" id="comicsGrid">
                    <?php if (empty($author_comics)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📚</div>
                            <div class="empty-state-text">У вас пока нет комиксов</div>
                            <button class="create-comic-btn" onclick="openCreateModal()">Создать первый комикс</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($author_comics as $comic): ?>
                            <?php
                            $status_class = 'status-' . ($comic['Status'] == '1' ? 'published' : ($comic['Status'] == '2' ? 'draft' : 'archived'));
                            $status_text = $comic['Status'] == '1' ? 'Опубликован' : ($comic['Status'] == '2' ? 'Черновик' : 'В архиве');
                            $created_date = date('d.m.Y', strtotime($comic['Created_at']));
                            ?>
                            
                            <div class="comic-card" data-status="<?= $comic['Status'] ?>">
                                <div class="comic-actions">
                                    <button class="action-btn" title="Редактировать" onclick="location.href='redact_comics.php?id=<?= $comic['ID'] ?>'">✏️</button>
                                    <button class="action-btn" title="Удалить" onclick="deleteComic(<?= $comic['ID'] ?>, '<?= htmlspecialchars($comic['Title']) ?>')">🗑️</button>
                                </div>
                                
                                <!-- Ссылка на страницу комикса -->
                                <a href="comic_detail.php?id=<?= $comic['ID'] ?>" class="comic-link">
                                    <div class="comic-cover-link">
                                        <?php if (!empty($comic['Cover_path'])): ?>
                                            <img src="<?= htmlspecialchars($comic['Cover_path']) ?>" alt="<?= htmlspecialchars($comic['Title']) ?>">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">
                                                Обложка
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="comic-title"><?= htmlspecialchars($comic['Title']) ?></h3>
                                </a>
                                
                                <div class="comic-meta">
                                    <span>Глав: <?= $comic['chapters_count'] ?? 0 ?></span>
                                    <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                </div>
                                <p style="font-size: 12px; color: #808367; margin-bottom: 10px;"><?= htmlspecialchars($comic['Genre_name']) ?></p>
                                <div class="comic-stats">
                                    <div class="stat">
                                        <div class="stat-number"><?= $comic['ratings_count'] ?? 0 ?></div>
                                        <div class="stat-label">Оценок</div>
                                    </div>
                                    <div class="stat">
                                        <div class="stat-number"><?= $comic['comments_count'] ?? 0 ?></div>
                                        <div class="stat-label">Комментарии</div>
                                    </div>
                                </div>
                                <div style="font-size: 11px; color: #808367; margin-top: 10px;">
                                    Создан: <?= $created_date ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Модальное окно создания комикса -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Создать новый комикс</h3>
                <button class="close-modal" onclick="closeCreateModal()">×</button>
            </div>
            
            <!-- Индикатор прогресса -->
            <div class="form-progress">
                <div class="form-progress-bar" id="progressBar"></div>
            </div>
            
            <!-- Индикатор шагов -->
            <div class="step-indicator">
                <div class="step active" id="step1Indicator">
                    <div class="step-number">1</div>
                    <span>Основное</span>
                </div>
                <div class="step" id="step2Indicator">
                    <div class="step-number">2</div>
                    <span>Глава</span>
                </div>
                <div class="step" id="step3Indicator">
                    <div class="step-number">3</div>
                    <span>Статус</span>
                </div>
            </div>
            
            <form id="createComicForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="create_comic" value="1">
                
                <div class="modal-body">
                    <!-- Шаг 1: Основная информация -->
                    <div class="form-step active" id="step1">
                        <div class="form-group">
                            <label class="form-label" for="comicTitle">Название комикса *</label>
                            <input type="text" id="comicTitle" name="title" class="form-input" placeholder="Введите название комикса" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="comicDescription">Описание *</label>
                            <textarea id="comicDescription" name="description" class="form-input form-textarea" placeholder="Опишите ваш комикс..." required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="comicGenre">Жанр *</label>
                            <select id="comicGenre" name="genre_id" class="form-input" required>
                                <option value="">Выберите жанр</option>
                                <?php foreach ($genres as $genre): ?>
                                    <option value="<?= $genre['ID'] ?>"><?= htmlspecialchars($genre['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Обложка комикса (необязательно)</label>
                            <div class="file-upload">
                                <label class="file-upload-label">
                                    <div class="file-upload-icon">📁</div>
                                    <div>
                                        <div>Нажмите для загрузки обложки</div>
                                        <small style="color: #808367;">PNG, JPG, GIF, WebP (макс. 5MB)</small>
                                    </div>
                                    <input type="file" id="comicCover" name="cover" accept="image/*">
                                </label>
                            </div>
                            <div id="coverPreview" style="margin-top: 10px; display: none;">
                                <img id="coverPreviewImage" src="" alt="Предпросмотр обложки" style="max-width: 200px; max-height: 200px; border-radius: 4px; border: 1px solid #ddd;">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Шаг 2: Глава -->
                    <div class="form-step" id="step2">
                        <div class="form-group">
                            <h4 style="margin-bottom: 15px;">Первая глава (необязательно)</h4>
                            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Вы можете добавить первую главу сейчас или сделать это позже</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="chapterTitle">Название главы</label>
                            <input type="text" id="chapterTitle" name="chapter_title" class="form-input" placeholder="Глава 1" value="Глава 1">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="chapterOrder">Порядковый номер главы</label>
                            <input type="number" id="chapterOrder" name="chapter_order" class="form-input" min="1" value="1">
                            <small style="color: #808367; font-size: 12px;">Определяет порядок глав в комиксе</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Файл главы (изображение или PDF)</label>
                            <div class="file-upload">
                                <label class="file-upload-label">
                                    <div class="file-upload-icon">🖼️</div>
                                    <div>
                                        <div>Нажмите для загрузки файла</div>
                                        <small style="color: #808367;">JPG, PNG, GIF, WebP, SVG, PDF (макс. 10MB)</small>
                                    </div>
                                    <input type="file" id="chapterFile" name="chapter_file" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.tiff,.svg,.pdf">
                                </label>
                            </div>
                            <div id="chapterPreview" style="margin-top: 10px; display: none;">
                                <div id="chapterPreviewContent">
                                    <img id="chapterPreviewImage" src="" alt="Предпросмотр главы" style="max-width: 200px; max-height: 200px; border-radius: 4px; border: 1px solid #ddd; display: none;">
                                    <div id="chapterPreviewPdf" style="display: none; text-align: center; padding: 20px; background: #f0f0f0; border-radius: 4px;">
                                        <div style="font-size: 24px; margin-bottom: 10px;">📄</div>
                                        <div style="font-size: 12px; color: #666;">PDF-файл</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Шаг 3: Статус -->
                    <div class="form-step" id="step3">
                        <div class="form-group">
                            <h4 style="margin-bottom: 15px;">Статус комикса</h4>
                            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Выберите статус для вашего комикса</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="comicStatus">Статус публикации</label>
                            <select id="comicStatus" name="status" class="form-input" required>
                                <option value="2">Черновик (виден только вам)</option>
                                <option value="1">Опубликовать (виден всем пользователям)</option>
                                <option value="3">Архив (скрыт от всех)</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 20px;">
                            <h5 style="margin-bottom: 10px;">Сводка</h5>
                            <div id="formSummary" style="font-size: 14px; color: #666;">
                                <!-- Здесь будет отображаться сводка заполненных данных -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="step-btn prev" id="prevBtn" style="display: none;">← Назад</button>
                    <div>
                        <button type="button" class="step-btn next" id="nextBtn">Далее →</button>
                        <button type="submit" class="step-btn submit" id="submitBtn" style="display: none;">Создать комикс</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Переменные для управления шагами
        let currentStep = 1;
        const totalSteps = 3;
        
        // Элементы шагов
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        
        // Индикаторы шагов
        const step1Indicator = document.getElementById('step1Indicator');
        const step2Indicator = document.getElementById('step2Indicator');
        const step3Indicator = document.getElementById('step3Indicator');
        
        // Кнопки навигации
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        
        // Прогресс бар
        const progressBar = document.getElementById('progressBar');
        
        // Функция обновления прогресса
        function updateProgress() {
            const progress = (currentStep - 1) / (totalSteps - 1) * 100;
            progressBar.style.width = progress + '%';
        }
        
        // Функция перехода к шагу
        function goToStep(step) {
            // Скрываем все шаги
            step1.classList.remove('active');
            step2.classList.remove('active');
            step3.classList.remove('active');
            
            // Убираем активный класс со всех индикаторов
            step1Indicator.classList.remove('active', 'completed');
            step2Indicator.classList.remove('active', 'completed');
            step3Indicator.classList.remove('active', 'completed');
            
            // Показываем текущий шаг
            if (step === 1) {
                step1.classList.add('active');
                step1Indicator.classList.add('active');
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'inline-block';
                submitBtn.style.display = 'none';
            } else if (step === 2) {
                step2.classList.add('active');
                step2Indicator.classList.add('active');
                step1Indicator.classList.add('completed');
                prevBtn.style.display = 'inline-block';
                nextBtn.style.display = 'inline-block';
                submitBtn.style.display = 'none';
            } else if (step === 3) {
                step3.classList.add('active');
                step3Indicator.classList.add('active');
                step1Indicator.classList.add('completed');
                step2Indicator.classList.add('completed');
                prevBtn.style.display = 'inline-block';
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'inline-block';
                updateFormSummary();
            }
            
            currentStep = step;
            updateProgress();
            
            // Прокручиваем к верху формы
            document.querySelector('.modal-body').scrollTop = 0;
        }
        
        // Функция обновления сводки формы
        function updateFormSummary() {
            const title = document.getElementById('comicTitle').value || 'Не указано';
            const genre = document.getElementById('comicGenre').selectedOptions[0]?.text || 'Не указан';
            const status = document.getElementById('comicStatus').selectedOptions[0]?.text || 'Черновик';
            const chapterTitle = document.getElementById('chapterTitle').value || 'Не указана';
            const chapterOrder = document.getElementById('chapterOrder').value || '1';
            const chapterFile = document.getElementById('chapterFile').files[0];
            const coverFile = document.getElementById('comicCover').files[0];
            
            let summaryHTML = `
                <p><strong>Название:</strong> ${title}</p>
                <p><strong>Жанр:</strong> ${genre}</p>
                <p><strong>Статус:</strong> ${status}</p>
                <p><strong>Обложка:</strong> ${coverFile ? 'Загружена' : 'Не загружена'}</p>
                <p><strong>Первая глава:</strong> ${chapterFile ? `${chapterTitle} (№${chapterOrder}, ${chapterFile.type.startsWith('image/') ? 'изображение' : 'PDF'})` : 'Не добавлена'}</p>
            `;
            
            document.getElementById('formSummary').innerHTML = summaryHTML;
        }
        
        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            // Обработчик кнопки "Далее"
            nextBtn.addEventListener('click', function() {
                // Проверяем валидность текущего шага
                if (currentStep === 1) {
                    const title = document.getElementById('comicTitle').value;
                    const description = document.getElementById('comicDescription').value;
                    const genre = document.getElementById('comicGenre').value;
                    
                    if (!title || !description || !genre) {
                        alert('Пожалуйста, заполните все обязательные поля (отмечены *)');
                        return;
                    }
                }
                
                if (currentStep < totalSteps) {
                    goToStep(currentStep + 1);
                }
            });
            
            // Обработчик кнопки "Назад"
            prevBtn.addEventListener('click', function() {
                if (currentStep > 1) {
                    goToStep(currentStep - 1);
                }
            });
            
            // Обработчик загрузки обложки
            document.getElementById('comicCover').addEventListener('change', function(e) {
                const preview = document.getElementById('coverPreview');
                const previewImage = document.getElementById('coverPreviewImage');
                const file = e.target.files[0];
                
                if (file) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                }
            });
            
            // Обработчик загрузки файла главы
            document.getElementById('chapterFile').addEventListener('change', function(e) {
                const preview = document.getElementById('chapterPreview');
                const previewImage = document.getElementById('chapterPreviewImage');
                const previewPdf = document.getElementById('chapterPreviewPdf');
                const file = e.target.files[0];
                
                if (file) {
                    if (file.type.startsWith('image/')) {
                        // Для изображений
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            previewImage.src = e.target.result;
                            previewImage.style.display = 'block';
                            previewPdf.style.display = 'none';
                            preview.style.display = 'block';
                        }
                        
                        reader.readAsDataURL(file);
                    } else if (file.type === 'application/pdf') {
                        // Для PDF
                        previewImage.style.display = 'none';
                        previewPdf.style.display = 'block';
                        preview.style.display = 'block';
                    }
                } else {
                    preview.style.display = 'none';
                }
            });
        });
        
        // Фильтрация комиксов по статусу
        function filterComics(status) {
            const comics = document.querySelectorAll('.comic-card');
            const tabs = document.querySelectorAll('.tab');
            
            // Обновляем активную вкладку
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Показываем/скрываем комиксы
            comics.forEach(comic => {
                if (status === 'all' || comic.dataset.status === status) {
                    comic.style.display = 'block';
                } else {
                    comic.style.display = 'none';
                }
            });
        }
        
        // Управление модальным окном
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'flex';
            // Сбрасываем форму и шаги
            goToStep(1);
            document.getElementById('createComicForm').reset();
            document.getElementById('coverPreview').style.display = 'none';
            document.getElementById('chapterPreview').style.display = 'none';
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        // Функция удаления комикса
        function deleteComic(comicId, comicTitle) {
            if (confirm(`Вы уверены, что хотите удалить комикс "${comicTitle}"? Это действие нельзя отменить.`)) {
                // Отправляем AJAX запрос для удаления
                fetch('delete_comic.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `comic_id=${comicId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Комикс успешно удален');
                        location.reload();
                    } else {
                        alert('Ошибка при удалении: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Произошла ошибка при удалении');
                });
            }
        }
        
        // Закрытие модального окна при клике вне его
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('createModal');
            if (e.target === modal) {
                closeCreateModal();
            }
        });
        
        // Валидация формы перед отправкой
        document.getElementById('createComicForm').addEventListener('submit', function(e) {
            // Проверяем обязательные поля
            const title = document.getElementById('comicTitle').value;
            const description = document.getElementById('comicDescription').value;
            const genre = document.getElementById('comicGenre').value;
            
            if (!title || !description || !genre) {
                e.preventDefault();
                alert('Пожалуйста, заполните все обязательные поля');
                goToStep(1);
                return false;
            }
            
            // Проверка размера файлов
            const coverFile = document.getElementById('comicCover').files[0];
            const chapterFile = document.getElementById('chapterFile').files[0];
            let isValid = true;
            
            if (coverFile && coverFile.size > 5 * 1024 * 1024) {
                alert('Размер файла обложки не должен превышать 5MB');
                isValid = false;
            }
            
            if (chapterFile && chapterFile.size > 10 * 1024 * 1024) {
                alert('Размер файла главы не должен превышать 10MB');
                isValid = false;
            }
            
            // Проверка типа файла для главы
            if (chapterFile) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/pdf'];
                const fileType = chapterFile.type;
                
                if (!allowedTypes.includes(fileType)) {
                    alert('Файл главы должен быть изображением (JPG, PNG, GIF, WebP, SVG) или PDF');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
            
            return isValid;
        });
    </script>
</body>
</html>