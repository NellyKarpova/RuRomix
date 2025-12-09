<?php
include 'config.php';
include 'check_auth.php';
session_start();

// Получаем ID комикса из GET параметра
$comic_id = $_GET['id'] ?? null;

if (!$comic_id) {
    header("Location: author_kabinet.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Получаем текущие данные комикса
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.Username as Author_name, g.Name as Genre_name 
        FROM Comics c 
        LEFT JOIN Users u ON c.Author_id = u.ID 
        LEFT JOIN Genres g ON c.Genres_id = g.ID 
        WHERE c.ID = ?
    ");
    $stmt->execute([$comic_id]);
    $comic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comic) {
        $errors[] = "Комикс не найден";
        header("Location: author_kabinet.php");
        exit;
    }
    
    // Проверяем, что пользователь - автор комикса
    if ($comic['Author_id'] != $user_id) {
        $errors[] = "У вас нет прав для редактирования этого комикса";
        header("Location: author_kabinet.php");
        exit;
    }
} catch (PDOException $e) {
    $errors[] = "Ошибка базы данных: " . $e->getMessage();
    header("Location: author_kabinet.php");
    exit;
}

// Получаем список жанров
try {
    $genres_stmt = $pdo->prepare("SELECT ID, Name FROM Genres ORDER BY Name");
    $genres_stmt->execute();
    $genres = $genres_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Ошибка загрузки жанров: " . $e->getMessage();
    $genres = [];
}

