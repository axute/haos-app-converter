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
window.editApp = editApp;
window.deleteApp = deleteApp;
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
window.toggleVersionFixation = toggleVersionFixation;
window.toggleAutoUpdate = toggleAutoUpdate;
window.updateAppMetadata = updateAppMetadata;

async function updateAppMetadata(slug, payload) {
    try {
        const res = await fetch(`${basePath}/apps/${slug}/metadata`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            console.warn('Fehler beim Speichern der Metadaten:', data);
        }
        return data;
    } catch (e) {
        console.error('Netzwerkfehler beim Speichern der Metadaten', e);
    }

}

async function toggleVersionFixation(slug, checked) {
    await updateAppMetadata(slug, { version_fixation: !!checked });
    // Falls der Edit-Dialog offen ist, Update-Buttons aus-/einblenden
    const versionButtonsGroup = document.querySelector('#updateSection .btn-group');
    if (versionButtonsGroup) {
        versionButtonsGroup.style.display = checked ? 'none' : 'inline-flex';
    }
}

async function toggleAutoUpdate(slug, checked) {
    await updateAppMetadata(slug, { auto_update: !!checked });
}

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

    // Icon handling
    const iconFileInput = document.getElementById('icon_file');
    if (iconFileInput) {
        iconFileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                iconBase64 = event.target.result;
                const iconPreview = document.getElementById('icon_preview');
                if (iconPreview) {
                    const previewImg = iconPreview.querySelector('img');
                    if (previewImg) previewImg.src = iconBase64;
                    iconPreview.style.display = 'block';
                }
            };
            reader.readAsDataURL(file);
        });
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

    // Watchdog UI toggle (show path only for http/https)
    const watchdogProtocol = document.getElementById('watchdog_protocol');
    const watchdogPortField = document.getElementById('watchdogPortField');
    const watchdogHttpFields = document.getElementById('watchdogHttpFields');
    const updateWatchdogUi = () => {
        if (!watchdogProtocol) return;
        const v = watchdogProtocol.value;
        if (watchdogPortField) watchdogPortField.style.display = v ? 'block' : 'none';
        if (watchdogHttpFields) watchdogHttpFields.style.display = (v === 'http' || v === 'https') ? 'block' : 'none';
    };
    if (watchdogProtocol) {
        watchdogProtocol.addEventListener('change', updateWatchdogUi);
        updateWatchdogUi();
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

function haAlert(message, title = 'Info') {
    const modalEl = document.getElementById('haAlertModal');
    if (!modalEl) {
        alert(message);
        return;
    }

    const titleEl = document.getElementById('haAlertTitle');
    const messageEl = document.getElementById('haAlertMessage');

    if (titleEl) titleEl.innerText = title;
    if (messageEl) messageEl.innerHTML = message;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function resetAccordion() {
    const accordionItems = document.querySelectorAll('#formAccordion .accordion-collapse');
    accordionItems.forEach((item, index) => {
        const collapse = bootstrap.Collapse.getOrCreateInstance(item, {toggle: false});
        if (index === 0) {
            collapse.show();
        } else {
            collapse.hide();
        }
    });
}

function startNew() {
    const appSelection = document.getElementById('appSelection');
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

    if (appSelection) appSelection.style.display = 'none';
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

    // Reset Health fields
    const timeoutInput = document.getElementById('timeout');
    if (timeoutInput) timeoutInput.value = '';
    const wdProto = document.getElementById('watchdog_protocol');
    if (wdProto) wdProto.value = '';
    const wdPort = document.getElementById('watchdog_port');
    if (wdPort) wdPort.value = '';
    const wdPath = document.getElementById('watchdog_path');
    if (wdPath) wdPath.value = '';
    const wdHttpFields = document.getElementById('watchdogHttpFields');
    if (wdHttpFields) wdHttpFields.style.display = 'none';

    const urlInput = document.getElementById('url');
    if (urlInput) urlInput.value = '';

    resetAccordion();

    if (portsContainer) portsContainer.innerHTML = '';
    if (mapContainer) mapContainer.innerHTML = '';
    if (envVarsContainer) envVarsContainer.innerHTML = '';
    if (iconPreview) iconPreview.style.display = 'none';

    featureFlags.forEach(flag => {
        const el = document.getElementById(flag);
        if (el) {
            el.checked = false;
        }
    });

    document.querySelectorAll('.privileged-checkbox').forEach(el => el.checked = false);

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
        const appSelection = document.getElementById('appSelection');
        const cancelBtn = document.getElementById('cancelBtn');
        const resultDiv = document.getElementById('result');

        if (converterForm) converterForm.style.display = 'none';
        if (settingsView) settingsView.style.display = 'none';
        if (appSelection) appSelection.style.display = 'block';
        if (cancelBtn) cancelBtn.style.display = 'none';
        if (resultDiv) resultDiv.style.display = 'none';

        // Refresh list if htmx is present
        if (typeof htmx !== 'undefined') {
            document.body.dispatchEvent(new Event('reload'));
        } else if (typeof loadApps === 'function') {
            loadApps();
        }
    }, 'Cancel Editing', 'Yes, cancel');
}

