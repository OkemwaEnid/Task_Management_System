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

if ($role == 'admin') {
    $tasks = $conn->query("SELECT t.*, u.username FROM tasks t JOIN users u ON t.assigned_to = u.id ORDER BY t.deadline");
    $tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);
} else {
    $tasks = $conn->prepare("SELECT * FROM tasks WHERE assigned_to = ? ORDER BY deadline");
    $tasks->execute([$user_id]);
    $tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);
}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['error']);
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
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>
        <?php if ($role == 'admin'): ?>
            <a href="manage_users.php">Manage Users</a> | <a href="manage_tasks.php">Manage Tasks</a>
            <h3>All Tasks</h3>
        <?php else: ?>
            <h3>Your Tasks</h3>
        <?php endif; ?>
        <table>
            <tr>
                <th>Title</th>
                <th>Description</th>
                <?php if ($role == 'admin'): ?>
                    <th>Assigned To</th>
                <?php endif; ?>
                <th>Status</th>
                <th>Deadline</th>
                <th>Action</th>
            </tr>
            <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="<?php echo $role == 'admin' ? 6 : 5; ?>">No tasks available.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                    <?php if ($role == 'admin'): ?>
                        <td><?php echo htmlspecialchars($task['username']); ?></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($task['status']); ?></td>
                    <td><?php echo htmlspecialchars($task['deadline']); ?></td>
                    <td>
                        <?php if ($role == 'admin'): ?>
                            <a href="edit_task.php?id=<?php echo $task['id']; ?>">Edit</a> |
                            <a href="delete_task.php?id=<?php echo $task['id']; ?>" onclick="return confirm('Are you sure you want to delete this task?')">Delete</a>
                        <?php else: ?>
                            <a href="update_task.php?id=<?php echo $task['id']; ?>">Update</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
        <a href="logout.php">Logout</a>
    </div>
</body>
</html>