<?php
namespace System\Console;

use RuntimeException;

/**
 * php bongo release:branch → the "release" git branch gets one new commit, "Bongo X.Y.Z":
 * the clean framework from release:build (no demo app, no tests). Nothing is tagged or
 * published; master and the working folder are not touched (a temporary git worktree is used).
 */
class ReleaseBranch
{
    public function __construct(protected string $repo, protected string $branch = 'release') {}

    /** Returns the new commit's short hash */
    public function update(): string
    {
        $version = \System\Framework::version();
        $tmp = sys_get_temp_dir() . '/bongo_release_' . bin2hex(random_bytes(4));
        $build = "$tmp/build";
        $tree = "$tmp/tree";

        try {
            (new ReleaseBuilder($this->repo))->build($build);
            $this->deleteTree("$build/tests");
            $this->deleteTree("$build/storage");

            $exists = trim($this->git("branch --list {$this->branch}")) !== '';
            if ($exists) {
                $this->git('worktree add -q ' . escapeshellarg($tree) . " {$this->branch}");
            } else {
                $this->git('worktree add -q --detach ' . escapeshellarg($tree));
                $this->git("checkout -q --orphan {$this->branch}", $tree);
            }

            // Replace everything in the branch with the new build
            foreach (scandir($tree) as $item) {
                if ($item !== '.' && $item !== '..' && $item !== '.git') $this->deleteTree("$tree/$item");
            }
            $this->copyTree($build, $tree);
            $this->git('add -A', $tree);
            if (trim($this->git('status --porcelain', $tree)) === '') {
                throw new RuntimeException("The {$this->branch} branch already holds this exact build. Nothing to commit.");
            }
            $source = trim($this->git('rev-parse --short HEAD'));
            $message = "Bongo $version\n\nThe framework only, built from master $source with php bongo release:build: no demo application and no tests.";
            $this->git('commit -q -m ' . escapeshellarg($message), $tree);
            return trim($this->git('rev-parse --short HEAD', $tree));
        } finally {
            if (is_dir($tree)) {
                $this->git('worktree remove --force ' . escapeshellarg($tree));
            }
            $this->deleteTree($tmp);
        }
    }

    protected function git(string $args, ?string $dir = null): string
    {
        $cmd = 'git -C ' . escapeshellarg($dir ?? $this->repo) . " $args 2>&1";
        exec($cmd, $out, $code);
        if ($code !== 0) {
            throw new RuntimeException("git $args failed: " . implode("\n", $out));
        }
        return implode("\n", $out);
    }

    protected function copyTree(string $from, string $to): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $dest = $to . '/' . substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $from)) + 1);
            $item->isDir() ? (is_dir($dest) || mkdir($dest, 0775, true)) : copy($item->getPathname(), $dest);
        }
    }

    protected function deleteTree(string $path): void
    {
        if (is_file($path)) { @unlink($path); return; }
        if (!is_dir($path)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($path);
    }
}
