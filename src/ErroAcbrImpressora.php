<?php
declare(strict_types=1);
namespace Acbr\Printer;

final class ErroAcbrImpressora extends \RuntimeException implements \JsonSerializable
{
    public bool $falhaLimpeza = false;
    public function __construct(public readonly string $codigo, string $mensagem, public readonly bool $resultadoIncerto = false)
    {
        parent::__construct($mensagem);
    }
    public function jsonSerialize(): array
    {
        return ['codigo' => $this->codigo, 'mensagem' => $this->getMessage(), 'resultadoIncerto' => $this->resultadoIncerto]
            + ($this->falhaLimpeza ? ['falhaLimpeza' => true] : []);
    }
}
