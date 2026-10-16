-- Doplneni puvodniho usbraidlogin.sql o tabulku pro sdilene odkazy.
-- Nejprve vyberte databazi usbraidlogin v phpMyAdmin nebo prikazem USE.
-- Struktura odpovida INSERT/SELECT v Web/content/ftp/view.php a shared.php.

CREATE TABLE IF NOT EXISTS `share` (
  `token` char(32) NOT NULL,
  `file_path` text NOT NULL,
  `expiration` datetime NOT NULL,
  `download_allowed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`token`),
  KEY `idx_share_expiration` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
