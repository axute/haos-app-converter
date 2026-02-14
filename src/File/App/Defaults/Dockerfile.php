<?php

namespace App\File\App\Defaults;

class Dockerfile
{
    public const string STARTUP_SCRIPT = '# Add startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh';
    public const string ALLOW_USER_ENV = '# gomplate for destruct variables
COPY --from=hairyhenderson/gomplate:stable /gomplate /bin/gomplate';
    public const string NEED_RUN_SH = '# Add wrapper script
COPY run.sh /run.sh
RUN chmod +x /run.sh
COPY original_entrypoint /run/original_entrypoint
COPY original_cmd /run/original_cmd
ENTRYPOINT ["/run.sh"]
CMD []';
    public const string DEFAULT_TAG = 'latest';
    public const string DEFAULT_IMAGE = 'alpine';
    public const string IMAGE_TAG = 'latest';

    public const array ARCHITECTURES_SUPPORTED_LONGTERM = [
        'aarch64',
        'arm64',
        'amd64',
    ];

    public const array ARCHITECTURES = [
        'aarch64',
        'arm64',
        'amd64',
        'armhf',
        'armv7',
        'i386'
    ];
}