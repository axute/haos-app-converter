// Global variables
window.bashioVersions = [];
let easyMDE;
let startupScriptEditor;
let originalVersion = '1.0.0';
let iconBase64 = '';
if (typeof basePath === 'undefined') {
    window.basePath = window.location.pathname.replace(/\/$/, '');
}

// Initialization
// Make functions global immediately
window.editAddon = editAddon;
window.deleteAddon = deleteAddon;
window.cancelConverter = cancelConverter;
window.startNew = startNew;
window.openSettings = openSettings;
window.closeSettings = closeSettings;
window.fetchImageTags = fetchImageTags;
window.detectPM = detectPM;
window.addEnvVar = addEnvVar;
window.addPort = addPortMapping;
window.addMap = addMapMapping;
window.selfConvert = selfConvert;
window.updateVersion = updateVersion;

async function fetchBashioVersions() {
    const loader = document.getElementById('bashioLoader');
    if (loader) loader.style.display = 'inline-block';
    try {
        const response = await fetch(`${basePath}/bashio-versions`);
        window.bashioVersions = await response.json();
        const select = document.getElementById('bashio_version');
        if (select) {
            select.innerHTML = '';
            window.bashioVersions.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = v;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Error fetching bashio versions', e);
    } finally {
        if (loader) loader.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('bashio_version')) {
        fetchBashioVersions();
    }
    easyMDE = new EasyMDE({
        element: document.getElementById('long_description'),
        spellChecker: false,
        placeholder: 'Detailed description (Markdown)...',
        status: false,
        minHeight: '200px'
    });

    startupScriptEditor = CodeMirror.fromTextArea(document.getElementById('startup_script'), {
        mode: 'shell',
        theme: 'monokai',
        lineNumbers: true,
        viewportMargin: Infinity
    });

    // Ingress Toggle UI
    const ingressCheckbox = document.getElementById('ingress');
    if (ingressCheckbox) {
        ingressCheckbox.addEventListener('change', (e) => {
            document.getElementById('ingressOptions').style.display = e.target.checked ? 'block' : 'none';
            document.getElementById('webUiPortContainer').style.display = e.target.checked ? 'none' : 'block';
        });
    }

    // Quirks Mode Toggle UI
    const quirksCheckbox = document.getElementById('quirks_mode');
    if (quirksCheckbox) {
        quirksCheckbox.addEventListener('change', toggleEditableCheckboxes);
    }

    const allowUserEnvCheckboxGlobal = document.getElementById('allow_user_env');
    if (allowUserEnvCheckboxGlobal) {
        allowUserEnvCheckboxGlobal.addEventListener('change', toggleEditableCheckboxes);
    }

    // Form Submissions
    const converterForm = document.getElementById('converterForm');
    if (converterForm) {
        converterForm.addEventListener('submit', handleConverterSubmit);
    }

    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', handleSettingsSubmit);
    }
});

// Helper: does detected PM support installing bash, jq and curl?
function pmSupportsBashJqCurl(pm) {
    if (!pm) return false;
    pm = ('' + pm).toLowerCase();
    return ['apk', 'apt', 'apt-get', 'yum', 'dnf', 'microdnf', 'zypper', 'pacman'].includes(pm);
}

function haConfirm(message, onConfirm, title = 'Confirm', confirmText = 'OK', confirmClass = 'btn-ha-primary') {
    const modalEl = document.getElementById('haConfirmModal');
    if (!modalEl) return;

    const titleEl = document.getElementById('haConfirmTitle');
    const messageEl = document.getElementById('haConfirmMessage');
    const confirmBtn = document.getElementById('haConfirmBtn');

    if (titleEl) titleEl.innerText = title;
    if (messageEl) messageEl.innerHTML = message;
    
    if (confirmBtn) {
        confirmBtn.innerText = confirmText;
        confirmBtn.className = `btn btn-sm px-4 ${confirmClass}`;
        
        // Remove old listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        newConfirmBtn.addEventListener('click', () => {
            onConfirm();
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        });
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function resetAccordion() {
    const accordionItems = document.querySelectorAll('#formAccordion .accordion-collapse');
    accordionItems.forEach((item, index) => {
        const collapse = bootstrap.Collapse.getOrCreateInstance(item, { toggle: false });
        if (index === 0) {
            collapse.show();
        } else {
            collapse.hide();
        }
    });
}

