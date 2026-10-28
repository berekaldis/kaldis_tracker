<footer class="footer-kaldis">
    <div class="container text-center">
        <div class="small mb-1">
            <strong>Kaldis Coffee Telegram Reply Tracker (v2)</strong> &bull; All timestamps stored in UTC and rendered in <code><?= e(config('app.timezone', 'Africa/Addis_Ababa')) ?></code>
        </div>
        <div class="text-white-50" style="font-size: 0.75rem;">
            Notice: Messages in registered groups are captured and retained for <?= (int)config('app.retention_days', 180) ?> days for operational response verification. Group members must be notified of monitoring.
        </div>
    </div>
</footer>
