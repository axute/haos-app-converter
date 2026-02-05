<div class="p-3 border rounded" style="background:#fafafa">
    <div class="row g-2">
        <div class="col-12 col-md-6">
            <div><strong>Image:</strong> <code><?= htmlspecialchars($addon['image'] ?? '') ?><?= !empty($addon['image_tag']) ? ':' . htmlspecialchars($addon['image_tag']) : '' ?></code></div>
            <div><strong>Detected PM:</strong> <?= htmlspecialchars($addon['detected_pm'] ?: 'unknown') ?></div>
            <div><strong>Backup:</strong> <?= htmlspecialchars($addon['backup'] ?: 'disabled') ?></div>
            <div><strong>Quirks:</strong> <?= $addon['quirks'] ? 'Ja' : 'Nein' ?></div>
        </div>
        <div class="col-12 col-md-6">
            <div><strong>Ingress:</strong> <?= $addon['ingress'] ? 'Enabled (' . $addon['ingress_port'] . ($addon['ingress_stream'] ? ', stream' : '') . ')' : 'Disabled' ?></div>
            <?php if (!empty($addon['webui'])): ?>
                <div><strong>WebUI Port:</strong> <?= htmlspecialchars($addon['webui']) ?></div>
            <?php endif; ?>
            <div><strong>Ports:</strong> 
                <?php 
                $ports = [];
                foreach (($addon['ports'] ?: []) as $p) $ports[] = $p['host'] . ':' . $p['container'];
                echo !empty($ports) ? htmlspecialchars(implode(', ', $ports)) : '—';
                ?>
            </div>
            <div><strong>Mounts:</strong> <?= !empty($addon['map']) ? htmlspecialchars(implode(', ', $addon['map'])) : '—' ?></div>
        </div>
        <div class="col-12">
            <div><strong>Env:</strong> 
                <?php 
                $envs = array_slice($addon['env_vars'] ?: [], 0, 5);
                foreach ($envs as $e) echo '<code>' . htmlspecialchars($e['key']) . '</code> ';
                if (count($addon['env_vars'] ?: []) > 5) echo ' … (+' . (count($addon['env_vars']) - 5) . ' weitere)';
                if (empty($addon['env_vars'])) echo '—';
                ?>
            </div>
        </div>
    </div>
</div>
