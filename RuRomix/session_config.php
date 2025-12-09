<?php
// Настройки сессии
ini_set('session.cookie_lifetime', 0); // Сессия до закрытия браузера
ini_set('session.cookie_secure', 0); // Для разработки - в продакшене установить 1 если HTTPS
ini_set('session.cookie_httponly', 1); // Защита от XSS
ini_set('session.use_strict_mode', 1); // Использовать строгий режим сессий

// Запуск сессии с настройками
session_start();

// Регенерация ID сессии для безопасности
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Регенерируем ID каждые 30 минут
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?>