async function deleteApp(slug) {
    haConfirm(`Do you really want to delete the app "${slug}"? This action cannot be undone.`, async () => {
        try {
            const response = await fetch(`${basePath}/apps/${slug}`, {
                method: 'DELETE'
            });
            const result = await response.json();
            if (result.status === 'success') {
                if (typeof htmx !== 'undefined') {
                    document.body.dispatchEvent(new Event('reload'));
                } else {
                    await loadApps();
                }
            } else {
                haAlert('Error during deletion: ' + result.message, 'Error');
            }
        } catch (error) {
            haAlert('An error occurred: ' + error.message, 'Error');
        }
    }, 'Delete App', 'Delete', 'btn-danger');
}

function handleIconSelect(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
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
            vars.push({key, value, editable});
        }
    });
    return vars;
}

function addPortMapping(containerPort = '', hostPort = '', protocol = 'tcp', description = '') {
    const container = document.getElementById('portsContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 port-mapping-row';
    div.innerHTML = `
        <input type="number" class="form-control port-container" placeholder="Container Port" value="${containerPort}" style="max-width: 140px;">
        <select class="form-select port-protocol" style="max-width: 90px;">
            <option value="tcp" ${protocol === 'tcp' ? 'selected' : ''}>TCP</option>
            <option value="udp" ${protocol === 'udp' ? 'selected' : ''}>UDP</option>
        </select>
        <span class="input-group-text">→</span>
        <input type="number" class="form-control port-host" placeholder="Host Port" value="${hostPort}" style="max-width: 140px;">
        <input type="text" class="form-control port-description" placeholder="Description (optional)" value="${description}">
        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">×</button>
    `;
    container.appendChild(div);
}

function getPortMappings() {
    const rows = document.querySelectorAll('.port-mapping-row');
    const ports = {};
    rows.forEach(row => {
        const container = row.querySelector('.port-container').value;
        const protocol = row.querySelector('.port-protocol').value;
        const host = row.querySelector('.port-host').value;
        if (container) {
            const key = `${container}/${protocol}`;
            ports[key] = host ? parseInt(host) : null;
        }
    });
    return ports;
}

function addMapMapping(folder = 'data', mode = 'rw', path = '') {
    const container = document.getElementById('mapContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 map-row';
    div.innerHTML = `
        <select class="form-select map-folder" style="max-width: 120px;">
            <option value="addon_config" ${folder === 'addon_config' ? 'selected' : ''}>addon_config</option>
            <option value="addons" ${folder === 'addons' ? 'selected' : ''}>addons</option>
            <option value="all_addon_configs" ${folder === 'all_addon_configs' ? 'selected' : ''}>all_addon_configs</option>
            <option value="data" ${folder === 'data' ? 'selected' : ''}>data</option>
            <option value="backup" ${folder === 'backup' ? 'selected' : ''}>backup</option>
            <option value="homeassistant_config" ${folder === 'homeassistant_config' ? 'selected' : ''}>homeassistant_config</option>
            <option value="media" ${folder === 'media' ? 'selected' : ''}>media</option>
            <option value="ssl" ${folder === 'ssl' ? 'selected' : ''}>ssl</option>
            <option value="share" ${folder === 'share' ? 'selected' : ''}>share</option>
        </select>
        <select class="form-select map-mode" style="max-width: 80px;">
            <option value="rw" ${mode === 'rw' ? 'selected' : ''}>RW</option>
            <option value="ro" ${mode === 'ro' ? 'selected' : ''}>RO</option>
        </select>
        <input type="text" class="form-control map-path" placeholder="Path (optional, e.g. /data)" value="${path}">
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
        const path = row.querySelector('.map-path').value.trim();
        maps.push({folder, mode, path: path || null});
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

    document.getElementById('version').value = parts.join('.');
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
        const response = await fetch(`${basePath}/image/${image}/pm/${tag}`);
        const data = await response.json();
        const pm = data.pm || 'unknown';
        pmInput.value = pm;

        const hint = document.getElementById('pmSupportInline');
        if (hint) {
            hint.innerHTML = pmSupportsBashJqCurl(pm) ? '<span class="text-success"><span class="mdi mdi-check-circle"></span> bash, jq + curl installable</span>' : '';
        }

        btn.disabled = pm !== 'unknown' && pm !== 'error';
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
        const response = await fetch(`${basePath}/image/${image}/tags`);
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
        await detectPM();
    } catch (e) {
        console.error('Error fetching tags', e);
        datalist.innerHTML = '';
        haAlert('Failed to fetch tags for image: ' + image, 'Error');
    } finally {
        btn.disabled = false;
        loader.style.display = 'none';
    }
}

const featureFlags = [
    'host_network', 'host_ipc', 'host_dbus', 'host_pid', 'host_uts',
    'hassio_api', 'homeassistant_api', 'docker_api', 'full_access',
    'audio', 'video', 'gpio', 'usb', 'uart', 'udev',
    'devicetree', 'kernel_modules', 'stdin', 'legacy', 'auth_api',
    'advanced', 'realtime', 'journald', 'apparmor', 'discovery'
];

function getPrivileged() {
    const caps = [];
    document.querySelectorAll('.privileged-checkbox:checked').forEach(el => {
        caps.push(el.value);
    });
    return caps;
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
        ingress_entry: document.getElementById('ingress_entry').value,
        ingress_stream: document.getElementById('ingress_stream').checked,
        panel_icon: document.getElementById('panel_icon').value || 'mdi:link-variant',
        panel_title: document.getElementById('panel_title').value,
        panel_admin: document.getElementById('panel_admin').checked,
        webui_port: document.getElementById('web_ui_port').value ? parseInt(document.getElementById('web_ui_port').value) : null,
        webui_protocol: document.getElementById('web_ui_protocol').value,
        webui_path: document.getElementById('web_ui_path').value,
        backup: document.querySelector('input[name="backup"]:checked').value,
        tmpfs: document.getElementById('tmpfs').checked,
        detected_pm: document.getElementById('detected_pm').value,
        quirks: document.getElementById('quirks_mode').checked,
        allow_user_env: document.getElementById('allow_user_env').checked,
        bashio_version: document.getElementById('bashio_version').value,
        url: document.getElementById('url') ? document.getElementById('url').value : null,
        ports: getPortMappings(),
        map: getMapMappings(),
        env_vars: getEnvVars(),
        startup_script: startupScriptEditor.getValue(),
        privileged: getPrivileged(),
        // Health
        timeout: (document.getElementById('timeout') && document.getElementById('timeout').value !== '') ? parseInt(document.getElementById('timeout').value) : null,
        watchdog: (function(){
            const protoEl = document.getElementById('watchdog_protocol');
            const portEl = document.getElementById('watchdog_port');
            const pathEl = document.getElementById('watchdog_path');
            const proto = protoEl ? protoEl.value : '';
            const portVal = portEl ? portEl.value : '';
            const pathVal = pathEl ? pathEl.value : '';
            if (!proto || !portVal) return null;
            const p = parseInt(portVal);
            if (proto === 'tcp') {
                return `tcp://[HOST]:[PORT:${p}]`;
            }
            const path = (pathVal && pathVal.startsWith('/')) ? pathVal : (pathVal ? '/' + pathVal : '/');
            return `${proto}://[HOST]:[PORT:${p}]${path}`;
        })(),
        feature_flags: {},
        // Feature Flags flach mappen
        host_network: document.getElementById('host_network').checked,
        host_ipc: document.getElementById('host_ipc').checked,
        host_dbus: document.getElementById('host_dbus').checked,
        host_pid: document.getElementById('host_pid').checked,
        host_uts: document.getElementById('host_uts').checked,
        hassio_api: document.getElementById('hassio_api').checked,
        homeassistant_api: document.getElementById('homeassistant_api').checked,
        docker_api: document.getElementById('docker_api').checked,
        full_access: document.getElementById('full_access').checked,
        audio: document.getElementById('audio').checked,
        video: document.getElementById('video').checked,
        gpio: document.getElementById('gpio').checked,
        usb: document.getElementById('usb').checked,
        uart: document.getElementById('uart').checked,
        udev: document.getElementById('udev').checked,
        devicetree: document.getElementById('devicetree').checked,
        kernel_modules: document.getElementById('kernel_modules').checked,
        stdin: document.getElementById('stdin').checked,
        legacy: document.getElementById('legacy').checked,
        auth_api: document.getElementById('auth_api').checked,
        advanced: document.getElementById('advanced').checked,
        realtime: document.getElementById('realtime').checked,
        journald: document.getElementById('journald').checked,
        apparmor: document.getElementById('apparmor').checked,
        discovery: document.getElementById('discovery').checked
    };

    // featureFlags.forEach(flag => {
    //     const el = document.getElementById(flag);
    //     if (el) {
    //         data.feature_flags[flag] = el.checked;
    //     }
    // });

    const response = await fetch(`${basePath}/apps/generate`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    });

    const result = await response.json();
    if (result.status === 'success') {
        const resultDiv = document.getElementById('result');
        const resultMessage = document.getElementById('resultMessage');
        if (resultMessage) {
            resultMessage.innerText = 'App has been created/updated successfully.';
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

        const appSelection = document.getElementById('appSelection');
        if (appSelection) appSelection.style.display = 'none';

        const cancelBtn = document.getElementById('cancelBtn');
        if (cancelBtn) cancelBtn.style.display = 'none';

        // Trigger htmx reload
        document.body.dispatchEvent(new Event('reload'));

        setTimeout(() => {
            if (resultDiv) resultDiv.style.display = 'none';
            if (appSelection) appSelection.style.display = 'block';
        }, 3000);
    } else {
        haAlert('Error: ' + result.message, 'Error');
    }
}

