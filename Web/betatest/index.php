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
<html lang="cz">
<head>
    <title>Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/index.css">
    <script src="js/bootstrap.bundle.js"></script>
</head>
<body class="container-fluid text-center">
    <div class="d-flex justify-content-end p-3">
        <button id="themeToggle" class="btn btn-sm theme-toggle">
            <i class="bi"></i>
            <span id="themeText"></span>
        </button>
    </div>
    
    <header class="row border-bottom m-5">
        <h1>USB RAID Array</h1>
    </header>

    <main>
        <section class="row m-3">
            <article class="col border border-2 border-primary rounded p-2 fixed">
                <form action="content/login.php" method="post">
                    <div class="mb-3">
                        <label for="uname">Username:</label>
                        <br>
                        <input type="text" name="uname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="pswd">Password:</label>
                        <br>
                        <input type="password" name="pswd" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <input type="submit" class="btn btn-primary" value="Login">
                    </div>
                </form>
            </article>
        </section>
    </main>

    <footer class="d-flex flex-column justify-content-center align-items-center p-3 border-top gap-3">
        <span class="text-muted">Developed by Michal Sedlák</span>
        <div class="d-flex gap-3">
            <a href="https://github.com/michalcz10/USB-RAID-pole" class="text-decoration-none" target="_blank" rel="noopener noreferrer">
                <img src="img/GitHub_Logo.png" alt="GitHub Logo" class="img-fluid hover-effect light-logo" style="height: 32px;">
                <img src="img/GitHub_Logo_White.png" alt="GitHub Logo" class="img-fluid hover-effect dark-logo" style="height: 32px;">
            </a>
            <a href="https://app.freelo.io/public/shared-link-view/?a=81efbcb4df761b3f29cdc80855b41e6d&b=4519c717f0729cc8e953af661e9dc981" class="text-decoration-none" target="_blank" rel="noopener noreferrer">
                <img src="img/freelo-logo-rgb.png" alt="Freelo Logo" class="img-fluid hover-effect light-logo" style="height: 24px;">
                <img src="img/freelo-logo-rgb-on-dark.png" alt="Freelo Logo" class="img-fluid hover-effect dark-logo" style="height: 24px;">
            </a>
        </div>
    </footer>
    
    <script src="js/theme.js"></script>
</body>
</html>