# USB RAID Array

Webový SFTP klient pro vzdálenou práci se soubory na RAID/NAS úložišti. Projekt vznikl jako školní práce zaměřená na využití starších USB flash disků a porovnání RAID 0, RAID 1 a RAID 5.

Aplikace běží na PHP serveru, účty a sdílené odkazy ukládá do MySQL/MariaDB a k úložišti se připojuje přes SFTP. Statistiky vzdáleného Linux serveru získává přes SSH. Samotné vytvoření RAID pole se provádí na serveru mimo tuto webovou aplikaci.

## Funkce

- Přihlášení, změna hesla a správa uživatelských účtů.
- Oprávnění pro nahrávání, stahování a mazání souborů.
- Procházení adresářů, hledání v aktuální složce a filtrování souborů/složek.
- Nahrávání souborů i adresářů, drag & drop a zobrazení průběhu přenosu.
- Vytváření, přejmenování a mazání položek; stažení adresáře jako ZIP.
- Editor textových souborů, náhled obrázků, videa, audia a PDF.
- Sdílení médií odkazem s expirací a volitelným stažením.
- Přehled disků, Linux MD RAID polí, CPU, RAM a swapu.
- Společná postranní navigace, modré akcenty, světlý a tmavý režim bez závislosti na Bootstrapu.

## Technologie a požadavky

| Část | Požadavky |
| --- | --- |
| Webový server | Apache, například v XAMPP; pro lokální vývoj lze použít i vestavěný PHP server. |
| PHP | Projekt je lokálně ověřený na PHP 8.2.12. Používané závislosti jsou uvedené v `Web/composer.lock`. |
| Databáze | MySQL/MariaDB; původní SQL dump pochází z MySQL 8.0.41. |
| PHP moduly | `mysqli` s `mysqlnd` pro `get_result()`, `session`, `fileinfo`; `zip` pro ZIP operace. Testy vykreslení používají také `dom`. |
| Závislosti | Composer a `phpseclib/phpseclib` řady 3; v repozitáři je také adresář `Web/vendor`. |
| Úložiště | Dostupný SFTP server a účet s přístupem k požadované cestě. |
| Statistiky | SSH přístup k Linux serveru, příkazy `df`, `free`, `vmstat`, `awk`, `tail` a čitelný `/proc/mdstat` pro Linux MD RAID. |
| Prohlížeč | Moderní prohlížeč s podporou nativního `<dialog>`. PDF náhled načítá PDF.js z CDN. |

Rozbalování archivů kromě ZIP může vyžadovat další nástroje, například UnRAR, 7-Zip nebo tar podle zvoleného formátu a operačního systému. PHP musí mít zapisovatelný dočasný adresář pro práci s archivy.

## Rychlé zprovoznění

### 1. Stažení projektu a závislostí

```sh
git clone https://github.com/michalcz10/USB-RAID-Array.git
cd USB-RAID-Array/Web
composer install
```

Pokud už projekt máš stažený, spusť `composer install` v jeho adresáři `Web`. Pro instalaci podle existujícího lock souboru používej `install`.

### 2. Databáze a první správce

Postup je v [dokumentaci databáze](Documentation/Databaze.md). Obsahuje dvě varianty:

1. Import [původního SQL dumpu](Documentation/usbraidlogin.sql) a [doplnění tabulky pro sdílení](Documentation/share.sql).
2. Ruční vytvoření databáze a obou tabulek.

Součástí návodu je vytvoření prvního administrátora s bcrypt hashem hesla. Původní dump obsahuje také uživatelská data a sám nevytváří databázi ani tabulku `share`.

### 3. Konfigurace připojení

V souboru `Web/config/config.php` nastav vlastní připojení. Následující hodnoty jsou příklady; nahrazují odpovídající proměnné v existujícím souboru:

```php
$DB_HOST = 'localhost';
$DB_USER = 'usbraid_app';
$DB_PASS = 'DOPLNTE_HESLO_DB';
$DB_NAME = 'usbraidlogin';

$SFTP_HOST = 'ADRESA_SFTP_SERVERU';
$SFTP_USER = 'SFTP_UZIVATEL';
$SFTP_PASS = 'DOPLNTE_HESLO_SFTP';

$SSH_HOST = 'ADRESA_SSH_SERVERU';
$SSH_USER = 'SSH_UZIVATEL';
$SSH_PASS = 'DOPLNTE_HESLO_SSH';
```

