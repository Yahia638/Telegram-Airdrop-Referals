<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$dbname = "DataBase_Name";
$user = "Database_user";
$password = "Database_password";

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
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        echo "User deleted successfully!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
if (isset($_POST['send_message']) && !empty($_POST['message'])) {
    $message = $_POST['message'];
    $stmt = $pdo->query("SELECT chat_id FROM users WHERE chat_id IS NOT NULL");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bot_token = 'Your_bot_API';
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    foreach ($users as $user) {
        $chat_id = $user['chat_id'];
        $data = [
            'chat_id' => $chat_id,
            'text' => $message
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        }
        curl_close($ch);
    }

    echo "Message sent to all users!";
}

$limit = 750;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_POST['search']) ? $_POST['search'] : '';
$search_query = $search ? "WHERE u.username LIKE :search OR u.wallet_address LIKE :search" : '';
$sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'u.id';
$sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] == 'desc' ? 'desc' : 'asc';

try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.username, 
            u.points, 
            u.wallet_address, 
            u.referrer_id, 
            r.username AS referrer_username,
            (SELECT COUNT(*) FROM users WHERE referrer_id = u.id) AS referral_count,
            (SELECT SUM(10) FROM users WHERE referrer_id = u.id) AS earned_points 
        FROM users u 
        LEFT JOIN users r ON u.referrer_id = r.id
        $search_query 
        ORDER BY $sort_column $sort_order
        LIMIT :limit OFFSET :offset
    ");
    if ($search) {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_users_stmt = $pdo->prepare("SELECT COUNT(u.id) AS total_users FROM users u $search_query");
    if ($search) {
        $total_users_stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    $total_users_stmt->execute();
    $total_users = $total_users_stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

    $total_pages = ceil($total_users / $limit);

    // Total points
    $total_points_stmt = $pdo->query("SELECT SUM(points) AS total_points FROM users");
    $total_points = $total_points_stmt->fetch(PDO::FETCH_ASSOC)['total_points'];
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
	<link rel="stylesheet" href="style.css">
</head>
<body>

<h2>User Management</h2>
<h3>Total Points: <?php echo $total_points; ?></h3>
<h3>Total Users: <?php echo $total_users; ?></h3>

<h2>Send Message to All Users</h2>
<form method="POST" action="">
    <textarea name="message" rows="4" cols="50" placeholder="Type your message here..." required></textarea>
    <br>
    <button type="submit" name="send_message">Send Message</button>
</form>

<h2>Search Users</h2>
<form method="POST" action="">
    <input type="text" name="search" placeholder="Search by username or wallet address" value="<?php echo htmlspecialchars($search ?? ''); ?>">
    <button type="submit">Search</button>
</form>

<div class="table-container">
    <table border="1">
        <thead>
            <tr>
                <th><a href="?sort_column=u.id&sort_order=<?php echo $sort_order == 'asc' ? 'desc' : 'asc'; ?>">ID</a></th>
                <th><a href="?sort_column=u.username&sort_order=<?php echo $sort_order == 'asc' ? 'desc' : 'asc'; ?>">Username</a></th>
                <th><a href="?sort_column=u.points&sort_order=<?php echo $sort_order == 'asc' ? 'desc' : 'asc'; ?>">Points</a></th>
                <th>Wallet Address</th>
                <th>Referrer</th>
                <th>Referral Count</th>
                <th>Earned Points</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                    <td><?php echo $user['points']; ?></td>
                    <td><?php echo $user['wallet_address'] ? htmlspecialchars($user['wallet_address']) : 'Not provided'; ?></td>
                    <td><?php echo $user['referrer_username'] ? htmlspecialchars($user['referrer_username']) : 'No Referrer'; ?></td>
                    <td><?php echo $user['referral_count']; ?></td>
                    <td><?php echo $user['earned_points'] ? $user['earned_points'] : 0; ?></td>
                    <td>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <input type="number" name="points" min="1" required>
                            <button type="submit" name="action" value="add_points">Add Points</button>
                        </form>
                        |
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <input type="number" name="points" min="1" required>
                            <button type="submit" name="action" value="remove_points">Remove Points</button>
                        </form>
                        |
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="delete_user" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
</div>

</body>
</html>
