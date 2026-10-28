# Kaldis Coffee Telegram Reply Tracker (v2)

A specialized web application built with plain PHP 8.x, MySQL/MariaDB (PDO), Bootstrap 5, and the Telegram Bot API to track messages sent in registered Kaldis Coffee Telegram groups and monitor which group members replied, which did not, and which are unknown or pending review.

---

## 1. Architecture & Tech Stack

- **Backend**: Plain PHP 8.x (no external frameworks like Laravel, Node.js, or React).
- **Database**: MySQL 8.x / MariaDB via PDO with prepared statements, `utf8mb4` character set for full emoji and Amharic script support.
- **Frontend**: Responsive HTML5, Bootstrap 5, Bootstrap Icons, custom Kaldis Coffee branding styling.
- **Telegram Integration**: Telegram Bot API webhook with `X-Telegram-Bot-Api-Secret-Token` validation and update deduplication.
- **Hosting**: Architecture separates public web root (`public/`) from application core (`app/`) and storage (`storage/`) for cPanel shared hosting security.
- **Timezone**: All timestamps stored in **UTC** in the database; rendered and displayed in **Africa/Addis_Ababa (EAT)** throughout the dashboard.
- **Identifier Handling**: Telegram Chat and User IDs are handled as 64-bit signed integers (`BIGINT`). Negative IDs (e.g., `-100...`) are preserved for supergroups.

---

## 2. Directory Structure

```text
kaldis-response-tracker/
├── app/
│   ├── config/
│   │   ├── config.php                 # Active application configuration
│   │   └── config.sample.php          # Sample configuration template
│   ├── controllers/
│   │   ├── AuthController.php         # Admin authentication, rate limiting & lockout
│   │   ├── DashboardController.php    # Metrics, 4 summary cards, filters & pagination
│   │   ├── GroupController.php        # Group registration, Bot API health check & roster
│   │   ├── TrackedMessageController.php # Message details & manual message tracking
│   │   ├── AuditController.php        # Security & operational audit log viewer
│   │   └── WebhookController.php      # Telegram webhook handler & secret token validator
│   ├── services/
│   │   ├── Database.php               # PDO singleton with prepared statement helpers
│   │   ├── AuthService.php            # Session security, CSRF protection & role management
│   │   ├── AuditService.php           # Audit logger with automatic credential redaction
│   │   ├── TelegramService.php        # Telegram Bot API client
│   │   └── MessageTrackingService.php # Core webhook idempotency & tracking logic
│   ├── views/
│   │   ├── layouts/                   # Header, navbar, footer, alerts & main template
│   │   ├── auth/                      # Login interface with lockout protection
│   │   ├── dashboard/                 # Summary cards, filterable table & "About" page
│   │   ├── groups/                    # Group listing, health check & member roster
│   │   ├── messages/                  # Original message inspection & response table
│   │   └── audit/                     # Audit trail inspector
│   └── helpers.php                    # Global helper functions (format_tz, csrf, config, etc.)
├── bin/
│   └── migrate.php                    # CLI database migration & default admin seeder
├── database/
│   └── migrations/
│       └── 001_initial_schema.sql     # Complete DDL migration script with indexes & foreign keys
├── public/                            # Only this directory is web-accessible
│   ├── index.php                      # Front controller with security headers & routing
│   ├── webhook.php                    # Dedicated direct Telegram webhook endpoint
│   ├── .htaccess                      # Apache rewrite rules & security restrictions
│   └── assets/
│       ├── css/app.css                # Custom Kaldis Coffee styling
│       └── js/app.js                  # Frontend utilities & alert handlers
├── storage/
│   ├── logs/                          # Webhook and system error logs
│   └── exports/                       # CSV export staging directory
└── tests/
    └── test_phase1.php                # Automated validation test suite
```

---

## 3. Telegram Integration & Monitoring Boundaries

### 3.1 How Monitoring Works
A Telegram bot (created via `@BotFather`) connects via an HTTPS webhook with a secret token. The bot must be added to each monitored group with privacy mode disabled or promoted to group administrator.

