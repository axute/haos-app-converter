<?php

namespace App\File\App\Defaults;

class MetadataJson
{
    public const array ALLOWED_KEYS = [
        'auto_update',
        'allow_user_env',
        'bashio_version',
        'detected_pm',
        'has_startup_script',
        'original_cmd',
        'quirks',
        'version_fixation',
    ];
}