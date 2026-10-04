<?php

/**
 * Maintainer commands (release:build, release:branch, release:package).
 * They live only in the framework repository; projects built from a release don't get them.
 * Loaded by the bongo CLI when this file exists.
 */

require_once __DIR__ . '/ReleaseBuilder.php';
require_once __DIR__ . '/ReleaseBranch.php';
require_once __DIR__ . '/Packager.php';

const RELEASE_COMMANDS_HELP = [
    'release:build <folder>' => 'Build a fresh project (framework + starter files, no demo app)',
    'release:branch' => 'Commit the clean framework (no demo, no tests) to the release branch',
    'release:package [--out=dist]' => 'Build the framework package for a GitHub release',
];

function runReleaseCommand(string $command, array $options, callable $optionValue): void
{
    $version = System\Framework::version();

    switch ($command) {
        case 'release:package':
            $path = relative_path((new System\Console\Packager(BASE_PATH))->package((string) $optionValue('out', BASE_PATH . '/dist')));
            echo "Package: $path" . PHP_EOL . "Checksum: $path.sha256" . PHP_EOL;
            echo "Attach both files to a GitHub release tagged v$version, e.g.:" . PHP_EOL;
            echo "  gh release create v$version $path $path.sha256 --notes \"What changed\"" . PHP_EOL;
            return;

        case 'release:branch':
            $commit = (new System\Console\ReleaseBranch(BASE_PATH))->update();
            echo "The release branch now holds Bongo $version (commit $commit): the framework only, no demo app, no tests." . PHP_EOL;
            echo 'Nothing was tagged or published. Push it with: git push origin release' . PHP_EOL;
            return;

        case 'release:build':
            $target = $options[0] ?? '';
            if ($target === '' || str_starts_with($target, '--')) {
                echo "Usage: php bongo release:build <folder>   e.g. php bongo release:build ../my-app\n";
                exit(1);
            }
            $count = (new System\Console\ReleaseBuilder(BASE_PATH))->build($target);
            echo "Built a fresh Bongo $version project in $target ($count files)." . PHP_EOL;
            echo "Next: cd $target, copy .env.example to .env, then php bongo key:generate, php bongo migrate, php bongo serve" . PHP_EOL;
            return;
    }
    throw new RuntimeException("Unknown command [$command]. Run: php bongo help");
}
