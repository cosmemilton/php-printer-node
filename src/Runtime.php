<?php
declare(strict_types=1);
namespace Acbr\Printer;

final class Runtime
{
    public static function plataforma(): string
    {
        if (PHP_INT_SIZE !== 8 || !in_array(strtolower(php_uname('m')), ['x86_64', 'amd64'], true)) {
            throw new ErroAcbrImpressora('PLATAFORMA_NAO_SUPORTADA', 'Use PHP x64 em Windows ou Linux.');
        }
        return match (PHP_OS_FAMILY) {
            'Windows' => 'win32', 'Linux' => 'linux',
            default => throw new ErroAcbrImpressora('PLATAFORMA_NAO_SUPORTADA', 'Use PHP x64 em Windows ou Linux.'),
        };
    }

    public static function arquivo(string $root, string $name): string
    {
        if ($name === '' || strlen($name) > 2048 || str_contains($name, '\\') || str_contains($name, ':') || str_starts_with($name, '/') ||
            preg_match('~(?:^|/)(?:\.|\.\.)(?:/|$)~', $name) || str_contains($name, '//') || str_contains($name, "\0")) {
            throw new ErroAcbrImpressora('RUNTIME_INVALIDO', 'Caminho inválido no manifesto do runtime.');
        }
        $current = $root;
        foreach (explode('/', $name) as $part) {
            $current .= DIRECTORY_SEPARATOR . $part;
            if (is_link($current)) throw new ErroAcbrImpressora('RUNTIME_INVALIDO', 'Links não são aceitos no runtime.');
        }
        return $current;
    }

    /** @return array{directory:string,executable:string} */
    public static function resolver(?string $directory = null): array
    {
        $platform = self::plataforma();
        $directory ??= dirname(__DIR__) . '/runtime/' . $platform . '-x64';
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) throw new ErroAcbrImpressora('RUNTIME_AUSENTE', 'Instale o runtime ou informe runtimeDirectory.');
        $file = $root . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($file) || is_link($file) || filesize($file) > 1024 * 1024) throw new ErroAcbrImpressora('RUNTIME_AUSENTE', 'Manifesto ausente ou inválido.');
        try { $manifest = json_decode((string)file_get_contents($file), true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new ErroAcbrImpressora('RUNTIME_INVALIDO', 'Manifesto inválido.'); }
        if (!is_array($manifest) || ($manifest['formatVersion'] ?? null) !== 1 || ($manifest['sourceRevision'] ?? null) !== 48590 ||
            ($manifest['platform'] ?? null) !== $platform || ($manifest['arch'] ?? null) !== 'x64' ||
            !is_string($manifest['executable'] ?? null) || !is_array($manifest['files'] ?? null) ||
            count($manifest['files']) > 10000 || !array_key_exists($manifest['executable'], $manifest['files'])) {
            throw new ErroAcbrImpressora('RUNTIME_INVALIDO', 'Contrato ou plataforma do runtime incompatível.');
        }
        foreach ($manifest['files'] as $name => $hash) {
            if (!is_string($name) || !is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) throw new ErroAcbrImpressora('RUNTIME_INVALIDO', 'Hash inválido.');
            $path = self::arquivo($root, $name);
            if (!is_file($path)) throw new ErroAcbrImpressora('RUNTIME_INCOMPLETO', 'Arquivo necessário ao runtime ausente.');
            $resolved = realpath($path);
            $prefix = $root . DIRECTORY_SEPARATOR;
            if ($resolved === false || !str_starts_with($platform === 'win32' ? strtolower($resolved) : $resolved, $platform === 'win32' ? strtolower($prefix) : $prefix)) {
                throw new ErroAcbrImpressora('RUNTIME_INVALIDO', 'Arquivo saiu do diretório do runtime.');
            }
            if (!hash_equals($hash, (string)hash_file('sha256', $path))) throw new ErroAcbrImpressora('RUNTIME_ALTERADO', 'O runtime difere dos hashes distribuídos.');
        }
        $executable = self::arquivo($root, $manifest['executable']);
        if ($platform === 'linux' && !is_executable($executable)) throw new ErroAcbrImpressora('RUNTIME_INCOMPLETO', 'O worker não tem permissão de execução.');
        return ['directory' => $root, 'executable' => $executable];
    }
}
