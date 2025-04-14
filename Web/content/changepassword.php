<?php
session_start();

if (!isset($_SESSION['uname'])) {
    header("location: ../index.php");
    exit();
}

$servername = "localhost:3306";
$username = "UNAME";
$password = "PSWD";
$db = "usbraidlogin";

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password'])) {
        $current_password = htmlspecialchars($_POST['current_password']);
        $new_password = htmlspecialchars($_POST['new_password']);
        $confirm_password = htmlspecialchars($_POST['confirm_password']);
        $uname = $_SESSION['uname'];

        if ($new_password !== $confirm_password) {
            $message = "New passwords do not match!";
            $messageType = "danger";
        } else {
            $conn = new mysqli($servername, $username, $password, $db);
            $conn->set_charset("utf8");

            if ($conn->connect_error) {
                $message = "Database connection failed!";
                $messageType = "danger";
            } else {
                $sql = "SELECT pswd FROM users WHERE uname=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $uname);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $stored_hash = $row["pswd"];

                    if (password_verify($current_password, $stored_hash)) {
                        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                        $update_sql = "UPDATE users SET pswd=? WHERE uname=?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("ss", $new_hash, $uname);

                        if ($update_stmt->execute()) {
                            $message = "Password changed successfully!";
                            $messageType = "success";
                        } else {
                            $message = "Error updating password: " . $conn->error;
                            $messageType = "danger";
                        }
                        $update_stmt->close();
                    } else {
                        $message = "Current password is incorrect!";
                        $messageType = "danger";
                    }
                } else {
                    $message = "User not found!";
                    $messageType = "danger";
                }

                $stmt->close();
                $conn->close();
            }
        }
    } else {
        $message = "All fields are required!";
        $messageType = "danger";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <link rel="icon" href="../img/favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/changepassword.css">
    <script src="../js/bootstrap.bundle.js"></script>
</head>
<body class="text-center">
    <div class="d-flex justify-content-end p-3">
        <button id="themeToggle" class="btn btn-sm theme-toggle">
            <i class="bi"></i>
            <span id="themeText"></span>
        </button>
    </div>
    
    <div class="container-fluid">
        <header class="row border-bottom my-3 my-md-5">
            <h1>USB RAID Array</h1>
            <div class="mb-3 p-3">
                <div class="btn-group-responsive">
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                    <a href="ftp/index.php" class="btn btn-primary">Back to Files</a>
                    <?php if (isset($_SESSION["admin"]) && $_SESSION["admin"] == true) { ?>
                        <a href="adminpanel.php" class="btn btn-primary">Admin Panel</a>
                    <?php } ?>
                </div>
            </div>
        </header>
        
        <section class="row content-section">
            <article class="col-12 border border-2 border-primary rounded p-2 p-md-4">
                <h2 class="mb-4">Change Password</h2>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> mb-4">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="mb-4 change-password-form">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </article>
        </section>
        
        <footer class="d-flex flex-column justify-content-center align-items-center p-3 border-top gap-3 m-3">
            <span class="text-muted">Developed by Michal Sedlák</span>
            <div class="d-flex gap-3 flex-wrap justify-content-center">
                <a href="https://github.com/michalcz10/USB-RAID-pole" class="text-decoration-none" target="_blank" rel="noopener noreferrer">
                    <img src="../img/GitHub_Logo.png" alt="GitHub Logo" class="img-fluid hover-effect light-logo" style="height: 32px;">
                    <img src="../img/GitHub_Logo_White.png" alt="GitHub Logo" class="img-fluid hover-effect dark-logo" style="height: 32px;">
                </a>
                <a href="https://app.freelo.io/public/shared-link-view/?a=81efbcb4df761b3f29cdc80855b41e6d&b=4519c717f0729cc8e953af661e9dc981" class="text-decoration-none" target="_blank" rel="noopener noreferrer">
                    <img src="../img/freelo-logo-rgb.png" alt="Freelo Logo" class="img-fluid hover-effect light-logo" style="height: 24px;">
                    <img src="../img/freelo-logo-rgb-on-dark.png" alt="Freelo Logo" class="img-fluid hover-effect dark-logo" style="height: 24px;">
                </a>
            </div>
        </footer>
        
        <script src="../js/theme.js"></script>
    </div>
</body>
</html>
