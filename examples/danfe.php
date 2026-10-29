<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use Acbr\Printer\Impressora;
$args=$argv;array_shift($args);$file=array_shift($args);
if (!$file || str_starts_with($file,'--') || array_diff($args,['--enviar'])) throw new InvalidArgumentException('Uso: php examples/danfe.php nota.xml [--enviar]');
$xml=file_get_contents($file);if($xml===false)throw new RuntimeException('XML não encontrado.');
$printer=new Impressora(['modo'=>'spooler','impressora'=>'ELGIN']);
try {
    $pdf=$printer->danfePdf($xml,['larguraPapel'=>72]);
    if(!$pdf['sucesso'])throw new RuntimeException('Não foi possível gerar PDF.');
    $output=fopen('danfe.pdf','xb');if(!$output)throw new RuntimeException('danfe.pdf já existe ou não pode ser criado.');
    try{fwrite($output,$pdf['pdf']);}finally{fclose($output);}
    echo 'PDF gerado sem envio físico: '.strlen($pdf['pdf'])." bytes\n";
    if(in_array('--enviar',$args,true)){
        $result=$printer->imprimirDanfe($xml,['copias'=>1,'larguraPapel'=>72]);
        echo json_encode(['sucesso'=>$result['sucesso'],'estado'=>$result['estado'],'envioIniciado'=>$result['envioIniciado'],'resultadoIncerto'=>$result['resultadoIncerto']??false])."\n";
    }
} finally {$printer->fechar();}