async function editApp(slug) {
    const response = await fetch(`${basePath}/apps/${slug}`);
    const app = await response.json();

    const appSelection = document.getElementById('appSelection');
    const converterForm = document.getElementById('converterForm');
    const cancelBtn = document.getElementById('cancelBtn');

    if (appSelection) appSelection.style.display = 'none';
    if (converterForm) converterForm.style.display = 'block';
    if (cancelBtn) cancelBtn.style.display = 'block';

    const nameInput = document.getElementById('name');
    if (nameInput) nameInput.value = app.name;

    const descInput = document.getElementById('description');
    if (descInput) descInput.value = app.description;

    const urlInput = document.getElementById('url');
    if (urlInput) urlInput.value = app.url || '';

    if (easyMDE) {
        easyMDE.value(app.long_description || '');
        setTimeout(() => easyMDE.codemirror.refresh(), 100);
    }
    if (startupScriptEditor) {
        startupScriptEditor.setValue(app.startup_script || '');
        setTimeout(() => startupScriptEditor.refresh(), 100);
    }

    const iconPreview = document.getElementById('icon_preview');
    if (app.icon_file) {
        iconBase64 = app.icon_file;
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
    if (imageInput) imageInput.value = app.image;

    const imageTagInput = document.getElementById('image_tag');
    if (imageTagInput) imageTagInput.value = app.image_tag || 'latest';

    const versionInput = document.getElementById('version');
    if (versionInput) {
        versionInput.value = app.version;
        versionInput.readOnly = true;
    }
    originalVersion = app.version || '1.0.0';

    const submitSection = document.getElementById('submitSection');
    const updateSection = document.getElementById('updateSection');
    if (submitSection) submitSection.style.display = 'none';
    if (updateSection) updateSection.style.display = 'block';

    // Update-Buttons (Major/Minor/Fix) bei aktiver Version-Fixierung ausblenden
    const versionButtonsGroup = document.querySelector('#updateSection .btn-group');
    if (versionButtonsGroup) {
        versionButtonsGroup.style.display = app.version_fixation ? 'none' : 'inline-flex';
    }

    const ingressCheckbox = document.getElementById('ingress');
    if (ingressCheckbox) ingressCheckbox.checked = app.ingress;

    const ingressPortInput = document.getElementById('ingress_port');
    if (ingressPortInput) ingressPortInput.value = app.ingress_port;

    const ingressEntryInput = document.getElementById('ingress_entry');
    if (ingressEntryInput) ingressEntryInput.value = app.ingress_entry || '/';

    const ingressStreamCheckbox = document.getElementById('ingress_stream');
    if (ingressStreamCheckbox) ingressStreamCheckbox.checked = app.ingress_stream || false;

    const panelIconInput = document.getElementById('panel_icon');
    if (panelIconInput) panelIconInput.value = app.panel_icon || 'mdi:link-variant';

    const panelTitleInput = document.getElementById('panel_title');
    if (panelTitleInput) panelTitleInput.value = app.panel_title || '';

    const panelAdminCheckbox = document.getElementById('panel_admin');
    if (panelAdminCheckbox) panelAdminCheckbox.checked = app.panel_admin !== false;

    let webuiPort = '';
    let webuiProtocol = 'http';
    let webuiPath = '/';
    if (app.webui) {
        // Parse "scheme://[HOST]:[PORT:xxxx]/path"
        const match = app.webui.match(/^(\w+):\/\/\[HOST]:\[PORT:(\d+)](.*)$/);
        if (match) {
            webuiProtocol = match[1];
            webuiPort = match[2];
            webuiPath = match[3] || '/';
        } else {
            // Fallback for simple [PORT:xxxx] if it was ever used like that
            const portMatch = app.webui.match(/\[PORT:(\d+)]/);
            if (portMatch) webuiPort = portMatch[1];
        }
    }
    const webUiPortInput = document.getElementById('web_ui_port');
    if (webUiPortInput) webUiPortInput.value = webuiPort;

    const webUiProtocolSelect = document.getElementById('web_ui_protocol');
    if (webUiProtocolSelect) webUiProtocolSelect.value = webuiProtocol;

    const webUiPathInput = document.getElementById('web_ui_path');
    if (webUiPathInput) webUiPathInput.value = webuiPath;

    // Health
    const timeoutField = document.getElementById('timeout');
    if (timeoutField) timeoutField.value = (app.timeout ?? '') === null ? '' : (app.timeout ?? '');

    const wdProtoSel = document.getElementById('watchdog_protocol');
    const wdPortInput = document.getElementById('watchdog_port');
    const wdPathInput = document.getElementById('watchdog_path');
    const wdHttpFields2 = document.getElementById('watchdogHttpFields');

    if (wdProtoSel && wdPortInput && wdPathInput) {
        wdProtoSel.value = '';
        wdPortInput.value = '';
        wdPathInput.value = '';
        if (app.watchdog) {
            let m = app.watchdog.match(/^tcp:\/\/\[HOST]:\[PORT:(\d+)]$/);
            if (m) {
                wdProtoSel.value = 'tcp';
                wdPortInput.value = m[1];
                wdPathInput.value = '';
            } else {
                m = app.watchdog.match(/^(\w+):\/\/\[HOST]:\[PORT:(\d+)](.*)$/);
                if (m) {
                    wdProtoSel.value = m[1];
                    wdPortInput.value = m[2];
                    wdPathInput.value = m[3] || '/';
                }
            }
        }
        if (wdHttpFields2) {
            wdHttpFields2.style.display = (wdProtoSel.value === 'http' || wdProtoSel.value === 'https') ? 'block' : 'none';
        }
    }

    const backupDisabled = document.getElementById('backup_disabled');
    if (backupDisabled) backupDisabled.checked = true;

    if (app.backup === 'hot') {
        const backupHot = document.getElementById('backup_hot');
        if (backupHot) backupHot.checked = true;
    } else if (app.backup === 'cold') {
        const backupCold = document.getElementById('backup_cold');
        if (backupCold) backupCold.checked = true;
    }

    const tmpfsCheckbox = document.getElementById('tmpfs');
    if (tmpfsCheckbox) tmpfsCheckbox.checked = app.tmpfs || false;

    const detectedPmInput = document.getElementById('detected_pm');
    if (detectedPmInput) detectedPmInput.value = app.detected_pm || 'unknown';

    // const btnDetectPM = document.getElementById('btnDetectPM');
    // if (btnDetectPM) {
    //     btnDetectPM.disabled = app.detected_pm && app.detected_pm !== 'unknown' && app.detected_pm !== 'error';
    // }
    const hint = document.getElementById('pmSupportInline');
    if (hint) {
        const pm = app.detected_pm || 'unknown';
        hint.innerHTML = pmSupportsBashJqCurl(pm) ? '<span class="text-success"><span class="mdi mdi-check-circle"></span> bash, jq + curl installable</span>' : '';
    }

    const ingressOptions = document.getElementById('ingressOptions');
    if (ingressOptions) ingressOptions.style.display = app.ingress ? 'block' : 'none';

    const webUiPortContainer = document.getElementById('webUiPortContainer');
    if (webUiPortContainer) webUiPortContainer.style.display = app.ingress ? 'none' : 'block';

    const portsContainer = document.getElementById('portsContainer');
    if (portsContainer) {
        portsContainer.innerHTML = '';
        if (app.ports && typeof app.ports === 'object') {
            Object.entries(app.ports).forEach(([key, host]) => {
                const [container, protocol] = key.split('/');
                const description = (app.ports_description && app.ports_description[key]) || '';
                addPortMapping(container, host || '', protocol || 'tcp', description);
            });
        }
    }

    const mapContainer = document.getElementById('mapContainer');
    if (mapContainer) {
        mapContainer.innerHTML = '';
        if (app.map && app.map.length > 0) {
            app.map.forEach(m => {
                if (typeof m === 'object' && m !== null) {
                    addMapMapping(m.type || m.folder, m.readOnly ? 'ro' : (m.mode || 'rw'), m.path || '');
                } else if (typeof m === 'string') {
                    // Fallback für altes Format falls nötig (folder:mode)
                    const parts = m.split(':');
                    addMapMapping(parts[0], parts[1] || 'rw');
                }
            });
        }
    }

    const envVarsContainer = document.getElementById('envVarsContainer');
    if (envVarsContainer) {
        envVarsContainer.innerHTML = '';
        if (app.env_vars && app.env_vars.length > 0) {
            app.env_vars.forEach(ev => {
                addEnvVar(ev.key, ev.value, ev.editable);
            });
        }
    }

    const quirksModeCheckbox = document.getElementById('quirks_mode');
    if (quirksModeCheckbox) quirksModeCheckbox.checked = app.quirks || false;

    const allowUserEnvCheckbox = document.getElementById('allow_user_env');
    if (allowUserEnvCheckbox) allowUserEnvCheckbox.checked = app.allow_user_env || false;

    const bashioVersionInput = document.getElementById('bashio_version');
    if (bashioVersionInput) {
        // Sicherstellen, dass die Liste geladen ist
        if (window.bashioVersions.length === 0) {
            await fetchBashioVersions();
        }
        bashioVersionInput.value = app.bashio_version || window.bashioVersions[0] || '';
    }

    featureFlags.forEach(flag => {
        const el = document.getElementById(flag);
        if (el) {
            el.checked = !!app[flag];
        }
    });

    if (app.privileged && Array.isArray(app.privileged)) {
        document.querySelectorAll('.privileged-checkbox').forEach(el => {
            el.checked = app.privileged.includes(el.value);
        });
    } else {
        document.querySelectorAll('.privileged-checkbox').forEach(el => el.checked = false);
    }

    resetAccordion();

    toggleEditableCheckboxes();

    window.scrollTo(0, 0);
}

async function toggleAppInfo(slug) {
    const panel = document.getElementById(`app-info-${slug}`);
    if (panel.dataset.loaded !== '1') {
        panel.innerHTML = `<div class="d-flex justify-content-center p-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>`;
        try {
            const res = await fetch(`${basePath}/apps/${slug}`);
            const a = await res.json();

            const pm = a.detected_pm || 'unknown';
            const ingress = a.ingress ? `Enabled (${a.ingress_port}${a.ingress_stream ? ', stream' : ''})` : 'Disabled';
            const webuiPort = a.webui;
            const portsList = Object.entries(a.ports || {}).map(([key, host]) => `${host || '~'}:${key}`).join(', ');
            const mapList = (a.map || []).join(', ');
            const envVars = (a.env_vars || []).slice(0, 5).map(e => `<code>${e.key}</code>`).join(', ');
            const envMore = (a.env_vars || []).length > 5 ? ` … (+${(a.env_vars || []).length - 5} weitere)` : '';
            const backup = a.backup ? a.backup : 'disabled';
            const quirks = a.quirks ? 'Ja' : 'Nein';
            const allowUserEnv = a.allow_user_env ? 'Ja' : 'Nein';

            let flagsHtml = '';
            featureFlags.forEach(flag => {
                if (a[flag]) {
                    const label = flag.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    flagsHtml += `<div><strong>${label}:</strong> Ja</div>`;
                }
            });

            panel.innerHTML = `
                <div class="p-3 border rounded" style="background:#fafafa">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div><strong>Image:</strong> <a href="${a.image_url}" target="_blank" class="text-decoration-none"><code>${a.image}${a.image_tag ? ':' + a.image_tag : ''}</code></a></div>
                            <div><strong>Detected PM:</strong> ${pm}</div>
                            <div><strong>Backup:</strong> ${backup}</div>
                            <div><strong>Quirks:</strong> ${quirks} (User Env: ${allowUserEnv})</div>
                            ${flagsHtml}
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

async function loadApps() {
    const response = await fetch(`${basePath}/apps`);
    const data = await response.json();
    const repoInfoDiv = document.getElementById('repoInfo');
    let apps;
    let repoName = '';
    let repoDesc = '';

    if (Array.isArray(data)) {
        apps = data;
        repoInfoDiv.innerHTML = '';
    } else {
        apps = data.apps || [];
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

    const list = document.getElementById('appList');
    if (apps.length === 0) {
        list.innerHTML = '<div class="list-group-item">No apps found</div>';
        return;
    }
    list.innerHTML = apps.map(app => {
        const isSelf = app.slug === 'haos_app_converter';
        return `
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="me-3 text-center" style="width: 40px; font-size: 24px;">
                        ${app.has_local_icon
            ? `<img src="${basePath}/apps/${app.slug}/icon.png" style="width: 32px; height: 32px;" alt="${app.slug}">`
            : '📦'} 
                    </div>
                    <div>
                        <strong>${app.name}</strong>
                        ${app.detected_pm ? `<span class="badge bg-info text-dark rounded-pill ms-1" style="font-size: 0.7rem;">${app.detected_pm}</span>` : ''}
                        ${app.detected_pm && pmSupportsBashJqCurl(app.detected_pm) ? `<span class="text-success ms-1" title="bash, jq + curl installable"><span class="mdi mdi-check-circle"></span></span>` : ''}
                        ${app.quirks ? `<span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.7rem;">quirks</span>` : ''}
                        <br>
                        <small class="text-muted d-block">${app.description}</small>
                        <small class="text-muted">Version: ${app.version} | Image: <code>${app.image}</code></small>
                    </div>
                </div>
                ${isSelf ?
            '<span class="badge bg-secondary rounded-pill">System</span>' :
            `<div class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-ha-outline rounded-pill me-1" title="Info" onclick="toggleAppInfo('${app.slug}')">
                            <span class="mdi mdi-information-outline"></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-ha-outline rounded-pill me-1" onclick="editApp('${app.slug}')">
                            <span class="mdi mdi-pencil"></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-ha-outline rounded-pill text-danger border-danger" onclick="deleteApp('${app.slug}')">
                            <span class="mdi mdi-delete"></span>
                        </button>
                    </div>`
        }
            </div>
            <div id="app-info-${app.slug}" class="collapse mt-3"></div>
        </div>
    `
    }).join('');
}

async function openSettings() {
    document.getElementById('appSelection').style.display = 'none';
    document.getElementById('converterForm').style.display = 'none';
    document.getElementById('settingsView').style.display = 'block';
    document.getElementById('cancelBtn').style.display = 'none';

    const response = await fetch(`${basePath}/settings`);
    const settings = await response.json();
    document.getElementById('repo_name').value = settings.name;
    document.getElementById('repo_maintainer').value = settings.maintainer;
    if (document.getElementById('repo_url')) {
        document.getElementById('repo_url').value = settings.url || '';
    }
}

function closeSettings() {
    document.getElementById('settingsView').style.display = 'none';
    document.getElementById('appSelection').style.display = 'block';
}

async function handleSettingsSubmit(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('repo_name').value,
        maintainer: document.getElementById('repo_maintainer').value,
        url: document.getElementById('repo_url') ? document.getElementById('repo_url').value : ''
    };
    const response = await fetch(`${basePath}/settings`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
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

async function selfConvert(slug, tag = 'latest') {
    haConfirm(`Do you want to export the HAOS App Converter (Version: ${tag}) as a Home Assistant app? The internal version will be incremented automatically.`, async () => {
        try {
            const response = await fetch(`${basePath}/apps/${encodeURIComponent(slug)}/convert/${encodeURIComponent(tag)}`);
            const result = await response.json();

            if (result.status === 'success') {
                haAlert(`App successfully created in: <br><code>${result.path}</code>`, 'Success');
                document.body.dispatchEvent(new Event('reload'));
            } else {
                haAlert('Error: ' + result.message, 'Error');
            }
        } catch (e) {
            haAlert('An error occurred: ' + e.message, 'Error');
        }
    }, 'Export Converter', 'Export');
}

async function loadTags(slug) {
    const list = document.getElementById('tagList');
    if (list.dataset.loaded === 'true') return;

    try {
        const response = await fetch(`${basePath}/converter/tags`);
        const tags = await response.json();

        const header = list.querySelector('.dropdown-header');
        const divider = list.querySelector('.dropdown-divider');
        list.innerHTML = '';
        list.appendChild(header.parentElement === list ? header : header.closest('li'));
        list.appendChild(divider.parentElement === list ? divider : divider.closest('li'));

        tags.forEach(tag => {
            const li = document.createElement('li');
            li.innerHTML = `<a class="dropdown-item" href="#" onclick="selfConvert('${slug}','${tag}')">${tag}</a>`;
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