function startNew() {
    const addonSelection = document.getElementById('addonSelection');
    const converterForm = document.getElementById('converterForm');
    const cancelBtn = document.getElementById('cancelBtn');
    const submitSection = document.getElementById('submitSection');
    const updateSection = document.getElementById('updateSection');
    const version = document.getElementById('version');
    const portsContainer = document.getElementById('portsContainer');
    const mapContainer = document.getElementById('mapContainer');
    const envVarsContainer = document.getElementById('envVarsContainer');
    const iconPreview = document.getElementById('icon_preview');
    const detectedPm = document.getElementById('detected_pm');
    
    if (addonSelection) addonSelection.style.display = 'none';
    if (converterForm) {
        converterForm.style.display = 'block';
        converterForm.reset();
    }
    if (cancelBtn) cancelBtn.style.display = 'block';
    if (submitSection) submitSection.style.display = 'block';
    if (updateSection) updateSection.style.display = 'none';
    
    if (version) {
        version.readOnly = false;
        version.value = '1.0.0';
    }
    
    if (easyMDE) easyMDE.value('');
    if (startupScriptEditor) startupScriptEditor.setValue('');
    
    resetAccordion();
    
    if (portsContainer) portsContainer.innerHTML = '';
    if (mapContainer) mapContainer.innerHTML = '';
    if (envVarsContainer) envVarsContainer.innerHTML = '';
    if (iconPreview) iconPreview.style.display = 'none';
    
    iconBase64 = '';
    
    if (detectedPm) detectedPm.value = '';
    const btnDetectPM = document.getElementById('btnDetectPM');
    if (btnDetectPM) btnDetectPM.disabled = false;
    const hint = document.getElementById('pmSupportInline');
    if (hint) hint.innerHTML = '';
    
    toggleEditableCheckboxes();
}

function cancelConverter() {
    haConfirm('Do you really want to cancel? All unsaved changes will be lost.', () => {
        const converterForm = document.getElementById('converterForm');
        const settingsView = document.getElementById('settingsView');
        const addonSelection = document.getElementById('addonSelection');
        const cancelBtn = document.getElementById('cancelBtn');
        const resultDiv = document.getElementById('result');

        if (converterForm) converterForm.style.display = 'none';
        if (settingsView) settingsView.style.display = 'none';
        if (addonSelection) addonSelection.style.display = 'block';
        if (cancelBtn) cancelBtn.style.display = 'none';
        if (resultDiv) resultDiv.style.display = 'none';
        
        // Refresh list if htmx is present
        if (typeof htmx !== 'undefined') {
            document.body.dispatchEvent(new Event('reload'));
        } else if (typeof loadAddons === 'function') {
            loadAddons();
        }
    }, 'Cancel Editing', 'Yes, cancel');
}

async function deleteAddon(slug) {
    haConfirm(`Do you really want to delete the add-on "${slug}"? This action cannot be undone.`, async () => {
        try {
            const response = await fetch(`${basePath}/addons/${slug}`, {
                method: 'DELETE'
            });
            const result = await response.json();
            if (result.status === 'success') {
                if (typeof htmx !== 'undefined') {
                    document.body.dispatchEvent(new Event('reload'));
                } else {
                    loadAddons();
                }
            } else {
                alert('Error during deletion: ' + result.message);
            }
        } catch (error) {
            alert('An error occurred: ' + error.message);
        }
    }, 'Delete Add-on', 'Delete', 'btn-danger');
}

