<?php

namespace App\Generator;

use App\Tools\Converter;
use Symfony\Component\Yaml\Yaml;

class HaRepository extends Yamlfile
{
    public const string FILENAME = 'repository.yaml';
    protected string $maintainer = Converter::NAME;
    protected ?string $url = null;

    public function __construct(protected string $name = Converter::DEFAULT_REPOSITORY_NAME)
    {

    }

    public static function getInstance(): ?static
    {
        $dataDir = self::getDataDir();
        $repositoryFile = $dataDir . '/' . self::FILENAME;
        if (is_file($repositoryFile) === false) {
            return null;
        } else return self::fromFile($repositoryFile);
    }

    protected static function getDataDir(): string
    {
        return getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data';
    }

    public static function fromFile(string $file): static
    {
        $content = Yaml::parseFile($file);
        $instance = new static($content['name']);
        if (isset($content['maintainer'])) $instance->setMaintainer($content['maintainer']);
        if (isset($content['url'])) $instance->setUrl($content['url']);
        return $instance;
    }

    public function jsonSerialize(): array
    {
        $output = [
            'name' => $this->name,
        ];
        if ($this->maintainer) $output['maintainer'] = $this->maintainer;
        if ($this->url) $output['url'] = $this->url;
        return $output;
    }

    public function getMaintainer(): ?string
    {
        return $this->maintainer;
    }

    public function setMaintainer(?string $maintainer): static
    {
        $this->maintainer = $maintainer;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function setName(string $name): HaRepository
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }
}