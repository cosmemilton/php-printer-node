<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use Acbr\Printer\Impressora;
if (array_diff(array_slice($argv,1),['--enviar'])) throw new InvalidArgumentException('Uso: php examples/escpos-avancado.php [--enviar]');
$fixture=json_decode((string)file_get_contents(__DIR__.'/escpos-fixture.json'),true,32,JSON_THROW_ON_ERROR);
$printer=new Impressora($fixture['configuracaoAvancada']);
try {
    $prepared=$printer->gerarEscpos($fixture['avancado']);
    if (!$prepared['sucesso'] || $prepared['envioIniciado']) throw new RuntimeException('Falha ao preparar o cupom.');
    foreach ($fixture['comandosHex'] as $name=>$hex) {
        if (!str_contains($prepared['dados'],hex2bin($hex))) throw new RuntimeException('Comando ausente: '.$name);
    }
    if (substr_count($prepared['dados'],hex2bin($fixture['comandosHex']['corteTotal']))!==1) throw new RuntimeException('Quantidade de cortes divergente.');
    if (hash('sha256',$prepared['dados'])!==$fixture['referenciaNode']['sha256']) throw new RuntimeException('Bytes divergentes da referência Node auditada.');
    echo json_encode(['bytes'=>strlen($prepared['dados']),'sha256'=>hash('sha256',$prepared['dados']),'comandos'=>count($fixture['comandosHex']),'envioFisico'=>false,'leituras'=>$fixture['leituras']],JSON_UNESCAPED_SLASHES)."\n";
    if (in_array('--enviar',$argv,true)) {
        $result=$printer->imprimirTexto($fixture['avancado']);
        echo json_encode(['sucesso'=>$result['sucesso'],'estado'=>$result['estado'],'envioIniciado'=>$result['envioIniciado'],'resultadoIncerto'=>$result['resultadoIncerto']??false])."\n";
    }
} finally {$printer->fechar();}
