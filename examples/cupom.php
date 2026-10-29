<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use Acbr\Printer\Impressora;
$send=in_array('--enviar',$argv,true);
$printer=new Impressora(['porta'=>'\\\\localhost\\elgin','tipo'=>0]);
try {
    $text="TESTE NAO FISCAL\nACBr Printer PHP\nTipo 0 - texto\n\n\n";
    $prepared=$printer->gerarEscpos($text);
    if (!$prepared['sucesso']) throw new RuntimeException('Não foi possível gerar o cupom.');
    echo 'Bytes gerados: '.strlen($prepared['dados'])."\n";
    if ($send) {
        $result=$printer->imprimirTexto($text);
        echo json_encode(['sucesso'=>$result['sucesso'],'estado'=>$result['estado'],'envioIniciado'=>$result['envioIniciado'],'resultadoIncerto'=>$result['resultadoIncerto']??false])."\n";
    }
} finally {$printer->fechar();}
