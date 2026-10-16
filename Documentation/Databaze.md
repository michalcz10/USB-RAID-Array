# Nastavení databáze

Tento návod popisuje databázi webové aplikace USB RAID Array pro MySQL/MariaDB. Lze použít phpMyAdmin v XAMPP nebo příkazový klient `mysql`.

Vyber jednu ze dvou cest: [import SQL](#varianta-a-import-sql) nebo [ruční vytvoření tabulek](#varianta-b-ruční-vytvoření-tabulek). Poté pokračuj [vytvořením prvního správce](#první-administrátor) a [nastavením připojení](#připojení-aplikace).

## Co aplikace ukládá

| Tabulka | Účel | Používají ji |
| --- | --- | --- |
| `users` | Webové účty, hashe hesel, role, vzdálené cesty a oprávnění. | `login.php`, `adminpanel.php`, `changepassword.php` |
| `share` | Tokeny sdílených médií, cesta k souboru, expirace a povolení stažení. | `ftp/view.php`, `ftp/shared.php` |

Obsah souborů zůstává na SFTP serveru. Databáze uchovává jen účty a metadata sdílení.

### Původní SQL soubor a doplnění

[usbraidlogin.sql](usbraidlogin.sql) je původní dump z phpMyAdmin. Obsahuje vytvoření tabulky `users` a vložení uživatelských dat. **Neobsahuje `CREATE DATABASE`, `USE` ani tabulku `share`.** Tabulka `users` v tomto dumpu nemá primární ani unikátní klíč.

[share.sql](share.sql) doplňuje strukturu, kterou aktuální kód potřebuje pro sdílené odkazy. Je odvozená z dotazů v `view.php` a `shared.php`, nikoli z původního dumpu. Neobsahuje testovací odkazy ani uživatelská data. `IF NOT EXISTS` při opakovaném spuštění nevytvoří druhou tabulku, ale také neopraví již existující nekompatibilní schéma.

Obě varianty níže používají databázi `usbraidlogin`, InnoDB a `utf8mb4_unicode_ci` podle původního SQL. Import původního dumpu prováděj do nové databáze; na existující tabulce `users` by `CREATE TABLE` skončilo chybou.

## Varianta A: import SQL

### Přes phpMyAdmin

1. V XAMPP spusť Apache a MySQL.
2. Otevři `http://localhost/phpmyadmin/` a přihlas se databázovým účtem s právem vytvářet databáze a tabulky.
3. Na kartě **SQL** vytvoř databázi:

   ```sql
   CREATE DATABASE `usbraidlogin`
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```

4. V levém panelu vyber **usbraidlogin**. Tento krok je nutný, protože dump neobsahuje příkaz `USE`.
5. Otevři **Import**, vyber `Documentation/usbraidlogin.sql`, ponech formát SQL a spusť import.
6. Ve stejné databázi znovu otevři **Import** a importuj `Documentation/share.sql`.
7. Zkontroluj, že jsou přítomné tabulky `users` a `share`.

Původní dump vkládá i účty s hashi hesel. Z hashe nelze zjistit původní heslo. Pro vlastní instalaci vytvoř vlastní účet podle kapitoly [První administrátor](#první-administrátor). Importované účty před zpřístupněním instalace zkontroluj a ponech jen ty, které chceš používat.

### Přes příkazový řádek

V PowerShellu otevři klienta z XAMPP:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -p
```

Použij jméno svého správce databáze místo `root`, pokud se liší. Heslo se zadává až do výzvy klienta. Jestli má lokální účet prázdné heslo, potvrď výzvu Enterem.

Na Linuxu nebo pokud je klient v `PATH`:

```sh
mysql -u root -p
```

V klientovi spusť následující příkazy. Cesty uprav podle umístění repozitáře; v příkladu pro Windows jsou použité dopředné lomítka:

```sql
CREATE DATABASE `usbraidlogin`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `usbraidlogin`;
SOURCE C:/cesta/USB-RAID-Array/Documentation/usbraidlogin.sql;
SOURCE C:/cesta/USB-RAID-Array/Documentation/share.sql;
SHOW TABLES;
```

`SOURCE` je příkaz klienta `mysql`. V phpMyAdmin používej kartu Import. Na Linuxu nahraď cesty například `/cesta/USB-RAID-Array/Documentation/usbraidlogin.sql` a odpovídající cestou k `share.sql`.

## Varianta B: ruční vytvoření tabulek

Tato varianta vytvoří prázdné tabulky bez importovaných účtů. Následující SQL vlož do karty **SQL** v phpMyAdmin nebo do klienta `mysql`. Původní dump už v této variantě neimportuj.

### 1. Databáze

```sql
CREATE DATABASE `usbraidlogin`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `usbraidlogin`;
```

Pokud databáze už existuje, vyber ji příkazem `USE` a přeskoč `CREATE DATABASE`. Před vytvořením tabulek ověř jejich existenci pomocí `SHOW TABLES`; existující tabulky nemaž kvůli opakování návodu.

### 2. Tabulka `users`

Struktura přesně odpovídá tabulce v původním `usbraidlogin.sql`:

```sql
CREATE TABLE `users` (
  `uname` varchar(255) NOT NULL,
  `pswd` varchar(60) NOT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  `defPath` varchar(255) NOT NULL DEFAULT '/',
  `delPer` tinyint(1) NOT NULL DEFAULT '0',
  `downPer` tinyint(1) NOT NULL DEFAULT '0',
  `upPer` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

| Sloupec | Význam |
| --- | --- |
| `uname` | Přihlašovací jméno. Každý účet vytvářej s odlišným jménem. |
| `pswd` | Bcrypt hash hesla vytvořený PHP, dlouhý 60 znaků. |
| `admin` | `1` pro správce, `0` pro běžný účet; správce má přístup ke správě účtů a statistikám serveru. |
| `defPath` | Absolutní vzdálená cesta na SFTP serveru, například `/mnt/raid`. |
| `delPer` | `1` povoluje mazání souborů a adresářů. |
| `downPer` | `1` povoluje stahování v rozhraní. |
| `upPer` | `1` povoluje nahrávání a úpravy, například vytváření, přejmenování, editaci a rozbalování archivů. |

Hodnoty oprávnění zadávej jako `0` nebo `1`. Role správce a souborová oprávnění jsou samostatné sloupce; pro plný přístup nastav prvnímu správci všechny čtyři přepínače na `1`.

Věrná kopie původního schématu nemá unikátní index pro `uname`. Administrace duplicity ověřuje při přidávání účtu, ale při ručním SQL je musíš zkontrolovat sám.

### 3. Tabulka `share`

Stejnou tabulku vytváří doplňující soubor `share.sql`:

```sql
CREATE TABLE IF NOT EXISTS `share` (
  `token` char(32) NOT NULL,
  `file_path` text NOT NULL,
  `expiration` datetime NOT NULL,
  `download_allowed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`token`),
  KEY `idx_share_expiration` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

| Sloupec | Význam |
| --- | --- |
| `token` | Identifikátor odkazu v URL; aplikace generuje 32 hexadecimálních znaků pomocí `bin2hex(random_bytes(16))`. |
| `file_path` | Vzdálená cesta ke sdílenému souboru. |
| `expiration` | Datum a čas konce platnosti. Dotaz v `shared.php` vyžaduje `expiration > NOW()`. |
| `download_allowed` | `1` povoluje stažení přes sdílený odkaz, `0` pouze náhled. |

Primární klíč zajišťuje jedinečnost tokenu, index `idx_share_expiration` usnadňuje dotazy podle expirace. Mezi tabulkami není cizí klíč: současný kód u odkazu neukládá autora ani ID uživatele.

### Alternativa: naklikání tabulek v phpMyAdmin

Místo spuštění uvedených `CREATE TABLE` můžeš stejné tabulky vytvořit přes formulář:

1. Vyber databázi `usbraidlogin`, zvol vytvoření nové tabulky `users` a počet sloupců **7**.
2. Zadej názvy a datové typy podle SQL výše: `uname VARCHAR(255)`, `pswd VARCHAR(60)`, `admin TINYINT(1)`, `defPath VARCHAR(255)`, `delPer TINYINT(1)`, `downPer TINYINT(1)` a `upPer TINYINT(1)`.
3. U všech sloupců ponech **NULL vypnuté** a nezapínej `A_I` (AUTO_INCREMENT). Přepínačům nastav výchozí hodnotu `0`, sloupci `defPath` hodnotu `/`. `uname` a `pswd` nemají výchozí hodnotu. Pro shodu s původním dumpem nenastavuj žádný primární klíč.
4. Vyber engine **InnoDB**, porovnávání **utf8mb4_unicode_ci** a tabulku ulož.
5. Vytvoř tabulku `share` se **4** sloupci: `token CHAR(32)`, `file_path TEXT`, `expiration DATETIME` a `download_allowed TINYINT(1)`.
6. Opět vypni NULL a AUTO_INCREMENT. U `download_allowed` nastav výchozí hodnotu `0`; ostatní sloupce výchozí hodnotu nemají. Sloupci `token` nastav index **PRIMARY**.
7. Použij InnoDB a utf8mb4_unicode_ci. Po uložení na kartě **Struktura** přidej běžný index `idx_share_expiration` na sloupci `expiration`.
8. Výsledek porovnej pomocí `SHOW CREATE TABLE users;` a `SHOW CREATE TABLE share;` s uvedenými SQL definicemi.

## První administrátor

### 1. Vytvoření hashe hesla

Heslo do `pswd` neukládej jako prostý text ani jako MD5/SHA hash. Přihlášení používá PHP `password_verify()` a aplikace vytváří hesla pomocí `PASSWORD_BCRYPT`.

Pokud je PHP v `PATH`, spusť:

```sh
php -r "echo password_hash('ZVOLTE_VLASTNI_HESLO', PASSWORD_BCRYPT), PHP_EOL;"
```

V PowerShellu s XAMPP lze použít plnou cestu:

```powershell
& 'C:\xampp\php\php.exe' -r "echo password_hash('ZVOLTE_VLASTNI_HESLO', PASSWORD_BCRYPT), PHP_EOL;"
```

`ZVOLTE_VLASTNI_HESLO` před spuštěním nahraď vlastním heslem. Zvol alespoň 8 znaků, jednu číslici a jedno velké písmeno, aby odpovídalo pravidlům správy účtů. Výstup bude bcrypt hash začínající například `$2y$10$`. Každé spuštění může pro stejné heslo vytvořit jiný platný hash.

Pokud nechceš heslo uvést v historii příkazové řádky, spusť `php -a` a zavolej `password_hash()` v interaktivním PHP shellu. Příkaz s vlastním heslem ani jeho výstup neukládej do veřejných ukázek dokumentace.

### 2. Vložení účtu

Nejprve ověř, zda zvolené jméno už není použité:

```sql
USE `usbraidlogin`;
SELECT `uname`, `admin`, `defPath` FROM `users` WHERE `uname` = 'spravce';
```

Pokud dotaz nic nevrátí, vlož účet. Text `SEM_VLOZTE_BCRYPT_HASH` nahraď celým skutečným hashem z předchozího kroku a `/mnt/raid` skutečnou cestou na SFTP serveru:

```sql
INSERT INTO `users`
  (`uname`, `pswd`, `admin`, `defPath`, `delPer`, `downPer`, `upPer`)
VALUES
  ('spravce', 'SEM_VLOZTE_BCRYPT_HASH', 1, '/mnt/raid', 1, 1, 1);
```

Tento SQL příkaz je šablona — placeholder není platný hash. Do přihlašovacího formuláře následně zadávej jméno `spravce` a původní heslo, ze kterého jsi hash vytvořil. Další účty už lze přidávat přes **User management**.

Pokud jméno existuje, další stejné nevkládej. Pro obnovení přístupu ke svému účtu lze v phpMyAdmin nahradit pouze jeho hash:

```sql
UPDATE `users`
SET `pswd` = 'SEM_VLOZTE_BCRYPT_HASH'
WHERE `uname` = 'spravce';
```

## Připojení aplikace

Pro aplikaci vytvoř samostatný databázový účet. Následující příklad předpokládá, že se PHP připojuje k databázi lokálně jako `usbraid_app` na hostiteli `localhost`:

```sql
CREATE USER 'usbraid_app'@'localhost' IDENTIFIED BY 'DOPLNTE_VLASTNI_HESLO_DB';
GRANT SELECT, INSERT, UPDATE, DELETE ON `usbraidlogin`.* TO 'usbraid_app'@'localhost';
```

DDL příkazy pro vytvoření tabulek spouštěj správcovským databázovým účtem. Aplikace pro běžný provoz nepotřebuje práva `CREATE` ani `DROP`. Pokud účet již existuje, `CREATE USER` neopakuj; nastav mu potřebná práva. Pro vzdálenou databázi použij hostitele odpovídajícího umístění PHP serveru místo `localhost`.

V existujícím `Web/config/config.php` nastav:

```php
$DB_HOST = 'localhost';
$DB_USER = 'usbraid_app';
$DB_PASS = 'DOPLNTE_VLASTNI_HESLO_DB';
$DB_NAME = 'usbraidlogin';
```

Účet MySQL, webový účet z tabulky `users` a SFTP/SSH účet jsou tři různé věci. Změna hesla ve webové aplikaci mění pouze hash v `users.pswd`.

### Časové pásmo sdílených odkazů

`view.php` počítá expiraci v `Europe/Prague`, zatímco databázový dotaz ji porovnává s `NOW()`. Databázový čas musí odpovídat tomuto pásmu, jinak může odkaz vypršet dříve nebo později. Ověř:

```sql
SELECT NOW() AS database_time,
       @@global.time_zone AS global_time_zone,
       @@session.time_zone AS session_time_zone,
       @@system_time_zone AS system_time_zone;
```

Pokud databáze používá `SYSTEM`, ověř časové pásmo systému databázového serveru. U spravované instalace lze nakonfigurovat časové pásmo MySQL na `Europe/Prague`, pokud má server načtené tabulky časových pásem. Jednorázové `SET time_zone` v phpMyAdmin mění pouze jeho spojení a nevyřeší nastavení nových spojení aplikace. Pevný posun `+01:00` nepokrývá český letní čas.

## Ověření instalace

V databázi spusť:

```sql
USE `usbraidlogin`;
SHOW TABLES;
DESCRIBE `users`;
DESCRIBE `share`;

SELECT `uname`, `admin`, `defPath`, `delPer`, `downPer`, `upPer`,
       CHAR_LENGTH(`pswd`) AS hash_length
FROM `users`;
```

Výsledek má obsahovat obě tabulky. U účtu vytvořeného podle návodu má mít `hash_length` hodnotu `60`. Délka sama o sobě ještě nepotvrzuje správnost hesla; tu ověří přihlášení.

V aplikaci pak ověř:

1. Přihlášení vlastním administrátorem a otevření správy účtů.
2. Správnou výchozí cestu a přístup k SFTP souborům.
3. Vytvoření sdíleného odkazu u testovacího média a jeho otevření v anonymním okně.
4. Platnost odkazu a chování volby stažení.

Změny role, cesty a oprávnění provedené přímo v databázi se do existujícího webového přihlášení nepromítnou okamžitě — hodnoty se načítají do session při přihlášení. Po takové změně se odhlas a znovu přihlas.

## Řešení chyb

| Chyba | Příčina a řešení |
| --- | --- |
| `Unknown database 'usbraidlogin'` | Vytvoř databázi a zkontroluj `$DB_NAME`. |
| `No database selected` při importu | Před importem vyber databázi v phpMyAdmin nebo spusť `USE usbraidlogin`. |
| `Table 'users' already exists` | Dump se importuje do již vytvořeného schématu. Zkontroluj existující tabulku a nepokračuj slepým mazáním dat. |
| `Table 'usbraidlogin.share' doesn't exist` | Importuj `share.sql` nebo tabulku vytvoř ručně podle návodu. |
| `Access denied for user` | Zkontroluj `$DB_USER`, `$DB_PASS`, hostitele a přidělená práva. |
| Přihlášení hlásí nesprávné údaje | Ověř jméno a bcrypt hash; do formuláře patří původní heslo. |
| Sdílený odkaz ihned expiruje | Zkontroluj časové pásmo a `NOW()` na databázovém serveru. |
| Čeština se ukládá nesprávně | Tabulky používají `utf8mb4`; současný helper v `config.php` nastavuje spojení na `utf8`. Pro plné čtyřbajtové znaky, například emoji, je potřeba sjednotit i spojení na `utf8mb4`. |
