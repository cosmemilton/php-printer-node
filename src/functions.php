<?php
declare(strict_types=1);
namespace Acbr\Printer;

function criarImpressora(array $configuracao = []): Impressora
{
    return new Impressora($configuracao);
}
