import { haAlert, haConfirm, showLogs } from './modules/ui.js';
import { 
    updateAppMetadata, 
    fetchBashioVersions, 
    fetchImageTags, 
    fetchImageEnvVars,
    fetchImagePorts,
    detectPM 
} from './modules/api.js';
import { 
    startNew, 
    editApp, 
    handleConverterSubmit, 
    updateVersion,
    addEnvVar,
    addPortMapping,
    addMapMapping,
    setIconBase64,
    featureFlags
} from './modules/form.js';

// Global variables (shared with modules via imports/exports where possible)
window.bashioVersions = [];
let easyMDE;
let startupScriptEditor;

if (typeof basePath === 'undefined') {
    window.basePath = window.location.pathname.replace(/\/$/, '');
}

// Make functions global for HTML onclick events
window.editApp = (slug) => editApp(slug, easyMDE, startupScriptEditor, toggleEditableCheckboxes, detectPM);
window.deleteApp = deleteApp;
window.cancelConverter = cancelConverterManual;
window.startNew = () => startNew(easyMDE, startupScriptEditor, toggleEditableCheckboxes);
window.openSettings = openSettings;
window.closeSettings = closeSettings;
window.fetchImageTags = fetchImageTagsWrapper;
window.fetchImageEnvVars = fetchImageEnvVars;
window.fetchImagePorts = fetchImagePorts;
window.detectPM = (force) => detectPM(force);
window.addPortMapping = () => addPortMapping();
window.addMapMapping = () => addMapMapping();
window.addEnvVar = () => addEnvVar();
window.addPort = () => addPortMapping();
window.addMap = () => addMapMapping();
window.selfConvert = selfConvert;
window.updateVersion = (type) => updateVersion(type);
window.downloadApp = downloadApp;
window.toggleVersionFixation = toggleVersionFixation;
window.toggleAutoUpdate = toggleAutoUpdate;
window.updateAppMetadata = updateAppMetadata;
window.haAlert = haAlert;
window.haConfirm = haConfirm;
window.showLogs = showLogs;
window.checkEnvWarnings = checkEnvWarnings;
window.loadTags = loadTags;
window.toggleAppInfo = toggleAppInfo;
window.uploadApp = uploadApp;

async function uploadApp() {
    const form = document.getElementById('uploadAppForm');
    const fileInput = document.getElementById('appZip');
    const progress = document.getElementById('uploadProgress');
    const modal = bootstrap.Modal.getInstance(document.getElementById('uploadModal'));

    if (!fileInput.files.length) {
        haAlert('Please select a file first.', 'Warning');
        return;
    }

    const formData = new FormData(form);
    progress.classList.remove('d-none');

    try {
        const response = await fetch(`${basePath}/apps/upload`, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        progress.classList.add('d-none');

        if (result.status === 'success') {
            modal.hide();
            form.reset();
            document.body.dispatchEvent(new Event('reload'));
            haAlert('App uploaded successfully!', 'Success');
        } else {
            haAlert('Upload failed: ' + result.message, 'Error');
        }
    } catch (error) {
        progress.classList.add('d-none');
        haAlert('An error occurred: ' + error.message, 'Error');
    }
}

function downloadApp(slug = null) {
    if (!slug) {
        const hiddenSlug = document.getElementById('form_slug');
        slug = hiddenSlug ? hiddenSlug.value : '';
    }
    
    if (!slug) {
        haAlert('Please save the app first before downloading.', 'Info');
        return;
    }
    
    window.location.href = `${basePath}/apps/${slug}/download`;
}

function cancelConverterManual() {
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

        document.body.dispatchEvent(new Event('reload'));
    }, 'Cancel Editing', 'Yes, cancel');
}

async function fetchImageTagsWrapper() {
    await fetchImageTags(async () => {
        await detectPM();
        await fetchImageEnvVars();
        await fetchImagePorts();
    });
}

async function deleteApp(slug) {
    haConfirm(`Do you really want to delete the app "${slug}"? This action cannot be undone.`, async () => {
        try {
            const response = await fetch(`${basePath}/apps/${slug}`, {
                method: 'DELETE'
            });
            const result = await response.json();
            if (result.status === 'success') {
                document.body.dispatchEvent(new Event('reload'));
            } else {
                haAlert('Error during deletion: ' + result.message, 'Error');
            }
        } catch (error) {
            haAlert('An error occurred: ' + error.message, 'Error');
        }
    }, 'Delete App', 'Delete', 'btn-danger');
}

async function toggleVersionFixation(slug, checked) {
    await updateAppMetadata(slug, { version_fixation: !!checked });
    const versionButtonsGroup = document.querySelector('#updateSection .btn-group');
    if (versionButtonsGroup) {
        versionButtonsGroup.style.display = checked ? 'none' : 'inline-flex';
    }
}

