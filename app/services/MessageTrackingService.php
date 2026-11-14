<?php
namespace App\Services;

class MessageTrackingService
{
    /**
     * Process an incoming webhook update payload.
     * Returns array with status and log info.
     */
    public function processUpdate(array $payload): array
    {
        $updateId = $payload['update_id'] ?? null;
        if ($updateId === null) {
            return ['status' => 'ignored', 'reason' => 'missing_update_id'];
        }

        // 1. Idempotency check: Record update in telegram_updates
        $updateType = $this->determineUpdateType($payload);
        $chatId = $this->extractChatId($payload, $updateType);

        try {
            $sql = "INSERT INTO telegram_updates (update_id, update_type, chat_id, payload, created_at)
                    VALUES (:update_id, :update_type, :chat_id, :payload, :created_at)";
            Database::execute($sql, [
                ':update_id' => $updateId,
                ':update_type' => $updateType,
                ':chat_id' => $chatId,
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':created_at' => utc_now(),
            ]);
        } catch (\PDOException $e) {
            // Duplicate update_id: already received and handled
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                return ['status' => 'duplicate', 'update_id' => $updateId];
            }
            error_log("Failed to insert telegram update: " . $e->getMessage());
        }

        // 2. Route by update type
        try {
            $result = match ($updateType) {
                'message' => $this->handleMessage($payload['message']),
                'edited_message' => $this->handleEditedMessage($payload['edited_message']),
                'my_chat_member' => $this->handleMyChatMember($payload['my_chat_member']),
                'chat_member' => $this->handleChatMember($payload['chat_member']),
                'callback_query' => $this->handleCallbackQuery($payload['callback_query']),
                'message_reaction' => $this->handleMessageReaction($payload['message_reaction']),
                default => ['status' => 'ignored', 'type' => $updateType],
            };

            // Mark update as processed
            try {
                Database::execute(
                    "UPDATE telegram_updates SET processed_at = :now WHERE update_id = :uid",
                    [':now' => utc_now(), ':uid' => $updateId]
                );
            } catch (\Throwable) {}

            // Check for expired deadlines and deliver single-message summary reports to authorized senders
            try {
                $this->processExpiredDeadlinesAndSendSummaries();
            } catch (\Throwable $e) {
                error_log("Expired deadline summary processing error: " . $e->getMessage());
            }

            // Periodic auto-delete retention purge
            try {
                self::autoDeleteOldTrackedMessagesThrottled();
            } catch (\Throwable $e) {
                error_log("Auto delete purge error: " . $e->getMessage());
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("Error processing update {$updateId}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            try {
                Database::execute(
                    "UPDATE telegram_updates SET error_message = :err WHERE update_id = :uid",
                    [':err' => $e->getMessage(), ':uid' => $updateId]
                );
            } catch (\Throwable) {}
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle incoming standard message.
     */
    private function handleMessage(array $message): array
    {
        $chat = $message['chat'] ?? [];
        $chatId = $chat['id'] ?? null;
        $chatType = $chat['type'] ?? '';

        // Ignore private chats (dashboard monitors group chats)
        if (!$chatId || !in_array($chatType, ['group', 'supergroup'])) {
            return ['status' => 'ignored', 'reason' => 'not_a_group_message'];
        }

        // A. Handle group migration to supergroup
        if (isset($message['migrate_to_chat_id'])) {
            return $this->handleGroupMigration($chatId, (int)$message['migrate_to_chat_id']);
        }
        if (isset($message['migrate_from_chat_id'])) {
            return $this->handleGroupMigration((int)$message['migrate_from_chat_id'], $chatId);
        }

        // B. Ensure group exists in database or update its activity
        $group = $this->ensureGroupRecord($chat);
        if (!$group || !$group['is_active']) {
            return ['status' => 'ignored', 'reason' => 'group_not_monitored_or_inactive'];
        }

        // C. Capture join / leave service events in the group
        $messageDate = isset($message['date']) 
            ? gmdate('Y-m-d H:i:s', (int)$message['date']) 
            : utc_now();

        if (!empty($message['new_chat_members'])) {
            foreach ($message['new_chat_members'] as $newMember) {
                if (!empty($newMember['id']) && empty($newMember['is_bot'])) {
                    $this->ensureMemberRecord($newMember, (int)$group['id'], $messageDate);
                }
            }
        }

        if (!empty($message['left_chat_member'])) {
            $leftUser = $message['left_chat_member'];
            if (!empty($leftUser['id'])) {
                $memberRec = Database::fetch("SELECT id FROM members WHERE telegram_user_id = :uid", [':uid' => (int)$leftUser['id']]);
                if ($memberRec) {
                    Database::execute(
                        "UPDATE group_members SET is_active = 0, left_at = :now WHERE group_id = :gid AND member_id = :mid",
                        [':now' => $messageDate, ':gid' => $group['id'], ':mid' => $memberRec['id']]
                    );
                }
            }
        }

        // D. Track / update member identity if sender is a user
        $from = $message['from'] ?? null;
        $senderChat = $message['sender_chat'] ?? null;
        $rawText = $message['text'] ?? '';
        $caption = $message['caption'] ?? '';
        $messageText = $rawText;

        if (!empty($message['photo'])) {
            $messageText = '[Photo' . (!empty($caption) ? ': ' . $caption : '') . ']';
        } elseif (!empty($message['voice'])) {
            $messageText = '[Voice Message' . (!empty($caption) ? ': ' . $caption : '') . ']';
        } elseif (!empty($message['video'])) {
            $messageText = '[Video' . (!empty($caption) ? ': ' . $caption : '') . ']';
        } elseif (!empty($message['document'])) {
            $docName = $message['document']['file_name'] ?? 'Document';
            $messageText = "[Document: {$docName}]" . (!empty($caption) ? ' ' . $caption : '');
        } elseif (!empty($message['sticker'])) {
            $messageText = '[Sticker: ' . ($message['sticker']['emoji'] ?? '😊') . ']';
        } elseif (!empty($caption)) {
            $messageText = $caption;
        }

        $messageId = (int)$message['message_id'];
        $messageDate = isset($message['date']) 
            ? gmdate('Y-m-d H:i:s', (int)$message['date']) 
            : utc_now();
        $threadId = isset($message['message_thread_id']) ? (int)$message['message_thread_id'] : null;

        // D. Check for Anonymous Admin posting
        $isAnonymousAdmin = false;
        if (!empty($senderChat) || (!empty($from['is_bot']) && ($from['username'] ?? '') === 'GroupAnonymousBot')) {
            $isAnonymousAdmin = true;
        }

        $trackHashtag = strtolower((string)config('telegram.track_hashtag', '#track'));
        $trackCommand = strtolower((string)config('telegram.track_command', '/track'));
        $lowerText = strtolower($messageText);
        $hasTrackHashtag = str_contains($lowerText, $trackHashtag);
        $hasTrackCommand = str_starts_with(trim($lowerText), $trackCommand);

        if ($isAnonymousAdmin && ($hasTrackHashtag || $hasTrackCommand)) {
            // Cannot attribute anonymous admin message
            AuditService::log('anonymous_admin_track_attempt', 'group', (string)$group['id'], [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'notice' => 'Message contained tracking trigger but was sent as anonymous admin. Cannot attribute to registered user ID.'
            ]);
            return ['status' => 'warning', 'reason' => 'anonymous_admin_cannot_be_tracked'];
        }

        if (!$from) {
            return ['status' => 'ignored', 'reason' => 'no_user_from'];
        }

        $userId = (int)$from['id'];
        $member = $this->ensureMemberRecord($from, (int)$group['id'], $messageDate);

        // E. Check if this is an authorized sender for this specific group
        $isAuthorizedUser = $this->isAuthorizedSenderForGroup((int)$group['id'], $userId);

        // F. Handle /checkin, /rollcall, or /roster interactive enrollment command
        $botUser = strtolower((string)config('telegram.bot_username', ''));
        $trimmedLower = trim($lowerText);
        $isCheckinCmd = in_array($trimmedLower, [
            '/checkin', '/rollcall', '/roster',
            '/checkin@' . $botUser, '/rollcall@' . $botUser, '/roster@' . $botUser
        ]);

        if ($isCheckinCmd && ($isAuthorizedUser || ($member['role'] ?? '') === 'admin' || ($member['role'] ?? '') === 'creator')) {
            $tg = new TelegramService();
            $keyboard = [
                [
                    [
                        'text' => '✋ Tap Here to Register / Check In',
                        'callback_data' => 'checkin:' . $group['id']
                    ]
                ]
            ];
            $tg->sendMessageWithKeyboard(
                $chatId,
                "☕ <b>Kaldis Coffee Team Roster Check-In</b>\n\nWelcome team! Please tap the button below to register your profile on the monitoring dashboard for <b>" . e($group['title']) . "</b>.",
                $keyboard
            );
            return ['status' => 'checkin_dispatched', 'group_id' => $group['id']];
        }

        // Case 1: Message contains #track from authorized user or group admin
        if ($hasTrackHashtag) {
            return $this->registerTrackedMessage(
                (int)$group['id'],
                $messageId,
                $userId,
                $threadId,
                $messageText,
                $messageDate,
                'hashtag'
            );
        }

        // Case 2: Reply with /track to an existing message
        if ($hasTrackCommand && isset($message['reply_to_message'])) {
            $repliedMessage = $message['reply_to_message'];
            $targetMessageId = (int)$repliedMessage['message_id'];
            $targetText = $repliedMessage['text'] ?? $repliedMessage['caption'] ?? '';
            $targetUserId = (int)($repliedMessage['from']['id'] ?? $userId);
            $targetDate = isset($repliedMessage['date']) 
                ? gmdate('Y-m-d H:i:s', (int)$repliedMessage['date']) 
                : utc_now();
            $targetThreadId = isset($repliedMessage['message_thread_id']) ? (int)$repliedMessage['message_thread_id'] : null;

            return $this->registerTrackedMessage(
                (int)$group['id'],
                $targetMessageId,
                $targetUserId,
                $targetThreadId,
                $targetText,
                $targetDate,
                'command'
            );
        }

        // Case 3: Automatic tracking of each conversation
        // Check if message is a continuation / reply to an existing tracked conversation
        $replyTo = $message['reply_to_message'] ?? null;
        $isTopicMessage = !empty($message['is_topic_message']);
        $isReplyToTracked = false;

        if ($replyTo !== null) {
            $replyToMessageId = (int)$replyTo['message_id'];
            // In Telegram topics, top-level topic messages have reply_to_message pointing to the thread root.
            if ($isTopicMessage && $threadId !== null && $replyToMessageId === $threadId) {
                $isReplyToTracked = false;
            } else {
                $targetTracked = Database::fetch(
                    "SELECT id FROM tracked_messages WHERE group_id = :gid AND telegram_message_id = :mid AND is_active = 1",
                    [':gid' => $group['id'], ':mid' => $replyToMessageId]
                );
                if ($targetTracked) {
                    $isReplyToTracked = true;
                } else {
                    $priorResp = Database::fetch(
                        "SELECT id FROM message_responses WHERE response_telegram_message_id = :rmid",
                        [':rmid' => $replyToMessageId]
                    );
                    if ($priorResp) {
                        $isReplyToTracked = true;
                    }
                }
            }
        }

        $trackingMode = (string)config('telegram.tracking_mode', 'all_authorized');
        $trackAllMessages = (bool)config('telegram.track_all_messages', true);

        if (!$isReplyToTracked) {
            $shouldAutoTrack = false;
            if ($trackingMode === 'all_authorized' && $isAuthorizedUser) {
                $shouldAutoTrack = true;
            } elseif ($trackingMode === 'all_messages' || $trackingMode === 'all_group_messages') {
                $shouldAutoTrack = true;
            } elseif ($trackAllMessages && $isAuthorizedUser && $trackingMode !== 'trigger_only') {
                $shouldAutoTrack = true;
            }

            if ($shouldAutoTrack && !empty(trim($messageText))) {
                return $this->registerTrackedMessage(
                    (int)$group['id'],
                    $messageId,
                    $userId,
                    $threadId,
                    $messageText,
                    $messageDate,
                    'auto'
                );
            }
        }

        // Case 4: Candidate reply to an already tracked message
        // Phase 1 captures reply candidates and forwards to tracking engine
        $this->processCandidateReply($group, $member, $message, $messageDate);

        return ['status' => 'processed', 'type' => 'message'];
    }

    /**
     * Handle edited_message update.
     */
    private function handleEditedMessage(array $editedMessage): array
    {
        $chat = $editedMessage['chat'] ?? [];
        $chatId = $chat['id'] ?? null;
        $messageId = (int)($editedMessage['message_id'] ?? 0);
        $newText = $editedMessage['text'] ?? $editedMessage['caption'] ?? '';
        $editDate = isset($editedMessage['edit_date']) 
            ? gmdate('Y-m-d H:i:s', (int)$editedMessage['edit_date']) 
            : utc_now();

        if (!$chatId || !$messageId) {
            return ['status' => 'ignored'];
        }

        $group = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $chatId]);
        if (!$group) {
            return ['status' => 'ignored', 'reason' => 'group_not_found'];
        }

        $tracked = Database::fetch(
            "SELECT * FROM tracked_messages WHERE group_id = :gid AND telegram_message_id = :mid",
            [':gid' => $group['id'], ':mid' => $messageId]
        );

        if (!$tracked) {
            return ['status' => 'ignored', 'reason' => 'message_not_tracked'];
        }

        $oldText = $tracked['message_text'];
        if ($oldText !== $newText) {
            Database::execute(
                "UPDATE tracked_messages 
                 SET message_text = :text, edited_at = :edited_at, updated_at = :now 
                 WHERE id = :id",
                [
                    ':text' => $newText,
                    ':edited_at' => $editDate,
                    ':now' => utc_now(),
                    ':id' => $tracked['id']
                ]
            );

            AuditService::log('tracked_message_edited', 'tracked_message', (string)$tracked['id'], [
                'group_id' => $group['id'],
                'telegram_message_id' => $messageId,
                'old_text' => mb_substr($oldText, 0, 500),
                'new_text' => mb_substr($newText, 0, 500),
                'edited_at' => $editDate,
            ]);
        }

        return ['status' => 'edited', 'tracked_message_id' => $tracked['id']];
    }

