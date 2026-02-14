<?php

namespace App\Tools;

use App\File\App\Defaults\Dockerfile;
use Exception;
use RuntimeException;
use Stringable;

class Crane implements Stringable
{
    public const string CONFIG = 'config';
    public const string TAGS = 'tags';
    public const string MANIFEST = 'manifest';
    public ?string $tagUpdateFix = null;
    public ?string $tagUpdateMinor = null;
    public ?string $tagUpdateMajor = null;
    public ?string $entrypoint = null;
    public ?string $cmd = null;
    public string $image;
    public string $image_tag = 'latest';

    public function __construct(string $fullImage)
    {
        if (str_contains($fullImage, ':')) {
            [
                $this->image,
                $this->image_tag
            ] = explode(':', $fullImage, 2);
        } else {
            $this->image = $fullImage;
        }
        $this->load();
    }

    public function load(bool $force = false): static
    {
        $this->getTags($force);
        $this->getEntryPoint($force);
        $this->getCmd($force);
        $this->getPossibleUpdates($force);
        return $this;
    }

    public function getTags(bool $force = false): array
    {
        $cacheEntry = $this->getCacheEntry(self::TAGS, $force);
        if ($cacheEntry !== null) {
            return $cacheEntry;
        }
        // crane ls verwenden
        $command = "crane ls " . escapeshellarg($this->image) . " 2>&1";
        $output = shell_exec($command);
        $tags = explode("\n", trim($output));
        return $this->setCasheEntry(self::TAGS, array_filter($tags, function ($tag) {
            return !empty($tag) && !str_contains($tag, "error") && !str_contains($tag, "standard_init_linux");
        }));

    }

    private function getCacheEntry(string $type, bool $force): null|string|array
    {
        if ($force === true) {
            return null;
        }
        $key = match ($type) {
            self::TAGS => $this->image,
            self::CONFIG, self::MANIFEST => $this->__toString(),
        };
        $cacheFilePath = $this->getCacheFilePath($type);
        if (is_file($cacheFilePath)) {
            $json = json_decode(file_get_contents($cacheFilePath), true);
            if (array_key_exists($key, $json)) {
                return $json[$key];
            }
        }
        return null;
    }

    public function __toString(): string
    {
        return $this->image . ':' . $this->image_tag;
    }

    private function getCacheFilePath(string $type): string
    {
        return App::getDataDir() . '/.cache/crane.' . $type . '.json';
    }

    protected function setCasheEntry(string $type, string|array $data): string|array
    {
        $key = match ($type) {
            self::TAGS => $this->image,
            self::CONFIG, self::MANIFEST => $this->__toString(),
        };
        $cacheFilePath = $this->getCacheFilePath($type);
        if (is_file($cacheFilePath)) {
            $json = json_decode(file_get_contents($cacheFilePath), true);
            $json[$key] = $data;
        } else {
            $json = [$key => $data];
        }
        Converter::writeFileContent($cacheFilePath, json_encode($json, JSON_PRETTY_PRINT));
        return $data;
    }

    public function getEntryPoint(?string $default = null, bool $force = false)
    {
        if ($this->entrypoint !== null && $force === false) {
            return $this->entrypoint;
        }
        try {
            $imageConfig = $this->config($force);
            $entrypoint = $imageConfig['config']['Entrypoint'] ?? null;
            if (is_array($entrypoint)) {
                $entrypoint = implode(' ', $entrypoint);
            }
            $this->entrypoint = $entrypoint ?? $default;
            return $this->entrypoint ?? $default;
        } catch (Exception) {
            $this->entrypoint = null;
            return $default;
        }
    }

