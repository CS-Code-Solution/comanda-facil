<?php

namespace App\Application\Operations;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;

class PrivateFiles
{
    public static function directory(string $path): void
    {
        if (is_link($path) || (! is_dir($path) && ! mkdir($path, 0700, true))) {
            throw new RuntimeException('Não foi possível criar diretório privado.');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $owner = getenv('USERDOMAIN').'\\'.getenv('USERNAME');
            $process = new Process(['icacls', $path, '/inheritance:r', '/grant:r', $owner.':(OI)(CI)F',
                '/remove:g', '*S-1-1-0', '*S-1-5-11', '*S-1-5-32-545']);
            $process->setTimeout(15)->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Não foi possível restringir as permissões do diretório privado.');
            }
        } elseif (! chmod($path, 0700)) {
            throw new RuntimeException('Não foi possível restringir diretório privado.');
        }
    }

    public static function file(string $path): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $owner = getenv('USERDOMAIN').'\\'.getenv('USERNAME');
            $process = new Process(['icacls', $path, '/inheritance:r', '/grant:r', $owner.':F',
                '/remove:g', '*S-1-1-0', '*S-1-5-11', '*S-1-5-32-545']);
            $process->setTimeout(15)->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Não foi possível restringir arquivo privado.');
            }
        } elseif (! chmod($path, 0600)) {
            throw new RuntimeException('Não foi possível restringir arquivo privado.');
        }
    }

    public static function cleanup(string $path, string $root): void
    {
        $resolved = realpath($path);
        $parent = realpath($root);
        if (! $resolved || ! $parent || $resolved === $parent || ! str_starts_with($resolved, $parent.DIRECTORY_SEPARATOR) || is_link($path)) {
            throw new RuntimeException('Limpeza recusada fora do diretório temporário.');
        }
        (new Filesystem)->deleteDirectory($resolved);
    }
}
