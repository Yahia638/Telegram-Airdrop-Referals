<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$dbname = "database_name";
$user = "database_user";
$password = "database_password";

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

if (isset($_POST['send_message']) && !empty($_POST['message'])) {
    $message = $_POST['message'];
    $stmt = $pdo->query("SELECT chat_id FROM users WHERE chat_id IS NOT NULL");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bot_token = 'Your_BOT_API'; // add your bot api here
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

$limit = 750;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_POST['search']) ? $_POST['search'] : '';
$search_query = $search ? "WHERE username LIKE :search OR wallet_address LIKE :search" : '';

try {
    $stmt = $pdo->prepare("SELECT id, username, points, wallet_address FROM users $search_query LIMIT :limit OFFSET :offset");
    if ($search) {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_users_sql = "SELECT COUNT(id) AS total_users FROM users $search_query";
    $total_users_stmt = $pdo->prepare($total_users_sql);
    if ($search) {
        $total_users_stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    $total_users_stmt->execute();
    $total_users = $total_users_stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    $total_pages = ceil($total_users / $limit);

    $total_points_sql = "SELECT SUM(points) AS total_points FROM users";
    $total_points_stmt = $pdo->query($total_points_sql);
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
		    <style>
body {
    font-family: 'Arial', sans-serif;
    background-color: #eef2f7;
    margin: 0;
    padding: 0;
}

.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    background-color: #ffffff;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}

h2, h3 {
    color: #333;
    text-align: center;
    margin-bottom: 20px;
}

form {
    margin-bottom: 20px;
    text-align: center;
}

form textarea {
    width: 90%;
    max-width: 800px;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
    resize: none;
}

form button {
    background-color: #007bff;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    margin-top: 10px;
}

form button:hover {
    background-color: #0056b3;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

table, th, td {
    border: 1px solid #ddd;
}

th, td {
    padding: 15px;
    text-align: left;
    font-size: 14px;
}

th {
    background-color: #007bff;
    color: #fff;
    text-transform: uppercase;
    cursor: pointer;
}

td {
    background-color: #f9f9f9;
}

td:nth-child(odd) {
    background-color: #f4f4f4;
}

a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

td form {
    display: flex;
    gap: 5px;
    align-items: center;
    justify-content: center;
}

td form input {
    width: 60px;
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
}

td form button {
    background-color: #28a745;
    color: #fff;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
}

td form button:hover {
    background-color: #218838;
}

td form button:nth-child(3) {
    background-color: #dc3545;
}

td form button:nth-child(3):hover {
    background-color: #c82333;
}

.table-container {
    overflow-x: auto;
    max-height: 500px;
    margin-top: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #ffffff;
}

.table-container table {
    min-width: 1000px;
}

.table-container thead th {
    position: sticky;
    top: 0;
    z-index: 2;
}
    </style>
</head>
<body>

    <h2>User Management</h2>
    <h3>Total Points: <?php echo $total_points; ?></h3>
    <h3>Total Users: <?php echo $total_users; ?></h3>

    <h2>Send Message to All Users</h2>
    <form method="POST" action="admin_panel.php">
        <textarea name="message" rows="4" cols="50" placeholder="Type your message here..." required></textarea>
        <br>
        <button type="submit" name="send_message">Send Message</button>
    </form>

    <h2>Search Users</h2>
    <form method="POST" action="admin_panel.php">
        <input type="text" name="search" placeholder="Search by username or wallet address" value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th onclick="sortTable(0)">ID</th>
                    <th onclick="sortTable(1)">Username</th>
                    <th onclick="sortTable(2)">Points</th>
                    <th>Wallet Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
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
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>

<script>
    function sortTable(columnIndex) {
        const table = document.querySelector("table");
        const rows = Array.from(table.rows).slice(1);
        const isAscending = table.getAttribute("data-sort-order") === "asc";
        
        rows.sort((rowA, rowB) => {
            const cellA = rowA.cells[columnIndex].innerText.trim();
            const cellB = rowB.cells[columnIndex].innerText.trim();

            if (!isNaN(cellA) && !isNaN(cellB)) {
                return isAscending ? cellA - cellB : cellB - cellA;
            }

            return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        });

        table.setAttribute("data-sort-order", isAscending ? "desc" : "asc");

        rows.forEach(row => table.tBodies[0].appendChild(row));
    }
</script>

</body>
</html>