    /**
     * Handle my_chat_member (Bot status in group changed).
     */
    private function handleMyChatMember(array $myChatMember): array
    {
        $chat = $myChatMember['chat'] ?? [];
        $chatId = $chat['id'] ?? null;
        $newStatus = $myChatMember['new_chat_member']['status'] ?? 'unknown';

        if (!$chatId) {
            return ['status' => 'ignored'];
        }

        $group = $this->ensureGroupRecord($chat);
        if (!$group) {
            return ['status' => 'ignored'];
        }

        $botStatus = match ($newStatus) {
            'administrator' => 'active',
            'member' => 'active',
            'restricted' => 'restricted',
            'left' => 'left',
            'kicked' => 'kicked',
            default => 'unknown',
        };

        $isAdmin = ($newStatus === 'administrator') ? 1 : 0;
        $canReadAll = !empty($myChatMember['new_chat_member']['can_read_all_group_messages']) ? 1 : 0;

        Database::execute(
            "UPDATE telegram_groups 
             SET bot_status = :status, bot_is_admin = :is_admin, bot_can_read_all_messages = :read_all, 
                 last_health_check_at = :health_at, updated_at = :updated_at 
             WHERE id = :id",
            [
                ':status' => $botStatus,
                ':is_admin' => $isAdmin,
                ':read_all' => $canReadAll,
                ':health_at' => utc_now(),
                ':updated_at' => utc_now(),
                ':id' => $group['id']
            ]
        );

        AuditService::log('bot_status_changed', 'group', (string)$group['id'], [
            'bot_status' => $botStatus,
            'is_admin' => $isAdmin,
            'telegram_status' => $newStatus,
        ]);

        if ($botStatus === 'active') {
            if (!empty($myChatMember['from']) && empty($myChatMember['from']['is_bot'])) {
                $this->ensureMemberRecord($myChatMember['from'], (int)$group['id'], utc_now());
            }
            \App\Controllers\GroupController::syncGroupAdministrators((int)$group['id'], $chatId);
        }

        return ['status' => 'updated_bot_status', 'bot_status' => $botStatus];
    }

