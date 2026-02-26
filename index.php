<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require "vendor/autoload.php";
use Telegram\Bot\Api;

// --- RAILWAY CONFIGURATION ---
$botToken = getenv('BOT_TOKEN'); 
$bot = new Api($botToken);

$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASS');
$port = getenv('DB_PORT');

try {
    // Railway uses a specific port, so we add it to the DSN
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // AUTO-CREATE TABLE: This solves your "No Table" problem!
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
    die("Database Error: " . $e->getMessage());
}

// --- WEBHOOK SETUP ---
// This automatically finds your Railway URL
$publicUrl = "https://" . $_SERVER['HTTP_HOST'] . "/index.php";
try {
    $bot->setWebhook(["url" => $publicUrl]);
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
}

$update = $bot->getWebhookUpdate();
$message = $update["message"] ?? null;
$callbackQuery = $update["callback_query"] ?? null;

if ($callbackQuery) {
    $chatId = $callbackQuery["message"]["chat"]["id"];
    $callbackData = $callbackQuery["data"];

    if ($callbackData === "claim") {
        $stmt = $pdo->prepare("SELECT wallet_address FROM users WHERE chat_id = :chat_id");
        $stmt->execute(["chat_id" => $chatId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData || !$userData["wallet_address"]) {
            $keyboard = ["inline_keyboard" => [[["text" => "Add Wallet Address", "callback_data" => "add_wallet"]]]];
            $bot->sendMessage([
                "chat_id" => $chatId,
                "text" => "⚠️ You need to add your Polygon wallet address to claim your points.",
                "reply_markup" => json_encode($keyboard),
            ]);
        } else {
            $bot->sendMessage([
                "chat_id" => $chatId,
                "text" => "We will send your coins very soon. Make sure you have added your Polygon address!",
            ]);
        }
    } elseif ($callbackData === "add_wallet") {
        $bot->sendMessage(["chat_id" => $chatId, "text" => "Please send your Polygon wallet address (starting with 0x)."]);
    } elseif ($callbackData === "balance") {
        $stmt = $pdo->prepare("SELECT points FROM users WHERE chat_id = :chat_id");
        $stmt->execute(["chat_id" => $chatId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $balance = $userData['points'] ?? 0;
        $bot->sendMessage(["chat_id" => $chatId, "text" => "💰 Your current balance is: $balance TURAN."]);
    } elseif ($callbackData === "top") {
        $stmt = $pdo->query("SELECT chat_id, points, username FROM users ORDER BY points DESC LIMIT 10");
        $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response = "🏆 <b>Top 10 Users:</b>\n";
        foreach ($topUsers as $index => $u) {
            $name = $u["username"] ? "@" . $u["username"] : "ID: " . $u["chat_id"];
            $response .= ($index + 1) . ". <b>" . $name . "</b> - <b>TURAN:</b> " . $u["points"] . "\n";
        }
        $bot->sendMessage(["chat_id" => $chatId, "text" => $response, "parse_mode" => "HTML"]);
    } elseif ($callbackData === "copy_referral_link") {
        $botName = "YOUR_BOT_USERNAME"; // Replace with your bot's username without @
        $referralLink = "https://t.me/$botName?start=$chatId";
        $bot->sendMessage(["chat_id" => $chatId, "text" => "📋 Your link:\n$referralLink", "parse_mode" => "HTML"]);
    }
}

if ($message) {
    $chatId = $message["chat"]["id"];
    $text = $message["text"] ?? "";
    $username = $message["from"]["username"] ?? null;

    if (strpos($text, "/start") === 0) {
        $referrerId = explode(" ", $text)[1] ?? null;
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = :chat_id");
        $stmt->execute(["chat_id" => $chatId]);
        $userExists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userExists) {
            $stmt = $pdo->prepare("INSERT INTO users (chat_id, referrer_id, username) VALUES (:chat_id, :ref, :uname)");
            $stmt->execute(["chat_id" => $chatId, "ref" => $referrerId, "uname" => $username]);
            
            if ($referrerId && $referrerId != $chatId) {
                $pdo->prepare("UPDATE users SET points = points + 10 WHERE chat_id = :ref")->execute(["ref" => $referrerId]);
            }
        }

        $keyboard = [
            "inline_keyboard" => [
                [["text" => "🎉 Claim TURAN", "callback_data" => "claim"], ["text" => "🏆 Leaderboard", "callback_data" => "top"]],
                [["text" => "💰 Balance", "callback_data" => "balance"], ["text" => "📋 Referral Link", "callback_data" => "copy_referral_link"]]
            ]
        ];

        $bot->sendMessage([
            "chat_id" => $chatId, 
            "text" => "🎉 <b>Welcome to LottoX Bot!</b>\nInvite friends to earn 10 TURAN!", 
            "parse_mode" => "HTML", 
            "reply_markup" => json_encode($keyboard)
        ]);
    } elseif (preg_match('/^0x[a-fA-F0-9]{40}$/', $text)) {
        $stmt = $pdo->prepare("UPDATE users SET wallet_address = :wallet WHERE chat_id = :chat_id");
        $stmt->execute(["wallet" => $text, "chat_id" => $chatId]);
        $bot->sendMessage(["chat_id" => $chatId, "text" => "✅ Wallet saved!"]);
    }
}
?>
