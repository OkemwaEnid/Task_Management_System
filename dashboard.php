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

$notifications = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
$notifications->execute([$user_id]);
$notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: dashboard.php");
    exit;
}

// Get tasks
if ($role === 'admin') {
    $stmt = $conn->query("SELECT t.*, u.username FROM tasks t JOIN users u ON t.assigned_to = u.id ORDER BY t.deadline");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE assigned_to = ? ORDER BY deadline");
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Task Management System</title>
    <link rel="stylesheet" href="css/style.css?v=2">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>Task Manager</h2>
            <ul>
                <?php if ($role === 'admin'): ?>
                    <li><a href="dashboard.php">All Tasks</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="manage_tasks.php">Manage Tasks</a></li>
                <?php else: ?>
                    <li><a href="dashboard.php">My Tasks</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1>Welcome, <?php echo htmlspecialchars($role); ?>!</h1>

            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
                <section class="notifications">
                    <h3>Notifications</h3>
                    <ul>
                        <?php foreach ($notifications as $note): ?>
                            <li><?php echo htmlspecialchars($note['message']); ?> (<?php echo $note['created_at']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="POST">
                        <button type="submit" name="mark_read">Mark All as Read</button>
                    </form>
                </section>
            <?php endif; ?>

            <!-- Tasks -->
            <section class="tasks-section">
                <h3><?php echo $role === 'admin' ? 'All Tasks' : 'Your Tasks'; ?></h3>
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <?php if ($role === 'admin'): ?>
                                <th>Assigned To</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="<?php echo $role === 'admin' ? 6 : 5; ?>">No tasks available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                                    <?php if ($role === 'admin'): ?>
                                        <td><?php echo htmlspecialchars($task['username']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($task['status']); ?></td>
                                    <td><?php echo htmlspecialchars($task['deadline']); ?></td>
                                    <td>
                                        <?php if ($role === 'admin'): ?>
                                            <a href="edit_task.php?id=<?php echo $task['id']; ?>">Edit</a> |
                                            <a href="delete_task.php?id=<?php echo $task['id']; ?>" onclick="return confirm('Delete this task?')">Delete</a>
                                        <?php else: ?>
                                            <a href="update_task.php?id=<?php echo $task['id']; ?>">Update</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>
</html>
