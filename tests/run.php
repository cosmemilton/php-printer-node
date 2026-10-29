<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Acbr\Printer\{Impressora,ErroAcbrImpressora,Runtime,InstaladorRuntime};

function check(bool $condition,string $message='Falha'): void { if (!$condition) throw new RuntimeException($message); }
function error(string $code,callable $action,?bool $uncertain=null): void {
    try { $action(); } catch (ErroAcbrImpressora $exception) {
        check($exception->codigo===$code,'Esperado '.$code.', recebido '.$exception->codigo);
        if ($uncertain!==null) check($exception->resultadoIncerto===$uncertain,'Incerteza divergente.');
        return;
    }
    throw new RuntimeException('Esperado erro '.$code);
}
$passed=0;
function test(string $name,callable $action): void { global $passed; $action(); $passed++; echo "OK $name\n"; }
function clean(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path,FilesystemIterator::SKIP_DOTS) as $entry) clean($entry->getPathname());
    rmdir($path);
}

test('configuração fechada e valores estritos',function():void {
    foreach ([['tipo'=>1],['tipo'=>true],['tipo'=>null],['timeoutMs'=>0],['colunas'=>1.5],['cortarPapel'=>1],['interpretarTags'=>'sim'],['porta'=>''],['porta'=>"x\0y"],['runtimeDirectory'=>1],['runtimeDirectory'=>null],['opcaoInventada'=>1]] as $config) error('CONFIGURACAO_INVALIDA',fn()=>new Impressora($config));
});
test('validação antes de acessar runtime',function():void {
    $p=new Impressora(['runtimeDirectory'=>__DIR__.'/nao-existe']);
    error('TEXTO_INVALIDO',fn()=>$p->gerarEscpos(''));
    error('TEXTO_INVALIDO',fn()=>$p->gerarEscpos("\xff"));
    error('TEXTO_INVALIDO',fn()=>$p->gerarEscpos(str_repeat('x',2097153)));
    error('XML_INVALIDO',fn()=>$p->danfePdf(['xmlBase64'=>'AB==']));
    error('XML_INVALIDO',fn()=>$p->danfePdf("\xff"));
    error('OPERACAO_INVALIDA',fn()=>$p->danfePdf('<NFe/>',['margemSuperior'=>1]));
    error('OPERACAO_INVALIDA',fn()=>$p->danfePdf('<NFe/>',['copias'=>0]));
    error('OPERACAO_INVALIDA',fn()=>$p->danfePdf('<NFe/>',['larguraPapel'=>INF]));
    error('DESTINO_AUSENTE',fn()=>$p->imprimirTexto('teste'));
    error('RUNTIME_AUSENTE',fn()=>$p->capacidades());
    $p->fechar(); error('IMPRESSORA_FECHADA',fn()=>$p->capacidades());
});
test('instalador não sobrescreve destino nem aceita checksum incorreto',function():void {
    error('DESTINO_EXISTENTE',fn()=>InstaladorRuntime::instalar(__DIR__,__FILE__));
    $input=tempnam(sys_get_temp_dir(),'acbr-archive-'); file_put_contents($input,'invalido');
    $destination=sys_get_temp_dir().'/acbr-install-test-'.bin2hex(random_bytes(8));
    try { error('ARTEFATO_DIVERGENTE',fn()=>InstaladorRuntime::instalar($destination,$input)); check(!file_exists($destination)); }
    finally { unlink($input); clean($destination); }
});
test('runtime público não baixa automaticamente e exige arquivo explícito',function():void {
    $lock=json_decode((string)file_get_contents(__DIR__.'/../runtime.lock.json'),true,16,JSON_THROW_ON_ERROR);
    check(InstaladorRuntime::URL===$lock['remoteUrl'] && $lock['status']==='public-manual-install');
    check(InstaladorRuntime::RUNTIME_VERSION==='0.1.2-local.1');
    $destination=sys_get_temp_dir().'/acbr-unpublished-test-'.bin2hex(random_bytes(8));
    error('RUNTIME_LOCAL_NAO_PUBLICADO',fn()=>InstaladorRuntime::instalar($destination));
    check(!file_exists($destination),'Não deve criar destino sem arquivo local.');
});

