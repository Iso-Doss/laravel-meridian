<?php

namespace IsoDoss\Meridian\Support;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Fingerprint of the files watched by "meridian:listen" (polling, no
 * dependency on Node like horizon:listen).
 */
class FileWatcher
{
    /**
     * Directories never watched: they change on their own.
     */
    public const IGNORED = ['vendor', 'node_modules', 'storage', '.git', 'cache'];

    /**
     * @param  array<int, string>  $paths  Files or directories, relative to the base path or absolute.
     */
    public function __construct(protected array $paths, protected string $basePath) {}

    /**
     * Changes whenever a watched file is added, removed or modified.
     */
    public function fingerprint(): string
    {
        $lines = [];

        foreach ($this->paths as $path) {
            $absolute = str_starts_with($path, '/') ? $path : rtrim($this->basePath, '/').'/'.$path;

            if (is_file($absolute)) {
                $lines[] = $this->line(new SplFileInfo($absolute));
            } elseif (is_dir($absolute)) {
                foreach ($this->phpFiles($absolute) as $file) {
                    $lines[] = $this->line($file);
                }
            }
        }

        sort($lines);

        return md5(implode("\n", $lines));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    protected function phpFiles(string $directory): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $file) => ! ($file->isDir() && in_array($file->getFilename(), self::IGNORED, true))
        ));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    protected function line(SplFileInfo $file): string
    {
        return $file->getPathname().':'.$file->getMTime().':'.$file->getSize();
    }
}
