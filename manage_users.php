<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

$edit_mode = false;
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user']) && !isset($_POST['edit_user_id'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $role]);
    } elseif (isset($_POST['edit_user_id'])) {
        $user_id = $_POST['edit_user_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $email, $password, $role, $user_id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $user_id]);
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    }
}

$users = $conn->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="css/style.css?v=3">
</head>
<body>
    <div class="container">
        <h2><?php echo $edit_mode ? 'Edit User' : 'Manage Users'; ?></h2>
        <form method="POST">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="edit_user_id" value="<?php echo $edit_user['id']; ?>">
            <?php endif; ?>
            <input type="text" name="username" placeholder="Username" value="<?php echo $edit_mode ? htmlspecialchars($edit_user['username']) : ''; ?>" required>
            <input type="email" name="email" placeholder="Email" value="<?php echo $edit_mode ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
            <input type="password" name="password" placeholder="Password (leave blank to keep unchanged)" <?php echo !$edit_mode ? 'required' : ''; ?>>
            <select name="role">
                <option value="user" <?php echo $edit_mode && $edit_user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                <option value="admin" <?php echo $edit_mode && $edit_user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
            <button type="submit" name="<?php echo $edit_mode ? 'edit_user' : 'add_user'; ?>">
                <?php echo $edit_mode ? 'Update User' : 'Add User'; ?>
            </button>
            <?php if ($edit_mode): ?>
                <a href="manage_users.php">Cancel Edit</a>
            <?php endif; ?>
        </form>
        <h3>Existing Users</h3>
        <table>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td>
                    <a href="manage_users.php?edit_id=<?php echo $user['id']; ?>">Edit</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" name="delete_user">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>