<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $assigned_to = $_POST['assigned_to'];
    $deadline = $_POST['deadline'];
    $stmt = $conn->prepare("INSERT INTO tasks (title, description, assigned_to, deadline) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $description, $assigned_to, $deadline]);

    // Send email notification
    $user = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $user->execute([$assigned_to]);
    $user = $user->fetch(PDO::FETCH_ASSOC);
    $to = $user['email'];
    $subject = "New Task Assigned: $title";
    $message = "A new task has been assigned to you.\nTitle: $title\nDescription: $description\nDeadline: $deadline";
    $headers = "From: no-reply@taskmanager.com";
    mail($to, $subject, $message, $headers);
}

$users = $conn->query("SELECT id, username FROM users WHERE role = 'user'")->fetchAll(PDO::FETCH_ASSOC);
$tasks = $conn->query("SELECT t.*, u.username FROM tasks t JOIN users u ON t.assigned_to = u.id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Tasks</title>
    <link rel="stylesheet" href="css/style.css?v=3">
</head>
<body>
    <h2>Manage Tasks</h2>
    <form method="POST">
        <input type="text" name="title" placeholder="Task Title" required>
        <textarea name="description" placeholder="Description"></textarea>
        <select name="assigned_to" required>
            <?php foreach ($users as $user): ?>
            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="deadline" required>
        <button type="submit">Assign Task</button>
    </form>
    <h3>Existing Tasks</h3>
    <table>
        <tr>
            <th>Title</th>
            <th>Assigned To</th>
            <th>Status</th>
            <th>Deadline</th>
        </tr>
        <?php foreach ($tasks as $task): ?>
        <tr>
            <td><?php echo htmlspecialchars($task['title']); ?></td>
            <td><?php echo htmlspecialchars($task['username']); ?></td>
            <td><?php echo htmlspecialchars($task['status']); ?></td>
            <td><?php echo htmlspecialchars($task['deadline']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <a href="dashboard.php" class="center-link">Back to Dashboard</a>
</body>
</html>