if (PHP_OS_FAMILY==='Linux') {
    $directory=sys_get_temp_dir().'/acbr-php-fake-'.bin2hex(random_bytes(8));mkdir($directory,0700);
    $worker=$directory.'/worker';
    $script=<<<'PHP'
#!/usr/bin/php
<?php
$r=json_decode(stream_get_contents(STDIN),true); $mode=$r['texto']??'OK';
$id=$r['id'];$command=$r['comando'];$physical=in_array($command,['imprimirTexto','imprimirDanfe']);
if ($mode==='TIMEOUT') { usleep(2000000); exit; }
if ($mode==='STDERR') { fwrite(STDERR,str_repeat('x',2097152)); usleep(500000); exit; }
if ($mode==='STDOUT') { echo str_repeat('x',33554433); usleep(500000); exit; }
if ($mode==='BADUTF8') { echo "\xff\n"; exit; }
if ($mode==='BADJSON') { echo "{bad}\n"; exit; }
if ($physical || $mode==='BADMARKER') { echo json_encode(['tipo'=>'envioIniciado','id'=>$id])."\n"; fflush(STDOUT); }
if ($mode==='CRASH') exit(7);
$f=['versao'=>1,'id'=>$id,'sourceRevision'=>48590,'sucesso'=>true,'estado'=>$physical?'ENVIADO':'GERADO','envioIniciado'=>$physical];
if ($command==='capacidades') {$f['estado']='PREPARADO';$f['capacidades']=['escpos'=>true,'danfePdf'=>true];}
if ($command==='listarImpressoras') {$f['estado']='PREPARADO';$f['impressoras']=['FAKE'];}
if ($command==='gerarEscpos') $f['dadosBase64']=base64_encode("\x1b@".$mode);
if ($command==='danfePdf') $f['pdfBase64']=base64_encode('%PDF-1.4 fake');
if ($mode==='BADID') $f['id']='outro';
if ($mode==='BADREV') $f['sourceRevision']=1;
if ($mode==='BADSTATE') $f['estado']='ENVIADO';
if ($mode==='BADB64') $f['dadosBase64']='AA=';
if ($mode==='NOOUTPUT') unset($f['dadosBase64']);
if ($mode==='ERROR') {$f['sucesso']=false;$f['estado']='ERRO';$f['codigo']='ERRO_ACBR';$f['mensagem']='Erro sintético';}
echo json_encode($f)."\n";if ($mode==='DUPLICATE')echo json_encode($f)."\n";
PHP;
    file_put_contents($worker,$script);chmod($worker,0700);
    $manifest=['formatVersion'=>1,'sourceRevision'=>48590,'platform'=>'linux','arch'=>'x64','executable'=>'worker','files'=>['worker'=>hash_file('sha256',$worker)]];
    file_put_contents($directory.'/manifest.json',json_encode($manifest));
    $create=fn(array $config=[])=>new Impressora($config+['runtimeDirectory'=>$directory]);
    try {
        test('JSONL capacidades, filas, bytes e PDF',function()use($create):void {
            $p=$create();check($p->capacidades()['capacidades']['escpos']);check($p->listarImpressoras()['impressoras']===['FAKE']);
            check($p->gerarEscpos('CAFÉ')['dados']==="\x1b@CAFÉ");check(str_starts_with($p->danfePdf(['xmlBase64'=>base64_encode('<NFe/>')],['larguraPapel'=>72])['pdf'],'%PDF-'));$p->fechar();
        });
        test('falha do motor mantém retorno estruturado',function()use($create):void { $p=$create();$r=$p->gerarEscpos('ERROR');check($r['sucesso']===false && $r['codigo']==='ERRO_ACBR' && $r['envioIniciado']===false); });
        test('respostas inválidas recusadas',function()use($create):void {foreach(['BADJSON','BADUTF8','BADID','BADREV','BADSTATE','BADB64','NOOUTPUT','DUPLICATE','BADMARKER']as$mode){$p=$create();error('RESPOSTA_INVALIDA',fn()=>$p->gerarEscpos($mode),false);}});
        test('envio sintético preserva marcador sem hardware',function()use($create):void { $p=$create(['porta'=>'fake']);$r=$p->imprimirTexto('OK');check($r['estado']==='ENVIADO'&&$r['envioIniciado']===true); });
        test('deadline e incerteza física sem repetição',function()use($create):void {
            $start=microtime(true);$p=$create(['timeoutMs'=>50]);error('TIMEOUT_MOTOR',fn()=>$p->gerarEscpos('TIMEOUT'),false);check(microtime(true)-$start<1.5);
            $p=$create(['timeoutMs'=>50,'porta'=>'fake']);error('TIMEOUT_MOTOR',fn()=>$p->imprimirTexto('TIMEOUT'),true);
            $p=$create(['porta'=>'fake']);error('FALHA_MOTOR',fn()=>$p->imprimirTexto('CRASH'),true);
        });
        test('limites de stdout e stderr',function()use($create):void {foreach(['STDERR','STDOUT']as$mode){$p=$create();error('RESPOSTA_EXCESSIVA',fn()=>$p->gerarEscpos($mode),false);}});
        test('destino Windows recusado no PHP Linux',function()use($create):void {$p=$create(['porta'=>'\\\\localhost\\elgin']);error('DESTINO_WINDOWS_REQUER_PHP_WINDOWS',fn()=>$p->imprimirTexto('teste'),false);});
        test('runtime adulterado e caminhos inseguros recusados',function()use($directory,$worker,$script,$manifest):void {
            file_put_contents($worker,$script."\n// alterado\n");error('RUNTIME_ALTERADO',fn()=>Runtime::resolver($directory));file_put_contents($worker,$script);
            $bad=$manifest;$bad['files']['../escape']=str_repeat('a',64);file_put_contents($directory.'/manifest.json',json_encode($bad));error('RUNTIME_INVALIDO',fn()=>Runtime::resolver($directory));
            file_put_contents($directory.'/manifest.json',json_encode($manifest));
        });
    } finally {clean($directory);}
} else echo "SKIP workers PHP sintéticos executáveis somente no Linux; use test:offline no Windows.\n";
echo "$passed testes passaram; nenhum dispositivo físico foi acessado.\n";
