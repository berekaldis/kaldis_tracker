<footer class="app-footer">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>
            <strong>Kaldi's Coffee Ethiopia</strong> &bull; Telegram Conversation Tracker (v2.0)
        </div>
        <div class="text-muted font-monospace small">
            Timezone: <?= e(config('app.timezone', 'Africa/Addis_Ababa')) ?> (EAT) &bull; Retention: <?= (int)config('app.retention_days', 180) ?> days
        </div>
    </div>
</footer>
