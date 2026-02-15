<?php

namespace App\File\App;

use App\File\Traits\TypeYamlTrait;

class ConfigYaml extends Config
{
    use TypeYamlTrait;

    public function getFilename(): string
    {
        return 'config.yaml';
    }

}