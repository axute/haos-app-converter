<?php

namespace App\File\App;

use App\File\App\Defaults\Dockerfile as Defaults;
use App\File\FileAbstract;
use App\Tools\Converter;
use App\Tools\Webform;

class Dockerfile extends FileAbstract
{
    public bool $allowUserEnv = false;
    public bool $startupScript = false;
    public bool $needRunSh = false;
    public string $image = Defaults::DEFAULT_IMAGE;
    public string $image_tag = Defaults::DEFAULT_TAG;

    public function loadFileContent(): static
    {
        if ($this->isFile()) {
            $content = file_get_contents($this->getFilePath());
            if (stripos($content, Defaults::STARTUP_SCRIPT) !== false) {
                $this->startupScript = true;
            }
            if (stripos($content, Defaults::ALLOW_USER_ENV) !== false) {
                $this->allowUserEnv = true;
            }
            if (stripos($content, Defaults::NEED_RUN_SH) !== false) {
                $this->needRunSh = true;
            }
            if (preg_match('/^FROM\s+(.+)$/m', $content, $matches)) {
                $fullImage = trim($matches[1]);
                if (str_contains($fullImage, ':')) {
                    [
                        $this->image,
                        $this->image_tag
                    ] = explode(':', $fullImage, 2);
                } else {
                    $this->image = $fullImage;
                }
            }
        }
        return $this;
    }

    public function getFilename(): string
    {
        return 'Dockerfile';
    }

    public function __toString()
    {
        $content = [
            'FROM ' . $this->image . ((empty($this->image_tag) === false) ? ':' . $this->image_tag : ''),
        ];
        if ($this->allowUserEnv) {
            $content[] = Defaults::ALLOW_USER_ENV;
        }
        if ($this->startupScript) {
            $content[] = Defaults::STARTUP_SCRIPT;
        }
        if ($this->needRunSh || $this->allowUserEnv || $this->startupScript) {
            $content[] = Defaults::NEED_RUN_SH;
        }
        return implode("\n", $content) . "\n";
    }

    public function setImage(string $image, ?string $image_tag = null): static
    {
        $imageParts = explode(':', $image, 2);
        if (count($imageParts) === 1) {
            if (!empty($image_tag)) {
                $imageParts[1] = $image_tag;
            } else {
                $imageParts[1] = Defaults::IMAGE_TAG;
            }
        }
        $this->image = $imageParts[0];
        $this->image_tag = $imageParts[1];
        $this->saveFileContent();
        return $this;
    }

    public function updateFromWebui(Webform $webform): static
    {
        $webform->setIfDefined($this, 'allow_user_env', false, 'allowUserEnv');
        $webform->setIfDefined($this, 'startup_script', false, 'startupScript');
        $this->needRunSh = $webform->isQuirks();
        $image = $webform->extractImage();
        if ($image !== null) {
            $imageParts = explode(':', $image, 2);
            $this->image = $imageParts[0];
            $this->image_tag = $imageParts[1] ?? null;
        }
        return $this->saveFileContent();
    }

    public function saveFileContent(): static
    {
        Converter::writeFileContent($this->getFilePath(), $this);
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'image'     => $this->image,
            'image_tag' => $this->image_tag,
            'image_url' => $this->getImageUrl(),
        ];
    }

    public function getImageUrl(): string
    {
        $image = $this->image;
        if (str_contains($image, '/')) {
            $parts = explode('/', $image);
            if (str_contains($parts[0], '.')) {
                // Registry like ghcr.io, quay.io, etc.
                return 'https://' . $image;
            }
            // Docker Hub user/repo
            return 'https://hub.docker.com/r/' . $image;
        }
        // Official Docker Hub image
        return 'https://hub.docker.com/_/' . $image;
    }
}