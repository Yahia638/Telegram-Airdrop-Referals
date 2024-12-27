<?php
session_start();

$host = "localhost";
$dbname = "lottox";
$user = "lottox";
$password = "clPENVSp5pr2b5j";

$dsn = "mysql:host=$host;dbname=$dbname";
try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}
if (!isset($_SESSION['admin_id'])) {
    die("Access denied. You must be logged in as an admin.");
}

if (isset($_POST['action']) && isset($_POST['id']) && isset($_POST['points'])) {
    $user_id = $_POST['id'];
    $action = $_POST['action'];
    $points = (int)$_POST['points']; 

    if ($action == 'add_points' && $points > 0) {
        $stmt = $pdo->prepare("UPDATE users SET points = points + :points WHERE id = :id");
        $stmt->execute([':points' => $points, ':id' => $user_id]);
        echo "Points added successfully!";
    } elseif ($action == 'remove_points' && $points > 0) {
        $stmt = $pdo->prepare("UPDATE users SET points = points - :points WHERE id = :id");
        $stmt->execute([':points' => $points, ':id' => $user_id]);
        echo "Points removed successfully!";
    } else {
        echo "Invalid points value.";
    }
}

$sql = "SELECT id, username, points, wallet_address FROM users";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
        }
        h2 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #000000;
        }
        a {
            text-decoration: none;
            color: #007bff;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <h2>User Management</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Points</th>
            <th>Wallet Address</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo $user['username']; ?></td>
                <td><?php echo $user['points']; ?></td>
                <td><?php echo $user['wallet_address'] ? $user['wallet_address'] : 'Not provided'; ?></td>
                <td>
                    <form method="POST" action="admin_panel.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <input type="number" name="points" min="1" required>
                        <button type="submit" name="action" value="add_points">Add Points</button>
                    </form>
                    |
                    <form method="POST" action="admin_panel.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <input type="number" name="points" min="1" required>
                        <button type="submit" name="action" value="remove_points">Remove Points</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

</body>
</html>