### 3.2 Technical Limitations (Explained on the Dashboard "About Monitoring" Page)
1. **No Historical Visibility**: The bot cannot read any messages sent before it joined a group.
2. **Membership Boundary**: The bot cannot see or monitor groups where it is not currently a member.
3. **No Native Full Roster Listing API**: Telegram Bot API provides only `getChatMember`, `getChatAdministrators`, and `getChatMemberCount`. The group roster is populated via CSV import and members seen sending messages.
4. **Join/Leave Events Require Admin Status**: Detecting `chat_member` join/leave events requires the bot to be an administrator and have `chat_member` in `allowed_updates`.
5. **Message Deletions Are Not Reported**: Telegram does not send webhook notifications for deleted messages. Deleted replies remain in the database.
6. **Bot-to-Bot Messages Are Suppressed**: Telegram never delivers messages sent by other bots.
7. **Anonymous Administrator Messages Cannot Be Tracked**: If an admin posts with "Send as Anonymous" enabled, the sender identity is replaced by the group entity or `GroupAnonymousBot`. It cannot be attributed to a registered user ID; the tracker ignores it and logs an audit warning.
8. **Privacy Mode Re-addition Requirement**: Disabling Privacy Mode in BotFather (`/setprivacy -> Disable`) only applies to groups joined *after* the change. For existing groups, the bot must be removed and re-added.
9. **No Monitoring of Personal Account Outside Groups**: The bot cannot monitor personal accounts outside registered groups where it is an active participant.

---

## 4. Message Tracking Rules

A message sent by a registered Telegram user ID becomes a tracked message when:
1. It contains the hashtag `#track` (case-insensitive, configurable), or
2. The sender replies to their own message with the command `/track`, or
3. An authorized administrator manually registers it from the dashboard.

### Extracted Reference Codes
If a message contains reference codes (e.g. `#task-102` or `#123`), the reference code is automatically extracted into `tracked_messages.reference_code` for reference-based reply matching.

### Edited Messages
When an `edited_message` update is received for a tracked message, the tracker updates `message_text`, records `edited_at` in UTC, and writes an audit log entry preserving the old and new text.

### Group Migration to Supergroup
When Telegram upgrades a standard group to a supergroup, it dispatches `migrate_to_chat_id` / `migrate_from_chat_id`. The application automatically updates the group's `telegram_chat_id` to the new supergroup ID and stores `previous_chat_id`, preserving all historical messages, members, and response records.

---

## 5. Security Controls & Role-Based Access Control (RBAC)

- **Admin Authentication**: Uses `password_hash()` (Argon2id/Bcrypt) and `password_verify()`.
- **Brute-Force Protection**: Login attempts are tracked in `login_attempts`. Accounts are locked out after 5 consecutive failed attempts for 15 minutes.
- **Session Security**: Session cookies configured with `HttpOnly`, `SameSite=Lax`, `Secure` (over HTTPS), 2-hour activity timeout, and `session_regenerate_id()` on login.
- **CSRF Defense**: Cryptographic CSRF tokens (`hash_equals()`) verified on all state-changing POST requests.
- **SQL Injection Prevention**: 100% prepared statements via PDO. No user input is directly concatenated.
- **XSS & Output Encoding**: All displayed text escaped with `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- **Security Headers**: `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`.
- **Three-Tier Role Hierarchy**:
  - `super_admin`: Full system control. Has access to in-app **Bot Configuration** (`/settings/bot`), one-click webhook management, administrator account management (`/settings/admins`), group management, message tracking, and audit logs.
  - `owner`: Operational management. Can register groups, toggle monitoring, run health checks, track messages, and review responses. Cannot change bot credentials or manage other admins.
  - `viewer`: Read-only access. Can view dashboard cards, reports, group roster, and audit logs. Cannot modify records.
- **Telegram Webhook Secret**: Every incoming request must provide the `X-Telegram-Bot-Api-Secret-Token` matching the configured token; non-matching requests are rejected immediately with HTTP 403 Forbidden.

---

## 6. Super Admin & Web-Based Bot Configuration

Super Administrators can configure the Telegram bot and manage webhooks directly within the dashboard at **`http://localhost/tgroup/settings/bot`**:
1. **Live Bot Diagnostics**: Shows real-time connection status, Bot ID, Username, and Group Privacy Mode status (notifying you if privacy mode is still enabled in `@BotFather`).
2. **Dynamic In-App Credentials**: Update Bot Token, Bot Username, Webhook Secret, Webhook URL, Authorized Sender User IDs, and `#track` trigger hashtag without editing code or config files.
3. **One-Click Webhook Registration**: Click **"Register / Update Webhook"** to automatically configure Telegram's webhook with `secret_token` and `allowed_updates` via the Bot API.
4. **Admin Accounts Manager** (`/settings/admins`): Create new administrators and assign roles (`super_admin`, `owner`, `viewer`).

Default Super Admin Accounts:
- **Username**: `superadmin` | **Password**: `KaldisSuperAdmin2026!`
- **Username**: `admin`      | **Password**: `KaldisAdmin2026!`

---

## 6. Installation & Deployment Guide (cPanel)

