<?php
$flashes = flash();
if (!empty($flashes)):
    foreach ($flashes as $type => $messages):
        $bsClass = match($type) {
            'success' => 'success',
            'danger', 'error' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            default => 'primary'
        };
        foreach ($messages as $msg):
?>
    <div class="alert alert-<?= $bsClass ?> alert-dismissible fade show shadow-sm" role="alert">
        <?= e($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php
        endforeach;
    endforeach;
endif;
?>
