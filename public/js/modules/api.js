export async function updateAppMetadata(slug, payload) {
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

export async function fetchBashioVersions() {
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

export async function fetchImageTags(detectPM) {
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
        if (detectPM) await detectPM();
    } catch (e) {
        console.error('Error fetching tags', e);
        datalist.innerHTML = '';
        if (window.haAlert) window.haAlert('Failed to fetch tags for image: ' + image, 'Error');
    } finally {
        btn.disabled = false;
        loader.style.display = 'none';
    }
}

export async function fetchImageEnvVars() {
    const image = document.getElementById('image').value.trim();
    const tag = document.getElementById('image_tag').value.trim() || 'latest';
    if (!image) return;

    const datalist = document.getElementById('envVarKeys');
    if (!datalist) return;

    try {
        const response = await fetch(`${basePath}/image/${image}/env-vars/${tag}`);
        const envVars = await response.json();
        
        datalist.innerHTML = '';
        if (envVars && typeof envVars === 'object') {
            Object.keys(envVars).forEach(key => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = envVars[key];
                datalist.appendChild(option);
            });
        }
    } catch (e) {
        console.error('Error fetching env vars', e);
    }
}

export async function fetchImagePorts() {
    const image = document.getElementById('image').value.trim();
    const tag = document.getElementById('image_tag').value.trim() || 'latest';
    if (!image) return;

    const datalist = document.getElementById('exposedPorts');
    if (!datalist) return;

    try {
        const response = await fetch(`${basePath}/image/${image}/ports/${tag}`);
        const ports = await response.json();

        datalist.innerHTML = '';
        if (ports && typeof ports === 'object') {
            Object.keys(ports).forEach(port => {
                const option = document.createElement('option');
                option.value = port;
                option.textContent = ports[port];
                datalist.appendChild(option);
            });
        }
    } catch (e) {
        console.error('Error fetching image ports', e);
    }
}

export async function detectPM(force = false) {
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

function pmSupportsBashJqCurl(pm) {
    if (!pm) return false;
    pm = ('' + pm).toLowerCase();
    return ['apk', 'apt', 'apt-get', 'yum', 'dnf', 'microdnf', 'zypper', 'pacman'].includes(pm);
}
