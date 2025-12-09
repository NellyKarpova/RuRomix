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
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    // Устанавливаем роль пользователя в сессии, если её нет
    if (!isset($_SESSION['user_role'])) {
        $_SESSION['user_role'] = $user_data['Role'];
    }
    
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

// Проверяем, что пользователь администратор
if ($_SESSION['user_role'] != 'admin') {
    die('У вас нет прав для доступа к этой странице');
}

// ========== ФУНКЦИИ ДЛЯ ЭКСПОРТА/ИМПОРТА ==========

// Функция для экспорта таблицы в JSON
function exportTableToJSON($pdo, $tableName) {
    try {
        // Получаем данные из таблицы
        $stmt = $pdo->query("SELECT * FROM $tableName");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($data)) {
            return false;
        }
        
        // Получаем информацию о таблице
        $stmt = $pdo->query("SHOW CREATE TABLE $tableName");
        $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Формируем структуру JSON
        $exportData = [
            'table_name' => $tableName,
            'table_structure' => $tableInfo['Create Table'],
            'export_date' => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'data' => $data
        ];
        
        // Кодируем в JSON с красивым форматированием
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($jsonContent === false) {
            throw new Exception("Ошибка кодирования JSON: " . json_last_error_msg());
        }
        
        // Создаем временный файл
        $filename = tempnam(sys_get_temp_dir(), $tableName . '_export_');
        file_put_contents($filename, $jsonContent);
        
        return $filename;
        
    } catch (PDOException $e) {
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Функция для импорта данных из JSON
function importTableFromJSON($pdo, $tableName, $jsonFile) {
    try {
        // Проверяем существование таблицы
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($stmt->rowCount() == 0) {
            return "Таблица $tableName не существует";
        }
        
        // Читаем JSON файл
        $jsonContent = file_get_contents($jsonFile);
        if (!$jsonContent) {
            return "Не удалось прочитать JSON файл";
        }
        
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return "Ошибка декодирования JSON: " . json_last_error_msg();
        }
        
        // Проверяем структуру JSON
        if (!isset($data['table_name']) || !isset($data['data']) || !is_array($data['data'])) {
            return "Некорректный формат JSON файла";
        }
        
        if ($data['table_name'] !== $tableName) {
            return "Имя таблицы в файле ({$data['table_name']}) не соответствует выбранной таблице ($tableName)";
        }
        
        // Получаем структуру таблицы
        $stmt = $pdo->query("DESCRIBE $tableName");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        if (empty($data['data'])) {
            return "Нет данных для импорта";
        }
        
        // Подготавливаем запрос для вставки
        $firstRow = $data['data'][0];
        $columnNames = array_keys($firstRow);
        
        // Проверяем соответствие колонок
        $missingColumns = array_diff($columnNames, $columns);
        if (!empty($missingColumns)) {
            return "Несоответствие колонок: " . implode(', ', $missingColumns);
        }
        
        $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
        $columnList = implode(', ', $columnNames);
        $insertStmt = $pdo->prepare("INSERT INTO $tableName ($columnList) VALUES ($placeholders)");
        
        $importedRows = 0;
        $skippedRows = 0;
        $errors = [];
        
        // Начинаем транзакцию
        $pdo->beginTransaction();
        
        try {
            foreach ($data['data'] as $index => $row) {
                if (count($row) == count($columnNames)) {
                    try {
                        $values = [];
                        foreach ($columnNames as $column) {
                            $values[] = $row[$column] ?? null;
                        }
                        
                        $insertStmt->execute($values);
                        $importedRows++;
                    } catch (PDOException $e) {
                        $skippedRows++;
                        $errors[] = "Строка $index: " . $e->getMessage();
                        continue;
                    }
                } else {
                    $skippedRows++;
                    $errors[] = "Строка $index: несоответствие количества колонок";
                }
            }
            
            $pdo->commit();
            
            return [
                'success' => true,
                'imported' => $importedRows,
                'skipped' => $skippedRows,
                'total' => count($data['data']),
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        return "Ошибка базы данных: " . $e->getMessage();
    } catch (Exception $e) {
        return "Ошибка импорта: " . $e->getMessage();
    }
}

// Функция для импорта данных из CSV (оставлена для обратной совместимости)
function importTableFromCSV($pdo, $tableName, $csvFile) {
    try {
        // Проверяем существование таблицы
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($stmt->rowCount() == 0) {
            return "Таблица $tableName не существует";
        }
        
        // Получаем структуру таблицы
        $stmt = $pdo->query("DESCRIBE $tableName");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Открываем CSV файл
        $file = fopen($csvFile, 'r');
        if (!$file) {
            return "Не удалось открыть CSV файл";
        }
        
        // Читаем заголовки
        $headers = fgetcsv($file);
        if (!$headers) {
            fclose($file);
            return "CSV файл пуст или имеет неверный формат";
        }
        
        // Проверяем соответствие колонок
        $missingColumns = array_diff($headers, $columns);
        if (!empty($missingColumns)) {
            fclose($file);
            return "Несоответствие колонок: " . implode(', ', $missingColumns);
        }
        
        // Подготавливаем запрос для вставки
        $placeholders = implode(', ', array_fill(0, count($headers), '?'));
        $columnNames = implode(', ', $headers);
        $insertStmt = $pdo->prepare("INSERT INTO $tableName ($columnNames) VALUES ($placeholders)");
        
        $importedRows = 0;
        $skippedRows = 0;
        
        // Начинаем транзакцию
        $pdo->beginTransaction();
        
        try {
            // Читаем и вставляем данные
            while (($row = fgetcsv($file)) !== FALSE) {
                if (count($row) == count($headers)) {
                    try {
                        $insertStmt->execute($row);
                        $importedRows++;
                    } catch (PDOException $e) {
                        $skippedRows++;
                        continue;
                    }
                } else {
                    $skippedRows++;
                }
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        fclose($file);
        return [
            'success' => true,
            'imported' => $importedRows,
            'skipped' => $skippedRows
        ];
        
    } catch (PDOException $e) {
        return "Ошибка базы данных: " . $e->getMessage();
    } catch (Exception $e) {
        return "Ошибка импорта: " . $e->getMessage();
    }
}

// Функция для создания бэкапа базы данных
function createDatabaseBackup($pdo) {
    try {
        $backupDir = __DIR__ . '/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $backupContent = "-- RuRomix Database Backup\n";
        $backupContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backupContent .= "-- PHP Version: " . PHP_VERSION . "\n";
        $backupContent .= "-- MySQL Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n\n";
        
        $backupContent .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $backupContent .= "--\n-- Table structure for table `$table`\n--\n\n";
            $backupContent .= "DROP TABLE IF EXISTS `$table`;\n";
            $backupContent .= $createTable['Create Table'] . ";\n\n";
            
            $data = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($data)) {
                $backupContent .= "--\n-- Dumping data for table `$table`\n--\n\n";
                
                $columns = array_keys($data[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                $backupContent .= "INSERT INTO `$table` ($columnList) VALUES\n";
                
                $values = [];
                foreach ($data as $row) {
                    $escapedValues = array_map(function($value) use ($pdo) {
                        if ($value === null) return 'NULL';
                        return $pdo->quote($value);
                    }, $row);
                    
                    $values[] = "(" . implode(', ', $escapedValues) . ")";
                }
                
                $backupContent .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $backupContent .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        if (file_put_contents($backupFile, $backupContent) !== false) {
            return [
                'success' => true,
                'file' => $backupFile,
                'size' => filesize($backupFile)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Не удалось записать файл'
            ];
        }
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Ошибка базы данных: ' . $e->getMessage()
        ];
    }
}

// Функция для восстановления из бэкапа
function restoreDatabaseBackup($pdo, $backupFile) {
    try {
        $backupContent = file_get_contents($backupFile);
        if (!$backupContent) {
            return [
                'success' => false,
                'error' => 'Не удалось прочитать файл бэкапа'
            ];
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        
        $queries = array_filter(array_map('trim', explode(';', $backupContent)));
        $executedQueries = 0;
        $errors = [];
        
        foreach ($queries as $query) {
            if (!empty($query)) {
                try {
                    $pdo->exec($query);
                    $executedQueries++;
                } catch (PDOException $e) {
                    $errors[] = "Ошибка в запросе: " . $e->getMessage();
                }
            }
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        
        return [
            'success' => empty($errors),
            'executed' => $executedQueries,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Ошибка восстановления: ' . $e->getMessage()
        ];
    }
}

// Функция для удаления бэкапа
function deleteBackupFile($filename) {
    $backupDir = __DIR__ . '/backups/';
    $filePath = $backupDir . $filename;
    
    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            return [
                'success' => true,
                'message' => 'Бэкап успешно удален'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Не удалось удалить файл'
            ];
        }
    } else {
        return [
            'success' => false,
            'error' => 'Файл не найден'
        ];
    }
}

// ========== ФУНКЦИИ ДЛЯ ИНТЕГРАЦИИ ==========

// Функция для получения ФИО из API
function getFIOFromAPI() {
    $url = "http://prb.sylas.ru/TransferSimulator/fullName";
    
    try {
        $response = file_get_contents($url);
        if ($response === FALSE) {
            throw new Exception("Не удалось подключиться к API");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
        }
        
        if (!isset($data['value'])) {
            throw new Exception("Некорректный формат ответа от API");
        }
        
        return $data['value'];
        
    } catch (Exception $e) {
        throw new Exception("Ошибка получения данных из API: " . $e->getMessage());
    }
}

// Функция проверки ФИО
function validateFIO($fio) {
    $errors = [];
    
    if (preg_match('/[!@#$%^&*()+\-=\[\]{}\'"\\\\|,.<>\/?;]/', $fio)) {
        $errors[] = "Найдены запрещенные спецсимволы";
    }
    
    if (preg_match('/[0-9]/', $fio)) {
        $errors[] = "Найдены цифры в ФИО";
    }
    
    if (!preg_match('/^[а-яА-ЯёЁ ]+$/u', $fio)) {
        $errors[] = "ФИО должно содержать только русские буквы";
    }
    
    $parts = explode(' ', $fio);
    if (count($parts) !== 3) {
        $errors[] = "Неверный формат ФИО. Ожидается: Фамилия Имя Отчество";
    }
    
    return [
        'is_valid' => empty($errors),
        'errors' => $errors
    ];
}

// ========== ФУНКЦИИ ДЛЯ РЕДАКТИРОВАНИЯ ТАБЛИЦ ==========

// Функция для получения структуры таблицы
function getTableStructure($pdo, $tableName) {
    try {
        $stmt = $pdo->query("DESCRIBE $tableName");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Функция для обновления записи в таблице
function updateTableRecord($pdo, $tableName, $id, $data) {
    try {
        // Получаем структуру таблицы
        $structure = getTableStructure($pdo, $tableName);
        
        // Формируем SET часть запроса
        $setParts = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            // Проверяем существование поля в таблице
            $fieldExists = false;
            foreach ($structure as $column) {
                if ($column['Field'] == $field) {
                    $fieldExists = true;
                    break;
                }
            }
            
            if ($fieldExists) {
                $setParts[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($setParts)) {
            return ['success' => false, 'error' => 'Нет данных для обновления'];
        }
        
        // Добавляем ID в параметры
        $params[] = $id;
        
        // Формируем и выполняем запрос
        $sql = "UPDATE $tableName SET " . implode(', ', $setParts) . " WHERE ID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return ['success' => true, 'affected' => $stmt->rowCount()];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Обработка запроса на получение ФИО из API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_fio'])) {
    try {
        $fio = getFIOFromAPI();
        $validation = validateFIO($fio);

        $_SESSION['integration_result'] = [
            'fio' => $fio,
            'is_valid' => $validation['is_valid'],
            'errors' => $validation['errors'],
            'timestamp' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        $_SESSION['integration_error'] = $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Обработка экспорта данных
if (isset($_GET['export'])) {
    $table = $_GET['export'];
    $allowed_tables = ['Comics', 'Users', 'Chapters', 'Genres', 'Subscriptions'];
    
    if (in_array($table, $allowed_tables)) {
        $filename = exportTableToJSON($pdo, $table);
        
        if ($filename) {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $table . '_export_' . date('Y-m-d') . '.json"');
            header('Pragma: no-cache');
            readfile($filename);
            unlink($filename);
            exit;
        } else {
            $_SESSION['error'] = "Ошибка при экспорте таблицы $table";
            header("Location: " . $_SERVER['PHP_SELF'] . '#data');
            exit;
        }
    }
}

// Обработка импорта данных
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $table = $_POST['import_table'] ?? '';
    $file = $_FILES['import_file'] ?? null;
    
    if ($table && $file && $file['error'] === UPLOAD_ERR_OK) {
        // Проверяем расширение файла
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($fileExt === 'json') {
            $result = importTableFromJSON($pdo, $table, $file['tmp_name']);
        } elseif ($fileExt === 'csv') {
            $result = importTableFromCSV($pdo, $table, $file['tmp_name']);
        } else {
            $_SESSION['error'] = "Неподдерживаемый формат файла. Используйте JSON или CSV";
            header("Location: " . $_SERVER['PHP_SELF'] . '#data');
            exit();
        }
        
        if (is_array($result) && $result['success']) {
            $message = "Успешно импортировано {$result['imported']} записей из {$result['total']} в таблицу $table.";
            if ($result['skipped'] > 0) {
                $message .= " Пропущено: {$result['skipped']}";
            }
            if (!empty($result['errors'])) {
                $message .= " Ошибки: " . implode('; ', array_slice($result['errors'], 0, 5));
                if (count($result['errors']) > 5) {
                    $message .= " (и еще " . (count($result['errors']) - 5) . " ошибок)";
                }
            }
            $_SESSION['message'] = $message;
        } else {
            $_SESSION['error'] = $result;
        }
    } else {
        $_SESSION['error'] = "Ошибка загрузки файла или не выбрана таблица";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . '#data');
    exit();
}

// Обработка создания бэкапа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    $result = createDatabaseBackup($pdo);
    
    if ($result['success']) {
        $_SESSION['message'] = "Бэкап успешно создан: " . basename($result['file']) . " (" . round($result['size'] / 1024, 2) . " KB)";
    } else {
        $_SESSION['error'] = $result['error'];
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . '#backup');
    exit();
}

// Обработка восстановления из бэкапа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $file = $_FILES['backup_file'] ?? null;
    
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $result = restoreDatabaseBackup($pdo, $file['tmp_name']);
        
        if ($result['success']) {
            $_SESSION['message'] = "База данных успешно восстановлена из бэкапа. Выполнено запросов: {$result['executed']}";
        } else {
            if (!empty($result['errors'])) {
                $_SESSION['error'] = "Ошибки при восстановлении: " . implode('; ', $result['errors']);
            } else {
                $_SESSION['error'] = $result['error'];
            }
        }
    } else {
        $_SESSION['error'] = "Ошибка загрузки файла бэкапа";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . '#backup');
    exit();
}

// Обработка скачивания бэкапа
if (isset($_GET['download_backup'])) {
    $filename = $_GET['download_backup'];
    $backupDir = __DIR__ . '/backups/';
    $filePath = $backupDir . $filename;
    
    if (file_exists($filePath)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        $_SESSION['error'] = "Файл бэкапа не найден";
        header("Location: " . $_SERVER['PHP_SELF'] . '#backup');
        exit;
    }
}

// Обработка удаления бэкапа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $filename = $_POST['backup_filename'] ?? '';
    
    if ($filename) {
        $result = deleteBackupFile($filename);
        
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['error'];
        }
    } else {
        $_SESSION['error'] = "Не указано имя файла для удаления";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . '#backup');
    exit();
}

// Обработка действий с таблицами
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';
    
    try {
        switch ($action) {
            case 'delete':
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM $table WHERE ID = ?");
                $stmt->execute([$id]);
                $_SESSION['message'] = "Запись успешно удалена";
                break;
                
            case 'update':
                $id = $_POST['id'];
                
                // Удаляем служебные поля из POST
                unset($_POST['action'], $_POST['table'], $_POST['id']);
                
                // Обновляем запись
                $result = updateTableRecord($pdo, $table, $id, $_POST);
                
                if ($result['success']) {
                    $_SESSION['message'] = "Запись успешно обновлена";
                } else {
                    $_SESSION['error'] = "Ошибка обновления: " . $result['error'];
                }
                break;
                
            case 'edit':
                // Сохраняем данные для редактирования в сессии
                $_SESSION['edit_data'] = [
                    'table' => $table,
                    'id' => $_POST['id']
                ];
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Ошибка: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Функция для получения данных из таблицы
function getTableData($pdo, $tableName) {
    $stmt = $pdo->query("SELECT * FROM $tableName");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получаем данные для отчетов
try {
    $comics_count = $pdo->query("SELECT COUNT(*) as count FROM Comics")->fetch()['count'];
    $users_count = $pdo->query("SELECT COUNT(*) as count FROM Users")->fetch()['count'];
    $chapters_count = $pdo->query("SELECT COUNT(*) as count FROM Chapters")->fetch()['count'];
} catch (PDOException $e) {
    $comics_count = $users_count = $chapters_count = 0;
}

// Получаем список бэкапов
$backupDir = __DIR__ . '/backups/';
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filePath = $backupDir . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath),
                'path' => $filePath
            ];
        }
    }
    usort($backups, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// Получаем данные для редактирования, если они есть
$editData = null;
if (isset($_SESSION['edit_data'])) {
    $table = $_SESSION['edit_data']['table'];
    $id = $_SESSION['edit_data']['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE ID = ?");
        $stmt->execute([$id]);
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
        $editData['_table'] = $table;
        $editData['_id'] = $id;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Ошибка получения данных для редактирования: " . $e->getMessage();
    }
    
    // Очищаем данные редактирования после использования
    unset($_SESSION['edit_data']);
}

$join_date = date('d.m.Y', strtotime($user_data['Created_at']));
$join_date_full = date('d F Y', strtotime($user_data['Created_at']));

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
    <title>Панель администратора - RuRomix</title>
    <link rel="stylesheet" href="style_main.css">
    <link rel="stylesheet" href="style_kabinets.css">
    <link rel="stylesheet" href="style_admin.css">
    <style>
        .edit-form-container {
            background: #f5f5f5;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .edit-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .hidden {
            display: none;
        }
        
        .btn-save {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .btn-save:hover {
            background: #218838;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>

    <?php include 'Admin_header.php'; ?>

    <div class="admin-panel">
        <ul class="admin-tabs">
            <li><a href="#" class="tab-link active" data-tab="chapters">Главы</a></li>
            <li><a href="#" class="tab-link" data-tab="comics">Комиксы</a></li>
            <li><a href="#" class="tab-link" data-tab="comics_ratings">Рейтинги</a></li>
            <li><a href="#" class="tab-link" data-tab="comment">Комментарии</a></li>
            <li><a href="#" class="tab-link" data-tab="genres">Жанры</a></li>
            <li><a href="#" class="tab-link" data-tab="notifications">Уведомления</a></li>
            <li><a href="#" class="tab-link" data-tab="subscriptions">Подписки</a></li>
            <li><a href="#" class="tab-link" data-tab="users">Пользователи</a></li>
            <li><a href="#" class="tab-link" data-tab="users_favorite">Избранное</a></li>
            <li><a href="#" class="tab-link" data-tab="data">Отчёты</a></li>
            <li><a href="#" class="tab-link" data-tab="backup">Бэкап</a></li>
            <li><a href="#" class="tab-link" data-tab="integration">Интеграция</a></li>
        </ul>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="success">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- Форма редактирования (скрыта по умолчанию) -->
        <?php if ($editData): ?>
        <div id="edit-form-container" class="edit-form-container">
            <h3>Редактирование записи</h3>
            <form method="POST" class="edit-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="table" value="<?= htmlspecialchars($editData['_table']) ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($editData['_id']) ?>">
                
                <?php foreach ($editData as $field => $value): ?>
                    <?php if ($field !== '_table' && $field !== '_id' && $field !== 'ID' && $field !== 'Created_at' && $field !== 'Updated_at'): ?>
                    <div class="form-group">
                        <label for="edit_<?= $field ?>"><?= htmlspecialchars($field) ?>:</label>
                        <?php if (strlen($value) > 100): ?>
                            <textarea id="edit_<?= $field ?>" name="<?= $field ?>" rows="4"><?= htmlspecialchars($value) ?></textarea>
                        <?php elseif (in_array($field, ['Status', 'Role', 'is_read', 'type'])): ?>
                            <select id="edit_<?= $field ?>" name="<?= $field ?>">
                                <?php if ($field === 'Status'): ?>
                                    <option value="0" <?= $value == 0 ? 'selected' : '' ?>>Активен</option>
                                    <option value="1" <?= $value == 1 ? 'selected' : '' ?>>Заблокирован</option>
                                    <option value="2" <?= $value == 2 ? 'selected' : '' ?>>Ожидает подтверждения</option>
                                <?php elseif ($field === 'Role'): ?>
                                    <option value="reader" <?= $value == 'reader' ? 'selected' : '' ?>>Читатель</option>
                                    <option value="author" <?= $value == 'author' ? 'selected' : '' ?>>Автор</option>
                                    <option value="moderator" <?= $value == 'moderator' ? 'selected' : '' ?>>Модератор</option>
                                    <option value="admin" <?= $value == 'admin' ? 'selected' : '' ?>>Администратор</option>
                                <?php elseif ($field === 'is_read'): ?>
                                    <option value="0" <?= $value == 0 ? 'selected' : '' ?>>Нет</option>
                                    <option value="1" <?= $value == 1 ? 'selected' : '' ?>>Да</option>
                                <?php else: ?>
                                    <option value="">Выберите значение</option>
                                    <?php 
                                    $stmt = $pdo->query("SELECT DISTINCT $field FROM " . $editData['_table']);
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                                    ?>
                                        <option value="<?= htmlspecialchars($row[$field]) ?>" <?= $value == $row[$field] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($row[$field]) ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" id="edit_<?= $field ?>" name="<?= $field ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save">Сохранить</button>
                    <button type="button" class="btn-cancel" onclick="document.getElementById('edit-form-container').style.display='none'">Отмена</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="tab-content active" id="chapters-tab">
            <h2>Управление главами</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Comics ID</th>
                        <th>Название</th>
                        <th>Порядок</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Chapters') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= $row['Comics_id'] ?></td>
                        <td><?= htmlspecialchars($row['Title']) ?></td>
                        <td><?= $row['Order_number'] ?></td>
                        <td><?= $row['Created_at'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Chapters">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Chapters">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="comics-tab">
            <h2>Управление комиксами</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Автор ID</th>
                        <th>Статус</th>
                        <th>Жанр ID</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Comics') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= htmlspecialchars($row['Title']) ?></td>
                        <td><?= htmlspecialchars(substr($row['Description'], 0, 50)) ?>...</td>
                        <td><?= $row['Author_id'] ?></td>
                        <td><?= $row['Status'] ?></td>
                        <td><?= $row['Genres_id'] ?></td>
                        <td><?= $row['Created_at'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Comics">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Comics">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="comics_ratings-tab">
            <h2>Рейтинги комиксов</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Comics ID</th>
                        <th>Рейтинг</th>
                        <th>Дата оценки</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Comics_ratings') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= $row['User_id'] ?></td>
                        <td><?= $row['Comics_id'] ?></td>
                        <td><?= $row['Rating_value'] ?></td>
                        <td><?= $row['Rated_at'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Comics_ratings">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Comics_ratings">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="comment-tab">
            <h2>Комментарии</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Comics ID</th>
                        <th>Содержание</th>
                        <th>Дата создания</th>
                        <th>Дата обновления</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Comment') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= $row['User_id'] ?></td>
                        <td><?= $row['Comics_id'] ?></td>
                        <td><?= htmlspecialchars(substr($row['Content'], 0, 50)) ?>...</td>
                        <td><?= $row['Created_at'] ?></td>
                        <td><?= $row['Updated_at'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Comment">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Comment">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="genres-tab">
            <h2>Жанры</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Genres') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= htmlspecialchars($row['Name']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Genres">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Genres">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="notifications-tab">
            <h2>Уведомления</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Тип</th>
                        <th>Source ID</th>
                        <th>Сообщение</th>
                        <th>Прочитано</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Notifications') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= $row['user_id'] ?></td>
                        <td><?= $row['type'] ?></td>
                        <td><?= $row['source_id'] ?></td>
                        <td><?= htmlspecialchars(substr($row['message'], 0, 50)) ?>...</td>
                        <td><?= $row['is_read'] ? 'Да' : 'Нет' ?></td>
                        <td><?= $row['created_at'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Notifications">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Notifications">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="subscriptions-tab">
            <h2>Подписки</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subscriber ID</th>
                        <th>Target User ID</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Subscriptions') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= $row['subscriber_id'] ?></td>
                        <td><?= $row['target_user_id'] ?></td>
                        <td><?= $row['created_at'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Subscriptions">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Subscriptions">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="users-tab">
            <h2>Пользователи</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Имя пользователя</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Статус</th>
                        <th>Дата регистрации</th>
                        <th>Последний вход</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Users') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= htmlspecialchars($row['Username']) ?></td>
                        <td><?= htmlspecialchars($row['Email']) ?></td>
                        <td><?= $row['Role'] ?></td>
                        <td><?= $row['Status'] ?></td>
                        <td><?= $row['Created_at'] ?></td>
                        <td><?= $row['Last_login'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Users">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Users">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить пользователя?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="users_favorite-tab">
            <h2>Избранное пользователей</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Comics ID</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (getTableData($pdo, 'Users_favorite') as $row): ?>
                    <tr>
                        <td><?= $row['ID'] ?></td>
                        <td><?= $row['User_id'] ?></td>
                        <td><?= $row['Comics_id'] ?></td>
                        <td><?= $row['Created_at'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="table" value="Users_favorite">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-edit">Редактировать</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="table" value="Users_favorite">
                                <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content" id="data-tab">
            <h2>Отчёты и данные</h2>
            
            <div class="report-cards">
                <div class="report-card">
                    <h3>Количество комиксов</h3>
                    <div class="number"><?= $comics_count ?></div>
                </div>
                <div class="report-card">
                    <h3>Количество пользователей</h3>
                    <div class="number"><?= $users_count ?></div>
                </div>
                <div class="report-card">
                    <h3>Количество глав</h3>
                    <div class="number"><?= $chapters_count ?></div>
                </div>
            </div>

            <h3>Экспорт данных (JSON)</h3>
            <div class="backup-actions">
                <a href="?export=Comics" class="export-link">Экспорт комиксов (JSON)</a>
                <a href="?export=Users" class="export-link">Экспорт пользователей (JSON)</a>
                <a href="?export=Chapters" class="export-link">Экспорт глав (JSON)</a>
                <a href="?export=Genres" class="export-link">Экспорт жанров (JSON)</a>
                <a href="?export=Subscriptions" class="export-link">Экспорт подписок (JSON)</a>
            </div>

            <h3>Импорт данных (поддерживаются JSON и CSV)</h3>
            <div class="import-form">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="import_table">Выберите таблицу для импорта:</label>
                        <select name="import_table" id="import_table" required>
                            <option value="">-- Выберите таблицу --</option>
                            <option value="Comics">Comics (Комиксы)</option>
                            <option value="Users">Users (Пользователи)</option>
                            <option value="Chapters">Chapters (Главы)</option>
                            <option value="Genres">Genres (Жанры)</option>
                            <option value="Subscriptions">Subscriptions (Подписки)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="import_file">Файл для импорта (JSON или CSV):</label>
                        <input type="file" name="import_file" id="import_file" accept=".json,.csv" required>
                        <small>Файл должен быть в формате JSON или CSV</small>
                    </div>
                    
                    <button type="submit" class="backup-btn" name="import">Импорт данных</button>
                </form>
            </div>
            
            <div class="integration-info">
                <h4>Информация о форматах:</h4>
                <ul>
                    <li><strong>JSON формат:</strong> Содержит полную структуру таблицы и данные в читаемом формате</li>
                    <li><strong>CSV формат:</strong> Простой табличный формат, совместимый с Excel</li>
                    <li>Рекомендуется использовать JSON для полной совместимости со структурой базы данных</li>
                </ul>
            </div>
        </div>

        <div class="tab-content" id="backup-tab">
            <h2>Резервное копирование базы данных</h2>
            
            <div class="backup-stats">
                <div class="stat-box">
                    <h4>Всего бэкапов</h4>
                    <div class="number"><?= count($backups) ?></div>
                </div>
                <div class="stat-box">
                    <h4>Последний бэкап</h4>
                    <div class="number">
                        <?php if (!empty($backups)): ?>
                            <?= date('d.m.Y H:i', $backups[0]['modified']) ?>
                        <?php else: ?>
                            Нет
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-box">
                    <h4>Общий размер</h4>
                    <div class="number">
                        <?php
                        $totalSize = 0;
                        foreach ($backups as $backup) {
                            $totalSize += $backup['size'];
                        }
                        echo round($totalSize / 1024, 2) . ' KB';
                        ?>
                    </div>
                </div>
            </div>

            <div class="backup-actions">
                <form method="POST">
                    <button type="submit" class="backup-btn" name="create_backup">Создать новый бэкап</button>
                </form>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="backup_file" accept=".sql" required>
                    <button type="submit" class="backup-btn" name="restore_backup">Восстановить из бэкапа</button>
                </form>
            </div>

            <h3>История бэкапов</h3>
            <div class="backup-list">
                <?php if (empty($backups)): ?>
                    <p>Бэкапы не найдены. Создайте первый бэкап.</p>
                <?php else: ?>
                    <?php foreach ($backups as $backup): ?>
                        <div class="backup-item">
                            <div class="backup-info">
                                <strong><?= htmlspecialchars($backup['name']) ?></strong><br>
                                <small>Размер: <?= round($backup['size'] / 1024, 2) ?> KB</small><br>
                                <small>Создан: <?= date('d.m.Y H:i:s', $backup['modified']) ?></small>
                            </div>
                            <div class="backup-actions-small">
                                <a href="?download_backup=<?= urlencode($backup['name']) ?>" class="btn btn-edit">Скачать</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="backup_filename" value="<?= htmlspecialchars($backup['name']) ?>">
                                    <button type="submit" class="btn btn-delete" name="delete_backup" onclick="return confirm('Удалить бэкап <?= htmlspecialchars($backup['name']) ?>?')">Удалить</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="integration-testing">
                <h4>Информация о бэкапах</h4>
                <ul>
                    <li>Бэкапы сохраняются в папку <code>/backups/</code></li>
                    <li>Каждый бэкап содержит полную копию базы данных</li>
                    <li>Формат файлов: SQL</li>
                    <li>Рекомендуется регулярно создавать бэкапы перед важными изменениями</li>
                </ul>
            </div>
        </div>

        <div class="tab-content" id="integration-tab">
            <h2>Интеграция с ФИО-сервисом</h2>
            
            <?php if (isset($_SESSION['integration_error'])): ?>
                <div class="error">
                    <h4>Ошибка интеграции:</h4>
                    <p><?= htmlspecialchars($_SESSION['integration_error']) ?></p>
                </div>
                <?php unset($_SESSION['integration_error']); ?>
            <?php endif; ?>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Получение данных ФИО из внешнего API</h3>
                </div>
                
                <div class="integration-info">
                    <p><strong>Описание интеграции:</strong></p>
                    <ul>
                        <li>Система подключается к внешнему API для получения случайных ФИО</li>
                        <li>Полученные данные проверяются на соответствие критериям</li>
                        <li>Результат проверки отображается ниже</li>
                    </ul>
                    
                    <p><strong>Критерии проверки:</strong></p>
                    <ol>
                        <li>Отсутствие специальных символов</li>
                        <li>Отсутствие цифр</li>
                        <li>ФИО написано на русском языке</li>
                    </ol>

                    <p><strong>Ожидаемый формат:</strong> <code>Фамилия Имя Отчество</code></p>
                    
                    <p><strong>API endpoint:</strong> <code>http://prb.sylas.ru/TransferSimulator/fullName</code></p>
                </div>

                <form method="post" class="integration-form">
                    <button type="submit" name="get_fio" class="add-btn">Получить ФИО из API</button>
                </form>

                <?php if (isset($_SESSION['integration_result'])): 
                    $result = $_SESSION['integration_result'];
                ?>
                <div class="integration-result" style="margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 5px;">
                    <h4>Результат проверки:</h4>
                    
                    <div class="result-item">
                        <strong>Полученные данные:</strong> 
                        <span style="font-family: monospace;"><?= htmlspecialchars($result['fio']) ?></span>
                    </div>
                    
                    <div class="result-item">
                        <strong>Статус проверки:</strong> 
                        <?php if ($result['is_valid']): ?>
                            <span style="color: green; font-weight: bold;">✅ Валидно</span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">❌ Не валидно</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="result-item">
                        <strong>Время проверки:</strong> <?= htmlspecialchars($result['timestamp']) ?>
                    </div>

                    <?php if (!$result['is_valid'] && !empty($result['errors'])): ?>
                    <div class="validation-errors" style="margin-top: 15px; padding: 10px; background-color: #ffe6e6; border-radius: 3px;">
                        <strong>Ошибки валидации:</strong>
                        <ul style="margin: 5px 0 0 20px;">
                            <?php foreach ($result['errors'] as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php 
                    unset($_SESSION['integration_result']);
                endif; 
                ?>

                <div class="integration-testing" style="margin-top: 30px;">
                    <h4>Тестирование API</h4>
                    <p>Для ручного тестирования API вы можете:</p>
                    <ol>
                        <li>Открыть в браузере: <a href="http://prb.sylas.ru/TransferSimulator/fullName" target="_blank">http://prb.sylas.ru/TransferSimulator/fullName</a></li>
                        <li>Использовать Postman с GET-запросом к указанному URL</li>
                        <li>Ожидаемый ответ: <code>{"value":"Фамилия Имя Отчество"}</code></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

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
                    <div class="stat-value">0</div>
                    <div class="stat-label">Избранное</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Подписки</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
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
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    tabLinks.forEach(l => l.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab') + '-tab';
                    document.getElementById(tabId).classList.add('active');
                    
                    // Скрываем форму редактирования при переключении вкладок
                    const editForm = document.getElementById('edit-form-container');
                    if (editForm) {
                        editForm.style.display = 'none';
                    }
                });
            });

            const userMenu = document.getElementById('userMenu');
            const dropdownMenu = document.getElementById('dropdownMenu');

            if (userMenu && dropdownMenu) {
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
            }

            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function() {
                    if(confirm('Вы уверены, что хотите выйти?')) {
                        window.location.href = 'logout.php';
                    }
                });
            }

            // Прокрутка к форме редактирования, если она есть
            <?php if ($editData): ?>
                const editForm = document.getElementById('edit-form-container');
                if (editForm) {
                    editForm.scrollIntoView({ behavior: 'smooth' });
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>