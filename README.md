# ACBr Printer PHP

Componente PHP independente de framework para **ACBrPosPrinter (texto/ESC-POS)**, **FortesReport/spooler** e **DANFE/PDF de XML NF-e/NFC-e**. SDK `0.1.0`, distribuído como `cosmemilton/acbr-printer-php`. Não depende de Node.js, Delphi, ACBr Pro nem do componente fiscal PHP.

O cliente PHP usa o motor Free Pascal/Lazarus **`0.1.2-local.1`**, derivado do motor auditado Node `0.1.1`, com a correção local do título do relatório de texto Fortes: protocolo JSONL 1, revisão ACBr 48590. Os componentes PHP/Python compartilham esse motor, sem exigir Node.js. O pacote Node publicado permanece inalterado. A integração com `cosmemilton/acbr-php` é opcional, pelo XML.

[Repositório dos fontes](https://github.com/cosmemilton/php-printer-node) · pacote Composer `cosmemilton/acbr-printer-php`.

## Requisitos e instalação

- PHP **8.2+ x64**, `ext-json`, `proc_open` habilitado, Windows ou Linux.
- Composer para autoload/distribuição. O SDK não usa extensões fiscais proprietárias.
- Windows: driver/fila instalados na conta que executará PHP.
- Linux: o motor completo usa GTK2, fontes e display (desktop, WSLg ou Xvfb), inclusive para capacidades/PDF.

```sh
composer require cosmemilton/acbr-printer-php:^0.1.0
php vendor/bin/acbr-printer --help
```

A instalação Composer disponibiliza o cliente PHP. Para desenvolver a partir de um clone, use `composer install --no-scripts --no-plugins` e `php bin/acbr-printer --help`. A instalação não baixa nem executa binários automaticamente. O cadastro e a atualização de versões estão descritos em [publicação](docs/PUBLICACAO.md).

### Runtime auditado

Há duas formas de disponibilizar o motor:

1. Usar `runtimeDirectory` apontando para `win32-x64` ou `linux-x64` completos, com o `manifest.json`, fontes e licenças.
2. Baixar o [bundle auditado da release `v0.1.0`](https://github.com/cosmemilton/php-printer-node/releases/download/v0.1.0/acbr-printer-runtime-0.1.2-local.1.tgz) e executar explicitamente o instalador para um **diretório novo**, sem sobrescrever uma instalação:

```sh
php vendor/bin/acbr-printer instalar-runtime --destino ./runtime/linux-x64 --arquivo /caminho/acbr-printer-runtime-0.1.2-local.1.tgz
# No Windows, use ./runtime/win32-x64.
```

O instalador requer `ext-zlib` e `ext-phar`. O download é manual e `--arquivo` continua obrigatório: `URL` e `runtime.lock.json` identificam a release pública, sem adicionar download automático. Sem arquivo, o código `RUNTIME_LOCAL_NAO_PUBLICADO` foi preservado por compatibilidade; a mensagem orienta baixar o bundle auditado e informar o arquivo local.

O tarball `acbr-printer-runtime-0.1.2-local.1.tgz` deve ter **71.442.624 bytes** e SHA256 `0aba5fb78b40099427a74076a70d4e8edcda3b8fa243a0f31a5db5c8d2e64093`. O instalador confere esses bytes, extrai somente a plataforma atual e valida todos os hashes. Não instala compiladores nem usa npm. Os fontes do driver corrigido estão em `runtime/<plataforma>-x64/sources/driver-source.tar.gz`, acompanhados de origem e patch. Esse bundle não é uma nova publicação do pacote Node.

Sem `runtimeDirectory`, o cliente procura `runtime/<plataforma>-x64` na raiz desta biblioteca. Os binários locais são ignorados pelo Git e pelo archive Composer; não substitua o manifesto para aceitar um executável alterado.

Ao instalar o motor em `./runtime` da aplicação consumidora, informe esse caminho em `runtimeDirectory` ao criar cada instância, como no exemplo abaixo.

## Primeiro cupom sem papel

```php
<?php
require __DIR__ . '/vendor/autoload.php';
use Acbr\Printer\Impressora;

$impressora = new Impressora([
    'tipo' => 0,
    'runtimeDirectory' => __DIR__ . '/runtime/' . (PHP_OS_FAMILY === 'Windows' ? 'win32-x64' : 'linux-x64'),
]);
try {
    $resultado = $impressora->gerarEscpos("TESTE NAO FISCAL\nPHP / ACBr Printer\n");
    if (!$resultado['sucesso']) {
        throw new RuntimeException($resultado['mensagem'] ?? $resultado['codigo']);
    }
    // String binária; esta operação não acessa a impressora.
    $bytes = $resultado['dados'];
} finally {
    $impressora->fechar();
}
```

A factory `Acbr\Printer\criarImpressora($configuracao)` é equivalente ao construtor.

## ESC/POS e spooler Windows

```php
$pos = new Impressora([
    'modo' => 'escpos',
    'porta' => '\\\\localhost\\elgin',
    'tipo' => 8,
    'interpretarTags' => true,
    'cortarPapel' => true, // somente quando o corte foi solicitado
]);
try {
    // Envio explícito; não repetir automaticamente em caso de falha:
    // $resultado = $pos->imprimirTexto("<n>TESTE PHP</n>\n\n\n</corte_total>");
} finally { $pos->fechar(); }

$grafica = new Impressora(['modo' => 'spooler', 'impressora' => 'ELGIN']);
try {
    $filas = $grafica->listarImpressoras()['impressoras'];
    // $resultado = $grafica->imprimirTexto("TESTE PHP / FORTESREPORT\n");
} finally { $grafica->fechar(); }
```

O tipo **0** é texto; **8** é `ppEscPosStar`, sem inferência pela marca da impressora. Tags são opt-in, e corte exige `cortarPapel: true`. Não há abertura implícita de gaveta. UNC é um recurso do Windows: execute **PHP e worker no Windows**. Fortes usa o nome da fila, não o endereço UNC.

## DANFE e PDF

```php
$xml = file_get_contents('./nota.xml'); // XML salvo; string UTF-8 ou ['xmlBase64' => ...]
if ($xml === false) throw new RuntimeException('XML não encontrado.');
$impressora = new Impressora(['modo' => 'spooler', 'impressora' => 'ELGIN']);
try {
    $pdf = $impressora->danfePdf($xml, ['larguraPapel' => 72]);
    if (!$pdf['sucesso']) throw new RuntimeException($pdf['mensagem'] ?? $pdf['codigo']);
    $arquivo = fopen('./danfe.pdf', 'xb');
    if (!$arquivo) throw new RuntimeException('Escolha um arquivo de saída novo.');
    try { fwrite($arquivo, $pdf['pdf']); } finally { fclose($arquivo); }
    // Envio explícito de uma via:
    // $envio = $impressora->imprimirDanfe($xml, ['copias' => 1, 'larguraPapel' => 72]);
} finally { $impressora->fechar(); }
```

NF-e 55 usa A4/Fortes. NFC-e 65 pode usar Fortes/bobina, PDF ou ESC/POS. No equipamento Elgin testado com Node, o driver informava **72 mm**: esse valor eliminou os cortes vistos com 80 mm. Confira o driver de cada equipamento. A opção `larguraPapel` é DANFE, não de texto simples; a API atual não expõe margens individuais.

O renderizador apenas lê o XML local: não assina, transmite, consulta ou comprova autorização. Um resultado fiscal pode ser passado como array contendo `xmlBase64`; a aplicação continua responsável por verificar seu estado fiscal e preservar o XML original.

## API

Métodos síncronos: `capacidades()`, `listarImpressoras()`, `gerarEscpos($texto)`, `imprimirTexto($texto)`, `danfePdf($xml, $opcoes = [])`, `imprimirDanfe($xml, $opcoes = [])` e `fechar()`.

| Configuração | Padrão / contrato |
| --- | --- |
| `modo` | `escpos` ou `spooler`; padrão `escpos` |
| `porta`, `impressora` | Destino POS / fila; obrigatório somente no envio correspondente |
| `tipo` | 0 ou 8; padrão 0 |
| `colunas` | 1–200; padrão 48 |
| `linhasEntreCupons` | 0–100; padrão 0 |
| `interpretarTags`, `cortarPapel` | booleanos; padrão false |
| `runtimeDirectory` | Diretório com manifesto e motor íntegros |
| `timeoutMs` | 1–120000; padrão 30000 |

Opções DANFE: `copias` (1–99, padrão 1) e `larguraPapel` (40–300 mm, padrão 80). Campos desconhecidos são rejeitados. O cliente PHP é síncrono, sem fila assíncrona ou `AbortSignal`; serialização entre processos pertence à aplicação.

Retornos são arrays com `sucesso`, `estado`, `envioIniciado` e diagnóstico opcional. Estados: `PREPARADO`, `GERADO`, `ENVIADO` e `ERRO`. `dados`/`pdf` são strings binárias, com `dadosBase64`/`pdfBase64` preservados. `ENVIADO` confirma o transporte, **não o papel**.

Falhas do motor retornam `sucesso: false`; falhas de validação/transporte/runtime lançam `ErroAcbrImpressora` com `codigo`, `resultadoIncerto`, `falhaLimpeza` e `jsonSerialize()`. Se uma operação física começa ou pode ter recebido a solicitação antes de falhar, o resultado é conservadoramente incerto. Não há retry automático. Confira fila e papel antes de decidir reimprimir.

Texto/XML têm limite de 2 MiB e precisam ser UTF-8. Saída JSONL limitada a 32 MiB e stderr a 1 MiB. O PHP usa arquivos stdio privados no diretório temporário e polling com prazo, evitando o comportamento bloqueante de pipes no Windows. Arquivos são removidos depois de confirmar o encerramento do processo. Solicitações não entram em argumentos, shell ou logs.

## Testes

```sh
composer test
composer test:offline
# Linux sem display: xvfb-run -a php tests/offline.php
```

O workflow `CI` está configurado para PHP 8.2 e 8.5 em Ubuntu 24.04 e Windows Server 2025. Ele valida a metadata Composer, o autoload, a sintaxe e os testes de cliente, sem instalar o runtime nativo. Os quatro grupos de configuração/instalador executam nos dois sistemas; os oito grupos de transporte com worker PHP sintético executam somente no Linux. A automação não executa `test:offline`, não baixa binários, não acessa a SEFAZ e não imprime.

`tests/run.php` usa processo sintético no Linux e valida configuração, JSONL, integridade, limites, deadline e incerteza. Não acessa equipamentos. `tests/offline.php` usa o motor real somente para capacidades, filas e bytes; com `ACBR_PRINTER_XML` aponta para XML sintético e também gera PDF em memória. `ACBR_PRINTER_RUNTIME` permite override nos testes.

Exemplos não enviam papel por padrão. `--enviar` é uma ação explícita e deve ser usado somente depois de conferir o destino. As confirmações físicas e os hashes da distribuição local estão no relatório produzido durante a validação.

```sh
php examples/cupom.php
php examples/escpos-avancado.php
php examples/danfe.php nota.xml
```

O exemplo especializado compara os bytes com a referência Node e confere negrito, sublinhado, expandido, altura dupla, tamanho combinado, condensado, invertido, três alinhamentos, QR Code, Code128, EAN-13 e um único corte total. Sem `--enviar`, tudo fica em memória. No papel, as leituras esperadas são QR `ACBR-PHP-TIPO8`, Code128 `ACBRPHP123` e EAN-13 `7891234567895`. Não aciona gaveta nem beep.

## Licença e reconstrução

LGPL-2.1-or-later; projeto independente, não oficial do Projeto ACBr. O diretório do runtime inclui fontes correspondentes ACBr/Fortes, locks, receita de build e notices. Preserve-os ao redistribuir, incluindo `sources/driver-source.tar.gz` e os registros de origem/patch do hotfix. O código e a receita da base estão no [ACBr Printer Node](https://github.com/cosmemilton/acbr-printer-node/tree/v0.1.1); o hotfix local define `JobTitle` explicitamente para evitar acesso a `Owner.Name` quando o relatório Fortes é criado sem proprietário. A versão PHP não requer Node para uso.

## Estado da validação

Os testes físicos foram confirmados pelo usuário, incluindo códigos, corte e DANFE.
Houve uma ocorrência de falha do driver Elgin no spooler; o serviço foi restaurado
sem repetir trabalhos. Veja [o registro completo e a limitação de estabilidade](docs/VALIDACAO.md).