SFTP a SSH mohou používat stejný server, ale mají samostatné konfigurační proměnné. Aktuální pomocné funkce používají výchozí port 22 a přihlášení heslem. Přihlašovací údaje do webu se ověřují proti tabulce `users`; jednotliví weboví uživatelé sdílejí nakonfigurované SFTP připojení.

Výchozí vzdálená cesta se načítá z `users.defPath` po přihlášení. Nastav například `/mnt/raid` podle skutečného připojení úložiště na vzdáleném serveru. Nejde o cestu na počítači s XAMPP. SFTP účet musí mít také odpovídající oprávnění v Linuxu.

### 4. Spuštění přes XAMPP

1. Spusť Apache a MySQL v XAMPP Control Panelu.
2. Zpřístupni adresář `Web` jako `C:\xampp\htdocs\Web` — zkopírováním nebo již nastaveným symlinkem.
3. Otevři `http://localhost/Web/` a přihlas se vytvořeným účtem.
4. Ve správě uživatelů nastav další účty, jejich vzdálené cesty a oprávnění.

Při úpravách projektu používej soubory, které Apache skutečně obsluhuje. U kopie v `htdocs` je potřeba změny překopírovat; symlink odkazuje přímo na pracovní adresář.

Alternativně lze z kořene repozitáře spustit lokální PHP server:

```sh
php -S 127.0.0.1:8080 -t Web
```

Pak otevři `http://127.0.0.1:8080/`. Databáze a vzdálený SFTP/SSH server musí být dostupné i v tomto režimu.

## Struktura projektu

```text
USB-RAID-Array/
├── README.md
├── LICENSE
├── Documentation/
│   ├── Databaze.md          # import, ruční vytvoření tabulek a první správce
│   ├── usbraidlogin.sql     # původní dump tabulky users včetně dat
│   ├── share.sql            # doplnění schématu pro sdílené odkazy
│   └── ...                  # původní technická dokumentace a podklady
├── tests/
│   ├── workspace_test.php
│   ├── serverstats_test.php
│   └── remotepaths_test.php
└── Web/
    ├── index.php            # přihlášení
    ├── config/config.php    # databáze, SFTP a SSH
    ├── includes/workspace.php
    ├── content/
    │   ├── adminpanel.php
    │   ├── changepassword.php
    │   └── ftp/             # správce souborů, náhledy a statistiky
    ├── css/                 # společné styly a rozložení
    ├── js/                  # téma a dialogy
    ├── composer.json
    └── vendor/
```

## Kontroly

Z kořene repozitáře:

```sh
php tests/workspace_test.php
php tests/serverstats_test.php
php tests/remotepaths_test.php
node --check Web/js/theme.js
node --check Web/js/ui.js
node --check Web/content/ftp/js/index.js
```

PHP testy používají testovací data a nepotřebují připojení k databázi ani SSH. Kontrolují vykreslení stránek, navigaci podle role, zpracování statistik a stavy RAID polí. Node.js slouží u uvedených příkazů ke kontrole syntaxe; pro běh aplikace není potřeba.

## Časté problémy

| Problém | Co zkontrolovat |
| --- | --- |
| Chyba připojení k databázi | Běžící MySQL, název databáze, `$DB_*` a oprávnění databázového účtu. |
| Chybí tabulka `users` nebo `share` | Dokončení [nastavení databáze](Documentation/Databaze.md). |
| Přihlášení do webu nefunguje | `pswd` musí obsahovat bcrypt hash, nikoli samotné heslo. |
| `Login Failed` nebo `SSH login failed` | Nastavení SFTP/SSH, dostupnost serveru a vzdálená oprávnění. |
| Nefunguje upload větších souborů | `upload_max_filesize`, `post_max_size`, `max_file_uploads` a časové limity PHP/serveru. Po změně `php.ini` v XAMPP restartuj Apache. |
| Statistiky úložiště jsou stejné | `/` a uživatelská výchozí cesta mohou ležet na stejném souborovém systému. |
| RAID uvádí `Not configured` | Na připojeném serveru není v `/proc/mdstat` detekováno Linux MD pole; RAID na hostiteli virtuálního serveru se tímto způsobem nezobrazuje. |
| PDF se nenačte | Dostupnost PDF souboru, přístup přes SFTP a načtení PDF.js z CDN. |
| Zobrazuje se staré rozhraní | Obnov stránku včetně cache a ověř, že XAMPP načítá správnou kopii projektu. |

## Licence

Projekt je dostupný pod [licencí MIT](LICENSE). Autorem je Michal Sedlák.
