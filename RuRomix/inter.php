<php?



// ========== ФУНКЦИИ ДЛЯ ИНТЕГРАЦИИ ==========

// Функция для получения ФИО из API
function getFIOFromAPI() {
    $url = "http://prb.sylas.ru/TransferSimulator/fullName";
    
    try {
        // Делаем запрос к API
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
        
        return $data['value']; // Возвращает "Фамилия Имя Отчество"
        
    } catch (Exception $e) {
        throw new Exception("Ошибка получения данных из API: " . $e->getMessage());
    }
}

// Функция проверки ФИО
function validateFIO($fio) {
    $errors = [];
    
    // 1. Проверка на спецсимволы (кроме пробелов)
    if (preg_match('/[!@#$%^&*()+\-=\[\]{}\'"\\\\|,.<>\/?;]/', $fio)) {
        $errors[] = "Найдены запрещенные спецсимволы";
    }
    
    // 2. Проверка на отсутствие цифр
    if (preg_match('/[0-9]/', $fio)) {
        $errors[] = "Найдены цифры в ФИО";
    }
    
    // 3. Проверка на русский язык
    if (!preg_match('/^[а-яА-ЯёЁ ]+$/u', $fio)) {
        $errors[] = "ФИО должно содержать только русские буквы";
    }
    
    // 4. Проверка формата (должно быть 3 компонента через пробелы)
    $parts = explode(' ', $fio);
    if (count($parts) !== 3) {
        $errors[] = "Неверный формат ФИО. Ожидается: Фамилия Имя Отчество";
    }
    
    return [
        'is_valid' => empty($errors),
        'errors' => $errors
    ];
}


<!--вкладка Интеграция -->
    <section class="tab-content" id="integration-tab">
        <h2>Интеграция с ФИО-сервисом</h2>
        
        <!-- Сообщения об ошибках/успехе -->
        <?php if (isset($_SESSION['integration_error'])): ?>
            <div class="error">
                <h4>Ошибка интеграции:</h4>
                <p><?= $_SESSION['integration_error'] ?></p>
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
                    <strong>Время проверки:</strong> <?= $result['timestamp'] ?>
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
    </section>
</div>



?>