### Step 1: Database Setup
1. Log in to cPanel and open **MySQL Databases**.
2. Create a new database (e.g., `kaldis_reply_tracker`).
3. Create a database user with a strong password and assign it **ALL PRIVILEGES** on the database.

### Step 2: Upload Files
Upload the project files to your cPanel hosting account.
- Place `app/`, `database/`, `storage/`, and `bin/` **above** `public_html` (e.g., in `/home/username/kaldis-response-tracker/`).
- Place the contents of `public/` inside `public_html/` (or a dedicated subdomain document root).
- Ensure `storage/logs/` and `storage/exports/` have write permissions (`0755` or `0775`).

### Step 3: Configure Application
Copy `app/config/config.sample.php` to `app/config/config.php` and set your production values:
```php
'app' => [
    'url' => 'https://tracker.kaldiscoffee.com',
    'timezone' => 'Africa/Addis_Ababa',
    'debug' => false,
],
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'kaldis_reply_tracker',
    'user' => 'cpanel_db_user',
    'password' => 'YourStrongDbPassword',
],
'telegram' => [
    'bot_token' => '1234567890:ABCdefGhIjKlMnOpQrStUvWxYz',
    'bot_username' => 'KaldisTrackerBot',
    'webhook_secret' => 'KaldisSecureWebhookSecret_2026',
    'authorized_user_ids' => [123456789], // Your personal Telegram numeric user ID
    'track_hashtag' => '#track',
],
'security' => [
    'session_secure' => true, // Enforce Secure cookies over HTTPS
],
```

### Step 4: Run Migrations & Create Initial Owner Account
Via cPanel Terminal / SSH:
```bash
php bin/migrate.php
```
*Alternatively, you can import `database/migrations/001_initial_schema.sql` via cPanel **phpMyAdmin**.*

Default credentials created by `bin/migrate.php`:
- **Username**: `admin`
- **Password**: `KaldisAdmin2026!`
- **Role**: `owner`

*(Change this password immediately after your first sign-in).*

### Step 5: Telegram BotFather Configuration
1. Open Telegram and search for `@BotFather`.
2. Disable privacy mode:
   - Send `/setprivacy`
   - Select your bot (`@KaldisTrackerBot`)
   - Select **Disable**
3. Configure bot name and description as desired.

### Step 6: Register Telegram Webhook
Execute the following `curl` command (replace `<BOT_TOKEN>`, `<YOUR_DOMAIN>`, and `<WEBHOOK_SECRET>`):
```bash
curl -X POST "https://api.telegram.org/bot<BOT_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://<YOUR_DOMAIN>/webhook.php",
    "secret_token": "<WEBHOOK_SECRET>",
    "allowed_updates": ["message", "edited_message", "chat_member", "my_chat_member", "message_reaction"],
    "drop_pending_updates": false
  }'
```

Verify webhook registration:
```bash
curl "https://api.telegram.org/bot<BOT_TOKEN>/getWebhookInfo"
```

### Step 7: Add Bot to Groups & Register in Dashboard
1. Add the bot to your target Telegram group(s).
2. If Privacy Mode was changed after the bot was already in the group, remove the bot and re-add it.
3. For best member visibility and join/leave events, promote the bot to **Administrator**.
4. Log into the web dashboard, navigate to **Telegram Groups**, and click **Register New Group** by entering the group chat ID (e.g. `-100...`). The dashboard verifies bot membership via the Telegram API automatically.

---

## 7. Verification & Automated Test Suite

Run the automated test suite locally or on the server:
```bash
php tests/test_phase1.php
```

### Acceptance Test Coverage (Phase 1)
- [x] All 9 database tables created with foreign keys, indexes, and BIGINT IDs.
- [x] Password verification, session regeneration, and CSRF token verification.
- [x] Login rate limiting and account lockout after 5 failed attempts.
- [x] Webhook secret token validation (`X-Telegram-Bot-Api-Secret-Token` header; 403 on mismatch).
- [x] Duplicate update delivery idempotency (no duplicate rows in `telegram_updates`).
- [x] Automatic group discovery and member profile recording.
- [x] `#track` hashtag rule from authorized Telegram user IDs.
- [x] Reference code extraction (e.g. `#inventory-01`).
- [x] Anonymous admin message track rejection and audit warning.
- [x] Edited message update with audit logging.
- [x] Group migration to supergroup (`migrate_to_chat_id`) preserving history.
- [x] 4 Dashboard summary cards respecting active filters.
- [x] Response percentage calculation `Replied / (Eligible - Excluded - Unknown)`.
- [x] UTC storage and `Africa/Addis_Ababa` display timezone conversion.
- [x] Role permission enforcement (`owner` vs `viewer`).