// Обработка формы редактирования комикса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_comic'])) {
    // Получаем данные из формы
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $genre_id = $_POST['genre_id'] ?? '';
    $status = $_POST['status'] ?? '1';
    
    // Валидация
    if (empty($title) || empty($description) || empty($genre_id)) {
        $errors[] = "Название, описание и жанр обязательны для заполнения";
    }
    
    // Проверка уникальности названия (кроме текущего комикса)
    if (empty($errors)) {
        try {
            $checkStmt = $pdo->prepare("SELECT ID FROM Comics WHERE Title = ? AND ID != ? AND Author_id = ?");
            $checkStmt->execute([$title, $comic_id, $user_id]);
            if ($checkStmt->fetch()) {
                $errors[] = "У вас уже есть комикс с таким названием";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка проверки данных: " . $e->getMessage();
        }
    }
    
    // Обработка обложки
    $cover_path = $comic['Cover_path']; // сохраняем текущий путь
    
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover'];
        
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
            $cover_filename = 'cover_' . uniqid() . '.' . $extension;
            $upload_path = 'covers/' . $cover_filename;
            
            // Создаем папку если не существует
            if (!is_dir('covers')) {
                mkdir('covers', 0777, true);
            }
            
            // Перемещаем загруженный файл
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $cover_path = $upload_path;
                
                // Удаляем старую обложку, если она существует
                if (!empty($comic['Cover_path']) && file_exists($comic['Cover_path']) && $comic['Cover_path'] != $upload_path) {
                    unlink($comic['Cover_path']);
                }
            } else {
                $errors[] = "Ошибка при загрузке обложки";
            }
        }
    }
    
    // Если ошибок нет - обновляем данные комикса
    if (empty($errors)) {
        try {
            $update_stmt = $pdo->prepare("
                UPDATE Comics 
                SET Title = ?, Description = ?, Status = ?, Genres_id = ?, Cover_path = ?
                WHERE ID = ? AND Author_id = ?
            ");
            
            if ($update_stmt->execute([$title, $description, $status, $genre_id, $cover_path, $comic_id, $user_id])) {
                $success = "Комикс успешно обновлен!";
                
                // Обновляем переменную $comic для отображения новых данных
                $comic['Title'] = $title;
                $comic['Description'] = $description;
                $comic['Status'] = $status;
                $comic['Genres_id'] = $genre_id;
                $comic['Cover_path'] = $cover_path;
                
                // Обновляем название жанра
                foreach ($genres as $genre) {
                    if ($genre['ID'] == $genre_id) {
                        $comic['Genre_name'] = $genre['Name'];
                        break;
                    }
                }
            } else {
                $errors[] = "Ошибка при обновлении комикса";
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
    <title>Редактирование комикса - RuRomix</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_redact.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Редактирование комикса</h1>
        
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

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_comic" value="1">
                
                <div class="comic-header">
                    <div class="cover-section">
                        <div class="cover-preview" id="coverPreview">
                            <?php if (!empty($comic['Cover_path']) && file_exists($comic['Cover_path'])): ?>
                                <img src="<?= htmlspecialchars($comic['Cover_path']) ?>" alt="Обложка комикса">
                            <?php else: ?>
                                <span>Обложка</span>
                            <?php endif; ?>
                        </div>
                        <div class="cover-upload">
                            <input type="file" id="coverInput" name="cover" class="cover-input" accept="image/*">
                            <label for="coverInput" class="cover-label">Изменить обложку</label>
                            <span style="font-size: 12px; color: #808367;">JPG, PNG, GIF до 5MB</span>
                        </div>
                    </div>
                    
                    <div class="comic-info">
                        <h2 style="color: #92ad71; margin-bottom: 10px;"><?= htmlspecialchars($comic['Title']) ?></h2>
                        <p style="color: #808367; margin-bottom: 5px;">Автор: <?= htmlspecialchars($comic['Author_name']) ?></p>
                        <p style="color: #808367; margin-bottom: 5px;">Жанр: <?= htmlspecialchars($comic['Genre_name']) ?></p>
                        <p style="color: #808367; margin-bottom: 5px;">Создан: <?= htmlspecialchars($comic['Created_at']) ?></p>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Основная информация</h3>
                    
                    <div class="form-group">
                        <label class="form-label" for="title">Название комикса *</label>
                        <input type="text" id="title" name="title" class="form-input" 
                               value="<?= htmlspecialchars($comic['Title']) ?>" 
                               placeholder="Введите название комикса" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="description">Описание *</label>
                        <textarea id="description" name="description" class="form-input form-textarea" 
                                  placeholder="Опишите сюжет вашего комикса..." required><?= htmlspecialchars($comic['Description']) ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="genre_id">Жанр *</label>
                            <select id="genre_id" name="genre_id" class="form-select" required>
                                <option value="">Выберите жанр</option>
                                <?php foreach ($genres as $genre): ?>
                                    <option value="<?= $genre['ID'] ?>" 
                                        <?= $genre['ID'] == $comic['Genres_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($genre['Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="status">Статус *</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="1" <?= $comic['Status'] == '1' ? 'selected' : '' ?>>Опубликован</option>
                                <option value="2" <?= $comic['Status'] == '2' ? 'selected' : '' ?>>Черновик</option>
                                <option value="3" <?= $comic['Status'] == '3' ? 'selected' : '' ?>>В архиве</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="author_kabinet.php" class="btn btn-secondary">Назад в кабинет</a>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete(<?= $comic_id ?>, '<?= htmlspecialchars(addslashes($comic['Title'])) ?>')">Удалить комикс</button>
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Обработчик загрузки обложки
        document.getElementById('coverInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('Файл слишком большой. Максимальный размер: 5MB');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const coverPreview = document.getElementById('coverPreview');
                    coverPreview.innerHTML = `<img src="${e.target.result}" alt="Обложка комикса">`;
                };
                reader.readAsDataURL(file);
            }
        });

        // Функция для удаления комикса
        function confirmDelete(comicId, comicTitle) {
            if (confirm('Вы уверены, что хотите удалить комикс "' + comicTitle + '"? Это действие нельзя отменить.')) {
                fetch('delete_comics.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + comicId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Комикс успешно удален');
                        window.location.href = 'author_kabinet.php';
                    } else {
                        alert('Ошибка при удалении: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Произошла ошибка при удалении');
                });
            }
        }
    </script>
</body>
</html>