    /**
     * Handle chat_member (Member join / leave).
     */
    private function handleChatMember(array $chatMember): array
    {
        $chat = $chatMember['chat'] ?? [];
        $chatId = $chat['id'] ?? null;
        $user = $chatMember['new_chat_member']['user'] ?? null;
        $newStatus = $chatMember['new_chat_member']['status'] ?? '';

        if (!$chatId || !$user) {
            return ['status' => 'ignored'];
        }

        $group = $this->ensureGroupRecord($chat);
        if (!$group) {
            return ['status' => 'ignored'];
        }

        $member = $this->ensureMemberRecord($user, (int)$group['id']);

        if (in_array($newStatus, ['left', 'kicked'])) {
            Database::execute(
                "UPDATE group_members 
                 SET is_active = 0, left_at = :left_at, updated_at = :updated_at 
                 WHERE group_id = :gid AND member_id = :mid",
                [
                    ':left_at' => utc_now(),
                    ':updated_at' => utc_now(),
                    ':gid' => $group['id'],
                    ':mid' => $member['id']
                ]
            );
        } elseif (in_array($newStatus, ['member', 'administrator', 'creator'])) {
            $role = match ($newStatus) {
                'creator' => 'creator',
                'administrator' => 'admin',
                default => 'member',
            };
            Database::execute(
                "UPDATE group_members 
                 SET is_active = 1, role = :role, left_at = NULL, updated_at = :updated_at 
                 WHERE group_id = :gid AND member_id = :mid",
                [
                    ':role' => $role,
                    ':updated_at' => utc_now(),
                    ':gid' => $group['id'],
                    ':mid' => $member['id']
                ]
            );
        }

        return ['status' => 'member_updated'];
    }

    /**
     * Handle group upgrade to supergroup.
     */
    public function handleGroupMigration(int $oldChatId, int $newChatId): array
    {
        $group = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :old", [':old' => $oldChatId]);

        if ($group) {
            Database::execute(
                "UPDATE telegram_groups 
                 SET telegram_chat_id = :new_id, previous_chat_id = :old_id, type = 'supergroup', updated_at = :now 
                 WHERE id = :id",
                [
                    ':new_id' => $newChatId,
                    ':old_id' => $oldChatId,
                    ':now' => utc_now(),
                    ':id' => $group['id']
                ]
            );

            AuditService::log('group_migrated_to_supergroup', 'group', (string)$group['id'], [
                'old_chat_id' => $oldChatId,
                'new_chat_id' => $newChatId,
            ]);

            return ['status' => 'migrated', 'group_id' => $group['id']];
        }

        return ['status' => 'migration_untracked_group'];
    }

