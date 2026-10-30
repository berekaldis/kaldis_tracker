<?php
namespace App\Services;

class TelegramService
{
    private string $botToken;
    private string $apiUrl;

    public function __construct(?string $token = null)
    {
        $this->botToken = $token ?: (string)config('telegram.bot_token', '');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
    }

    /**
     * Send HTTP request to Telegram Bot API.
     */
    public function request(string $method, array $params = []): array
    {
        if (empty($this->botToken)) {
            return [
                'ok' => false,
                'description' => 'Telegram bot token is not configured in app/config/config.php'
            ];
        }

        $url = $this->apiUrl . $method;
        $ch = curl_init();

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        // Automatically use bundled Mozilla CA certificate if available
        $caBundle = dirname(__DIR__, 2) . '/storage/cacert.pem';
        if (file_exists($caBundle)) {
            $curlOptions[CURLOPT_CAINFO] = $caBundle;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        $curlErrNo = curl_errno($ch);

        // Resilient fallback for Windows environments with SSL inspection or CA issues
        if ($response === false && in_array($curlErrNo, [CURLE_SSL_CACERT, CURLE_PEER_FAILED_VERIFICATION, 60, 77])) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
        }
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'description' => 'cURL error: ' . $curlErr,
                'http_code' => $httpCode,
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'description' => 'Invalid JSON received from Telegram: ' . substr($response, 0, 200),
                'http_code' => $httpCode,
            ];
        }

        return $decoded;
    }

    /**
     * Get Bot details (identity & permissions).
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Get Chat information by Telegram Chat ID.
     */
    public function getChat(int|string $chatId): array
    {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    /**
     * Get Chat Member information.
     */
    public function getChatMember(int|string $chatId, int|string $userId): array
    {
        return $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }

    /**
     * Get Chat Member count.
     */
    public function getChatMemberCount(int|string $chatId): array
    {
        return $this->request('getChatMemberCount', ['chat_id' => $chatId]);
    }

    /**
     * Get Chat Administrators.
     */
    public function getChatAdministrators(int|string $chatId): array
    {
        return $this->request('getChatAdministrators', ['chat_id' => $chatId]);
    }

    /**
     * Set Webhook with explicit allowed_updates and secret_token.
     */
    public function setWebhook(string $url, string $secretToken, array $allowedUpdates = []): array
    {
        if (empty($allowedUpdates)) {
            $allowedUpdates = [
                'message',
                'edited_message',
                'chat_member',
                'my_chat_member',
                'message_reaction',
                'callback_query'
            ];
        }

        return $this->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => $allowedUpdates,
            'drop_pending_updates' => false
        ]);
    }

    /**
     * Get Webhook status and health info.
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Delete Webhook.
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->request('deleteWebhook', ['drop_pending_updates' => $dropPendingUpdates]);
    }

    /**
     * Send text message.
     */
    public function sendMessage(int|string $chatId, string $text, ?int $replyToMessageId = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyToMessageId !== null) {
            $params['reply_to_message_id'] = $replyToMessageId;
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Send message with inline keyboard buttons.
     */
    public function sendMessageWithKeyboard(int|string $chatId, string $text, array $inlineKeyboard, ?int $replyToMessageId = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => $inlineKeyboard,
            ],
        ];

        if ($replyToMessageId !== null) {
            $params['reply_to_message_id'] = $replyToMessageId;
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Answer an incoming callback query from an inline button tap.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert,
        ];

        if ($text !== null) {
            $params['text'] = $text;
        }

        return $this->request('answerCallbackQuery', $params);
    }
}
