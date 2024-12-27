<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require "vendor/autoload.php";
use Telegram\Bot\Api;

$botToken = "BOT_API_HERE"; //add your telegram API
$bot = new Api($botToken);

$host = "localhost";
$dbname = "lottox";
$user = "lottox";
$password = "clPENVSp5pr2b5j";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $bot->setWebhook(["url" => "https://turanswap.com/index.php"]); //change turanswap.com to your website name 
} catch (Exception $e) {
    error_log("Webhook setup error: " . $e->getMessage());
}

$update = $bot->getWebhookUpdate();
$message = $update["message"] ?? null;
$callbackQuery = $update["callback_query"] ?? null;

if ($callbackQuery) {
    $chatId = $callbackQuery["message"]["chat"]["id"];
    $callbackData = $callbackQuery["data"];

    if ($callbackData === "claim") {
        $stmt = $pdo->prepare(
            "SELECT wallet_address FROM users WHERE chat_id = :chat_id"
        );
        $stmt->execute(["chat_id" => $chatId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user["wallet_address"]) {
            $keyboard = [
                "inline_keyboard" => [
                    [
                        [
                            "text" => "Add Wallet Address",
                            "callback_data" => "add_wallet",
                        ],
                    ],
                ],
            ];
            $bot->sendMessage([
                "chat_id" => $chatId,
                "text" =>
                    "?? You need to add your Polygon wallet address to claim your points.",
                "reply_markup" => json_encode($keyboard),
            ]);
        } else {
            $bot->sendMessage([
                "chat_id" => $chatId,
                "text" =>
                    "We will send your coins very soon. Make sure you have added your Polygon address!",
            ]);
        }
    } elseif ($callbackData === "add_wallet") {
        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" => "Please send your Polygon wallet address.",
        ]);
    } elseif ($callbackData === "top") {
        $stmt = $pdo->query(
            "SELECT chat_id, points, username FROM users ORDER BY points DESC LIMIT 10"
        );
        $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = "🏆 <b>Top 10 Users:</b>\n";
        foreach ($topUsers as $index => $user) {
            $username = $user["username"]
                ? "@" . $user["username"]
                : "User ID: " . $user["chat_id"];
            $response .=
                $index +
                1 .
                ". <b>" .
                $username .
                "</b> - <b>TURAN:</b> " .
                $user["points"] .
                "\n";
        }
        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" => $response,
            "parse_mode" => "HTML",
        ]);
    }
}

