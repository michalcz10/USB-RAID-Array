<?php
session_start();
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    session_destroy();
    header("location: ../index.php");
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "localhost:3306";
$username = "UNAME";
$password = "PSWD";
$db = "usbraidlogin";

$conn = new mysqli($servername, $username, $password, $db);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['uname']) && isset($_POST['pswd'])) {
        $uname = trim($_POST['uname']);
        $pswd = trim($_POST['pswd']);
        $is_admin = isset($_POST['admin']) ? 1 : 0;
        $defPath = isset($_POST['defPath']) ? trim($_POST['defPath']) : '';
        $delPer = isset($_POST['delPer']) ? (int)$_POST['delPer'] : 0;
        $dowPer = isset($_POST['downPer']) ? (int)$_POST['downPer'] : 0;
        $upPer = isset($_POST['upPer']) ? (int)$_POST['upPer'] : 0;

        if (empty($uname) || empty($pswd)) {
            $_SESSION['message'] = 'Error: Username and password are required!';
            $_SESSION['message_type'] = 'error';
        } else {
            $sql_check = "SELECT * FROM users WHERE uname = ?";
            $stmt_check = $conn->prepare($sql_check);
            if (!$stmt_check) {
                $_SESSION['message'] = 'Error: Database preparation failed.';
                $_SESSION['message_type'] = 'error';
            } else {
                $stmt_check->bind_param("s", $uname);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();

                if ($result_check->num_rows > 0) {
                    $_SESSION['message'] = 'Error: Username already exists!';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $sql_insert = "INSERT INTO users (uname, pswd, admin, defPath, delPer, downPer, upPer) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    if (!$stmt_insert) {
                        $_SESSION['message'] = 'Error: Database preparation failed.';
                        $_SESSION['message_type'] = 'error';
                    } else {
                        $hash = password_hash($pswd, PASSWORD_BCRYPT);
                        if (!$hash) {
                            $_SESSION['message'] = 'Error: Password hashing failed.';
                            $_SESSION['message_type'] = 'error';
                        } else {
                            $stmt_insert->bind_param("ssisiii", $uname, $hash, $is_admin, $defPath, $delPer, $dowPer, $upPer);
                            if ($stmt_insert->execute()) {
                                $_SESSION['message'] = 'User added successfully!';
                                $_SESSION['message_type'] = 'success';
                            } else {
                                $_SESSION['message'] = 'Error: Failed to add user. Please try again later.';
                                $_SESSION['message_type'] = 'error';
                            }
                        }
                    }
                }
                $stmt_check->close();
                if (isset($stmt_insert)) {
                    $stmt_insert->close();
                }
            }
        }

        header("Location: adminpanel.php");
        exit();
    } else {
        $_SESSION['message'] = 'Error: Missing form data!';
        $_SESSION['message_type'] = 'error';
        header("Location: adminpanel.php");
        exit();
    }
}

if (isset($_GET['delete'])) {
    $delete_uname = htmlspecialchars($_GET['delete']);
    $sql = "DELETE FROM users WHERE uname=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $delete_uname);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'User deleted successfully!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error: Failed to delete user.';
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = 'Error: Database preparation failed.';
        $_SESSION['message_type'] = 'error';
    }

    header("Location: adminpanel.php");
    exit();
}

$result = $conn->query("SELECT uname, admin, defPath, delPer, downPer, upPer FROM users");

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['message_type']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <link rel="icon" href="../img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/adminpanel.css">
    <script src="../js/bootstrap.bundle.js"></script>
    <script>
        function confirmDelete(uname) {
            if (confirm('Are you sure you want to delete this user?')) {
                window.location.href = 'adminpanel.php?delete=' + encodeURIComponent(uname);
            }
        }
    </script>
</head>
<body class="text-center">
<div class="d-flex justify-content-end p-3">
    <button id="themeToggle" class="btn btn-sm theme-toggle">
        <i class="bi"></i>
        <span id="themeText"></span>
    </button>
</div>

<div class="custom-container">
    <header class="row border-bottom m-5">
        <h1>USB RAID Array</h1>
        <div class="mb-3 p-3">
            <a href="logout.php" class="btn btn-danger">Logout</a>
            <a href="changepassword.php" class="btn btn-warning">Change Password</a>
            <a href="ftp/index.php" class="btn btn-primary">SFTP</a>
            <a href="ftp/serverstat.php" class="btn btn-primary">Server Status</a>
        </div>
    </header>
    <section class="row">
        <article class="col border border-2 border-primary rounded p-2">
            <h2 class="mb-4">User Management</h2>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="" class="mb-4 add-user-form">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="uname" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="pswd" class="form-control" placeholder="Password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Default Path</label>
                    <input type="text" name="defPath" class="form-control" placeholder="Default Path (e.g., /mnt/raid)" required>
                </div>
                <div class="d-flex flex-column align-items-center mb-3">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="admin" id="adminCheck" value="1" class="form-check-input">
                        <label class="form-check-label" for="adminCheck">Admin</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="delPer" id="delPerCheck" value="1" class="form-check-input">
                        <label class="form-check-label" for="delPerCheck">Delete Permission</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="downPer" id="downPerCheck" value="1" class="form-check-input">
                        <label class="form-check-label" for="downPerCheck">Download Permission</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="upPer" id="upPerCheck" value="1" class="form-check-input">
                        <label class="form-check-label" for="upPerCheck">Upload Permission</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Add User</button>
            </form>
            <h3 class="mb-3">Users List</h3>
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Username</th>
                        <th>Admin</th>
                        <th>Default Path</th>
                        <th>Delete Permission</th>
                        <th>Download Permission</th>
                        <th>Upload Permission</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['uname']); ?></td>
                            <td><?php echo $row['admin'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo htmlspecialchars($row['defPath']); ?></td>
                            <td><?php echo $row['delPer'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $row['downPer'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $row['upPer'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="confirmDelete('<?php echo htmlspecialchars($row['uname']); ?>')">Delete</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </article>
    </section>
    <footer class="d-flex flex-column justify-content-center align-items-center p-3 border-top gap-3 m-3">
        <span class="text-muted">Developed by Michal Sedlák</span>
        <div class="d-flex gap-3">
            <a href="https://github.com/michalcz10/USB-RAID-Array" class="text-decoration-none" target="_blank" rel="noopener noreferrer">
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
<?php
$conn->close();
?>