-- RuRomix Database Backup
-- Generated: 2025-12-01 09:20:44
-- PHP Version: 8.2.12
-- MySQL Version: 10.6.22-MariaDB-0ubuntu0.22.04.1

SET FOREIGN_KEY_CHECKS=0;

--
-- Table structure for table `Chapters`
--

DROP TABLE IF EXISTS `Chapters`;
CREATE TABLE `Chapters` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Comics_id` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Order_number` int(11) NOT NULL,
  `Created_at` date DEFAULT curdate(),
  PRIMARY KEY (`ID`),
  KEY `Comics_id` (`Comics_id`),
  CONSTRAINT `Chapters_ibfk_1` FOREIGN KEY (`Comics_id`) REFERENCES `Comics` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `Chapters`
--

INSERT INTO `Chapters` (`ID`, `Comics_id`, `Title`, `Order_number`, `Created_at`) VALUES
('1', '1', 'Начало конца', '2', '2025-10-31'),
('2', '1', 'И наступит мгла', '2', '2025-10-31'),
('3', '3', 'Какое тёмное утро', '1', '2025-10-31'),
('4', '2', 'Да кто же ты такой', '1', '2025-10-31'),
('5', '4', 'Потерянный с детства', '1', '2025-10-31'),
('6', '6', 'последний день средь маглов', '1', '2025-10-31'),
('7', '5', 'Звезда полярная', '1', '2025-10-31'),
('8', '5', 'Потерянный путь', '2', '2025-10-31'),
('9', '5', 'Утраченный трон', '3', '2025-10-31'),
('10', '5', 'Конец начала', '4', '2025-10-31');

--
-- Table structure for table `Comics`
--

DROP TABLE IF EXISTS `Comics`;
CREATE TABLE `Comics` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(255) NOT NULL,
  `Description` text NOT NULL,
  `Author_id` int(11) NOT NULL,
  `Status` varchar(20) NOT NULL,
  `Created_at` date DEFAULT curdate(),
  `Genres_id` int(11) NOT NULL,
  `Cover_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Author_id` (`Author_id`),
  KEY `Genres_id` (`Genres_id`),
  CONSTRAINT `Comics_ibfk_1` FOREIGN KEY (`Genres_id`) REFERENCES `Genres` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `Comics`
--

INSERT INTO `Comics` (`ID`, `Title`, `Description`, `Author_id`, `Status`, `Created_at`, `Genres_id`, `Cover_path`) VALUES
('1', 'Шепот в темноте', 'Группа исследователей \"Пароактивность\" сталкивается с древним злом в заброшенном особняке', '2', '1', '2025-10-29', '1', 'covers/terObloja.jpg'),
('2', 'Ядовитая страсть', 'Токсичные отношения между успешным бизнесменом и его ассистенткой, полные манипуляций и страсти', '4', '2', '2025-10-29', '3', NULL),
('3', 'Под звездным небом', 'Двое незнакомцев встречаются во время метеоритного дождя и влюбляются с первого взгляда', '4', '2', '2025-10-29', '5', NULL),
('4', 'Разбитые мечты', 'История музыканта, теряющего слух, и его борьбы за место в мире, который больше не слышит его', '2', '1', '2025-10-29', '2', NULL),
('5', 'Звездные хроники: Наследие', 'Фан-комикс по вселенной популярного сериала, рассказывающий о новых персонажах', '4', '3', '2025-10-29', '4', NULL),
('6', 'Врата магии', 'Молодой ученик волшебника обнаруживает портал в параллельный мир, полный мифических существ', '8', '1', '2025-10-29', '6', NULL),
('13', 'плюш', 'dffdfdf', '2', '1', '2025-11-09', '3', 'covers/691041b980857.jpg'),
('14', 'плюш', 'dffdfdf', '2', '1', '2025-11-09', '3', 'covers/691046983c9c0.jpg'),
('15', 'проба', '8888', '14', '1', '2025-11-14', '5', 'covers/cover_6916e8b2bcce5.jpg'),
('16', 'uyuyujh', 'fghfghgfh', '15', '2', '2025-11-21', '9', 'covers/cover_6920280f56d47.jpg'),
('17', 'длщл', 'ш9ген65еае', '15', '1', '2025-11-24', '7', 'covers/cover_6923d62e7af09.png');

--
-- Table structure for table `Comics_ratings`
--