function handleIconSelect(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            iconBase64 = e.target.result;
            const preview = document.getElementById('icon_preview');
            const previewImg = preview.querySelector('img');
            previewImg.src = iconBase64;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function addEnvVar(key = '', value = '', editable = false) {
    const container = document.getElementById('envVarsContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 env-var-row';
    div.innerHTML = `
        <input type="text" class="form-control env-key" placeholder="Key" value="${key}">
        <input type="text" class="form-control env-value" placeholder="Value" value="${value}">
        <div class="input-group-text">
            <input class="form-check-input mt-0 env-editable" type="checkbox" ${editable ? 'checked' : ''} title="Editable in HA GUI (needs Quirks Mode)" onchange="checkEnvWarnings()">
        </div>
        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove(); checkEnvWarnings();">×</button>
    `;
    container.appendChild(div);
    checkEnvWarnings();
}

function checkEnvWarnings() {
    const envWarning = document.getElementById('envWarning');
    if (!envWarning) return;
    
    const quirksMode = document.getElementById('quirks_mode');
    const quirksEnabled = quirksMode ? quirksMode.checked : false;
    const editables = document.querySelectorAll('.env-editable:checked');
    envWarning.style.display = quirksEnabled && editables.length > 0 ? 'block' : 'none';
}

function getEnvVars() {
    const rows = document.querySelectorAll('.env-var-row');
    const vars = [];
    rows.forEach(row => {
        const key = row.querySelector('.env-key').value.trim();
        const value = row.querySelector('.env-value').value;
        const editable = row.querySelector('.env-editable').checked;
        if (key) {
            vars.push({ key, value, editable });
        }
    });
    return vars;
}

function addPortMapping(containerPort = '', hostPort = '') {
    const container = document.getElementById('portsContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 port-mapping-row';
    div.innerHTML = `
        <input type="number" class="form-control port-container" placeholder="Container Port" value="${containerPort}">
        <span class="input-group-text">→</span>
        <input type="number" class="form-control port-host" placeholder="Host Port" value="${hostPort}">
        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">×</button>
    `;
    container.appendChild(div);
}

function getPortMappings() {
    const rows = document.querySelectorAll('.port-mapping-row');
    const ports = [];
    rows.forEach(row => {
        const container = row.querySelector('.port-container').value;
        const host = row.querySelector('.port-host').value;
        if (container && host) {
            ports.push({ container: parseInt(container), host: parseInt(host) });
        }
    });
    return ports;
}

function addMapMapping(folder = 'config', mode = 'rw') {
    const container = document.getElementById('mapContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 map-row';
    div.innerHTML = `
        <select class="form-select map-folder">
            <option value="config" ${folder === 'config' ? 'selected' : ''}>config</option>
            <option value="ssl" ${folder === 'ssl' ? 'selected' : ''}>ssl</option>
            <option value="share" ${folder === 'share' ? 'selected' : ''}>share</option>
            <option value="media" ${folder === 'media' ? 'selected' : ''}>media</option>
            <option value="addons" ${folder === 'addons' ? 'selected' : ''}>addons</option>
            <option value="backup" ${folder === 'backup' ? 'selected' : ''}>backup</option>
        </select>
        <select class="form-select map-mode" style="max-width: 100px;">
            <option value="rw" ${mode === 'rw' ? 'selected' : ''}>RW</option>
            <option value="ro" ${mode === 'ro' ? 'selected' : ''}>RO</option>
        </select>
        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">×</button>
    `;
    container.appendChild(div);
}

function getMapMappings() {
    const rows = document.querySelectorAll('.map-row');
    const maps = [];
    rows.forEach(row => {
        const folder = row.querySelector('.map-folder').value;
        const mode = row.querySelector('.map-mode').value;
        maps.push(`${folder}:${mode}`);
    });
    return maps;
}

async function updateVersion(type) {
    let parts = originalVersion.split('.').map(x => parseInt(x) || 0);
    while (parts.length < 3) parts.push(0);

    if (type === 'major') {
        parts[0]++;
        parts[1] = 0;
        parts[2] = 0;
    } else if (type === 'minor') {
        parts[1]++;
        parts[2] = 0;
    } else if (type === 'fix') {
        parts[2]++;
    }

    const newVersion = parts.join('.');
    document.getElementById('version').value = newVersion;
    document.getElementById('converterForm').dispatchEvent(new Event('submit'));
}

async function detectPM(force = false) {
    const image = document.getElementById('image').value.trim();
    const tag = document.getElementById('image_tag').value.trim() || 'latest';
    if (!image) return;
    
    const pmInput = document.getElementById('detected_pm');
    const btn = document.getElementById('btnDetectPM');
    const loader = document.getElementById('pmLoader');

    if (!force && pmInput.value && pmInput.value !== 'unknown' && pmInput.value !== 'error' && pmInput.value !== 'detecting...') {
        return;
    }

    pmInput.value = 'detecting...';
    btn.disabled = true;
    if (loader) loader.style.display = 'inline-block';
    
    try {
        const response = await fetch(`${basePath}/detect-pm?image=${encodeURIComponent(image)}&tag=${encodeURIComponent(tag)}`);
        const data = await response.json();
        const pm = data.pm || 'unknown';
        pmInput.value = pm;

        const hint = document.getElementById('pmSupportInline');
        if (hint) {
            hint.innerHTML = pmSupportsBashJqCurl(pm) ? '<span class="text-success"><span class="mdi mdi-check-circle"></span> bash, jq + curl installable</span>' : '';
        }
        
        if (pm !== 'unknown' && pm !== 'error') {
            btn.disabled = true;
        } else {
            btn.disabled = false;
        }
    } catch (e) {
        console.error('Error detecting PM', e);
        pmInput.value = 'error';
        btn.disabled = false;
    } finally {
        if (loader) loader.style.display = 'none';
    }
}

async function fetchImageTags() {
    const image = document.getElementById('image').value.trim();
    if (!image) return;
    
    const btn = document.getElementById('btnFetchTags');
    const loader = document.getElementById('tagLoader');
    const datalist = document.getElementById('imageTagOptions');
    
    btn.disabled = true;
    loader.style.display = 'inline-block';
    datalist.innerHTML = '<option value="loading...">';
    
    try {
        const response = await fetch(`${basePath}/image-tags?image=${encodeURIComponent(image)}`);
        const tags = await response.json();
        datalist.innerHTML = '';
        tags.forEach(tag => {
            const option = document.createElement('option');
            option.value = tag;
            if (tag.endsWith('.sig') || tag.startsWith('sha256-')) {
                option.textContent = tag + ' (Signature/Hash)';
            }
            datalist.appendChild(option);
        });
        datalist.dataset.image = image;
        detectPM();
    } catch (e) {
        console.error('Error fetching tags', e);
        datalist.innerHTML = '';
        alert('Failed to fetch tags for image: ' + image);
    } finally {
        btn.disabled = false;
        loader.style.display = 'none';
    }
}

async function handleConverterSubmit(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('name').value,
        description: document.getElementById('description').value,
        long_description: easyMDE.value(),
        icon_file: iconBase64,
        image: document.getElementById('image').value,
        image_tag: document.getElementById('image_tag').value,
        version: document.getElementById('version').value,
        ingress: document.getElementById('ingress').checked,
        ingress_port: parseInt(document.getElementById('ingress_port').value),
        ingress_stream: document.getElementById('ingress_stream').checked,
        panel_icon: document.getElementById('panel_icon').value || 'mdi:link-variant',
        webui_port: document.getElementById('web_ui_port').value ? parseInt(document.getElementById('web_ui_port').value) : null,
        backup: document.querySelector('input[name="backup"]:checked').value,
        detected_pm: document.getElementById('detected_pm').value,
        quirks: document.getElementById('quirks_mode').checked,
        allow_user_env: document.getElementById('allow_user_env').checked,
        bashio_version: document.getElementById('bashio_version').value,
        ports: getPortMappings(),
        map: getMapMappings(),
        env_vars: getEnvVars(),
        startup_script: startupScriptEditor.getValue()
    };

    const response = await fetch(`${basePath}/generate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    const result = await response.json();
    if (result.status === 'success') {
        const resultDiv = document.getElementById('result');
        const resultMessage = document.getElementById('resultMessage');
        if (resultMessage) {
            resultMessage.innerText = 'Add-on has been created/updated successfully.';
        }
        const resultDetails = document.getElementById('resultDetails');
        if (resultDetails) {
            resultDetails.innerHTML = `<p class="mb-0"><strong>Path:</strong> <code id="resultPath">${result.path}</code></p>`;
        }
        
        if (resultDiv) {
            resultDiv.style.display = 'block';
        }
        
        const converterForm = document.getElementById('converterForm');
        if (converterForm) converterForm.style.display = 'none';
        
        const addonSelection = document.getElementById('addonSelection');
        if (addonSelection) addonSelection.style.display = 'none';
        
        const cancelBtn = document.getElementById('cancelBtn');
        if (cancelBtn) cancelBtn.style.display = 'none';
        
        // Trigger htmx reload
        document.body.dispatchEvent(new Event('reload'));
        
        setTimeout(() => {
            if (resultDiv) resultDiv.style.display = 'none';
            if (addonSelection) addonSelection.style.display = 'block';
        }, 3000);
    } else {
        alert('Error: ' + result.message);
    }
}

async function editAddon(slug) {
    const response = await fetch(`${basePath}/addons/${slug}`);
    const addon = await response.json();

    const addonSelection = document.getElementById('addonSelection');
    const converterForm = document.getElementById('converterForm');
    const cancelBtn = document.getElementById('cancelBtn');
    
    if (addonSelection) addonSelection.style.display = 'none';
    if (converterForm) converterForm.style.display = 'block';
    if (cancelBtn) cancelBtn.style.display = 'block';

    const nameInput = document.getElementById('name');
    if (nameInput) nameInput.value = addon.name;
    
    const descInput = document.getElementById('description');
    if (descInput) descInput.value = addon.description;

    if (easyMDE) {
        easyMDE.value(addon.long_description || '');
        setTimeout(() => easyMDE.codemirror.refresh(), 100);
    }
    if (startupScriptEditor) {
        startupScriptEditor.setValue(addon.startup_script || '');
        setTimeout(() => startupScriptEditor.refresh(), 100);
    }
    
    const iconPreview = document.getElementById('icon_preview');
    if (addon.icon_file) {
        iconBase64 = addon.icon_file;
        if (iconPreview) {
            const previewImg = iconPreview.querySelector('img');
            if (previewImg) previewImg.src = iconBase64;
            iconPreview.style.display = 'block';
        }
    } else {
        iconBase64 = '';
        if (iconPreview) iconPreview.style.display = 'none';
    }

    const imageInput = document.getElementById('image');
    if (imageInput) imageInput.value = addon.image;
    
    const imageTagInput = document.getElementById('image_tag');
    if (imageTagInput) imageTagInput.value = addon.image_tag || 'latest';
    
    const versionInput = document.getElementById('version');
    if (versionInput) {
        versionInput.value = addon.version;
        versionInput.readOnly = true;
    }
    originalVersion = addon.version || '1.0.0';

    const submitSection = document.getElementById('submitSection');
    const updateSection = document.getElementById('updateSection');
    if (submitSection) submitSection.style.display = 'none';
    if (updateSection) updateSection.style.display = 'block';

    const ingressCheckbox = document.getElementById('ingress');
    if (ingressCheckbox) ingressCheckbox.checked = addon.ingress;
    
    const ingressPortInput = document.getElementById('ingress_port');
    if (ingressPortInput) ingressPortInput.value = addon.ingress_port;
    
    const ingressStreamCheckbox = document.getElementById('ingress_stream');
    if (ingressStreamCheckbox) ingressStreamCheckbox.checked = addon.ingress_stream || false;
    
    const panelIconInput = document.getElementById('panel_icon');
    if (panelIconInput) panelIconInput.value = addon.panel_icon || 'mdi:link-variant';
    
    let webuiPort = '';
    if (addon.webui) {
        const match = addon.webui.match(/\[PORT:(\d+)\]/);
        if (match) webuiPort = match[1];
    }
    const webUiPortInput = document.getElementById('web_ui_port');
    if (webUiPortInput) webUiPortInput.value = webuiPort;
    
    const backupDisabled = document.getElementById('backup_disabled');
    if (backupDisabled) backupDisabled.checked = true;
    
    if (addon.backup === 'hot') {
        const backupHot = document.getElementById('backup_hot');
        if (backupHot) backupHot.checked = true;
    } else if (addon.backup === 'cold') {
        const backupCold = document.getElementById('backup_cold');
        if (backupCold) backupCold.checked = true;
    }
    
    const detectedPmInput = document.getElementById('detected_pm');
    if (detectedPmInput) detectedPmInput.value = addon.detected_pm || 'unknown';
    
    const btnDetectPM = document.getElementById('btnDetectPM');
    if (btnDetectPM) {
        if (addon.detected_pm && addon.detected_pm !== 'unknown' && addon.detected_pm !== 'error') {
            btnDetectPM.disabled = true;
        } else {
            btnDetectPM.disabled = false;
        }
    }
    const hint = document.getElementById('pmSupportInline');
    if (hint) {
        const pm = addon.detected_pm || 'unknown';
        hint.innerHTML = pmSupportsBashJqCurl(pm) ? '<span class="text-success"><span class="mdi mdi-check-circle"></span> bash, jq + curl installable</span>' : '';
    }

    const ingressOptions = document.getElementById('ingressOptions');
    if (ingressOptions) ingressOptions.style.display = addon.ingress ? 'block' : 'none';
    
    const webUiPortContainer = document.getElementById('webUiPortContainer');
    if (webUiPortContainer) webUiPortContainer.style.display = addon.ingress ? 'none' : 'block';
    
    const portsContainer = document.getElementById('portsContainer');
    if (portsContainer) {
        portsContainer.innerHTML = '';
        if (addon.ports && addon.ports.length > 0) {
            addon.ports.forEach(p => {
                addPortMapping(p.container, p.host);
            });
        }
    }

    const mapContainer = document.getElementById('mapContainer');
    if (mapContainer) {
        mapContainer.innerHTML = '';
        if (addon.map && addon.map.length > 0) {
            addon.map.forEach(m => {
                addMapMapping(m);
            });
        }
    }

    const envVarsContainer = document.getElementById('envVarsContainer');
    if (envVarsContainer) {
        envVarsContainer.innerHTML = '';
        if (addon.env_vars && addon.env_vars.length > 0) {
            addon.env_vars.forEach(ev => {
                addEnvVar(ev.key, ev.value, ev.editable);
            });
        }
    }

    const quirksModeCheckbox = document.getElementById('quirks_mode');
    if (quirksModeCheckbox) quirksModeCheckbox.checked = addon.quirks || false;
    
    const allowUserEnvCheckbox = document.getElementById('allow_user_env');
    if (allowUserEnvCheckbox) allowUserEnvCheckbox.checked = addon.allow_user_env || false;

    const bashioVersionInput = document.getElementById('bashio_version');
    if (bashioVersionInput) {
        // Sicherstellen, dass die Liste geladen ist
        if (window.bashioVersions.length === 0) {
            await fetchBashioVersions();
        }
        bashioVersionInput.value = addon.bashio_version || window.bashioVersions[0] || '';
    }
    
    resetAccordion();

    toggleEditableCheckboxes();

    window.scrollTo(0, 0);
}

async function toggleAddonInfo(slug) {
    const panel = document.getElementById(`addon-info-${slug}`);
    if (panel.dataset.loaded !== '1') {
        panel.innerHTML = `<div class="d-flex justify-content-center p-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>`;
        try {
            const res = await fetch(`${basePath}/addons/${slug}`);
            const a = await res.json();
            
            const pm = a.detected_pm || 'unknown';
            const ingress = a.ingress ? `Enabled (${a.ingress_port}${a.ingress_stream ? ', stream' : ''})` : 'Disabled';
            const webuiPort = a.webui;
            const portsList = (a.ports || []).map(p => `${p.host}:${p.container}`).join(', ');
            const mapList = (a.map || []).join(', ');
            const envVars = (a.env_vars || []).slice(0, 5).map(e => `<code>${e.key}</code>`).join(', ');
            const envMore = (a.env_vars || []).length > 5 ? ` … (+${(a.env_vars||[]).length - 5} weitere)` : '';
            const backup = a.backup ? a.backup : 'disabled';
            const quirks = a.quirks ? 'Ja' : 'Nein';
            const allowUserEnv = a.allow_user_env ? 'Ja' : 'Nein';

            panel.innerHTML = `
                <div class="p-3 border rounded" style="background:#fafafa">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div><strong>Image:</strong> <code>${a.image}${a.image_tag ? ':'+a.image_tag : ''}</code></div>
                            <div><strong>Detected PM:</strong> ${pm}</div>
                            <div><strong>Backup:</strong> ${backup}</div>
                            <div><strong>Quirks:</strong> ${quirks} (User Env: ${allowUserEnv})</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div><strong>Ingress:</strong> ${ingress}</div>
                            ${webuiPort ? `<div><strong>WebUI Port:</strong> ${webuiPort}</div>` : ''}
                            <div><strong>Ports:</strong> ${portsList || '—'}</div>
                            <div><strong>Mounts:</strong> ${mapList || '—'}</div>
                        </div>
                        <div class="col-12">
                            <div><strong>Env:</strong> ${envVars || '—'}${envMore}</div>
                        </div>
                    </div>
                </div>
            `;
            panel.dataset.loaded = '1';
        } catch (e) {
            panel.innerHTML = `<div class="text-danger">Fehler beim Laden der Informationen.</div>`;
        }
    }
    const collapse = bootstrap.Collapse.getOrCreateInstance(panel);
    collapse.toggle();
}

async function loadAddons() {
    const response = await fetch(`${basePath}/addons`);
    const data = await response.json();
    const repoInfoDiv = document.getElementById('repoInfo');
    let addons = [];
    let repoName = '';
    let repoDesc = '';

    if (Array.isArray(data)) {
        addons = data;
        repoInfoDiv.innerHTML = '';
    } else {
        addons = data.addons || [];
        if (data.repository) {
            repoName = data.repository.name || '';
            repoDesc = data.repository.description || '';
            if (repoName || repoDesc) {
                repoInfoDiv.innerHTML = `${repoName ? `<strong>${repoName}</strong>` : ''}${repoName && repoDesc ? ' — ' : ''}${repoDesc ? `${repoDesc}` : ''}`;
            } else {
                repoInfoDiv.innerHTML = '';
            }
        } else {
            repoInfoDiv.innerHTML = '';
        }
    }

    const list = document.getElementById('addonList');
    if (addons.length === 0) {
        list.innerHTML = '<div class="list-group-item">No add-ons found</div>';
        return;
    }
    list.innerHTML = addons.map(addon => {
        const isSelf = addon.slug === 'haos_addon_converter';
        return `
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="me-3 text-center" style="width: 40px; font-size: 24px;">
                        ${addon.has_local_icon 
                            ? `<img src="${basePath}/addons/${addon.slug}/icon.png" style="width: 32px; height: 32px;">` 
                            : '📦'} 
                    </div>
                    <div>
                        <strong>${addon.name}</strong>
                        ${addon.detected_pm ? `<span class="badge bg-info text-dark rounded-pill ms-1" style="font-size: 0.7rem;">${addon.detected_pm}</span>` : ''}
                        ${addon.detected_pm && pmSupportsBashJqCurl(addon.detected_pm) ? `<span class="text-success ms-1" title="bash, jq + curl installable"><span class="mdi mdi-check-circle"></span></span>` : ''}
                        ${addon.quirks ? `<span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.7rem;">quirks</span>` : ''}
                        <br>
                        <small class="text-muted d-block">${addon.description}</small>
                        <small class="text-muted">Version: ${addon.version} | Image: <code>${addon.image}</code></small>
                    </div>
                </div>
                ${isSelf ? 
                    '<span class="badge bg-secondary rounded-pill">System</span>' : 
                    `<div class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-ha-outline rounded-pill me-1" title="Info" onclick="toggleAddonInfo('${addon.slug}')">
                            <span class="mdi mdi-information-outline"></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-ha-outline rounded-pill me-1" onclick="editAddon('${addon.slug}')">
                            <span class="mdi mdi-pencil"></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-ha-outline rounded-pill text-danger border-danger" onclick="deleteAddon('${addon.slug}')">
                            <span class="mdi mdi-delete"></span>
                        </button>
                    </div>`
                }
            </div>
            <div id="addon-info-${addon.slug}" class="collapse mt-3"></div>
        </div>
    `}).join('');
}

// Initial load removed - handled by HTMX in templates/index.php
/*
if (document.getElementById('addonList')) {
    loadAddons();
}
*/

async function openSettings() {
    document.getElementById('addonSelection').style.display = 'none';
    document.getElementById('converterForm').style.display = 'none';
    document.getElementById('settingsView').style.display = 'block';
    document.getElementById('cancelBtn').style.display = 'none';
    
    const response = await fetch(`${basePath}/settings`);
    const settings = await response.json();
    document.getElementById('repo_name').value = settings.name;
    document.getElementById('repo_maintainer').value = settings.maintainer;
}

function closeSettings() {
    document.getElementById('settingsView').style.display = 'none';
    document.getElementById('addonSelection').style.display = 'block';
}

async function handleSettingsSubmit(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('repo_name').value,
        maintainer: document.getElementById('repo_maintainer').value
    };
    const response = await fetch(`${basePath}/settings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const result = await response.json();
    if (result.status === 'success') {
        document.getElementById('settingsResult').style.display = 'block';
        setTimeout(() => {
            document.getElementById('settingsResult').style.display = 'none';
            closeSettings();
        }, 1500);
    }
}

async function selfConvert(tag = 'latest') {
    haConfirm(`Do you want to export the HAOS Add-on Converter (Version: ${tag}) as a Home Assistant add-on? The internal version will be incremented automatically.`, async () => {
        try {
            const response = await fetch(`${basePath}/self-convert`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tag: tag })
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                alert('Add-on successfully created in: ' + result.path);
                document.body.dispatchEvent(new Event('reload'));
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            alert('An error occurred: ' + e.message);
        }
    }, 'Export Converter', 'Export');
}

async function loadTags(btn) {
    const list = document.getElementById('tagList');
    if (list.dataset.loaded === 'true') return;
    
    try {
        const response = await fetch(`${basePath}/tags`);
        const tags = await response.json();
        
        const header = list.querySelector('.dropdown-header');
        const divider = list.querySelector('.dropdown-divider');
        list.innerHTML = '';
        list.appendChild(header.parentElement === list ? header : header.closest('li'));
        list.appendChild(divider.parentElement === list ? divider : divider.closest('li'));
        
        tags.forEach(tag => {
            const li = document.createElement('li');
            li.innerHTML = `<a class="dropdown-item" href="#" onclick="selfConvert('${tag}')">${tag}</a>`;
            list.appendChild(li);
        });
        list.dataset.loaded = 'true';
    } catch (e) {
        console.error('Error loading tags', e);
    }
}

function toggleEditableCheckboxes() {
    const quirksMode = document.getElementById('quirks_mode');
    const allowUserEnv = document.getElementById('allow_user_env');
    if (!quirksMode) return;
    
    const quirksEnabled = quirksMode.checked;
    const allowUserEnvEnabled = allowUserEnv ? allowUserEnv.checked : false;
    
    const startupScriptSection = document.getElementById('startupScriptSection');
    if (startupScriptSection) {
        // Sektion anzeigen wenn Quirks ODER Allow User Env aktiv ist
        startupScriptSection.style.display = (quirksEnabled || allowUserEnvEnabled) ? 'block' : 'none';
        if ((quirksEnabled || allowUserEnvEnabled) && startupScriptEditor) {
            setTimeout(() => startupScriptEditor.refresh(), 100);
        }
    }
    
    document.querySelectorAll('.env-editable').forEach(cb => {
        cb.disabled = !quirksEnabled;
        if (!quirksEnabled) cb.checked = false;
    });
    checkEnvWarnings();
}