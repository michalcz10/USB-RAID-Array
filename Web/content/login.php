<?php
session_start();

if (isset($_POST['uname'], $_POST['pswd'])) {
    $servername = "localhost:3306";
    $username = "UNAME";
    $password = "PSWD";
    $db = "usbraidlogin";

    $uname = htmlspecialchars($_POST['uname']);
    $pswd = htmlspecialchars($_POST['pswd']);

    $conn = new mysqli($servername, $username, $password, $db);
    $conn->set_charset("utf8");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT * FROM users WHERE uname=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['login_error'] = "Database error. Please try again.";
        $conn->close();
        header("Location: ../index.php");
        exit();
    }

    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $login_successful = false;
        while ($row = $result->fetch_assoc()) {
            $hash = $row["pswd"];
            $admin = (bool)$row["admin"];
            $defPath = $row["defPath"];
            $delPer = $row["delPer"];
            $downPer = $row["downPer"];
            $upPer = $row["upPer"];

            if (password_verify($pswd, $hash)) {
                // Login successful - store info in session
                $_SESSION['uname'] = $uname;
                $_SESSION['admin'] = $admin;
                $_SESSION['defPath'] = $defPath;
                $_SESSION['downPer'] = $downPer;
                $_SESSION['delPer'] = $delPer;
                $_SESSION['upPer'] = $upPer;
                $login_successful = true;
                break;
            }
        }
        $result->free_result();
        $stmt->close();
        $conn->close();

        if ($login_successful) {
            header("Location: ftp/index.php");
            exit();
        } else {
            // If password verification fails
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: ../index.php");
            exit();
        }
    } else {
        // If no user is found
        $result->free_result();
        $stmt->close();
        $conn->close();
        $_SESSION['login_error'] = "Invalid username or password.";
        header("Location: ../index.php");
        exit();
    }
} else {
    // If form fields are not set
    $_SESSION['login_error'] = "Please fill in all fields.";
    header("Location: ../index.php");
    exit();
}
?>