DROP TABLE IF EXISTS `Comics_ratings`;
CREATE TABLE `Comics_ratings` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_id` int(11) NOT NULL,
  `Comics_id` int(11) NOT NULL,
  `Rating_value` int(11) NOT NULL CHECK (`Rating_value` in (-1,1)),
  `Rated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `User_id` (`User_id`,`Comics_id`),
  KEY `Comics_id` (`Comics_id`),
  CONSTRAINT `Comics_ratings_ibfk_1` FOREIGN KEY (`Comics_id`) REFERENCES `Comics` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `Comment`
--

DROP TABLE IF EXISTS `Comment`;
CREATE TABLE `Comment` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_id` int(11) NOT NULL,
  `Comics_id` int(11) NOT NULL,
  `Content` text NOT NULL,
  `Created_at` date NOT NULL,
  `Updated_at` date NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `User_id` (`User_id`,`Comics_id`),
  KEY `Comics_id` (`Comics_id`),
  CONSTRAINT `Comment_ibfk_1` FOREIGN KEY (`Comics_id`) REFERENCES `Comics` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `Genres`
--

DROP TABLE IF EXISTS `Genres`;
CREATE TABLE `Genres` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `Genres`
--

INSERT INTO `Genres` (`ID`, `Name`) VALUES
('1', 'Хоррор'),
('2', 'Драма'),
('3', 'Дарк-романс'),
('4', 'Фандом'),
('5', 'Романтика'),
('6', 'Фэнтази'),
('7', 'Комедия'),
('8', 'Ориджиннал'),
('9', 'Приключения'),
('10', 'Триллер');

--
-- Table structure for table `Notifications`
--

DROP TABLE IF EXISTS `Notifications`;
CREATE TABLE `Notifications` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('new_subscriber','new_chapter','new_comic') NOT NULL,
  `source_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `Notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `Notifications`
--

INSERT INTO `Notifications` (`ID`, `user_id`, `type`, `source_id`, `message`, `is_read`, `created_at`) VALUES
('2', '15', 'new_subscriber', '16', 'Пользователь rrrrrrr подписался на вас', '1', '2025-11-24 11:29:23');

--
-- Table structure for table `Subscriptions`
--

DROP TABLE IF EXISTS `Subscriptions`;
CREATE TABLE `Subscriptions` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `subscriber_id` int(11) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unique_subscription` (`subscriber_id`,`target_user_id`),
  KEY `target_user_id` (`target_user_id`),
  CONSTRAINT `Subscriptions_ibfk_1` FOREIGN KEY (`subscriber_id`) REFERENCES `Users` (`ID`),
  CONSTRAINT `Subscriptions_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `Users` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `Subscriptions`
--

INSERT INTO `Subscriptions` (`ID`, `subscriber_id`, `target_user_id`, `created_at`) VALUES
('1', '15', '14', '2025-11-22 11:03:04'),
('3', '15', '16', '2025-11-22 12:10:01'),
('4', '16', '15', '2025-11-24 11:29:23');

--
-- Table structure for table `Users`
--

DROP TABLE IF EXISTS `Users`;
CREATE TABLE `Users` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Username` varchar(50) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Role` varchar(20) NOT NULL,
  `Password_hash` varchar(255) NOT NULL,
  `Created_at` date NOT NULL,
  `Status` int(11) NOT NULL,
  `Last_login` date NOT NULL,
  `Avatar_path` varchar(255) DEFAULT 'umolch_avatar.jpeg',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `Users`
--

INSERT INTO `Users` (`ID`, `Username`, `Email`, `Role`, `Password_hash`, `Created_at`, `Status`, `Last_login`, `Avatar_path`) VALUES
('1', 'ivan_petrovich', 'Petrov@mail.ru', 'reader', '$2y$10$r8Z8Q7s6Y5V4t3e2w1qA0u9z8x7c6v5b4n3m2l1k0j9h8g7f6d5s4', '2024-01-15', '0', '2024-03-20', 'umolch_avatar.jpeg'),
('2', 'anna_sidorova', 'AnSidorova@gmail.com', 'author', '$2y$10$q1w2e3r4t5y6u7i8o9p0a1s2d3f4g5h6j7k8l9z0x1c2v3b4n5m6', '2024-01-20', '0', '2024-03-19', 'umolch_avatar.jpeg'),
('4', 'max_cont', 'maxcont@yandex.ru', 'author', '$2y$10$z1x2c3v4b5n6m7l8k9j0h1g2f3d4s5a6p7o8i9u0y7t6r5e4w3q2', '2024-02-05', '1', '2024-03-18', 'umolch_avatar.jpeg'),
('5', 'maria_read', 'mariaRead@mail.ru', 'reader', '$2y$10$p1o2i3u4y5t6r7e8w9q0a1s2d3f4g5h6j7k8l9m0n1b2v3c4x5z6', '2024-02-12', '0', '2024-03-20', 'umolch_avatar.jpeg'),
('6', 'Olega_Moder', 'moderOlega@ruromix.ru', 'moderator', '$2y$10$m1n2b3v4c5x6z7l8k9j0h1g2f3d4s5a6p7o8i9u0y7t6r5e4w3q2', '2024-01-25', '0', '2024-03-19', 'umolch_avatar.jpeg'),
('7', 'Ulya_fan', 'Ulay@gmail.com', 'reader', '$2y$10$k1j2h3g4f5d6s7a8p9o0i1u2y3t4r5e6w7q8a9s0d1f2g3h4j5k6', '2024-02-28', '2', '2024-03-17', 'umolch_avatar.jpeg'),
('8', 'Artema788', 'artem.comics@mail.ru', 'author', '$2y$10$l1k2j3h4g5f6d7s8a9p0o1i2u3y4t5r6e7w8q9a0s1d2f3g4h5j6', '2024-03-01', '2', '2024-03-10', 'umolch_avatar.jpeg'),
('9', 'sveta_Svet', 'sveta1985@yandex.ru', 'reader', '$2y$10$h1j2k3l4m5n6b7v8c9x0z1a2s3d4f5g6h7j8k9l0m1n2b3v4c5x6', '2024-03-05', '0', '2024-03-20', 'umolch_avatar.jpeg'),
('10', 'problem', 'Problems@mail.ru', 'reader', '$2y$10$q1a2z3w4s5x6e7d8c9r0f1v2g3b4h5n6m7j8u9i0k1o2l3p4i5u6', '2024-02-15', '2', '2024-02-25', 'umolch_avatar.jpeg'),
('12', 'Kolya517', 'Nikolya@gmail.com', 'reader', '$2y$10$yIJ3TZkbRIF6st9LN3YhyOOxn5CJay2orfSdVv9YJouu9giKJi8le', '2025-11-09', '0', '2025-11-09', 'uploads/avatars/avatar_691039540e977.jpeg'),
('13', 'Эванс', 'HiMyLine@gmail.com', 'reader', '$2y$10$YOlwTngHMwm24NJiou/hAuxwkknZmp6yfRZf0Ofn38ilhliTbreHy', '2025-11-09', '0', '2025-11-09', 'umolch_avatar.jpeg'),
('14', 'Rurosya', 'Rurosya@gmail.com', 'author', '$2y$10$17LYxPhuWdw8rvU3yb2u7.x9vGp4DgvBDfELjcgyd4O58ztc0og3W', '2025-11-14', '0', '2025-11-14', 'uploads/avatars/avatar_6916e3abab3b5.jpg'),
('15', 'RuRomix', 'RuRomix@gmail.com', 'admin', '$2y$10$fmn3YiemgZzZSR8FGpWoUeIiDTVADPbAaeXTGyGv9bkjp0yVYNhWW', '2025-11-15', '0', '2025-12-01', 'uploads/avatars/avatar_6918235199442.png'),
('16', 'rrrrrrr', 'rrrrr@mail.ru', 'author', '$2y$10$1BE5hDQfORuhI1W9uF4aIOk1GW05rA9BftqXou4SkEuA8pB9Jweva', '2025-11-22', '0', '2025-11-28', 'uploads/avatars/avatar_6926b033ad1b6.jpg');

--
-- Table structure for table `Users_favorite`
--

DROP TABLE IF EXISTS `Users_favorite`;
CREATE TABLE `Users_favorite` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_id` int(11) NOT NULL,
  `Comics_id` int(11) NOT NULL,
  `Created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unique_favorite` (`User_id`,`Comics_id`),
  KEY `Users_favorite_ibfk_2` (`Comics_id`),
  CONSTRAINT `Users_favorite_ibfk_1` FOREIGN KEY (`User_id`) REFERENCES `Users` (`ID`),
  CONSTRAINT `Users_favorite_ibfk_2` FOREIGN KEY (`Comics_id`) REFERENCES `Comics` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `Users_favorite`
--

INSERT INTO `Users_favorite` (`ID`, `User_id`, `Comics_id`, `Created_at`) VALUES
('7', '15', '13', '2025-11-19 11:32:44');

SET FOREIGN_KEY_CHECKS=1;
