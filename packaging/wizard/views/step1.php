<h2>Step 1: System Status</h2>
<p>Review the health of your system before configuring plugins and settings.</p>

<div class="status-grid" id="system-checks">
    <?php foreach ($systemChecks as $key => $check): ?>
        <div class="status-card status-<?= $check['status'] ?>">
            <h3><?= htmlspecialchars($check['name']) ?></h3>
            <span class="status-badge"><?= $check['status'] ?></span>
            <?php if (isset($check['version'])): ?>
                <p>Version: <?= htmlspecialchars($check['version']) ?></p>
            <?php endif; ?>
            <?php if (isset($check['message'])): ?>
                <p><?= htmlspecialchars($check['message']) ?></p>
            <?php endif; ?>
            <?php if (isset($check['free_gb'])): ?>
                <p><?= $check['free_gb'] ?>GB free / <?= $check['total_gb'] ?>GB total</p>
            <?php endif; ?>
            <?php if (isset($check['available_mb'])): ?>
                <p><?= $check['available_mb'] ?>MB available / <?= $check['total_mb'] ?>MB total</p>
            <?php endif; ?>
            <?php if (isset($check['cluster_status'])): ?>
                <p>Cluster: <?= htmlspecialchars($check['cluster_status']) ?></p>
            <?php endif; ?>
            <?php if (isset($check['extensions'])): ?>
                <ul class="ext-list">
                    <?php foreach ($check['extensions'] as $ext => $ok): ?>
                        <li class="<?= $ok ? 'ext-ok' : 'ext-missing' ?>"><?= $ext ?>: <?= $ok ? 'OK' : 'Missing' ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<button class="btn btn-secondary" onclick="refreshChecks()">Refresh</button>
