<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Acbr\Printer\Impressora;

function ensure(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
$runtime=getenv('ACBR_PRINTER_RUNTIME')?:null;
$config=$runtime?['runtimeDirectory'=>$runtime]:[];
$printer=new Impressora($config);
try {
    $caps=$printer->capacidades();ensure($caps['sucesso']&&$caps['capacidades']['escpos'],'Capacidades');
    $rows=$printer->listarImpressoras();ensure($rows['sucesso'],'Filas');
    $bytes=$printer->gerarEscpos("TESTE PHP OFFLINE\n");ensure($bytes['sucesso']&&!$bytes['envioIniciado']&&str_contains($bytes['dados'],'TESTE PHP OFFLINE'),'Texto 0');
    if (($fixture=getenv('ACBR_PRINTER_XML'))!==false && $fixture!=='') {
        $xml=file_get_contents($fixture);if($xml===false)throw new RuntimeException('Fixture ausente.');
        $pdf=$printer->danfePdf($xml,['larguraPapel'=>72]);ensure($pdf['sucesso']&&!$pdf['envioIniciado']&&str_starts_with($pdf['pdf'],'%PDF-'),'PDF');
        echo 'PDF bytes: '.strlen($pdf['pdf'])."\n";
    }
} finally {$printer->fechar();}
$printer=new Impressora(['tipo'=>8,'interpretarTags'=>true,'cortarPapel'=>true]+$config);
try {
    $bytes=$printer->gerarEscpos("<n>NEGRITO PHP</n>\n</corte_total>");
    ensure($bytes['sucesso']&&!$bytes['envioIniciado']&&str_contains($bytes['dados'],"\x1d\x56\x00"),'ESC/POS 8/corte em memória');
} finally {$printer->fechar();}
echo "Runtime real conferido sem impressão física.\n";
