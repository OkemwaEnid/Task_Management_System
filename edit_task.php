<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "Access denied. Admins only.";
    header("Location: login.php");
    exit;
}

// Check for config.php
if (!file_exists('includes/config.php')) {
    die("Error: includes/config.php not found.");
}
require_once 'includes/config.php';

// Check for PHPMailer (only on live server)
$is_local = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost');
if (!$is_local) {
    $phpmailer_files = ['includes/PHPMailer/src/PHPMailer.php', 'includes/PHPMailer/src/SMTP.php', 'includes/PHPMailer/src/Exception.php'];
    foreach ($phpmailer_files as $file) {
        if (!file_exists($file)) {
            die("Error: $file not found.");
        }
    }
    require_once 'includes/PHPMailer/src/PHPMailer.php';
    require_once 'includes/PHPMailer/src/SMTP.php';
    require_once 'includes/PHPMailer/src/Exception.php';
}

// Connect to DB
$db = new Database();
$conn = $db->connect();
if (!$conn) {
    die("Error: Database connection failed.");
}

// Get task ID
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($task_id <= 0) {
    $_SESSION['error'] = "Invalid task ID.";
    header("Location: dashboard.php");
    exit;
}

// Fetch task details
$task_stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
$task_stmt->execute([$task_id]);
$task = $task_stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    $_SESSION['error'] = "Task not found.";
    header("Location: dashboard.php");
    exit;
}

// Fetch users
$users_stmt = $conn->query("SELECT id, username FROM users WHERE role = 'user'");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $assigned_to = (int)$_POST['assigned_to'];
    $status = $_POST['status'];
    $deadline = $_POST['deadline'];

    if (empty($title) || !in_array($status, ['Pending', 'In Progress', 'Completed']) || $assigned_to <= 0) {
        $error = "Invalid input. Please check the title, status, or assigned user.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, assigned_to = ?, status = ?, deadline = ? WHERE id = ?");
            $stmt->execute([$title, $description, $assigned_to, $status, $deadline, $task_id]);

            // Send notification if reassigned
            if ($task['assigned_to'] != $assigned_to) {
                $user_stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
                $user_stmt->execute([$assigned_to]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $to = $user['email'];
                    $subject = "Task Reassigned: $title";
                    $message = "Dear {$user['username']},\n\nA task has been reassigned to you.\n\nTitle: $title\nDescription: $description\nDeadline: $deadline";

                    if ($is_local) {
                        error_log("Email notification (local):\n$message\n\n", 3, "/tmp/mail.log");
                        $success = "Task updated successfully. Email logged to /tmp/mail.log.";
                    } else {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.mailtrap.io'; // Replace with real SMTP
                            $mail->SMTPAuth = true;
                            $mail->Username = 'your_mailtrap_username';
                            $mail->Password = 'your_mailtrap_password';
                            $mail->Port = 587;
                            $mail->setFrom('no-reply@taskmanager.com', 'Task Manager');
                            $mail->addAddress($to);
                            $mail->Subject = $subject;
                            $mail->Body = $message;
                            $mail->send();
                            $success = "Task updated and email sent.";
                        } catch (Exception $e) {
                            error_log("PHPMailer Error: {$mail->ErrorInfo}");
                            $error = "Task updated, but email notification failed.";
                        }
                    }
                }
            } else {
                $success = "Task updated successfully.";
            }

            $_SESSION['success'] = $success;
            $_SESSION['error'] = $error;
            header("Location: dashboard.php");
            exit;

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Task</title>
    <link rel="stylesheet" href="css/style.css?v=4">
</head>
<body>
    <div class="container">
        <h2>Edit Task</h2>

        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" placeholder="Task Title" required>
            <textarea name="description" placeholder="Description"><?php echo htmlspecialchars($task['description']); ?></textarea>
            <select name="assigned_to" required>
                <option value="">Select User</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>" <?php echo ($user['id'] == $task['assigned_to']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['username']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="status" required>
                <option value="Pending" <?php echo ($task['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="In Progress" <?php echo ($task['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                <option value="Completed" <?php echo ($task['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
            </select>
            <input type="date" name="deadline" value="<?php echo htmlspecialchars($task['deadline']); ?>" required>
            <button type="submit">Save Changes</button>
        </form>

        <a href="dashboard.php" class="center-link">← Back to Dashboard</a>
    </div>
</body>
</html>