    /**
     * Register a new tracked message.
     */
    public function registerTrackedMessage(
        int $groupId,
        int $messageId,
        int $userId,
        ?int $threadId,
        string $text,
        string $date,
        string $source = 'hashtag',
        ?string $deadlineAt = null
    ): array {
        // Extract reference code if present (e.g. #task-123 or #123)
        $referenceCode = $this->extractReferenceCode($text);

        // If no explicit deadline passed, apply default configured response limitation
        if (empty($deadlineAt)) {
            $defaultHours = (int)config('telegram.default_deadline_hours', 24);
            if ($defaultHours > 0) {
                $baseTime = strtotime($date);
                if ($baseTime <= 0) {
                    $baseTime = time();
                }
                $deadlineAt = gmdate('Y-m-d H:i:s', $baseTime + ($defaultHours * 3600));
            }
        }

        // 1. Ensure database schema integrity & auto-repair missing columns
        Database::ensureSchemaIntegrity();

        try {
            // 2. Dynamically detect columns of tracked_messages table to adapt to any schema version
            $colList = Database::fetchAll("SHOW COLUMNS FROM tracked_messages");
            $fields = array_column($colList, 'Field');
            $fieldMap = array_fill_keys(array_map('strtolower', $fields), true);

            $now = utc_now();
            $insertData = [
                'group_id' => $groupId,
                'telegram_message_id' => $messageId,
                'thread_id' => $threadId,
                'reference_code' => $referenceCode,
                'message_text' => $text,
                'message_date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (isset($fieldMap['telegram_user_id'])) {
                $insertData['telegram_user_id'] = $userId;
            }
            if (isset($fieldMap['user_id'])) {
                $insertData['user_id'] = $userId;
            }
            if (isset($fieldMap['sender_id'])) {
                $insertData['sender_id'] = $userId;
            }
            if (isset($fieldMap['deadline_at'])) {
                $insertData['deadline_at'] = $deadlineAt;
            }
            if (isset($fieldMap['tracking_source'])) {
                $insertData['tracking_source'] = $source;
            }
            if (isset($fieldMap['is_active'])) {
                $insertData['is_active'] = 1;
            }

            $colNames = array_keys($insertData);
            $colSql = '`' . implode('`, `', $colNames) . '`';
            $valSql = ':' . implode(', :', $colNames);

            $params = [];
            foreach ($insertData as $k => $v) {
                $params[':' . $k] = $v;
            }

            $sql = "INSERT INTO tracked_messages ({$colSql}) VALUES ({$valSql})";
            Database::execute($sql, $params);

            $trackedId = (int)Database::lastInsertId();

            AuditService::log('tracked_message_created', 'tracked_message', (string)$trackedId, [
                'group_id' => $groupId,
                'telegram_message_id' => $messageId,
                'sender_id' => $userId,
                'reference_code' => $referenceCode,
                'source' => $source
            ]);

            // Initialize member status records for active group members
            $this->initializeRosterForTrackedMessage($trackedId, $groupId, $date);

            return [
                'status' => 'tracked',
                'tracked_message_id' => $trackedId,
                'reference_code' => $referenceCode
            ];
        } catch (\PDOException $e) {
            // Already tracked
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                return ['status' => 'already_tracked', 'message_id' => $messageId];
            }
            // If unknown column error somehow occurred, force schema repair and retry once
            if (str_contains($e->getMessage(), '42S22') || str_contains($e->getMessage(), '1054') || str_contains($e->getMessage(), 'Unknown column')) {
                Database::ensureSchemaIntegrity(true);
                return [
                    'status' => 'repaired_and_queued',
                    'notice' => 'Database schema auto-repaired. Please resend or re-try tracking.'
                ];
            }
            throw $e;
        }
    }

    /**
     * Initialize eligible roster responses for a new tracked message.
     * If bot visibility in the group was uncertain when the message was sent,
     * record members as 'unknown' rather than confirmed non-responses.
     */
    private function initializeRosterForTrackedMessage(int $trackedMessageId, int $groupId, string $messageDate): void
    {
        $group = Database::fetch("SELECT bot_status FROM telegram_groups WHERE id = :id", [':id' => $groupId]);
        if (!$group || $group['bot_status'] === 'active') {
            // Bot visibility was confirmed. Members who haven't responded yet will be
            // accurately calculated as confirmed non-responses (not_replied).
            return;
        }

        // Bot status was restricted/unknown: mark eligible members as 'unknown'
        $sql = "SELECT gm.member_id, m.telegram_user_id 
                FROM group_members gm
                JOIN members m ON gm.member_id = m.id
                WHERE gm.group_id = :gid 
                  AND gm.joined_at <= :mdate1 
                  AND (gm.left_at IS NULL OR gm.left_at > :mdate2)
                  AND m.is_bot = 0";

        $members = Database::fetchAll($sql, [':gid' => $groupId, ':mdate1' => $messageDate, ':mdate2' => $messageDate]);

        foreach ($members as $m) {
            try {
                $now = utc_now();
                Database::execute(
                    "INSERT IGNORE INTO message_responses (tracked_message_id, member_id, member_telegram_user_id, status, created_at, updated_at)
                     VALUES (:tmid, :mid, :uid, 'unknown', :created_at, :updated_at)",
                    [
                        ':tmid' => $trackedMessageId,
                        ':mid' => $m['member_id'],
                        ':uid' => $m['telegram_user_id'],
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]
                );
            } catch (\Throwable) {
                // Ignore conflict
            }
        }
    }

    /**
     * Process reply candidates against existing tracked messages.
     */
    private function processCandidateReply(array $group, array $member, array $message, string $replyDate): void
    {
        $replyTo = $message['reply_to_message'] ?? null;
        $rawText = $message['text'] ?? '';
        $caption = $message['caption'] ?? '';
        $messageText = $rawText;

        if (!empty($message['photo'])) {
            $messageText = '[Photo' . (!empty($caption) ? ': ' . $caption : '') . ']';
        } elseif (!empty($message['voice'])) {
            $messageText = '[Voice Message' . (!empty($caption) ? ': ' . $caption : '') . ']';
        } elseif (!empty($message['video'])) {
            $messageText = '[Video' . (!empty($caption) ? ': ' . $caption : '') . ']';
        } elseif (!empty($message['document'])) {
            $docName = $message['document']['file_name'] ?? 'Document';
            $messageText = "[Document: {$docName}]" . (!empty($caption) ? ' ' . $caption : '');
        } elseif (!empty($message['sticker'])) {
            $messageText = '[Sticker: ' . ($message['sticker']['emoji'] ?? '😊') . ']';
        } elseif (!empty($caption)) {
            $messageText = $caption;
        }

        $messageId = (int)$message['message_id'];
        $memberId = (int)$member['id'];
        $userId = (int)$member['telegram_user_id'];
        $threadId = isset($message['message_thread_id']) ? (int)$message['message_thread_id'] : null;
        $isTopicMessage = !empty($message['is_topic_message']);

        // Check 1: Direct Reply
        if ($replyTo !== null) {
            $replyToMessageId = (int)$replyTo['message_id'];

            // Rule 4: Forum topics root message filter
            // In forum topics, replies to the topic root are NOT direct replies unless targeting the actual message ID
            if ($isTopicMessage && $threadId !== null && $replyToMessageId === $threadId) {
                // Topic root message: skip counting as direct reply unless tracked message IS the thread root
                $tracked = Database::fetch(
                    "SELECT * FROM tracked_messages 
                     WHERE group_id = :gid AND telegram_message_id = :mid AND is_active = 1",
                    [':gid' => $group['id'], ':mid' => $replyToMessageId]
                );
                if (!$tracked || $tracked['thread_id'] === null) {
                    // Not a direct reply to tracked message
                    return;
                }
            } else {
                $tracked = Database::fetch(
                    "SELECT * FROM tracked_messages 
                     WHERE group_id = :gid AND telegram_message_id = :mid AND is_active = 1",
                    [':gid' => $group['id'], ':mid' => $replyToMessageId]
                );
            }

            if ($tracked) {
                // If the author replies to their own tracked message, do not record author as a member response
                $authorUserId = (int)($tracked['telegram_user_id'] ?? $tracked['user_id'] ?? 0);
                if ($authorUserId === $userId) {
                    return; // Author follow-up in own thread
                }

                // Direct reply to our tracked message!
                // Rule 8: Do not count messages sent before the tracked message
                if ($replyDate >= $tracked['message_date']) {
                    $this->recordResponse(
                        (int)$tracked['id'],
                        $memberId,
                        $userId,
                        $messageId,
                        $messageText,
                        $replyDate,
                        'replied',
                        'direct_reply'
                    );
                    return;
                }
            }

            // Rule 5: Reply to another member's reply to my message goes to Pending Review
            $priorResponse = Database::fetch(
                "SELECT * FROM message_responses 
                 WHERE response_telegram_message_id = :rmid",
                [':rmid' => $replyToMessageId]
            );

            if ($priorResponse) {
                // If the author of the tracked message is replying in this thread, do not record author as a member response
                $parentTracked = Database::fetch(
                    "SELECT * FROM tracked_messages WHERE id = :id",
                    [':id' => $priorResponse['tracked_message_id']]
                );
                $parentAuthorId = (int)($parentTracked['telegram_user_id'] ?? $parentTracked['user_id'] ?? 0);
                if ($parentTracked && $parentAuthorId === $userId) {
                    return; // Author follow-up in own thread
                }

                $this->recordResponse(
                    (int)$priorResponse['tracked_message_id'],
                    $memberId,
                    $userId,
                    $messageId,
                    $messageText,
                    $replyDate,
                    'pending_review',
                    'direct_reply'
                );
                return;
            }
        }

        // Check 2: Reference code match (e.g. member included #123)
        $refMatches = [];
        if (preg_match_all('/#([a-zA-Z0-9_\-]+)/', $messageText, $refMatches)) {
            $codes = $refMatches[1];
            foreach ($codes as $code) {
                if (strtolower($code) === 'track') continue;
                $tracked = Database::fetch(
                    "SELECT * FROM tracked_messages 
                     WHERE group_id = :gid AND (reference_code = :ref OR reference_code = :hashref) AND is_active = 1",
                    [':gid' => $group['id'], ':ref' => $code, ':hashref' => '#' . $code]
                );

                if ($tracked && $replyDate > $tracked['message_date']) {
                    $this->recordResponse(
                        (int)$tracked['id'],
                        $memberId,
                        $userId,
                        $messageId,
                        $messageText,
                        $replyDate,
                        'replied',
                        'reference'
                    );
                    return;
                }
            }
        }
    }

    /**
     * Record or update message response maintaining primary valid response.
     */
    private function recordResponse(
        int $trackedMessageId,
        int $memberId,
        int $memberUserId,
        int $responseMessageId,
        string $text,
        string $responseDate,
        string $status,
        string $matchingMethod
    ): void {
        $existing = null;
        try {
            $existing = Database::fetch(
                "SELECT * FROM message_responses WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid",
                [':tmid' => $trackedMessageId, ':uid' => $memberUserId]
            );
        } catch (\Throwable) {
            try {
                $existing = Database::fetch(
                    "SELECT * FROM message_responses WHERE tracked_message_id = :tmid AND member_id = :mid",
                    [':tmid' => $trackedMessageId, ':mid' => $memberId]
                );
            } catch (\Throwable) {}
        }

        if ($existing) {
            // Rule 11: Keep first valid response as primary; if existing is already 'replied', do not overwrite
            if ($existing['status'] === 'replied') {
                return;
            }

            Database::execute(
                "UPDATE message_responses 
                 SET response_telegram_message_id = :rmid, response_text = :text, response_date = :rdate,
                     status = :status, matching_method = :method, updated_at = :now
                 WHERE id = :id",
                [
                    ':rmid' => $responseMessageId,
                    ':text' => $text,
                    ':rdate' => $responseDate,
                    ':status' => $status,
                    ':method' => $matchingMethod,
                    ':now' => utc_now(),
                    ':id' => $existing['id']
                ]
            );
        } else {
            $now = utc_now();
            $mrCols = Database::fetchAll("SHOW COLUMNS FROM message_responses");
            $mrFields = array_fill_keys(array_map('strtolower', array_column($mrCols, 'Field')), true);

            $insertData = [
                'tracked_message_id' => $trackedMessageId,
                'member_id' => $memberId,
                'response_telegram_message_id' => $responseMessageId,
                'response_text' => $text,
                'response_date' => $responseDate,
                'status' => $status,
                'matching_method' => $matchingMethod,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (isset($mrFields['member_telegram_user_id'])) {
                $insertData['member_telegram_user_id'] = $memberUserId;
            }
            if (isset($mrFields['telegram_user_id'])) {
                $insertData['telegram_user_id'] = $memberUserId;
            }
            if (isset($mrFields['user_id'])) {
                $insertData['user_id'] = $memberUserId;
            }

            $colNames = array_keys($insertData);
            $colSql = '`' . implode('`, `', $colNames) . '`';
            $valSql = ':' . implode(', :', $colNames);

            $params = [];
            foreach ($insertData as $k => $v) {
                $params[':' . $k] = $v;
            }

            Database::execute("INSERT INTO message_responses ({$colSql}) VALUES ({$valSql})", $params);
        }
    }

    /**
     * Helper to extract task reference code from message text.
     */
    private function extractReferenceCode(string $text): ?string
    {
        // Check for specific references like #task-123, #kaldis-45, or #123 (excluding #track)
        if (preg_match_all('/#([a-zA-Z0-9_\-]+)/', $text, $matches)) {
            foreach ($matches[0] as $code) {
                if (strtolower($code) !== '#track') {
                    return $code;
                }
            }
        }
        return null;
    }

    /**
     * Check if a Telegram user is an authorized sender for a specific group.
     */
    public function isAuthorizedSenderForGroup(int $groupId, int $userId): bool
    {
        try {
            $sender = Database::fetch(
                "SELECT id FROM group_authorized_senders 
                 WHERE group_id = :gid AND telegram_user_id = :uid AND is_active = 1",
                [':gid' => $groupId, ':uid' => $userId]
            );
            if ($sender) {
                return true;
            }
        } catch (\Throwable) {}

        // Global fallback for super-admin sender IDs configured in system settings
        $globalAuth = (array)config('telegram.authorized_user_ids', []);
        if (in_array((string)$userId, array_map('strval', $globalAuth))) {
            return true;
        }

        // Check if user is an admin or creator in this group roster
        try {
            $adminRole = Database::fetch(
                "SELECT gm.role 
                 FROM group_members gm 
                 JOIN members m ON gm.member_id = m.id 
                 WHERE gm.group_id = :gid AND m.telegram_user_id = :uid AND gm.is_active = 1",
                [':gid' => $groupId, ':uid' => $userId]
            );
            if (!empty($adminRole['role']) && in_array($adminRole['role'], ['creator', 'admin'])) {
                return true;
            }
        } catch (\Throwable) {}

        return false;
    }

    /**
     * Ensure telegram_groups record exists and update last_update_at.
     */
    public function ensureGroupRecord(array $chat): ?array
    {
        $chatId = $chat['id'] ?? null;
        if (!$chatId) return null;

        $title = $chat['title'] ?? 'Group ' . $chatId;
        $username = $chat['username'] ?? null;
        $type = $chat['type'] ?? 'supergroup';

        $group = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $chatId]);

        if ($group) {
            Database::execute(
                "UPDATE telegram_groups 
                 SET title = :title, username = :username, type = :type, last_update_at = :last_update, updated_at = :updated_at 
                 WHERE id = :id",
                [
                    ':title' => $title,
                    ':username' => $username,
                    ':type' => $type,
                    ':last_update' => utc_now(),
                    ':updated_at' => utc_now(),
                    ':id' => $group['id']
                ]
            );
            $group['title'] = $title;
            $group['username'] = $username;
            return $group;
        }

        // New group discovered via message
        $now = utc_now();
        Database::execute(
            "INSERT INTO telegram_groups 
             (telegram_chat_id, title, username, type, is_active, bot_status, created_at, updated_at, last_update_at)
             VALUES (:cid, :title, :username, :type, 1, 'active', :created_at, :updated_at, :last_update)",
            [
                ':cid' => $chatId,
                ':title' => $title,
                ':username' => $username,
                ':type' => $type,
                ':created_at' => $now,
                ':updated_at' => $now,
                ':last_update' => $now,
            ]
        );

        return Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $chatId]);
    }

    /**
     * Ensure member record exists and belongs to group_members.
     */
    public function ensureMemberRecord(array $user, int $groupId, ?string $seenAt = null): array
    {
        $userId = (int)$user['id'];
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if (empty($fullName)) {
            $fullName = $user['username'] ?? "User {$userId}";
        }
        $username = $user['username'] ?? null;
        $isBot = !empty($user['is_bot']) ? 1 : 0;
        $joinedAt = $seenAt ?: utc_now();

        $memCols = Database::fetchAll("SHOW COLUMNS FROM members");
        $memFields = array_fill_keys(array_map('strtolower', array_column($memCols, 'Field')), true);
        $userCol = isset($memFields['telegram_user_id']) ? 'telegram_user_id' : (isset($memFields['user_id']) ? 'user_id' : 'telegram_user_id');

        $member = null;
        try {
            $member = Database::fetch("SELECT * FROM members WHERE {$userCol} = :uid", [':uid' => $userId]);
        } catch (\Throwable) {}

        if ($member) {
            Database::execute(
                "UPDATE members SET full_name = :fn, username = :un, updated_at = :now WHERE id = :id",
                [
                    ':fn' => $fullName,
                    ':un' => $username,
                    ':now' => utc_now(),
                    ':id' => $member['id']
                ]
            );
            $member['full_name'] = $fullName;
            $member['username'] = $username;
        } else {
            $now = utc_now();
            $insCols = ['full_name', 'username', 'is_bot', 'created_at', 'updated_at'];
            $insVals = [':fn', ':un', ':bot', ':created_at', ':updated_at'];
            $params = [
                ':fn' => $fullName,
                ':un' => $username,
                ':bot' => $isBot,
                ':created_at' => $now,
                ':updated_at' => $now,
            ];

            if (isset($memFields['telegram_user_id'])) {
                $insCols[] = 'telegram_user_id';
                $insVals[] = ':uid';
                $params[':uid'] = $userId;
            }
            if (isset($memFields['user_id'])) {
                $insCols[] = 'user_id';
                $insVals[] = ':uid_legacy';
                $params[':uid_legacy'] = $userId;
            }

            $colSql = '`' . implode('`, `', $insCols) . '`';
            $valSql = implode(', ', $insVals);

            Database::execute(
                "INSERT INTO members ({$colSql}) VALUES ({$valSql})
                 ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), username = VALUES(username), updated_at = VALUES(updated_at)",
                $params
            );
            try {
                $member = Database::fetch("SELECT * FROM members WHERE {$userCol} = :uid", [':uid' => $userId]);
            } catch (\Throwable) {}
        }

        if (!$member) {
            $member = [
                'id' => 0,
                'telegram_user_id' => $userId,
                'full_name' => $fullName,
                'username' => $username,
                'is_bot' => $isBot,
            ];
        }

        // Link in group_members if not present
        if (!empty($member['id'])) {
            $gm = Database::fetch(
                "SELECT * FROM group_members WHERE group_id = :gid AND member_id = :mid",
                [':gid' => $groupId, ':mid' => $member['id']]
            );

            if (!$gm) {
                $now = utc_now();
                Database::execute(
                    "INSERT IGNORE INTO group_members (group_id, member_id, role, joined_at, source, is_active, created_at, updated_at)
                     VALUES (:gid, :mid, 'member', :joined_at, 'seen_in_group', 1, :created_at, :updated_at)",
                    [
                        ':gid' => $groupId,
                        ':mid' => $member['id'],
                        ':joined_at' => $joinedAt,
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]
                );
            }
        }

        return $member;
    }

    /**
     * Handle inline button callback queries (e.g. checkin:123).
     */
    private function handleCallbackQuery(array $callbackQuery): array
    {
        $callbackId = (string)($callbackQuery['id'] ?? '');
        $from = $callbackQuery['from'] ?? null;
        $message = $callbackQuery['message'] ?? [];
        $chat = $message['chat'] ?? [];
        $chatId = $chat['id'] ?? null;
        $data = (string)($callbackQuery['data'] ?? '');

        if (!$from || empty($from['id'])) {
            return ['status' => 'ignored', 'reason' => 'no_user_in_callback'];
        }

        if (str_starts_with($data, 'checkin:')) {
            $groupId = (int)substr($data, strlen('checkin:'));
            $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);
            if (!$group && $chatId) {
                $group = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $chatId]);
            }

            if ($group) {
                $member = $this->ensureMemberRecord($from, (int)$group['id'], utc_now());

                $tg = new TelegramService();
                $userName = $from['first_name'] ?? 'Team Member';
                $tg->answerCallbackQuery(
                    $callbackId,
                    "✅ Success {$userName}! You have been registered on the Kaldis Tracker roster for {$group['title']}.",
                    true // Display modal popup alert
                );

                AuditService::log('member_checked_in_via_button', 'group', (string)$group['id'], [
                    'telegram_user_id' => $from['id'],
                    'name' => $userName,
                    'group_title' => $group['title'],
                ]);

                return ['status' => 'checked_in', 'user_id' => $from['id'], 'group_id' => $group['id']];
            }
        }

        $tg = new TelegramService();
        $tg->answerCallbackQuery($callbackId, "Received.");
        return ['status' => 'ignored', 'reason' => 'unknown_callback_data'];
    }

    /**
     * Handle message_reaction update (Emoji reactions on tracked messages).
     * Staff reacting with 👍, ❤️, 🔥, 👌, ✅ are automatically recorded as having replied/acknowledged.
     */
    public function handleMessageReaction(array $reaction): array
    {
        $chat = $reaction['chat'] ?? [];
        $chatId = $chat['id'] ?? null;
        $messageId = (int)($reaction['message_id'] ?? 0);
        $user = $reaction['user'] ?? null;
        $date = isset($reaction['date']) ? gmdate('Y-m-d H:i:s', (int)$reaction['date']) : utc_now();

        if (!$chatId || !$messageId || !$user) {
            return ['status' => 'ignored', 'reason' => 'invalid_reaction_payload'];
        }

        // Ignore bot reactions
        if (!empty($user['is_bot'])) {
            return ['status' => 'ignored', 'reason' => 'bot_reaction'];
        }

        $group = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $chatId]);
        if (!$group || !$group['is_active']) {
            return ['status' => 'ignored', 'reason' => 'group_not_tracked'];
        }

        // Find the tracked message by group and message_id
        $tracked = Database::fetch(
            "SELECT * FROM tracked_messages WHERE group_id = :gid AND telegram_message_id = :mid AND is_active = 1",
            [':gid' => $group['id'], ':mid' => $messageId]
        );

        if (!$tracked) {
            return ['status' => 'ignored', 'reason' => 'message_not_tracked'];
        }

        // Author reacting to own message does not count as response
        $userId = (int)$user['id'];
        $authorUserId = (int)($tracked['telegram_user_id'] ?? $tracked['user_id'] ?? 0);
        if ($authorUserId === $userId) {
            return ['status' => 'ignored', 'reason' => 'author_reaction'];
        }

        $member = $this->ensureMemberRecord($user, (int)$group['id'], $date);
        $memberId = (int)$member['id'];

        // Check new reactions added
        $newReactions = $reaction['new_reaction'] ?? [];
        if (empty($newReactions)) {
            return ['status' => 'reaction_cleared'];
        }

        // Extract emojis from new reactions
        $emojis = [];
        foreach ($newReactions as $r) {
            if (($r['type'] ?? '') === 'emoji' && !empty($r['emoji'])) {
                $emojis[] = $r['emoji'];
            } elseif (($r['type'] ?? '') === 'custom_emoji') {
                $emojis[] = '⭐';
            }
        }

        $emojiStr = !empty($emojis) ? implode(' ', $emojis) : '👍';
        $responseText = "[Reaction: {$emojiStr}]";

        $this->recordResponse(
            (int)$tracked['id'],
            $memberId,
            $userId,
            $messageId,
            $responseText,
            $date,
            'replied',
            'reaction'
        );

        AuditService::log('message_reaction_recorded', 'tracked_message', (string)$tracked['id'], [
            'group_id' => $group['id'],
            'member_id' => $memberId,
            'telegram_user_id' => $userId,
            'emojis' => $emojis,
            'reaction_date' => $date
        ]);

        return [
            'status' => 'reaction_recorded',
            'tracked_message_id' => $tracked['id'],
            'emojis' => $emojis
        ];
    }

    private function determineUpdateType(array $payload): string
    {
        if (isset($payload['message'])) return 'message';
        if (isset($payload['edited_message'])) return 'edited_message';
        if (isset($payload['callback_query'])) return 'callback_query';
        if (isset($payload['my_chat_member'])) return 'my_chat_member';
        if (isset($payload['chat_member'])) return 'chat_member';
        if (isset($payload['message_reaction'])) return 'message_reaction';
        return 'unknown';
    }

    private function extractChatId(array $payload, string $type): ?int
    {
        return match ($type) {
            'message' => $payload['message']['chat']['id'] ?? null,
            'edited_message' => $payload['edited_message']['chat']['id'] ?? null,
            'callback_query' => $payload['callback_query']['message']['chat']['id'] ?? null,
            'my_chat_member' => $payload['my_chat_member']['chat']['id'] ?? null,
            'chat_member' => $payload['chat_member']['chat']['id'] ?? null,
            'message_reaction' => $payload['message_reaction']['chat']['id'] ?? null,
            default => null,
        };
    }

    /**
     * Check for expired deadlines and send summary notification directly to Authorized Sender.
     */
    public function processExpiredDeadlinesAndSendSummaries(): array
    {
        $notifyEnabled = (bool)(int)config('telegram.notify_sender_on_deadline', 1);
        if (!$notifyEnabled) {
            return ['status' => 'notifications_disabled', 'count' => 0];
        }

        Database::ensureSchemaIntegrity();

        $now = utc_now();
        // Fetch up to 10 expired tracked messages that haven't had their summary report sent
        $expiredMessages = Database::fetchAll(
            "SELECT tm.*, tg.title AS group_title, tg.telegram_chat_id 
             FROM tracked_messages tm
             JOIN telegram_groups tg ON tm.group_id = tg.id
             WHERE tm.is_active = 1 
               AND tm.deadline_at IS NOT NULL 
               AND tm.deadline_at <= :now 
               AND (tm.summary_sent_at IS NULL OR tm.summary_sent_at = '0000-00-00 00:00:00')
             ORDER BY tm.deadline_at ASC 
             LIMIT 10",
            [':now' => $now]
        );

        if (empty($expiredMessages)) {
            return ['status' => 'no_due_summaries', 'count' => 0];
        }

        $processed = [];
        $dashboardCtrl = new \App\Controllers\DashboardController();
        $tg = new \App\Services\TelegramService();

        foreach ($expiredMessages as $msg) {
            $msgId = (int)$msg['id'];
            $groupId = (int)$msg['group_id'];
            $senderUserId = (int)($msg['telegram_user_id'] ?? $msg['user_id'] ?? 0);
            $msgDate = $msg['message_date'];

            // 1. Calculate stats for this message
            $stats = $dashboardCtrl->calculateMessageStats($msgId, $groupId, $msgDate);

            // 2. Fetch roster breakdown: who replied and who is still pending
            $roster = Database::fetchAll(
                "SELECT m.id, m.telegram_user_id, m.full_name, m.username, mr.status, mr.response_date
                 FROM group_members gm
                 JOIN members m ON gm.member_id = m.id
                 LEFT JOIN message_responses mr ON mr.tracked_message_id = :tmid AND mr.member_telegram_user_id = m.telegram_user_id
                 WHERE gm.group_id = :gid 
                   AND gm.joined_at <= :mdate 
                   AND (gm.left_at IS NULL OR gm.left_at > :mdate2)
                   AND m.is_bot = 0 
                   AND m.telegram_user_id != :sender_id",
                [
                    ':tmid' => $msgId,
                    ':gid' => $groupId,
                    ':mdate' => $msgDate,
                    ':mdate2' => $msgDate,
                    ':sender_id' => $senderUserId
                ]
            );

            $repliedList = [];
            $pendingList = [];

            foreach ($roster as $mem) {
                $displayName = trim($mem['full_name']);
                if (!empty($mem['username'])) {
                    $displayName .= ' (@' . $mem['username'] . ')';
                }

                if (($mem['status'] ?? '') === 'replied') {
                    $turnaround = '';
                    if (!empty($mem['response_date'])) {
                        $diff = max(0, strtotime($mem['response_date']) - strtotime($msgDate));
                        $turnaround = ' (' . format_duration($diff) . ')';
                    }
                    $repliedList[] = $displayName . $turnaround;
                } else {
                    $pendingList[] = $displayName;
                }
            }

            // Fetch sender info for display
            $senderMember = Database::fetch("SELECT full_name, username FROM members WHERE telegram_user_id = :uid", [':uid' => $senderUserId]);
            $senderName = !empty($senderMember['full_name']) ? $senderMember['full_name'] : "Authorized Sender";

            // 3. Format detailed summary report
            $excerpt = mb_strimwidth($msg['message_text'], 0, 100, '...');
            $deadlineDisplay = !empty($msg['deadline_at']) ? format_tz($msg['deadline_at']) : 'Completed';
            $portalUrl = rtrim(config('app.url', ''), '/') . '/messages/view?id=' . $msgId;

            $report = "☕ <b>Kaldi's Coffee &bull; Deadline Summary Report</b>\n\n";
            $report .= "📋 <b>Tracked Message:</b> <i>\"" . e($excerpt) . "\"</i>\n";
            $report .= "👥 <b>Group:</b> " . e($msg['group_title']) . "\n";
            $report .= "⏰ <b>Time Limitation:</b> Ended at " . e($deadlineDisplay) . "\n\n";

            $rate = number_format($stats['percentage'], 1);
            $report .= "📊 <b>Final Response Rate:</b> <b>{$rate}%</b> ({$stats['replied']}/{$stats['eligible']} Replied)\n\n";

            if (!empty($repliedList)) {
                $report .= "✅ <b>Replied (" . count($repliedList) . "):</b>\n";
                $showReplied = array_slice($repliedList, 0, 15);
                foreach ($showReplied as $rName) {
                    $report .= "• " . e($rName) . "\n";
                }
                if (count($repliedList) > 15) {
                    $report .= "<i>...and " . (count($repliedList) - 15) . " more</i>\n";
                }
                $report .= "\n";
            }

            if (!empty($pendingList)) {
                $report .= "⚠️ <b>Not Replied / Pending (" . count($pendingList) . "):</b>\n";
                $showPending = array_slice($pendingList, 0, 20);
                foreach ($showPending as $pName) {
                    $report .= "• " . e($pName) . "\n";
                }
                if (count($pendingList) > 20) {
                    $report .= "<i>...and " . (count($pendingList) - 20) . " more</i>\n";
                }
                $report .= "\n";
            }

            if (!empty($stats['fastest_turnaround_formatted']) && $stats['fastest_turnaround_formatted'] !== '—') {
                $report .= "⚡ <b>Fastest Response:</b> {$stats['fastest_turnaround_formatted']}\n";
            }
            if (!empty($stats['avg_turnaround_formatted']) && $stats['avg_turnaround_formatted'] !== '—') {
                $report .= "⏱️ <b>Avg Turnaround:</b> {$stats['avg_turnaround_formatted']}\n";
            }

            $report .= "\n🔗 <a href=\"" . e($portalUrl) . "\">Open Detailed Audit on Dashboard</a>";

            // 4. Send report to Authorized Sender
            $sentSuccessfully = false;
            $deliveryMethod = 'direct_dm';

            if ($senderUserId > 0) {
                // Try sending directly in private chat to the sender
                $sendResp = $tg->sendMessage($senderUserId, $report, null, null, 'HTML');
                if (!empty($sendResp['ok'])) {
                    $sentSuccessfully = true;
                    $deliveryMethod = 'private_chat';
                } else {
                    // Private chat failed (user has not started a chat with the bot or blocked).
                    // Fall back to sending in the group where the message was posted!
                    $mention = !empty($senderMember['username']) ? '@' . $senderMember['username'] : $senderName;
                    $groupReport = "🔔 <b>Summary Report for {$mention} (Time Limitation Expired):</b>\n\n" . $report;
                    $sendRespGroup = $tg->sendMessage(
                        (int)$msg['telegram_chat_id'],
                        $groupReport,
                        (int)$msg['telegram_message_id'],
                        !empty($msg['thread_id']) ? (int)$msg['thread_id'] : null,
                        'HTML'
                    );
                    if (!empty($sendRespGroup['ok'])) {
                        $sentSuccessfully = true;
                        $deliveryMethod = 'group_fallback';
                    }
                }
            } else {
                $sendRespGroup = $tg->sendMessage(
                    (int)$msg['telegram_chat_id'],
                    $report,
                    (int)$msg['telegram_message_id'],
                    !empty($msg['thread_id']) ? (int)$msg['thread_id'] : null,
                    'HTML'
                );
                if (!empty($sendRespGroup['ok'])) {
                    $sentSuccessfully = true;
                    $deliveryMethod = 'group_channel';
                }
            }

            // 5. Mark as sent so it is never re-sent
            Database::execute(
                "UPDATE tracked_messages SET summary_sent_at = :now WHERE id = :id",
                [':now' => utc_now(), ':id' => $msgId]
            );

            AuditService::log('deadline_summary_sent', 'tracked_message', (string)$msgId, [
                'sender_user_id' => $senderUserId,
                'delivery_method' => $deliveryMethod,
                'stats' => $stats,
                'success' => $sentSuccessfully,
            ]);

            $processed[] = [
                'message_id' => $msgId,
                'sender_user_id' => $senderUserId,
                'delivery_method' => $deliveryMethod,
                'success' => $sentSuccessfully,
            ];
        }

        return ['status' => 'processed', 'summaries_sent' => count($processed), 'details' => $processed];
    }

    /**
     * Throttled check for auto-deleting old tracked messages.
     */
    public static function autoDeleteOldTrackedMessagesThrottled(): void
    {
        static $lastRun = 0;
        if (time() - $lastRun < 3600) { // Throttle: run at most once per hour per process
            return;
        }
        $lastRun = time();
        self::autoDeleteOldTrackedMessages();
    }

    /**
     * Automatically purge old tracked messages and their reply logs based on retention days setting.
     */
    public static function autoDeleteOldTrackedMessages(): array
    {
        $retentionDays = (int)config('telegram.tracked_message_retention_days', 0);
        if ($retentionDays <= 0) {
            $retentionDays = (int)config('app.retention_days', 0);
        }

        if ($retentionDays <= 0) {
            return ['status' => 'retention_disabled', 'deleted_count' => 0];
        }

        Database::ensureSchemaIntegrity();

        try {
            $oldMessages = Database::fetchAll(
                "SELECT id FROM tracked_messages WHERE message_date < (UTC_TIMESTAMP() - INTERVAL :days DAY)",
                [':days' => $retentionDays]
            );

            if (empty($oldMessages)) {
                return ['status' => 'no_expired_messages', 'deleted_count' => 0];
            }

            $ids = array_map('intval', array_column($oldMessages, 'id'));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Delete corresponding message responses first
            Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN ({$placeholders})", $ids);

            // Delete tracked messages
            Database::execute("DELETE FROM tracked_messages WHERE id IN ({$placeholders})", $ids);

            AuditService::log('tracked_messages_purged_by_retention', 'system', 'retention', [
                'retention_days' => $retentionDays,
                'purged_count' => count($ids),
            ]);

            return [
                'status' => 'purged',
                'retention_days' => $retentionDays,
                'deleted_count' => count($ids),
            ];
        } catch (\Throwable $e) {
            error_log("Auto-delete retention error: " . $e->getMessage());
            return ['status' => 'error', 'error' => $e->getMessage(), 'deleted_count' => 0];
        }
    }
}
