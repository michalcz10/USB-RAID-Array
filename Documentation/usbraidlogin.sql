-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Počítač: localhost:3306
-- Vytvořeno: Čtv 20. bře 2025, 12:55
-- Verze serveru: 8.0.41-0ubuntu0.22.04.1
-- Verze PHP: 8.1.2-1ubuntu2.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáze: `usbraidlogin`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `users`
--

CREATE TABLE `users` (
  `uname` varchar(255) NOT NULL,
  `pswd` varchar(60) NOT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  `defPath` varchar(255) NOT NULL DEFAULT '/',
  `delPer` tinyint(1) NOT NULL DEFAULT '0',
  `downPer` tinyint(1) NOT NULL DEFAULT '0',
  `upPer` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Vypisuji data pro tabulku `users`
--

INSERT INTO `users` (`uname`, `pswd`, `admin`, `defPath`, `delPer`, `downPer`, `upPer`) VALUES
('admin', '$2y$10$QZEnBMv01f92Ikrta3Qo4ewbmoozVuDT.uPhtztNT7bsIwDQ.Fy8e', 1, '/', 1, 1, 1);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
