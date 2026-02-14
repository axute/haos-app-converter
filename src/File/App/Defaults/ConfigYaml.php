<?php

namespace App\File\App\Defaults;

class ConfigYaml
{
    public const array _BOOT_OPTIONS = [
        'auto',
        'manual',
        'disabled'
    ];
    public const array ARCH = [
        'aarch64',
        'amd64',
    ];
    public const string PANEL_ICON = 'mdi:link-variant';
    public const string STARTUP = 'application';
    public const string BOOT = 'auto';
    public const int INGRESS_PORT = 80;
    public const string PORT_PROTOCOL = 'tcp';
    public const string INGRESS_ENTRY = '/';
    public const false INGRESS_STREAM = false;
    public const bool PANEL_ADMIN = true;
    public const false TMPFS = false;
    public const string WEBUI_PROTOCOL = 'http';
    public const string MAP_TYPE = 'type';
    public const string MAP_READ_ONLY = 'read_only';
    public const string MAP_PATH = 'path';
    const array _BACKUP_OPTIONS = [
        'hot',
        'cold',
        'disabled',
        null
    ];
    public const array _POSSIBLE_FEATURE_FLAGS = [
        'host_network',
        'host_ipc',
        'host_dbus',
        'host_pid',
        'host_uts',
        'hassio_api',
        'homeassistant_api',
        'docker_api',
        'full_access',
        'audio',
        'video',
        'gpio',
        'usb',
        'uart',
        'udev',
        'devicetree',
        'kernel_modules',
        'stdin',
        'legacy',
        'auth_api',
        'advanced',
        'realtime',
        'journald'
    ];
    public const string WEBUI_PATH = '/';
    public const string VERSION = '1.0.0';
    public const array MAPPINGS = [
        'addon_config',
        'addons',
        'all_addon_configs',
        'backup',
        'data',
        'homeassistant_config',
        'media',
        'ssl',
        'share',
    ];
}