export function addPortMapping(container = "", host = "", protocol = "tcp", description = "") {
    const container_el = document.getElementById("portsContainer");
    const div = document.createElement("div");
    div.className = "row g-2 mb-2 port-mapping-row";
    div.innerHTML = `<div class="col-12 col-md-3">
    <div class="input-group input-group-sm">
        <span class="input-group-text">Cont.</span>
        <input type="number" class="form-control port-container" placeholder="80" value="${container}">
    </div>
</div>
<div class="col-12 col-md-2">
    <select class="form-select form-select-sm port-protocol">
        <option value="tcp" ${protocol === "tcp" ? "selected" : ""}>TCP</option>
        <option value="udp" ${protocol === "udp" ? "selected" : ""}>UDP</option>
    </select>
</div>
<div class="col-12 col-md-3">
    <div class="input-group input-group-sm"><span class="input-group-text">Host</span><input type="number" class="form-control port-host" placeholder="8080" value="${host}"></div>
</div>
<div class="col-12 col-md-3"><input type="text" class="form-control form-control-sm port-description" placeholder="Description" value="${description}"></div>
<div class="col-12 col-md-1 text-end">
    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(' .port-mapping-row').remove()"><span class="mdi mdi-delete"></span></button>
</div>`;
    container_el.appendChild(div);
}

export function addMapMapping(type = "config", mode = "rw", path = "") {
    const container = document.getElementById("mapContainer");
    const div = document.createElement("div");
    div.className = "row g-2 mb-2 map-mapping-row";
    div.innerHTML = `<div class="col-12 col-md-3">
    <select class="form-select form-select-sm map-type">
        <option value="config" ${type === "config" ? "selected" : ""}>config</option>
        <option value="ssl" ${type === "ssl" ? "selected" : ""} >ssl</option>
        <option value="addons" ${type === "addons" ? "selected" : ""}>addons</option>
        <option value="backup" ${type === "backup" ? "selected" : ""}>backup</option>
        <option value="share" ${type === "share" ? "selected" : ""}>share</option>
        <option value="media" ${type === "media" ? "selected" : ""}>media</option>
    </select>
    </div>
    <div class="col-12 col-md-2">
        <select class="form-select form-select-sm map-mode">
        <option value="rw" ${mode === "rw" ? "selected" : ""}>RW</option>
        <option value="ro" ${mode === "ro" ? "selected" : ""}>RO</option>
    </select>
    </div>
    <div class="col-12 col-md-6">
        <input type="text" class="form-control form-control-sm map-path" placeholder="Path" value="${path}">
    </div>
    <div class="col-12 col-md-1 text-end">
    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.map-mapping-row').remove()">
    <span class="mdi mdi-delete"></span></button></div>`;
    container.appendChild(div);
}

export function addEnvVar(key = "", value = "", editable = false) {
    const container = document.getElementById("envVarsContainer");
    const div = document.createElement("div");
    div.className = "row g-2 mb-2 env-var-row";
    div.innerHTML = `
        <div class="col-12 col-md-4"><input type="text" class="form-control form-control-sm env-key" placeholder="KEY" value="${key}"></div>
        <div class="col-12 col-md-5"><input type="text" class="form-control form-control-sm env-value" placeholder="VALUE" value="${value}"></div>
        <div class="col-12 col-md-2 d-flex align-items-center justify-content-center">
            <div class="form-check form-switch"><input class="form-check-input env-editable" type="checkbox" ${editable ? "checked" : ""} title="Editable by user"></div>
        </div>
        <div class="col-12 col-md-1 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(' .env-var-row').remove()"><span class="mdi mdi-delete"></span></button>
        </div>`;
    container.appendChild(div);

}