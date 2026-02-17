<?php

namespace App;

use App\Interfaces\ArchiveAwareInterface;
use App\Interfaces\ArchiveInterface;
use App\Tools\Archive\ArchiveAwareTrait;
use App\Tools\Archive\DataFolder;
use App\Tools\Logger;
use Exception;

class Repository implements ArchiveAwareInterface
{
    use ArchiveAwareTrait;
    public function __construct(?ArchiveInterface $archive = null)
    {
        if($archive === null) {
            $this->archive = new DataFolder();
            if(!is_dir(App::getDataDir())) {
                mkdir(App::getDataDir(), 0777, true);
            }
            $this->archive->open(App::getDataDir());
        } else {
            $this->archive = $archive;
        }
        $this->archive->setRepository(true);
    }

    public function list(): array
    {
        $apps = [];
        for($i = 0; $i< $this->archive->numFiles; $i++ ) {
            $filename = $this->archive->getNameIndex($i);
            if($this->archive->isDir($filename)) {
                if(stripos(trim($filename,'/'),'/') === false) {
                    $slug = basename(trim($filename,'/'));
                    try {
                        $app = App::getByZip($slug, $this->archive);
                        if($app->config->isFile()) {
                            $apps[] = $app->getData();
                        }

                    } catch (Exception $e) {
                        Logger::error($e->getMessage(), $e);
                        continue;
                    }
                }
            }
        }

        // Alphabetisch sortieren nach "name"
        usort($apps, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $apps;
    }

    public function getArchive(): ArchiveInterface
    {
        return $this->archive;
    }

    public function prepareFilename(string $filename): string
    {
        return $filename;
    }
}