<?php

namespace App\Tools;

use App\File\App\Defaults\Dockerfile;
use Exception;
use RuntimeException;

class Crane
{
    public const string CONFIG = 'config';
    public const string TAGS = 'tags';
    public const string MANIFEST = 'manifest';

    public static function getUpdateDetailed(string $image, string $tag): array
    {
        // 1. Alle Tags abrufen, um nach neueren Versionen mit gleicher Major/Minor zu suchen
        $allTags = self::getTags($image);
        $result = [
            'fix'   => null,
            'minor' => null,
            'major' => null
        ];
        if (!empty($allTags)) {
            $currentVersion = Version::fromSemverTag($tag);
            // Wenn der aktuelle Tag eine Version ist (z. B. 1.2.3)
            if ($currentVersion !== null) {

                foreach ($allTags as $foundTag) {
                    if (self::sameSchema($foundTag, $tag) === false) {
                        continue;
                    }
                    $foundVersion = Version::fromSemverTag($foundTag);
                    if ($foundVersion !== null) {
                        if ($foundVersion->major == $currentVersion->major && $foundVersion->minor == $currentVersion->minor) {
                            if (version_compare($foundTag, $tag, '>')) {
                                $result['fix'] = $foundTag;
                            }
                        } else if ($foundVersion->major == $currentVersion->major) {
                            if (version_compare($foundTag, $tag, '>')) {
                                $result['minor'] = $foundTag;
                            }
                        } else if ($foundVersion->major > $currentVersion->major) {
                            $result['major'] = $foundTag;
                        }
                    }
                }
            }
        }
        return $result;
    }

    public static function getTags(string $image): array
    {
        $cacheEntry = self::getCacheEntry(self::TAGS, $image);
        if ($cacheEntry !== null) {
            return $cacheEntry;
        }
        // crane ls verwenden
        $command = "crane ls " . escapeshellarg($image) . " 2>&1";
        $output = shell_exec($command);
        $tags = explode("\n", trim($output));
        return self::setCasheEntry(self::TAGS, $image, array_filter($tags, function ($tag) {
            return !empty($tag) && !str_contains($tag, "error") && !str_contains($tag, "standard_init_linux");
        }));
    }

    public static function getCacheEntry(string $type, string $image): null|string|array
    {
        if (is_file(self::getCacheFilePath($type))) {
            $json = json_decode(file_get_contents(self::getCacheFilePath($type)), true);
            if (array_key_exists($image, $json)) {
                return $json[$image];
            }
        }
        return null;
    }

    private static function getCacheFilePath(string $type): string
    {
        return App::getDataDir() . '/.cache/crane.' . $type . '.json';
    }

    public static function setCasheEntry(string $type, string $image, string|array $data): string|array
    {
        if (is_file(self::getCacheFilePath($type))) {
            $json = json_decode(file_get_contents(self::getCacheFilePath($type)), true);
            $json[$image] = $data;
        } else {
            $json = [$image => $data];
        }
        Converter::writeFileContent(self::getCacheFilePath($type), json_encode($json, JSON_PRETTY_PRINT));
        return $data;
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

    public static function getArchitectures(string $fullImage): array
    {
        $cacheEntry = self::getCacheEntry(self::MANIFEST, $fullImage);
        if ($cacheEntry !== null) {
            return $cacheEntry;
        }
        $command = "crane manifest " . escapeshellarg($fullImage) . " 2>&1";
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
                return self::setCasheEntry(self::MANIFEST, $fullImage, self::reworkArchitectures($foundArchitectures));
            }
        }

        try {
            $data = self::getConfig($fullImage);
            if (array_key_exists('architecture', $data) && in_array($data['architecture'], $allowedArchitectures)) {
                return self::setCasheEntry(self::MANIFEST, $fullImage, self::reworkArchitectures([$data['architecture']]));
            }
            return self::setCasheEntry(self::MANIFEST, $fullImage, []);
        } catch (RuntimeException) {
            return self::setCasheEntry(self::MANIFEST, $fullImage, []);
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

    public static function getConfig(string $image): array
    {
        $cacheEntry = self::getCacheEntry(self::CONFIG, $image);
        if ($cacheEntry !== null) {
            return $cacheEntry;
        }
        $command = "crane config " . escapeshellarg($image) . " 2>&1";
        $output = shell_exec($command);
        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }
        return self::setCasheEntry(self::CONFIG, $image, $data);
    }

    public static function getOriginalCmd(string $image, ?string $default = null): ?string
    {
        try {
            $imageConfig = Crane::getConfig($image);
            $cmd = $imageConfig['config']['Cmd'] ?? null;
            if (is_array($cmd)) {
                $cmd = implode(' ', $cmd);
            }
            return $cmd ?? $default;
        } catch (Exception) {
            return $default;
        }
    }

    public static function getOriginalEntrypoint(string $image, ?string $default = null)
    {
        try {
            $imageConfig = Crane::getConfig($image);
            $entrypoint = $imageConfig['config']['Entrypoint'] ?? null;
            if (is_array($entrypoint)) {
                $entrypoint = implode(' ', $entrypoint);
            }
            return $entrypoint ?? $default;
        } catch (Exception) {
            return $default;
        }
    }
}