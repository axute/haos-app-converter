<?php if (isset($repository) && is_array($repository)): ?>
    <div id="repoInfo" class="mb-2 text-muted small" style="padding: 0 16px;">
        <?php if (!empty($repository['name'])): ?><strong><?= htmlspecialchars($repository['name']) ?></strong><?php endif; ?>
        <?php if (!empty($repository['name']) && !empty($repository['description'])): ?> — <?php endif; ?>
        <?php if (!empty($repository['description'])): ?><?= htmlspecialchars($repository['description']) ?><?php endif; ?>
    </div>
<?php endif; ?>

<div id="addonList" class="list-group list-group-flush mb-3">
    <?php if (empty($addons)): ?>
        <div class="list-group-item">No add-ons found</div>
    <?php else: ?>
        <?php foreach ($addons as $addon): ?>
            <?php $isSelf = ($addon['slug'] === 'haos_addon_converter'); ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="me-3 text-center" style="width: 40px; font-size: 24px;">
                            <?php if ($addon['has_local_icon']): ?>
                                <img src="<?= $basePath ?? '' ?>/addons/<?= $addon['slug'] ?>/icon.png" style="width: 32px; height: 32px;">
                            <?php else: ?>
                                📦
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($addon['name']) ?></strong>
                            <?php if (!empty($addon['detected_pm'])): ?>
                                <span class="badge bg-info text-dark rounded-pill ms-1" style="font-size: 0.7rem;"><?= htmlspecialchars($addon['detected_pm']) ?></span>
                            <?php endif; ?>
                            <?php if ($addon['quirks']): ?>
                                <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.7rem;">quirks</span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted d-block"><?= htmlspecialchars($addon['description']) ?></small>
                            <small class="text-muted">Version: <?= htmlspecialchars($addon['version']) ?> | Image: <code><?= htmlspecialchars($addon['image']) ?></code></small>
                        </div>
                    </div>
                    <?php if ($isSelf): ?>
                        <span class="badge bg-secondary rounded-pill">System</span>
                    <?php else: ?>
                        <div class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-ha-outline rounded-pill me-1" title="Info" 
                                    hx-get="/fragments/addon-details/<?= $addon['slug'] ?>" 
                                    hx-target="#addon-info-<?= $addon['slug'] ?>"
                                    onclick="bootstrap.Collapse.getOrCreateInstance(document.getElementById('addon-info-<?= $addon['slug'] ?>')).toggle()">
                                <span class="mdi mdi-information-outline"></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-ha-outline rounded-pill me-1" onclick="editAddon('<?= $addon['slug'] ?>')">
                                <span class="mdi mdi-pencil"></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-ha-outline rounded-pill text-danger border-danger" onclick="deleteAddon('<?= $addon['slug'] ?>')">
                                <span class="mdi mdi-delete"></span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="addon-info-<?= $addon['slug'] ?>" class="collapse mt-3"></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
