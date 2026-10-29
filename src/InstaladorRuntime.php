<?php
declare(strict_types=1);
namespace Acbr\Printer;

/** Instalação explícita e verificável; não executa durante composer install. */
final class InstaladorRuntime
{
    public const RUNTIME_VERSION = '0.1.2-local.1';
    public const FILE = 'acbr-printer-runtime-0.1.2-local.1.tgz';
    public const URL = null;
    public const SHA256 = '0aba5fb78b40099427a74076a70d4e8edcda3b8fa243a0f31a5db5c8d2e64093';
    public const BYTES = 71442624;

    public static function instalar(string $destino, ?string $arquivoLocal = null): array
    {
        if ($arquivoLocal === null) throw new ErroAcbrImpressora('RUNTIME_LOCAL_NAO_PUBLICADO', 'Informe arquivo local; publicação remota ainda não existe.');
        if (!extension_loaded('zlib') || !class_exists(\PharData::class)) throw new ErroAcbrImpressora('DEPENDENCIA_AUSENTE', 'Instalação requer ext-zlib e ext-phar.');
        if (trim($destino) === '' || str_contains($destino, "\0") || file_exists($destino) || is_link($destino)) throw new ErroAcbrImpressora('DESTINO_EXISTENTE', 'Escolha um diretório novo para o runtime.');
        $platform = Runtime::plataforma();
        $parent = dirname($destino);
        if (!is_dir($parent) && !mkdir($parent,0700,true)) throw new ErroAcbrImpressora('INSTALACAO_FALHOU', 'Não foi possível preparar o destino.');
        $parent = realpath($parent);
        if ($parent === false) throw new ErroAcbrImpressora('INSTALACAO_FALHOU', 'Destino inválido.');
        $destination = $parent . DIRECTORY_SEPARATOR . basename($destino);
        if (in_array(basename($destination), ['.','..',''],true)) throw new ErroAcbrImpressora('INSTALACAO_FALHOU', 'Destino inválido.');
        $temporary = $parent . DIRECTORY_SEPARATOR . '.acbr-printer-install-' . bin2hex(random_bytes(12));
        if (!mkdir($temporary,0700)) throw new ErroAcbrImpressora('INSTALACAO_FALHOU','Não foi possível preparar instalação.');
        $bundle = $temporary . '/bundle.tgz'; $tar = $temporary . '/bundle.tar'; $stage = $temporary . '/runtime';
        try {
            if (!is_file($arquivoLocal) || !copy($arquivoLocal,$bundle)) throw new ErroAcbrImpressora('INSTALACAO_FALHOU','Tarball local indisponível.');
            if (filesize($bundle)!==self::BYTES || !hash_equals(self::SHA256,(string)hash_file('sha256',$bundle))) throw new ErroAcbrImpressora('ARTEFATO_DIVERGENTE','O tarball difere dos bytes auditados.');
            $input = gzopen($bundle,'rb'); $output = fopen($tar,'xb');
            if (!$input || !$output) throw new ErroAcbrImpressora('INSTALACAO_FALHOU','Não foi possível abrir o tarball.');
            try {
                $total=0;
                while (!gzeof($input)) {
                    $chunk=gzread($input,1048576);
                    if ($chunk===false) throw new ErroAcbrImpressora('INSTALACAO_FALHOU','Arquivo gzip inválido.');
                    $total+=strlen($chunk);
                    if ($total>300*1024*1024 || fwrite($output,$chunk)!==strlen($chunk)) throw new ErroAcbrImpressora('INSTALACAO_FALHOU','Tarball excede o orçamento de extração.');
                }
            } finally { gzclose($input); fclose($output); }
            $archive = new \PharData($tar);
            $prefix='package/runtime/'.$platform.'-x64/';
            $manifestContent=self::member($archive,$prefix.'manifest.json');
            $manifest=json_decode($manifestContent,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !is_array($manifest['files']??null) || count($manifest['files'])>10000) throw new ErroAcbrImpressora('RUNTIME_INVALIDO','Inventário inválido.');
            mkdir($stage,0700);
            foreach ($manifest['files'] as $name=>$hash) {
                if (!is_string($name) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash)) throw new ErroAcbrImpressora('RUNTIME_INVALIDO','Inventário inválido.');
                $path=Runtime::arquivo($stage,$name); $data=self::member($archive,$prefix.$name);
                if (!hash_equals($hash,hash('sha256',$data))) throw new ErroAcbrImpressora('RUNTIME_ALTERADO','Hash de arquivo extraído divergente.');
                if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
                if (file_put_contents($path,$data,LOCK_EX)!==strlen($data)) throw new ErroAcbrImpressora('INSTALACAO_FALHOU','Falha ao gravar runtime.');
                chmod($path,0600);
            }
            file_put_contents($stage.'/manifest.json',$manifestContent); chmod($stage.'/manifest.json',0600);
            $executable=Runtime::arquivo($stage,(string)($manifest['executable']??'')); chmod($executable,0700);
            Runtime::resolver($stage);
            if (file_exists($destination) || !rename($stage,$destination)) throw new ErroAcbrImpressora('INSTALACAO_FALHOU','O destino foi alterado durante a instalação.');
            return ['sucesso'=>true,'diretorio'=>$destination,'platform'=>$platform,'sourceRevision'=>48590,'runtimeVersion'=>self::RUNTIME_VERSION,'bundleSha256'=>self::SHA256];
        } catch (ErroAcbrImpressora $error) { throw $error; }
        catch (\Throwable) { throw new ErroAcbrImpressora('INSTALACAO_FALHOU','Não foi possível instalar o runtime auditado.'); }
        finally { self::remover($temporary); }
    }

    private static function member(\PharData $archive,string $name): string
    {
        if (!isset($archive[$name]) || !$archive[$name]->isFile() || $archive[$name]->isLink() || $archive[$name]->getSize()>200*1024*1024) throw new ErroAcbrImpressora('RUNTIME_INVALIDO','Arquivo do runtime ausente ou inválido.');
        return $archive[$name]->getContent();
    }

    private static function remover(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) return;
        foreach (new \FilesystemIterator($directory,\FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir() && !$entry->isLink()) self::remover($entry->getPathname()); else @unlink($entry->getPathname());
        }
        @rmdir($directory);
    }
}
