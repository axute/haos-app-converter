// noinspection ES6UnusedImports
import { haAlert, haConfirm, resetAccordion } from './ui.js';
// noinspection ES6UnusedImports
import { updateAppMetadata, fetchBashioVersions, fetchImageEnvVars, fetchImagePorts } from './api.js';

export const featureFlags = [
    'host_network', 'host_ipc', 'host_dbus', 'host_pid', 'host_uts',
    'hassio_api', 'homeassistant_api', 'docker_api', 'full_access',
    'audio', 'video', 'gpio', 'usb', 'uart', 'udev',
    'devicetree', 'kernel_modules', 'stdin', 'legacy', 'auth_api',
    'advanced', 'realtime', 'journald', 'apparmor', 'discovery'
];

let originalVersion = '1.0.0';
let iconBase64 = '';

export function setIconBase64(val) { iconBase64 = val; }
export function getIconBase64() { return iconBase64; }
export function setOriginalVersion(val) { originalVersion = val; }

export function startNew(easyMDE, startupScriptEditor, toggleEditableCheckboxes) {
    const appSelection = document.getElementById('appSelection');
    const converterForm = document.getElementById('converterForm');
    const cancelBtn = document.getElementById('cancelBtn');
    // noinspection JSUnusedLocalSymbols
    const submitSection = document.getElementById('submitSection');
    // noinspection JSUnusedLocalSymbols
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
        converterForm.classList.add('mode-new');
        converterForm.classList.remove('mode-edit', 'version-fixed');
        converterForm.reset();
    }
    if (cancelBtn) cancelBtn.style.display = 'block';
    // Remove direct style manipulation for submitSection/updateSection as it is handled by CSS

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

    const versionUpdateButtons = document.getElementById('versionUpdateButtons');
    if (versionUpdateButtons) {
        versionUpdateButtons.style.display = 'block';
    }

    const versionFixation = document.getElementById('version_fixation');
    if (versionFixation) {
        versionFixation.checked = false;
        versionFixation.onchange = () => {
            if (versionFixation.checked) {
                converterForm.classList.add('version-fixed');
            } else {
                converterForm.classList.remove('version-fixed');
            }
        };
    }

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
    const formSlug = document.getElementById('form_slug');
    if (formSlug) formSlug.value = '';
    const btnDetectPM = document.getElementById('btnDetectPM');
    if (btnDetectPM) btnDetectPM.disabled = false;
    const hint = document.getElementById('pmSupportInline');
    if (hint) hint.innerHTML = '';

    if (toggleEditableCheckboxes) toggleEditableCheckboxes();
}

