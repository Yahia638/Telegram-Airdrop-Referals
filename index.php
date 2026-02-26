<?php
// 1. Basic Error Handling & Autoload
error_reporting(E_ALL);
ini_set("display_errors", 1);
require "vendor/autoload.php";
use Telegram\Bot\Api;

// 2. Load Railway Variables (The Copy-Paste Trick)
$botToken = getenv('BOT_TOKEN'); 
$bot = new Api($botToken);

$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASS');
$port = getenv('DB_PORT');

// 3. Database Connection & Auto-Table Creator
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Creates the table automatically if it's missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id VARCHAR(100) UNIQUE,
        username VARCHAR(100),
        referrer_id VARCHAR(100),
        points INT DEFAULT 0,
        wallet_address VARCHAR(255),
        invited_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// 4. Automatic Webhook Handshake
// This finds your Railway URL and tells Telegram where to send messages.
$publicUrl = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
if (isset($_GET['setup_webhook'])) {
    $response = $bot->setWebhook(['url' => $publicUrl]);
    die("Webhook Status: " . ($response ? "SUCCESS ✅" : "FAILED ❌"));
}

// 5. Handling Bot Messages
$update = $bot->getWebhookUpdate();
$message = $update["message"] ?? null;
$callbackQuery = $update["callback_query"] ?? null;

// Bot Username for Referrals (CHANGE THIS PART)
$botUsername = "Your_Bot_Name_Bot"; 

if ($callbackQuery) {
    $chatId = $callbackQuery["message"]["chat"]["id"];
    $callbackData = $callbackQuery["data"];

    if ($callbackData === "balance") {
        $stmt = $pdo->prepare("SELECT points FROM users WHERE chat_id = :id");
        $stmt->execute(['id' => $chatId]);
        $u = $stmt->fetch();
        $pts = $u['points'] ?? 0;
        $bot->sendMessage(['chat_id' => $chatId, 'text' => "💰 Your Balance: $pts TURAN"]);
    }
    // ... add more callback handlers (claim, top, etc) as needed
}

if ($message) {
    $chatId = $message["chat"]["id"];
    $text = $message["text"] ?? "";
    $username = $message["from"]["username"] ?? "User";

    if (strpos($text, "/start") === 0) {
        // Handle Referrals
        $refId = explode(" ", $text)[1] ?? null;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = :id");
        $stmt->execute(['id' => $chatId]);
        
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO users (chat_id, referrer_id, username) VALUES (?, ?, ?)")
                ->execute([$chatId, $refId, $username]);
            
            if ($refId && $refId != $chatId) {
                $pdo->prepare("UPDATE users SET points = points + 10 WHERE chat_id = ?")->execute([$refId]);
            }
        }

        $keyboard = [
            "inline_keyboard" => [
                [["text" => "💰 Check Balance", "callback_data" => "balance"]],
                [["text" => "📋 Copy My Link", "callback_data" => "copy_link"]]
            ]
        ];

        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" => "🚀 Welcome $username! Start inviting friends to earn TURAN.",
            "reply_markup" => json_encode($keyboard)
        ]);
    }
}
