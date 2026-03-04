<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ProjectIndex extends Command
{
    protected $signature = 'bim:index {--out=PROJECT_INDEX.md}';
    protected $description = 'Generate a markdown index of project files';

    public function handle(): int
    {
        $root = base_path();
        $out  = base_path($this->option('out'));

        $ignoreDirs = [
            'vendor', 'node_modules', 'storage', 'bootstrap/cache', '.git',
            'public/storage',
        ];

        $ignoreFiles = [
            '.env', '.DS_Store',
        ];

        $lines = [];
        $lines[] = "# BIM Project Index";
        $lines[] = "";
        $lines[] = "Generated at: " . now()->toDateTimeString();
        $lines[] = "";

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($it as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            $rel  = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');

            // ignore dirs
            foreach ($ignoreDirs as $d) {
                if (str_starts_with($rel, rtrim($d, '/') . '/')) {
                    continue 2;
                }
            }

            // ignore files
            foreach ($ignoreFiles as $f) {
                if (basename($rel) === $f) {
                    continue 2;
                }
            }

            if ($file->isFile()) {
                $lines[] = "- `{$rel}`";
            }
        }

        file_put_contents($out, implode("\n", $lines) . "\n");

        $this->info("✅ Project index written to: " . $out);
        return self::SUCCESS;
    }
}