<?php
namespace App\Controllers;

use App\Services\MessageTrackingService;

class WebhookController
{
    public function handle(): void
    {
        // 1. Validate X-Telegram-Bot-Api-Secret-Token header
        $expectedSecret = (string)config('telegram.webhook_secret', '');
        $receivedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

        if (empty($expectedSecret) || !hash_equals($expectedSecret, $receivedSecret)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'error' => '403 Forbidden: Invalid or missing Telegram webhook secret token.'
            ]);
            exit;
        }

        // 2. Read incoming JSON payload
        $rawInput = file_get_contents('php://input');
        if (empty($rawInput)) {
            http_response_code(200);
            echo json_encode(['ok' => true, 'note' => 'Empty payload received']);
            exit;
        }

        $payload = json_decode($rawInput, true);
        if (!is_array($payload)) {
            $this->logError("Malformed JSON payload received: " . substr($rawInput, 0, 500));
            http_response_code(200);
            echo json_encode(['ok' => true, 'note' => 'Malformed payload ignored']);
            exit;
        }

        // 3. Process update via MessageTrackingService
        try {
            $service = new MessageTrackingService();
            $result = $service->processUpdate($payload);

            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'result' => $result]);
            exit;
        } catch (\Throwable $e) {
            $logMsg = sprintf("[%s] Webhook error: %s\nTrace: %s\nPayload: %s\n",
                utc_now(),
                $e->getMessage(),
                $e->getTraceAsString(),
                substr($rawInput, 0, 1000)
            );
            $this->logError($logMsg);

            // Respond HTTP 200 quickly so Telegram does not repeatedly hammer the webhook
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'error_logged' => true]);
            exit;
        }
    }

    private function logError(string $message): void
    {
        $logPath = dirname(__DIR__, 2) . '/storage/logs/webhook.log';
        @file_put_contents($logPath, date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL, FILE_APPEND);
    }
}
