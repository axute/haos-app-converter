export const featureFlags = ["host_network", "host_ipc", "host_dbus", "host_pid", "host_uts", "hassio_api", "homeassistant_api", "docker_api", "full_access", "audio", "video", "gpio", "usb", "uart", "udev", "devicetree", "kernel_modules", "stdin", "legacy", "auth_api", "advanced", "realtime", "journald", "apparmor", "discovery"];
let iconBase64 = "";
let originalVersion = "1.0.0";

export function setIconBase64(val) {
    iconBase64 = val;
}

export function getIconBase64() {
    return iconBase64;
}

export function setOriginalVersion(val) {
    originalVersion = val;
}

export function getOriginalVersion() {
    return originalVersion;
}
