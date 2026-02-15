<?php

namespace App\File\App;

use App\File\Traits\TypeJsonTrait;

class ConfigJson extends Config
{
    use TypeJsonTrait;

    public function getFilename(): string
    {
        return 'config.json';
    }
}