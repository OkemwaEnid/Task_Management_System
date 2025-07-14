<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND assigned_to = ?");
$task->execute([$task_id, $_SESSION['user_id']]);
$task = $task->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    $_SESSION['error'] = "Task not found or not assigned to you.";
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status'];
    if (in_array($status, ['Pending', 'In Progress', 'Completed'])) {
        $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $stmt->execute([$status, $task_id]);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid status selected.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Task</title>
    <link rel="stylesheet" href="css/style.css?v=3">
</head>
<body>
    <div class="container">
        <h2>Update Task: <?php echo htmlspecialchars($task['title']); ?></h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <select name="status" required>
                <option value="Pending" <?php if ($task['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                <option value="In Progress" <?php if ($task['status'] == 'In Progress') echo 'selected'; ?>>In Progress</option>
                <option value="Completed" <?php if ($task['status'] == 'Completed') echo 'selected'; ?>>Completed</option>
            </select>
            <button type="submit">Update Status</button>
        </form>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>