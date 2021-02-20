-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Фев 20 2021 г., 10:55
-- Версия сервера: 10.4.13-MariaDB
-- Версия PHP: 7.4.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `vk_mini_app`
--

-- --------------------------------------------------------

--
-- Структура таблицы `accounts`
--

DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `id` int(15) UNSIGNED NOT NULL,
  `vk_user_id` int(15) NOT NULL COMMENT 'Ид пользователя ВК',
  `acc_id` int(15) NOT NULL COMMENT 'Номер ЛС учетной системы в числовом представлении'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- ССЫЛКИ ТАБЛИЦЫ `accounts`:
--   `vk_user_id`
--       `vk_users` -> `vk_user_id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `acc_id` int(15) UNSIGNED NOT NULL COMMENT 'Номер ЛС учетной системы в числовом представлении',
  `secret_code` int(15) UNSIGNED NOT NULL COMMENT 'Проверочный код на квитанции',
  `acc_id_repr` varchar(15) NOT NULL COMMENT 'Текстовое представление номера ЛС',
  `tenant_repr` varchar(80) NOT NULL COMMENT 'ФИО квартиросъемщика',
  `address_repr` varchar(80) NOT NULL COMMENT 'Представление адреса'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- ССЫЛКИ ТАБЛИЦЫ `clients`:
--

-- --------------------------------------------------------

--
-- Структура таблицы `indications`
--

DROP TABLE IF EXISTS `indications`;
CREATE TABLE `indications` (
  `id` int(15) UNSIGNED NOT NULL,
  `meter_id` int(10) UNSIGNED NOT NULL COMMENT 'Ид счетчика',
  `count` int(15) UNSIGNED DEFAULT NULL COMMENT 'Показания',
  `recieve_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата получения',
  `vk_user_id` int(15) NOT NULL COMMENT 'Ид пользователя ВК, передавшего показания'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- ССЫЛКИ ТАБЛИЦЫ `indications`:
--   `meter_id`
--       `meters` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `meters`
--

DROP TABLE IF EXISTS `meters`;
CREATE TABLE `meters` (
  `id` int(15) UNSIGNED NOT NULL,
  `index_num` int(15) UNSIGNED NOT NULL COMMENT 'Номер (код) в системе управления для идентификации',
  `acc_id` int(15) UNSIGNED NOT NULL COMMENT 'Номер ЛС в учетной системе (он же первичный ключ таблицы clients)',
  `title` varchar(50) NOT NULL COMMENT 'Наименование п/у',
  `current_count` int(10) UNSIGNED NOT NULL COMMENT 'Текущие показания',
  `updated` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата обновления текущих показаний'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- ССЫЛКИ ТАБЛИЦЫ `meters`:
--   `acc_id`
--       `clients` -> `acc_id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `registration_requests`
--

DROP TABLE IF EXISTS `registration_requests`;
CREATE TABLE `registration_requests` (
  `id` int(10) NOT NULL,
  `vk_user_id` int(15) NOT NULL COMMENT 'Ид пользователя ВК',
  `acc_id` varchar(15) NOT NULL COMMENT 'Введенный пользователем номер ЛС',
  `surname` varchar(25) NOT NULL COMMENT 'Введенная пользователем фамилия',
  `first_name` varchar(25) NOT NULL COMMENT 'Введенное пользователем имя',
  `patronymic` varchar(25) NOT NULL COMMENT 'Введенное пользователем отчество',
  `street` varchar(50) NOT NULL COMMENT 'Введенная пользователем улица',
  `n_dom` varchar(15) NOT NULL COMMENT 'Введенный пользователем номер дома',
  `n_kv` varchar(3) NOT NULL COMMENT 'Введенный пользователем номер квартиры',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата запроса',
  `update_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_approved` int(1) UNSIGNED DEFAULT NULL COMMENT 'Подтвержден?',
  `linked_acc_id` int(15) UNSIGNED DEFAULT NULL,
  `processed_by` int(15) DEFAULT NULL COMMENT 'ВК ид пользователя, обработавшего запрос',
  `rejection_reason` varchar(50) DEFAULT NULL COMMENT 'Причина отказа в привязке лицевого счета',
  `hide_in_app` int(1) NOT NULL DEFAULT 0 COMMENT 'Не показывать в приложении',
  `del_in_app` int(1) NOT NULL DEFAULT 0 COMMENT 'Удалена через приложение'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- ССЫЛКИ ТАБЛИЦЫ `registration_requests`:
--

-- --------------------------------------------------------

--
-- Структура таблицы `vk_users`
--

DROP TABLE IF EXISTS `vk_users`;
CREATE TABLE `vk_users` (
  `vk_user_id` int(15) NOT NULL COMMENT 'Ид пользователя VK',
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата регистрации (запроса на регистрацию)',
  `is_blocked` int(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Заблокирован',
  `privileges` enum('USER','OPERATOR','ADMIN') NOT NULL DEFAULT 'USER' COMMENT 'Привилегии пользователя',
  `registered_by` int(15) NOT NULL COMMENT 'Ид пользователя, подтвердившего регистрацию данного пользователя'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- ССЫЛКИ ТАБЛИЦЫ `vk_users`:
--

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_acc_link` (`vk_user_id`,`acc_id`);

--
-- Индексы таблицы `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`acc_id`);

--
-- Индексы таблицы `indications`
--
ALTER TABLE `indications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meter_id` (`meter_id`);

--
-- Индексы таблицы `meters`
--
ALTER TABLE `meters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`acc_id`);

--
-- Индексы таблицы `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vk_user_id` (`vk_user_id`);

--
-- Индексы таблицы `vk_users`
--
ALTER TABLE `vk_users`
  ADD PRIMARY KEY (`vk_user_id`),
  ADD KEY `privileges` (`privileges`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(15) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `indications`
--
ALTER TABLE `indications`
  MODIFY `id` int(15) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `meters`
--
ALTER TABLE `meters`
  MODIFY `id` int(15) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `registration_requests`
--
ALTER TABLE `registration_requests`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`vk_user_id`) REFERENCES `vk_users` (`vk_user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `indications`
--
ALTER TABLE `indications`
  ADD CONSTRAINT `indications_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `meters`
--
ALTER TABLE `meters`
  ADD CONSTRAINT `meters_ibfk_1` FOREIGN KEY (`acc_id`) REFERENCES `clients` (`acc_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