export async function editApp(slug, easyMDE, startupScriptEditor, toggleEditableCheckboxes, detectPM) {
    const response = await fetch(`${basePath}/apps/${slug}`);
    const app = await response.json();

    const appSelection = document.getElementById('appSelection');
    const converterForm = document.getElementById('converterForm');
    const cancelBtn = document.getElementById('cancelBtn');

    if (appSelection) appSelection.style.display = 'none';
    if (converterForm) {
        converterForm.style.display = 'block';
        converterForm.classList.add('mode-edit');
        converterForm.classList.remove('mode-new');
        if (app.version_fixation) {
            converterForm.classList.add('version-fixed');
        } else {
            converterForm.classList.remove('version-fixed');
        }
        converterForm.reset();
    }
    if (cancelBtn) cancelBtn.style.display = 'block';

    const formSlug = document.getElementById('form_slug');
    if (formSlug) formSlug.value = slug;

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
    if (imageInput) {
        imageInput.value = app.image;
        if (typeof fetchImageEnvVars !== 'undefined') {
             fetchImageEnvVars();
        }
        if (typeof fetchImagePorts !== 'undefined') {
             fetchImagePorts();
        }
    }

    const imageTagInput = document.getElementById('image_tag');
    if (imageTagInput) imageTagInput.value = app.image_tag || 'latest';

    const versionInput = document.getElementById('version');
    if (versionInput) {
        versionInput.value = app.version;
        versionInput.readOnly = true;
    }
    originalVersion = app.version || '1.0.0';

    // Removed direct manipulation of submitSection/updateSection

    const versionFixation = document.getElementById('version_fixation');
    if (versionFixation) {
        versionFixation.checked = !!app.version_fixation;
        versionFixation.onchange = () => {
            if (versionFixation.checked) {
                converterForm.classList.add('version-fixed');
            } else {
                converterForm.classList.remove('version-fixed');
            }
        };
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
        const match = app.webui.match(/^(\w+):\/\/\[HOST]:\[PORT:(\d+)](.*)$/);
        if (match) {
            webuiProtocol = match[1];
            webuiPort = match[2];
            webuiPath = match[3] || '/';
        } else {
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

    const pmSupportsBashJqCurl = (pm) => {
        if (!pm) return false;
        pm = ('' + pm).toLowerCase();
        return ['apk', 'apt', 'apt-get', 'yum', 'dnf', 'microdnf', 'zypper', 'pacman'].includes(pm);
    };

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
    if (toggleEditableCheckboxes) toggleEditableCheckboxes();
    window.scrollTo(0, 0);
}

export function addEnvVar(key = '', value = '', editable = false) {
    const container = document.getElementById('envVarsContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 env-var-row';
    div.innerHTML = `
        <input type="text" class="form-control env-key" placeholder="Key" value="${key}" list="envVarKeys">
        <input type="text" class="form-control env-value" placeholder="Value" value="${value}">
        <div class="input-group-text">
            <input class="form-check-input mt-0 env-editable" type="checkbox" ${editable ? 'checked' : ''} title="Editable in HA GUI (needs Quirks Mode)">
        </div>
        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove(); if(window.checkEnvWarnings) window.checkEnvWarnings();">×</button>
    `;
    const cb = div.querySelector('.env-editable');
    cb.addEventListener('change', () => { if(window.checkEnvWarnings) window.checkEnvWarnings(); });
    container.appendChild(div);
    if(window.checkEnvWarnings) window.checkEnvWarnings();
}

export function addPortMapping(containerPort = '', hostPort = '', protocol = 'tcp', description = '') {
    const container = document.getElementById('portsContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 port-mapping-row';
    div.innerHTML = `
        <input type="number" class="form-control port-container" placeholder="Port" value="${containerPort}" style="max-width: 140px;" list="exposedPorts">
        <select class="form-select port-protocol" style="max-width: 90px;">
            <option value="tcp" ${protocol === 'tcp' ? 'selected' : ''}>TCP</option>
            <option value="udp" ${protocol === 'udp' ? 'selected' : ''}>UDP</option>
        </select>
        <span class="input-group-text">→</span>
        <input type="number" class="form-control port-host" placeholder="Host Port" value="${hostPort}" style="max-width: 140px;">
        <input type="text" class="form-control port-description" placeholder="Description (optional)" value="${description}">
        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">×</button>
    `;
    
    // Auto-update protocol based on datalist selection if possible
    const portInput = div.querySelector('.port-container');
    const protoSelect = div.querySelector('.port-protocol');
    portInput.addEventListener('input', () => {
        const datalist = document.getElementById('exposedPorts');
        if (datalist) {
            for (const option of datalist.options) {
                if (option.value === portInput.value) {
                    const proto = option.textContent.toLowerCase();
                    if (proto === 'tcp' || proto === 'udp') {
                        protoSelect.value = proto;
                    }
                    break;
                }
            }
        }
    });

    container.appendChild(div);
}

export function addMapMapping(folder = 'data', mode = 'rw', path = '') {
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

export function getEnvVars() {
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

export function getPortMappings() {
    const rows = document.querySelectorAll('.port-mapping-row');
    const ports = {};
    const descriptions = {};
    rows.forEach(row => {
        const container = row.querySelector('.port-container').value;
        const protocol = row.querySelector('.port-protocol').value;
        const host = row.querySelector('.port-host').value;
        const description = row.querySelector('.port-description').value.trim();
        if (container) {
            const key = `${container}/${protocol}`;
            ports[key] = host ? parseInt(host) : null;
            if (description) {
                descriptions[key] = description;
            }
        }
    });
    return {ports, descriptions};
}

export function getMapMappings() {
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

export function getPrivileged() {
    const caps = [];
    document.querySelectorAll('.privileged-checkbox:checked').forEach(el => {
        caps.push(el.value);
    });
    return caps;
}

export async function handleConverterSubmit(easyMDE, startupScriptEditor) {
    const data = {
        name: document.getElementById('name').value,
        description: document.getElementById('description').value,
        long_description: easyMDE ? easyMDE.value() : '',
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
        version_fixation: document.getElementById('version_fixation').checked,
        bashio_version: document.getElementById('bashio_version').value,
        url: document.getElementById('url') ? document.getElementById('url').value : null,
        ports_data: getPortMappings(),
        map: getMapMappings(),
        env_vars: getEnvVars(),
        startup_script: startupScriptEditor ? startupScriptEditor.getValue() : '',
        privileged: getPrivileged(),
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

    const response = await fetch(`${basePath}/apps/generate`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    });

    const result = await response.json();
    if (result.status === 'success') {
        const formSlug = document.getElementById('form_slug');
        if (formSlug) formSlug.value = result.slug || '';

        const resultDiv = document.getElementById('result');
        const resultMessage = document.getElementById('resultMessage');
        if (resultMessage) resultMessage.innerText = 'App has been created/updated successfully.';
        const resultDetails = document.getElementById('resultDetails');
        if (resultDetails) resultDetails.innerHTML = `<p class="mb-0"><strong>Path:</strong> <code id="resultPath">${result.path}</code></p>`;
        if (resultDiv) resultDiv.style.display = 'block';
        if (document.getElementById('converterForm')) document.getElementById('converterForm').style.display = 'none';
        if (document.getElementById('appSelection')) document.getElementById('appSelection').style.display = 'none';
        if (document.getElementById('cancelBtn')) document.getElementById('cancelBtn').style.display = 'none';

        document.body.dispatchEvent(new Event('reload'));

        setTimeout(() => {
            if (resultDiv) resultDiv.style.display = 'none';
            if (document.getElementById('appSelection')) document.getElementById('appSelection').style.display = 'block';
        }, 3000);
    } else {
        haAlert('Error: ' + result.message, 'Error');
    }
}

export function updateVersion(type) {
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

    const versionInput = document.getElementById('version');
    if (versionInput) {
        versionInput.value = parts.join('.');
        // Manually trigger submit if fixation is off, but here we just want to update the field
    }
}
