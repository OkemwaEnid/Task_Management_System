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
$error = '';
$success = '';

if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_user) {
        $error = "User not found.";
        $edit_mode = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user']) && !isset($_POST['edit_user_id'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];

        // Check for existing username
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check_stmt->execute([$username]);
        if ($check_stmt->fetchColumn() > 0) {
            $error = "Username '$username' already exists. Please choose a different username.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $password, $role]);
                $success = "User added successfully.";
            } catch (PDOException $e) {
                $error = "Error adding user: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['edit_user_id'])) {
        $user_id = $_POST['edit_user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];

        // Check for username conflict (excluding current user)
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $check_stmt->execute([$username, $user_id]);
        if ($check_stmt->fetchColumn() > 0) {
            $error = "Username '$username' is already taken by another user.";
        } else {
            try {
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $password, $role, $user_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role, $user_id]);
                }
                $success = "User updated successfully.";
            } catch (PDOException $e) {
                $error = "Error updating user: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        try {
            $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            $success = "User deleted successfully.";
        } catch (PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
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
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
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
                <a href="manage_users.php" class="center-link">Cancel Edit</a>
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
                <td class="action-links">
                    <a href="manage_users.php?edit_id=<?php echo $user['id']; ?>">Edit</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" name="delete_user" class="delete">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <a href="dashboard.php" class="center-link">Back to Dashboard</a>
    </div>
</body>
</html>