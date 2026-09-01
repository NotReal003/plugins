<?php
/**
 * Merges the shared `repositories` entries (plugins/shared/repositories.json) into a
 * project's composer.json, writing the result to an output file. This keeps the
 * NGEssentials dependency graph (private path-libs + public VCS repos) defined in one
 * place instead of duplicated across every consumer (Lobby, Skywars, Bedwars, the
 * vendored libraries, ...).
 *
 * `path` repository URLs in the shared file are relative to the shared file's own
 * directory (plugins/shared/), so they're rewritten here to be relative to the
 * consuming project's directory instead -- this lets one shared file work for
 * consumers at any nesting depth (plugins/Lobby vs plugins/libraries/libVanilla).
 *
 * Usage: php merge-composer.php <project-composer.json> <shared-repositories.json> <output-path>
 */
declare(strict_types=1);

function relativePath(string $from, string $to): string
{
    $from = explode('/', rtrim($from, '/'));
    $to = explode('/', rtrim($to, '/'));

    while (count($from) > 0 && count($to) > 0 && $from[0] === $to[0]) {
        array_shift($from);
        array_shift($to);
    }

    $up = array_fill(0, count($from), '..');

    return implode('/', array_merge($up, $to)) ?: '.';
}

if (count($argv) < 4) {
    fwrite(STDERR, "usage: merge-composer.php <project-composer.json> <shared-repositories.json> <output-path>\n");
    exit(1);
}

[, $projectFile, $sharedFile, $outFile] = $argv;

// realpath() returns false for a missing file, which used to reach dirname() as a TypeError and bury
// the actual cause. The common case is pointing at composer-build.json in a project that has none.
foreach (['project manifest' => $projectFile, 'shared repositories file' => $sharedFile] as $what => $file) {
    if (realpath($file) === false) {
        fwrite(STDERR, "error: $what '$file' does not exist (working directory: " . getcwd() . ")\n");
        exit(1);
    }
}

$projectDir = dirname(realpath($projectFile));
$sharedDir = dirname(realpath($sharedFile));

$project = json_decode(file_get_contents($projectFile), true, flags: JSON_THROW_ON_ERROR);
$shared = json_decode(file_get_contents($sharedFile), true, flags: JSON_THROW_ON_ERROR);

$sharedRepos = array_filter(array_map(function (array $repo) use ($sharedDir, $projectDir) {
    if ($repo['type'] === 'path') {
        $absolute = realpath($sharedDir . '/' . $repo['url']);
        if ($absolute === false) {
            fwrite(STDERR, "warning: path repository '{$repo['url']}' (relative to $sharedDir) does not exist\n");
            return $repo;
        }
        if ($absolute === $projectDir) {
            // Don't let a project point a path repository at itself.
            return null;
        }
        $repo['url'] = relativePath($projectDir, $absolute);
    }
    return $repo;
}, $shared['repositories'] ?? []));

$project['repositories'] = array_merge($project['repositories'] ?? [], $sharedRepos);

file_put_contents($outFile, json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