    private function config(bool $force): array
    {
        $cacheEntry = $this->getCacheEntry(self::CONFIG, $force);
        if ($cacheEntry !== null) {
            return $cacheEntry;
        }
        $command = "crane config " . escapeshellarg($this) . " 2>&1";
        $output = shell_exec($command);
        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }
        return $this->setCasheEntry(self::CONFIG, $data);
    }

    public function getCmd(?string $default = null, bool $force = false)
    {
        if ($this->cmd !== null && $force === false) {
            return $this->cmd;
        }
        try {
            $imageConfig = $this->config($force);
            $cmd = $imageConfig['config']['Cmd'] ?? null;
            if (is_array($cmd)) {
                $cmd = implode(' ', $cmd);
            }
            $this->cmd = $cmd;
            return $this->cmd ?? $default;
        } catch (Exception) {
            $this->cmd = null;
            return $default;
        }
    }

    public function getPossibleUpdates(bool $force = false): array
    {
        if (($this->tagUpdateFix !== null || $this->tagUpdateMinor !== null || $this->tagUpdateMajor !== null) && $force === false) {
            return [
                'fix'   => $this->tagUpdateFix,
                'minor' => $this->tagUpdateMinor,
                'major' => $this->tagUpdateMajor
            ];
        }
        // 1. Alle Tags abrufen, um nach neueren Versionen mit gleicher Major/Minor zu suchen
        $allTags = $this->getTags($force);
        if (!empty($allTags)) {
            $currentVersion = Version::fromSemverTag($this->image_tag);
            // Wenn der aktuelle Tag eine Version ist (z. B. 1.2.3)
            if ($currentVersion !== null) {
                foreach ($allTags as $foundTag) {
                    if (self::sameSchema($foundTag, $this->image_tag) === false) {
                        continue;
                    }
                    $foundVersion = Version::fromSemverTag($foundTag);
                    if ($foundVersion !== null) {
                        if ($foundVersion->major == $currentVersion->major && $foundVersion->minor == $currentVersion->minor) {
                            if (version_compare($foundTag, $this->image_tag, '>')) {
                                $this->tagUpdateFix = $foundTag;
                            }
                        } else if ($foundVersion->major == $currentVersion->major) {
                            if (version_compare($foundTag, $this->image_tag, '>')) {
                                $this->tagUpdateMinor = $foundTag;
                            }
                        } else if ($foundVersion->major > $currentVersion->major) {
                            $this->tagUpdateMajor = $foundTag;
                        }
                    }
                }
            }
        }
        return [
            'fix'   => $this->tagUpdateFix,
            'minor' => $this->tagUpdateMinor,
            'major' => $this->tagUpdateMajor
        ];
    }

    protected static function sameSchema(string $tag1, string $tag2): bool
    {
        $str_starts_with_V1 = str_starts_with($tag1, 'v');
        $str_starts_with_V2 = str_starts_with($tag2, 'v');
        if ($str_starts_with_V1 !== $str_starts_with_V2) {
            return false;
        }
        $version1 = Version::fromSemverTag($tag1);
        $version2 = Version::fromSemverTag($tag2);
        if ($version1 === null || $version2 === null) {
            return $version1 === $version2;
        }
        if ($version1->buildMetadata !== $version2->buildMetadata) {
            return false;
        }
        if ($version1->preRelease !== $version2->preRelease) {
            return false;
        }
        return true;
    }

    public static function i(string $fullImage): Crane
    {
        return new static($fullImage);
    }

    public function getArchitectures(bool $force = false): array
    {
        $cacheEntry = $this->getCacheEntry(self::MANIFEST, $force);
        if ($cacheEntry !== null) {
            return $cacheEntry;
        }
        $command = "crane manifest " . escapeshellarg($this) . " 2>&1";
        $output = @shell_exec($command);
        $data = @json_decode($output, true);
        $allowedArchitectures = Dockerfile::ARCHITECTURES;
        if (is_array($data) && array_key_exists('manifests', $data)) {
            $foundArchitectures = [];
            foreach ($data['manifests'] as $manifest) {
                $architecture = $manifest['platform']['architecture'] ?? null;
                $os = $manifest['platform']['os'] ?? null;
                $variant = $manifest['platform']['variant'] ?? null;
                if ($architecture === null || $os !== 'linux') {
                    continue;
                }
                if (in_array($architecture, $allowedArchitectures)) {
                    $foundArchitectures[] = $architecture;
                } else if (in_array($architecture . $variant, $allowedArchitectures)) {
                    $foundArchitectures[] = $architecture . $variant;
                }
            }
            if (count($foundArchitectures)) {
                return $this->setCasheEntry(self::MANIFEST, self::reworkArchitectures($foundArchitectures));
            }
        }

        try {
            $data = $this->config($force);
            if (array_key_exists('architecture', $data) && in_array($data['architecture'], $allowedArchitectures)) {
                return $this->setCasheEntry(self::MANIFEST, self::reworkArchitectures([$data['architecture']]));
            }
            return $this->setCasheEntry(self::MANIFEST, []);
        } catch (RuntimeException) {
            return $this->setCasheEntry(self::MANIFEST, []);
        }
    }

    protected static function reworkArchitectures(array $architectures): array
    {
        if (in_array('arm64', $architectures, true)) {
            if (in_array('aarch64', $architectures, true) === false) {
                $architectures[] = 'aarch64';
            }
            $key = array_search('arm64', $architectures, true);
            unset($architectures[$key]);
            return array_values($architectures);
        }
        return $architectures;
    }

}