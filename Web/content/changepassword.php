<?php
session_start();

if (!isset($_SESSION['uname'])) {
    header("location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/workspace.php';
// $username = "UNAME";
// $password = "PSWD";
// $db = "usbraidlogin";

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password'])) {
        $current_password = htmlspecialchars($_POST['current_password']);
        $new_password = htmlspecialchars($_POST['new_password']);
        $confirm_password = htmlspecialchars($_POST['confirm_password']);
        $uname = $_SESSION['uname'];

        if(!CheckPassword($new_password)) {
            $message = "New password must be at least 8 characters long, contain at least one number and one uppercase letter!";
            $messageType = "danger";
        } else if ($new_password !== $confirm_password) {
            $message = "New passwords do not match!";
            $messageType = "danger";
        } else {
            $conn = getDatabaseConnection();
            // charset already set in getDatabaseConnection()

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

function CheckPassword($password) {
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <link rel="icon" href="../img/favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= workspaceAsset('../', 'css/modern.css') ?>">
    <link rel="stylesheet" href="<?= workspaceAsset('../', 'css/workspace.css') ?>">
    <link rel="stylesheet" href="<?= workspaceAsset('../', 'content/css/changepassword.css') ?>">
    <script src="<?= workspaceAsset('../', 'js/theme.js') ?>"></script>
    <script src="<?= workspaceAsset('../', 'js/ui.js') ?>" defer></script>
</head>
<?php renderWorkspaceStart([
    'root' => '../',
    'active' => 'account',
    'section' => 'Account settings',
    'eyebrow' => 'Your account',
    'title' => 'Account settings',
    'description' => 'Keep your workspace account secure and up to date.',
    'class' => 'account-page',
]); ?>


        <section class="account-layout">
            <article class="content-panel">
                <div class="panel-heading"><h2>Change password</h2><p>Choose a strong password to protect your files.</p></div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> mb-4">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="mb-4 change-password-form">
                    <div class="mb-3">
                        <label class="form-label" for="currentPassword">Current password</label>
                        <input type="password" id="currentPassword" autocomplete="current-password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newPassword">New password</label>
                        <input type="password" id="newPassword" autocomplete="new-password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirmPassword">Confirm new password</label>
                        <input type="password" id="confirmPassword" autocomplete="new-password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </article>
<aside class="account-help"><span class="eyebrow">Password requirements</span><h3>A little extra protection</h3><p>Use at least 8 characters, one uppercase letter and one number.</p><p>You can return to your files at any time using the workspace navigation.</p></aside>
        </section>

        <?php renderWorkspaceEnd(); ?>
</body>
</html>
