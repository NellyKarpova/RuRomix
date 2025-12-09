<?php
include 'config.php';
session_start();

// Получаем ID главы из GET-параметра
$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;

if ($chapter_id === 0) {
    header("Location: Index_RuRomix.php");
    exit;
}

// Получаем данные главы и комикса
$chapter_data = [];
$comic_data = [];
$next_chapter = null;
$prev_chapter = null;

try {
    // Получаем данные главы
    $stmt = $pdo->prepare("
        SELECT ch.*, c.Title as comic_title, c.ID as comic_id, 
               c.Author_id, u.Username as author_name
        FROM Chapters ch
        INNER JOIN Comics c ON ch.Comics_id = c.ID
        INNER JOIN Users u ON c.Author_id = u.ID
        WHERE ch.ID = ?
    ");
    $stmt->execute([$chapter_id]);
    $chapter_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter_data) {
        header("Location: Index_RuRomix.php");
        exit;
    }
    
    // Получаем данные комикса
    $comic_stmt = $pdo->prepare("SELECT * FROM Comics WHERE ID = ?");
    $comic_stmt->execute([$chapter_data['comic_id']]);
    $comic_data = $comic_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Получаем следующую главу
    $next_stmt = $pdo->prepare("
        SELECT * FROM Chapters 
        WHERE Comics_id = ? AND Order_number > ? 
        ORDER BY Order_number ASC 
        LIMIT 1
    ");
    $next_stmt->execute([$chapter_data['comic_id'], $chapter_data['Order_number']]);
    $next_chapter = $next_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Получаем предыдущую главу
    $prev_stmt = $pdo->prepare("
        SELECT * FROM Chapters 
        WHERE Comics_id = ? AND Order_number < ? 
        ORDER BY Order_number DESC 
        LIMIT 1
    ");
    $prev_stmt->execute([$chapter_data['comic_id'], $chapter_data['Order_number']]);
    $prev_chapter = $prev_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

// Определяем тип файла
$file_extension = '';
$is_image = false;
$is_pdf = false;

if (!empty($chapter_data['Content'])) {
    $file_extension = strtolower(pathinfo($chapter_data['Content'], PATHINFO_EXTENSION));
    $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg']);
    $is_pdf = ($file_extension === 'pdf');
}

// Проверяем существование файла
$file_exists = false;
if (!empty($chapter_data['Content']) && file_exists($chapter_data['Content'])) {
    $file_exists = true;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($chapter_data['Title']) ?> - <?= htmlspecialchars($chapter_data['comic_title']) ?></title>
    <link rel="stylesheet" href="style_main.css">
    <style>
        /* Стили для читалки */
        .chapter-header {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .chapter-header h1 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 24px;
        }
        
        .chapter-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .back-to-comic {
            display: inline-block;
            margin-bottom: 15px;
            color: #666;
            text-decoration: none;
        }
        
        .back-to-comic:hover {
            color: #333;
        }
        
        .chapter-content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 80px;
        }
        
        .chapter-content {
            text-align: center;
            margin: 20px 0;
        }
        
        .chapter-content img {
            max-width: 100%;
            height: auto;
            margin: 10px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .reader-controls {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #ddd;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .reader-nav {
            display: flex;
            gap: 10px;
        }
        
        .reader-nav-btn {
            padding: 10px 20px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .reader-nav-btn:hover {
            background: #f5f5f5;
        }
        
        .reader-nav-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .file-not-found {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .file-not-found-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .chapter-list-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #f0f0f0;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .chapter-list-link:hover {
            background: #e0e0e0;
        }
        
        .no-content {
            text-align: center;
            padding: 50px 20px;
            color: #666;
            font-size: 16px;
        }
        
        .current-chapter {
            display: inline-block;
            margin-left: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
    <!-- Подключаем PDF.js для отображения PDF -->
    <?php if ($is_pdf): ?>
    <script src="https://mozilla.github.io/pdf.js/build/pdf.js"></script>
    <?php endif; ?>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="chapter-header">
            <a href="comic_detail.php?id=<?= $chapter_data['comic_id'] ?>" class="back-to-comic">
                ← Назад к комиксу
            </a>
            <h1><?= htmlspecialchars($chapter_data['Title']) ?></h1>
            <div class="chapter-meta">
                <strong><?= htmlspecialchars($chapter_data['comic_title']) ?></strong> • 
                Глава <?= $chapter_data['Order_number'] ?> • 
                Автор: <?= htmlspecialchars($chapter_data['author_name']) ?>
                <?php if (!empty($chapter_data['Content'])): ?>
                    • Формат: .<?= $file_extension ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="chapter-content-wrapper">
            <?php if (!empty($chapter_data['Content'])): ?>
                <?php if ($file_exists): ?>
                    <?php if ($is_image): ?>
                        <!-- Отображение изображения -->
                        <div class="chapter-content">
                            <img src="<?= htmlspecialchars($chapter_data['Content']) ?>" 
                                 alt="<?= htmlspecialchars($chapter_data['Title']) ?>">
                        </div>
                    <?php elseif ($is_pdf): ?>
                        <!-- Отображение PDF -->
                        <div class="chapter-content">
                            <canvas id="pdf-canvas"></canvas>
                        </div>
                    <?php else: ?>
                        <!-- Другие типы файлов (скачивание) -->
                        <div class="file-not-found">
                            <div class="file-not-found-icon">📄</div>
                            <h3>Файл недоступен для просмотра</h3>
                            <p>Формат файла .<?= $file_extension ?> не поддерживается для онлайн-просмотра.</p>
                            <a href="<?= htmlspecialchars($chapter_data['Content']) ?>" 
                               download 
                               class="chapter-list-link">
                                Скачать файл
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Файл не найден -->
                    <div class="file-not-found">
                        <div class="file-not-found-icon">❌</div>
                        <h3>Файл не найден</h3>
                        <p>Файл главы отсутствует на сервере.</p>
                        <a href="comic_detail.php?id=<?= $chapter_data['comic_id'] ?>" 
                           class="chapter-list-link">
                            Вернуться к списку глав
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Нет файла главы -->
                <div class="no-content">
                    <div style="font-size: 48px; margin-bottom: 20px;">📖</div>
                    <h3>Контент главы отсутствует</h3>
                    <p>Автор еще не добавил содержимое для этой главы.</p>
                    <a href="comic_detail.php?id=<?= $chapter_data['comic_id'] ?>" 
                       class="chapter-list-link">
                        Вернуться к списку глав
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Панель навигации по главам -->
    <div class="reader-controls">
        <div class="reader-nav">
            <?php if ($prev_chapter): ?>
                <a href="chapters.php?chapter_id=<?= $prev_chapter['ID'] ?>" 
                   class="reader-nav-btn">
                    ← Предыдущая глава
                </a>
            <?php else: ?>
                <span class="reader-nav-btn disabled">← Предыдущая глава</span>
            <?php endif; ?>
            
            <a href="comic_detail.php?id=<?= $chapter_data['comic_id'] ?>" 
               class="reader-nav-btn">
                К списку глав
            </a>
        </div>
        
        <div class="current-chapter">
            Глава <?= $chapter_data['Order_number'] ?>
        </div>
        
        <div class="reader-nav">
            <?php if ($next_chapter): ?>
                <a href="chapters.php?chapter_id=<?= $next_chapter['ID'] ?>" 
                   class="reader-nav-btn">
                    Следующая глава →
                </a>
            <?php else: ?>
                <span class="reader-nav-btn disabled">Следующая глава →</span>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Управление отображением PDF
        <?php if ($is_pdf && $file_exists): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // URL PDF файла
            const pdfUrl = '<?= htmlspecialchars($chapter_data['Content']) ?>';
            
            // Конфигурация PDF.js
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://mozilla.github.io/pdf.js/build/pdf.worker.js';
            
            let pdfDoc = null;
            let pageNum = 1;
            let pageRendering = false;
            let pageNumPending = null;
            const scale = 1.5;
            
            const canvas = document.getElementById('pdf-canvas');
            const ctx = canvas.getContext('2d');
            
            // Рендеринг страницы
            function renderPage(num) {
                pageRendering = true;
                pdfDoc.getPage(num).then(function(page) {
                    const viewport = page.getViewport({scale: scale});
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    const renderContext = {
                        canvasContext: ctx,
                        viewport: viewport
                    };
                    
                    const renderTask = page.render(renderContext);
                    renderTask.promise.then(function() {
                        pageRendering = false;
                        if (pageNumPending !== null) {
                            renderPage(pageNumPending);
                            pageNumPending = null;
                        }
                    });
                });
            }
            
            // Загрузка PDF
            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                renderPage(pageNum);
            });
        });
        <?php endif; ?>
        
        // Управление клавишами для навигации
        document.addEventListener('keydown', function(e) {
            <?php if ($prev_chapter): ?>
            if (e.key === 'ArrowLeft') {
                window.location.href = 'chapters.php?chapter_id=<?= $prev_chapter['ID'] ?>';
            }
            <?php endif; ?>
            
            <?php if ($next_chapter): ?>
            if (e.key === 'ArrowRight') {
                window.location.href = 'chapters.php?chapter_id=<?= $next_chapter['ID'] ?>';
            }
            <?php endif; ?>
            
            // Esc для выхода
            if (e.key === 'Escape') {
                window.location.href = 'comic_detail.php?id=<?= $chapter_data['comic_id'] ?>';
            }
        });
    </script>
</body>
</html>