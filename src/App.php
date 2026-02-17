<?php

namespace App;

use App\File\App\Config;
use App\File\App\ConfigJson;
use App\File\App\ConfigYaml;
use App\File\App\Dockerfile;
use App\File\App\IconPng;
use App\File\App\LogoPng;
use App\File\App\MetadataJson;
use App\File\App\OriginalCmd;
use App\File\App\OriginalEntrypoint;
use App\File\App\ReadmeMd;
use App\File\App\RunSh;
use App\File\App\StartSh;
use App\File\FileAbstract;
use App\File\Repository\RepositoryYaml;
use App\Interfaces\ArchiveAwareInterface;
use App\Interfaces\ArchiveInterface;
use App\Tools\Archive\ArchiveAwareTrait;
use App\Tools\Archive\ZipFolder;
use App\Tools\Webform;
use Cocur\Slugify\Slugify;
use Exception;

class App implements ArchiveAwareInterface
{
    use ArchiveAwareTrait;
    public readonly Dockerfile $dockerfile;
    public readonly Config $config;
    public readonly MetadataJson $metadataJson;
    public readonly RepositoryYaml $repositoryYaml;
    public readonly IconPng $iconPng;
    public readonly LogoPng $logoPng;
    public readonly RunSh $runSh;
    public readonly StartSh $startSh;
    public readonly OriginalCmd $originalCmd;
    public readonly OriginalEntrypoint $originalEntrypoint;
    public readonly ReadmeMd $readmeMd;

    private function __construct(public string $slug, ?ArchiveInterface $archive = null)
    {
        if ($archive === null) {
            $archive = (new Repository())->getArchive();
        }
        $this->archive = $archive;
        $configJson = new ConfigJson($this);
        $configYaml = new ConfigYaml($this);
        if ($configYaml->isFile() === true && $configJson->isFile() === true) {
            $configJson->clearFile();
        } else if ($configJson->isFile() === true) {
            $configYaml->setData($configJson->getData())->saveFileContent();
            $configJson->clearFile();
        }
        $configYaml->slug = $this->slug;
        $this->config = $configYaml;
        $this->dockerfile = new Dockerfile($this);
        $this->iconPng = new IconPng($this);
        $this->logoPng = new LogoPng($this);
        $this->metadataJson = new MetadataJson($this);
        $this->originalCmd = new OriginalCmd($this);
        $this->originalEntrypoint = new OriginalEntrypoint($this);
        $this->readmeMd = new ReadmeMd($this);
        $this->runSh = new RunSh($this);
        $this->startSh = new StartSh($this);
    }
    public static function list(): array
    {
        $apps = [];
        $dataDir = self::getDataDir();
        if (is_dir($dataDir)) {
            $dirs = array_filter(glob($dataDir . '/*'), 'is_dir');
            foreach ($dirs as $dir) {
                $slug = basename($dir);
                try {
                    $apps[] = App::get($slug)->getData();
                } catch (Exception) {
                    continue;
                }
            }
        }

        // Alphabetisch sortieren nach "name"
        usort($apps, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $apps;
    }

    public static function getDataDir(): string
    {
        static $dir = null;
        if ($dir === null) {
            $dir = str_replace('\\', '/', getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../data');
        }
        return $dir;
    }

    public static function detectOrCrate(array $data): ?App
    {
        $slug = self::detectSlug($data);
        return $slug !== null ? self::get($slug) : null;
    }

    public static function detectSlug(array $data): ?string
    {
        if (array_key_exists('slug', $data) && !empty($data['slug'])) {
            return $data['slug'];
        }
        if (array_key_exists('name', $data) && !empty($data['name'])) {
            return (self::getSlugify())->slugify($data['name']);
        }
        return null;
    }

    public static function getSlugify(): Slugify
    {
        return new Slugify([
            'separator' => '_',
            'rulesets'  => [
                'default',
                'german'
            ]
        ]);
    }

    public static function get(string $slug)
    {
        static $instances = [];
        if (array_key_exists($slug, $instances) === false) {
            $instances[$slug] = new App($slug);
        }
        return $instances[$slug];
    }

    public static function getByZipFilePath(string $zipFilePath): ?App
    {
        $zipArchive = new ZipFolder();
        if ($zipArchive->open($zipFilePath) !== true) {
            $slug = pathinfo($zipFilePath, PATHINFO_FILENAME);
            return App::getByZip($slug, $zipArchive);
        }
        return null;

    }

    public static function getByZip(string $slug, ArchiveInterface $zipArchiveWrapper): App
    {
        return new App($slug, $zipArchiveWrapper);
    }


    public function increaseVersion(): static
    {
        $currentVersion = $this->config->version;
        if($this->metadataJson->version_fixation === true) {
            return $this->mergeVersion();
        }
        if ($currentVersion === null) {
            return $this;
        }
        // Version hochzählen
        $parts = explode('.', $currentVersion);
        if (count($parts) === 3) {
            $parts[2]++;
            $this->config->version = implode('.', $parts);
        } else {
            $this->config->version = $currentVersion . '.1';
        }

        return $this->save();
    }

    public function mergeVersion(): static
    {
        $versionFixation = $this->metadataJson->version_fixation;
        if ($versionFixation !== true) {
            return $this;
        }
        $this->config->setVersionFromTag($this->dockerfile->image_tag);
        $this->save();
        return $this;
    }

    public function getAppDir(): string
    {
        return App::getDataDir() . '/' . $this->slug;
    }

    public function update(array $data): static
    {
        $webform = new Webform($data);
        array_map(fn(FileAbstract $app) => $app->updateFromWebUi($webform), $this->getFiles());
        return $this;
    }

    /**
     * @return array<FileAbstract>
     */
    protected function getFiles(): array
    {
        return [
            $this->config,
            $this->dockerfile,
            $this->iconPng,
            $this->logoPng,
            $this->metadataJson,
            $this->originalCmd,
            $this->originalEntrypoint,
            $this->readmeMd,
            $this->runSh,
            $this->startSh
        ];
    }

    public function getData(): array
    {
        return self::merge(...array_map(fn(FileAbstract $file) => $file->jsonSerialize(), $this->getFiles()));
    }

    protected static function merge(array $data, array ...$datas): array
    {
        foreach ($datas as $overwrite) {
            foreach ($overwrite as $key => $value) {
                $data[$key] = $value;
            }
        }
        return $data;
    }

    public function save(): static
    {
        array_map(fn(FileAbstract $file) => $file->saveFileContent(), $this->getFiles());
        return $this;
    }

    public function prepareFilename(string $filename): string
    {
        if ($this->getArchive()->isRepository()) {
            return $this->slug . '/' . $filename;
        }
        return $filename;
    }

//    public function __destruct()
//    {
//        $this->archive->close();
//    }

}