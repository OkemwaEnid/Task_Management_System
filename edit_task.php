<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

$error = '';
$success = '';
$task = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid task ID.";
    header("Location: dashboard.php");
    exit;
}

$task_id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) {
    $_SESSION['error'] = "Task not found.";
    header("Location: dashboard.php");
    exit;
}

$users = $conn->query("SELECT id, username FROM users")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    $deadline = $_POST['deadline'];
    $assigned_to = $_POST['assigned_to'];
    $original_assigned_to = $task['assigned_to'];

    if (empty($title) || empty($description) || empty($status) || empty($deadline) || empty($assigned_to)) {
        $error = "All fields are required.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, status = ?, deadline = ?, assigned_to = ? WHERE id = ?");
            $stmt->execute([$title, $description, $status, $deadline, $assigned_to, $task_id]);
            $success = "Task updated successfully.";

            if ($assigned_to != $original_assigned_to) {
                $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$assigned_to]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $message = "New task: $title assigned to you.";
                    error_log("Creating notification for user_id: $assigned_to, message: $message", 3, '/tmp/debug.log');
                    try {
                        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                        $stmt->execute([$assigned_to, $message]);
                        error_log("Notification inserted for user_id: $assigned_to", 3, '/tmp/debug.log');
                    } catch (PDOException $e) {
                        error_log("Failed to insert notification: " . $e->getMessage(), 3, '/tmp/debug.log');
                    }

                    $phpmailer_path = 'includes/PHPMailer/src/PHPMailer.php';
                    if (file_exists($phpmailer_path)) {
                        require_once $phpmailer_path;
                        require_once 'includes/PHPMailer/src/SMTP.php';
                        require_once 'includes/PHPMailer/src/Exception.php';

                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.mailtrap.io';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'your_mailtrap_username';
                            $mail->Password = 'your_mailtrap_password';
                            $mail->SMTPSecure = 'tls';
                            $mail->Port = 587;

                            $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
                            $stmt->execute([$assigned_to]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($user) {
                                $mail->setFrom('no-reply@taskmanager.com', 'Task Manager');
                                $mail->addAddress($user['email'], $user['username']);
                                $mail->Subject = 'Task Assignment Update';
                                $mail->Body = "Dear {$user['username']},\n\nYou have been assigned a task: {$title}\nDescription: {$description}\nDeadline: {$deadline}\n\nPlease check the Task Management System for details.";
                                $mail->send();
                                $success .= " Email notification sent to {$user['username']}.";
                            }
                        } catch (Exception $e) {
                            error_log("PHPMailer Error: {$mail->ErrorInfo}", 3, '/tmp/mail.log');
                            $error = "Task updated, but failed to send email notification.";
                        }
                    } else {
                        error_log("PHPMailer not found at $phpmailer_path", 3, '/tmp/mail.log');
                        $error = "Task updated, but email notification failed due to missing PHPMailer.";
                    }
                } else {
                    error_log("User not found for user_id: $assigned_to", 3, '/tmp/debug.log');
                }
            } else {
                error_log("No notification created: assigned_to ($assigned_to) same as original_assigned_to ($original_assigned_to)", 3, '/tmp/debug.log');
            }
        } catch (PDOException $e) {
            $error = "Error updating task: " . $e->getMessage();
            error_log("Task update error: " . $e->getMessage(), 3, '/tmp/debug.log');
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Task</title>
    <link rel="stylesheet" href="css/style.css?v=3">
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
        <form method="POST">
            <input type="text" name="title" placeholder="Task Title" value="<?php echo htmlspecialchars($task['title']); ?>" required>
            <textarea name="description" placeholder="Task Description" required><?php echo htmlspecialchars($task['description']); ?></textarea>
            <select name="status" required>
                <option value="Pending" <?php echo $task['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="In Progress" <?php echo $task['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="Completed" <?php echo $task['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
            <input type="date" name="deadline" value="<?php echo htmlspecialchars($task['deadline']); ?>" required>
            <select name="assigned_to" required>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $task['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Update Task</button>
        </form>
        <a href="dashboard.php" class="center-link">Back to Dashboard</a>
    </div>
</body>
</html>