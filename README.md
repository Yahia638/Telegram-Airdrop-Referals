
# Telegram Airdrop Bot

This is a Telegram bot for managing LottoX users, claiming points, and referring others to earn rewards. The bot integrates with a MySQL database to manage users' data, and it allows users to interact with the bot, claim points, and manage their wallet addresses.

## Features

- **User Registration**: New users can sign up with a referral link and earn points when their referred friends join.
- **Referral System**: Users can invite others using their unique referral link.
- **Claim Points**: Users can claim their points once they provide their wallet address.
- **Top Users**: Display a leaderboard of the top users based on their points.
- **Wallet Address Management**: Users can add their Polygon wallet address for point claims.

## Prerequisites

- PHP 7.4 or higher
- Composer
- MySQL database

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/ramykatour/Telegram-Airdrop.git
   cd Telegram-Airdrop
   ```

2. Install the dependencies using Composer:

   ```bash
   composer install
   ```

3. Set up your `.env` or configuration file for the database and bot token:

   - Replace `BOT_API_HERE` with your Telegram bot API token.
   - Configure your database connection (`$host`, `$dbname`, `$user`, `$password`).

4. Set the webhook for your bot (replace `https://lottox.site/index.php` with your actual endpoint):

   ```php
   $bot->setWebhook(["url" => "https://lottox.site/index.php"]);
   ```

5. Make sure your server is accessible and has SSL enabled for the webhook.

## Usage

### Commands
- `/start`: Start the bot and receive your referral link.
- `/generate`: Generate your referral link.
- `/top`: View the top 10 users based on points.
- Provide your Polygon wallet address to claim points.

### Webhook

- The bot listens for webhook updates and responds to user messages and button clicks.
- When a user presses the "Claim Points" button, they must add a wallet address to receive points.
- The leaderboard displays the top users with the most points.

## Database Structure

### Users Table

| Field         | Type    | Description                       |
|---------------|---------|-----------------------------------|
| `chat_id`     | INT     | Telegram user ID                  |
| `referrer_id` | INT     | Referrer user ID                  |
| `username`    | VARCHAR | Telegram username                 |
| `wallet_address` | VARCHAR | User's Polygon wallet address    |
| `points`      | INT     | Points accumulated by the user    |
| `invited_at`  | DATETIME| Time when the user was invited    |

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