if ($message) {
    $chatId = $message["chat"]["id"];
    $text = $message["text"];
    if (strpos($text, "/start") === 0) {
        $referrerId = null;
        if (strpos($text, " ") !== false) {
            $parts = explode(" ", $text);
            $referrerId = intval($parts[1]);
        }
        $username = $message["from"]["username"] ?? null;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = :chat_id");
        $stmt->execute(["chat_id" => $chatId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $stmt = $pdo->prepare(
                "INSERT INTO users (chat_id, referrer_id, username) VALUES (:chat_id, :referrer_id, :username)"
            );
            $stmt->execute([
                "chat_id" => $chatId,
                "referrer_id" => $referrerId,
                "username" => $username,
            ]);
            if ($referrerId) {
                $stmt = $pdo->prepare(
                    "SELECT invited_at FROM users WHERE chat_id = :chat_id"
                );
                $stmt->execute(["chat_id" => $chatId]);
                $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existingUser["invited_at"]) {
                    $stmt = $pdo->prepare(
                        "UPDATE users SET points = points + 10 WHERE chat_id = :referrerId"
                    );
                    $stmt->execute(["referrerId" => $referrerId]);
                    $stmt = $pdo->prepare(
                        "UPDATE users SET invited_at = NOW() WHERE chat_id = :chat_id"
                    );
                    $stmt->execute(["chat_id" => $chatId]);
                }
            }
        }

        $referralLink = "https://t.me/lottixbot?start=$chatId";  //change lottixbot to your bot name  
        $keyboard = [
    "inline_keyboard" => [
        [
            ["text" => "🎉 Claim TURAN", "callback_data" => "claim"],
            ["text" => "🏆 Leaderboard", "callback_data" => "top"],
        ],
        [
            ["text" => "💰 Check Balance", "callback_data" => "balance"],
            ["text" => "🔄 Change Wallet", "callback_data" => "change_wallet"],
        ],
        [
            ["text" => "📱 Join Channel", "url" => "https://t.me/turannetwork"],
            ["text" => "🐦 Join Twitter", "url" => "https://x.com/turan_network"],
        ],
        [
            ["text" => "TURAN | SWAP", "url" => "https://turanswap.com"], 
        ],
        [
            ["text" => "📋 Copy Referral Link", "callback_data" => "copy_referral_link"], 
        ],
    ],
];

        $photoPath = "loogo.jpg";

        $paramsPhoto = [
            "chat_id" => $chatId,
            "photo" => new CURLFile($photoPath),
        ];

        $ch = curl_init();
        curl_setopt(
            $ch,
            CURLOPT_URL,
            "https://api.telegram.org/bot" . $botToken . "/sendPhoto"
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsPhoto);
        $responsePhoto = curl_exec($ch);
        curl_close($ch);

$messageText = "
🎉 <b>Welcome to LottoX Bot!</b> 🎉
You can invite your friends and earn 10 TURAN when they sign up and participate!
💰 <b>TURAN</b> is a special token used within the LottoX platform, and you can use it for various rewards and actions.
🔄 We are also working on a <b>Polygon (MATIC) swap</b>, where you can easily exchange your TURAN tokens for Polygon tokens or vice versa.
🚀 <b>Invite as many friends as you can</b> and maximize your TURAN earnings!
<b>Start participating and enjoy the benefits!</b>
";

        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" => $messageText,
            "parse_mode" => "HTML",
            "reply_markup" => json_encode($keyboard),
        ]);
    }

    elseif ($text === "/generate") {
        $referralLink = "https://t.me/lottixbot?start=$chatId"; //change lottixbot to your bot name 
        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" => "Your referral link: $referralLink",
        ]);
    }

    elseif ($text === "/top") {
        $stmt = $pdo->query(
            "SELECT chat_id, points, username FROM users ORDER BY points DESC LIMIT 10"
        );
        $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = "🏆 <b>Top 10 Users:</b>\n";
        foreach ($topUsers as $index => $user) {
            $username = $user["username"]
                ? "@" . $user["username"]
                : "User ID: " . $user["chat_id"];
            $response .=
                $index +
                1 .
                ". <b>" .
                $username .
                "</b> - <b>TURAN:</b> " .
                $user["points"] .
                "\n";
        }
        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" => $response,
            "parse_mode" => "HTML",
        ]);
    }

    elseif (preg_match('/^0x[a-fA-F0-9]{40}$/', $text)) {
        $stmt = $pdo->prepare(
            "UPDATE users SET wallet_address = :wallet_address WHERE chat_id = :chat_id"
        );
        $stmt->execute(["wallet_address" => $text, "chat_id" => $chatId]);

        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" => "🎉 Your wallet address has been saved successfully!",
        ]);
    }
    else {
        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" =>
                "Sorry, I didn't understand that command. Try /start, /generate, /top, or provide your wallet address.",
        ]);
    }
} elseif ($callbackData === "balance") {
    $stmt = $pdo->prepare("SELECT points FROM users WHERE chat_id = :chat_id");
    $stmt->execute(["chat_id" => $chatId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $balance = $user["points"];
        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" => "💰 Your current balance is: $balance TURAN.",
        ]);
    } else {
        $bot->sendMessage([
            "chat_id" => $chatId,
            "text" =>
                "❌ Could not fetch your balance. Please register first using /start.",
        ]);
    }
} elseif ($callbackData === "change_wallet") {
    $bot->sendMessage([
        "chat_id" => $chatId,
        "text" => "🔄 Please send your new Polygon wallet address.",
    ]);
} elseif (preg_match('/^0x[a-fA-F0-9]{40}$/', $text)) {
    $stmt = $pdo->prepare(
        "UPDATE users SET wallet_address = :wallet_address WHERE chat_id = :chat_id"
    );
    $stmt->execute(["wallet_address" => $text, "chat_id" => $chatId]);

    $bot->sendMessage([
        "chat_id" => $chatId,
        "text" => "✅ Your wallet address has been updated successfully!",
    ]);
}
if ($callbackData === "copy_referral_link") {
    $referralLink = "https://t.me/lottixbot?start=$chatId"; //change lottixbot to your bot name 
    $bot->sendMessage([
        "chat_id" => $chatId,
        "text" => "📋 Here's your referral link:\n\n<i>$referralLink</i>\n\nYou can copy and share it with your friends!",
        "parse_mode" => "HTML",
    ]);
}
?>
