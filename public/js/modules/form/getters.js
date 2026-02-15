export function getPortMappings() {
    const rows = document.querySelectorAll(".port-mapping-row");
    const ports = {};
    const descriptions = {};
    rows.forEach(row => {
        const container = row.querySelector(".port-container").value;
        const protocol = row.querySelector(".port-protocol").value;
        const host = row.querySelector(".port-host").value;
        const description = row.querySelector(".port-description").value.trim();
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
    const rows = document.querySelectorAll(".map-mapping-row");
    const maps = [];
    rows.forEach(row => {
        const type = row.querySelector(".map-type").value;
        const mode = row.querySelector(".map-mode").value;
        const path = row.querySelector(".map-path").value;
        if (path) {
            maps.push({type, mode, path});
        }
    });
    return maps;
}

export function getEnvVars() {
    const rows = document.querySelectorAll(".env-var-row");
    const envVars = [];
    rows.forEach(row => {
        const key = row.querySelector(".env-key").value;
        const value = row.querySelector(".env-value").value;
        const editable = row.querySelector(".env-editable").checked;
        if (key) {
            envVars.push({key, value, editable});
        }
    });
    return envVars;
}

export function getPrivileged() {
    const privileged = [];
    document.querySelectorAll(".privileged-checkbox:checked").forEach(el => {
        privileged.push(el.value);
    });
    return privileged;
}
