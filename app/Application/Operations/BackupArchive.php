<?php

namespace App\Application\Operations;

use RuntimeException;
use ZipArchive;

class BackupArchive
{
    public function password(): string
    {
        $key = config('pilot.backup_key');
        if (! is_string($key) || ! str_starts_with($key, 'base64:') || strlen(base64_decode(substr($key, 7), true) ?: '') !== 32) {
            throw new RuntimeException('Configure OPERATIONS_BACKUP_KEY com uma chave aleatória base64 de 32 bytes, distinta de APP_KEY.');
        }
        if (hash_equals((string) config('app.key'), $key)) {
            throw new RuntimeException('A chave de backup precisa ser diferente da chave da aplicação.');
        }
        if (! ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true)) {
            throw new RuntimeException('A extensão ZIP precisa suportar AES-256.');
        }

        return $key;
    }

    /** @param array<string, mixed> $manifest
     * @param  array<string, string>  $files  entry => absolute path
     */
    public function write(string $path, array $manifest, array $files): void
    {
        $password = $this->password();
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Não foi possível criar pacote de backup.');
        }
        try {
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            foreach ($files as $entry => $source) {
                if (! $zip->addFile($source, $entry)) {
                    throw new RuntimeException('Falha ao incluir arquivo no backup.');
                }
            }
            foreach (['manifest.json', ...array_keys($files)] as $entry) {
                if (! $zip->setEncryptionName($entry, ZipArchive::EM_AES_256, $password)) {
                    throw new RuntimeException('Falha ao cifrar pacote de backup.');
                }
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('Não foi possível concluir pacote cifrado.');
            }
        }
        PrivateFiles::file($path);
    }

    /** Validates all entries before the database is touched.
     * @return array<string, mixed>
     */
    public function unpack(string $archive, string $destination): array
    {
        $zip = new ZipArchive;
        if (! is_file($archive) || is_link($archive) || $zip->open($archive) !== true) {
            throw new RuntimeException('Pacote de backup inválido.');
        }
        try {
            $zip->setPassword($this->password());
            $stat = $zip->statName('manifest.json');
            if (! $stat || $stat['size'] > 2097152 || $stat['encryption_method'] !== ZipArchive::EM_AES_256) {
                throw new RuntimeException('Manifesto ausente ou excede o limite.');
            }
            $text = $zip->getFromName('manifest.json');
            if (! is_string($text)) {
                throw new RuntimeException('Chave incorreta ou pacote corrompido.');
            }
            $manifest = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || ($manifest['version'] ?? null) !== 1 || ! isset($manifest['entries'], $manifest['tables'], $manifest['app_key'])) {
                throw new RuntimeException('Manifesto de backup não suportado.');
            }
            $entries = $manifest['entries'];
            if (! is_array($entries) || count($entries) > 10001 || $zip->numFiles !== count($entries) + 1) {
                throw new RuntimeException('Lista de arquivos inválida.');
            }
            $total = 0;
            $seen = [];
            foreach ($entries as $entry) {
                $name = $entry['entry'] ?? '';
                if (! is_string($name) || ! preg_match('/^(database\.dump|files\/[0-9]{6})$/', $name) || isset($seen[$name])) {
                    throw new RuntimeException('Nome de arquivo inválido no pacote.');
                }
                $seen[$name] = true;
                $info = $zip->statName($name);
                $total += $info['size'] ?? 0;
                if (! $info || $info['encryption_method'] !== ZipArchive::EM_AES_256 || ! is_int($entry['size'] ?? null) || $info['size'] !== $entry['size'] || $total > config('pilot.restore_max_bytes')) {
                    throw new RuntimeException('Tamanho de restauração inválido ou acima do limite.');
                }
                if ($name !== 'database.dump' && (! is_string($entry['path'] ?? null) || ! preg_match('/^catalog\/photos\/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$/', $entry['path']))) {
                    throw new RuntimeException('Caminho de foto inválido no pacote.');
                }
                $stream = $zip->getStream($name);
                $target = $destination.'/'.str_replace('/', '-', $name);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Chave incorreta ou arquivo corrompido.');
                }
                $out = fopen($target, 'xb');
                if ($out === false) {
                    fclose($stream);
                    throw new RuntimeException('Falha ao criar arquivo privado de restauração.');
                }
                $copied = stream_copy_to_stream($stream, $out, $entry['size'] + 1);
                fclose($stream);
                fclose($out);
                PrivateFiles::file($target);
                $checksum = hash_file('sha256', $target);
                if ($copied !== $entry['size'] || $checksum === false || ! is_string($entry['sha256'] ?? null) || ! hash_equals($entry['sha256'], $checksum)) {
                    throw new RuntimeException('Integridade do backup inválida.');
                }
            }
            if (! isset($seen['database.dump'])) {
                throw new RuntimeException('Backup sem banco de dados.');
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }
}
