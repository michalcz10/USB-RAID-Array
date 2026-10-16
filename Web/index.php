<?php
session_start();

if (empty($_SESSION)) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
} elseif (isset($_SESSION["uname"])) {
    header("location: content/ftp/index.php");
    exit();
} elseif (isset($_SESSION['login_error'])) {
    echo "<script>alert('" . $_SESSION['login_error'] . "');</script>";
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · USB RAID</title>
    <link rel="icon" href="img/favicon.ico">
    <link rel="stylesheet" href="css/modern.css">
    <link rel="stylesheet" href="css/index.css">
    <script src="js/theme.js"></script>
</head>
<body class="login-page">
    <header class="login-top"><a class="login-brand" href="index.php"><span>▤</span> USB RAID</a><button id="themeToggle" class="btn btn-sm"><i aria-hidden="true"></i><span id="themeText">Theme</span></button></header>
    <main class="login-layout">
        <section class="login-story"><div class="eyebrow">Your storage. Your workspace.</div><h1>A home for<br>every file<span>.</span></h1><p>Connect to your RAID storage and keep your files within reach. One simple workspace, powered by SFTP.</p><div class="storage-illustration" aria-hidden="true"><div class="storage-top">USB RAID <span>SFTP</span></div><div class="storage-drive"><b>01</b><span></span><i></i></div><div class="storage-drive"><b>02</b><span></span><i></i></div><div class="storage-drive"><b>03</b><span></span><i></i></div><div class="storage-bottom">REMOTE STORAGE / LOCAL CONTROL</div></div></section>
        <section class="login-card"><div class="eyebrow">Welcome back</div><h2>Sign in to your workspace</h2><p class="text-muted">Use your account to access your files.</p><form action="content/login.php" method="post"><div class="mb-3"><label for="uname">Username</label><input id="uname" name="uname" class="form-control" autocomplete="username" placeholder="Enter your username" required></div><div class="mb-4"><label for="pswd">Password</label><input id="pswd" type="password" name="pswd" class="form-control" autocomplete="current-password" placeholder="Enter your password" required></div><button type="submit" class="btn btn-primary">Sign in <span aria-hidden="true">→</span></button></form><div class="login-note">Need access? Contact your administrator.</div></section>
    </main>
    <footer class="login-footer"><span>Developed by Michal Sedlák</span><a href="https://github.com/michalcz10/USB-RAID-Array" target="_blank" rel="noopener noreferrer">Project on GitHub ↗</a></footer>
</body>
</html>
