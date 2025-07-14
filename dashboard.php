<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$tasks = $conn->prepare("SELECT * FROM tasks WHERE assigned_to = ?");
$tasks->execute([$user_id]);
$tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/style.css?v=3">
</head>
<body>
    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($role); ?>!</h2>
        <?php if ($role == 'admin'): ?>
            <a href="manage_users.php">Manage Users</a> | <a href="manage_tasks.php">Manage Tasks</a>
        <?php endif; ?>
        <h3>Your Tasks</h3>
        <table>
            <tr>
                <th>Title</th>
                <th>Description</th>
                <th>Status</th>
                <th>Deadline</th>
                <th>Action</th>
            </tr>
            <?php foreach ($tasks as $task): ?>
            <tr>
                <td><?php echo htmlspecialchars($task['title']); ?></td>
                <td><?php echo htmlspecialchars($task['description']); ?></td>
                <td><?php echo htmlspecialchars($task['status']); ?></td>
                <td><?php echo htmlspecialchars($task['deadline']); ?></td>
                <td><a href="update_task.php?id=<?php echo $task['id']; ?>">Update</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <a href="logout.php">Logout</a>
    </div>
</body>
</html>