async function toggleAutoUpdate(slug, checked) {
    await updateAppMetadata(slug, { auto_update: !!checked });
}

function checkEnvWarnings() {
    const envWarning = document.getElementById('envWarning');
    if (!envWarning) return;

    const quirksMode = document.getElementById('quirks_mode');
    const quirksEnabled = quirksMode ? quirksMode.checked : false;
    const editables = document.querySelectorAll('.env-editable:checked');
    envWarning.style.display = quirksEnabled && editables.length > 0 ? 'block' : 'none';
}

function toggleEditableCheckboxes() {
    const quirksMode = document.getElementById('quirks_mode');
    const allowUserEnv = document.getElementById('allow_user_env');
    if (!quirksMode) return;

    const quirksEnabled = quirksMode.checked;
    const allowUserEnvEnabled = allowUserEnv ? allowUserEnv.checked : false;

    const startupScriptSection = document.getElementById('startupScriptSection');
    if (startupScriptSection) {
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

async function openSettings() {
    const converterForm = document.getElementById('converterForm');
    const appSelection = document.getElementById('appSelection');
    const settingsView = document.getElementById('settingsView');
    const cancelBtn = document.getElementById('cancelBtn');

    if (converterForm) converterForm.style.display = 'none';
    if (appSelection) appSelection.style.display = 'none';
    if (settingsView) settingsView.style.display = 'block';
    if (cancelBtn) cancelBtn.style.display = 'block';

    const response = await fetch(`${basePath}/settings`);
    const settings = await response.json();
    document.getElementById('repo_name').value = settings.name;
    document.getElementById('repo_maintainer').value = settings.maintainer;
    if (document.getElementById('repo_url')) {
        document.getElementById('repo_url').value = settings.url || '';
    }
}

function closeSettings() {
    const settingsView = document.getElementById('settingsView');
    const appSelection = document.getElementById('appSelection');
    const cancelBtn = document.getElementById('cancelBtn');

    if (settingsView) settingsView.style.display = 'none';
    if (appSelection) appSelection.style.display = 'block';
    if (cancelBtn) cancelBtn.style.display = 'none';
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
            const mapList = (a.map || []).map(m => m.type || m.folder).join(', ');
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

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('bashio_version')) {
        fetchBashioVersions();
    }
    const longDescEl = document.getElementById('long_description');
    if (longDescEl) {
        easyMDE = new EasyMDE({
            element: longDescEl,
            spellChecker: false,
            placeholder: 'Detailed description (Markdown)...',
            status: false,
            minHeight: '200px'
        });
    }

    const startupScriptEl = document.getElementById('startup_script');
    if (startupScriptEl) {
        startupScriptEditor = CodeMirror.fromTextArea(startupScriptEl, {
            mode: 'shell',
            theme: 'monokai',
            lineNumbers: true,
            viewportMargin: Infinity
        });
    }

    const ingressCheckbox = document.getElementById('ingress');
    if (ingressCheckbox) {
        ingressCheckbox.addEventListener('change', (e) => {
            document.getElementById('ingressOptions').style.display = e.target.checked ? 'block' : 'none';
            document.getElementById('webUiPortContainer').style.display = e.target.checked ? 'none' : 'block';
        });
    }

    const quirksCheckbox = document.getElementById('quirks_mode');
    if (quirksCheckbox) {
        quirksCheckbox.addEventListener('change', toggleEditableCheckboxes);
    }

    const allowUserEnvCheckboxGlobal = document.getElementById('allow_user_env');
    if (allowUserEnvCheckboxGlobal) {
        allowUserEnvCheckboxGlobal.addEventListener('change', toggleEditableCheckboxes);
    }

    const iconFileInput = document.getElementById('icon_file');
    if (iconFileInput) {
        iconFileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                const b64 = event.target.result;
                setIconBase64(b64);
                const iconPreview = document.getElementById('icon_preview');
                if (iconPreview) {
                    const previewImg = iconPreview.querySelector('img');
                    if (previewImg) previewImg.src = b64;
                    iconPreview.style.display = 'block';
                }
            };
            reader.readAsDataURL(file);
        });
    }

    const converterForm = document.getElementById('converterForm');
    if (converterForm) {
        converterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleConverterSubmit(easyMDE, startupScriptEditor);
        });
    }

    // Init Custom AppArmor UI (toggle name input visibility)
    const apparmorCustom = document.getElementById('apparmor_custom');
    const apparmorNameContainer = document.getElementById('apparmor_name_container');
    if (apparmorCustom && apparmorNameContainer) {
        const toggle = () => {
            apparmorNameContainer.style.display = apparmorCustom.checked ? 'block' : 'none';
        };
        apparmorCustom.addEventListener('change', toggle);
        toggle();
    }

    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', handleSettingsSubmit);
    }

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