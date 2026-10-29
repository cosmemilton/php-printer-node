<?php
declare(strict_types=1);
namespace Acbr\Printer;

/** Cliente síncrono. Uma chamada cria e encerra um worker; não há retry. */
final class Impressora
{
    public const VERSION = '0.1.0';
    public const PROTOCOL_VERSION = 1;
    public const SOURCE_REVISION = 48590;
    public const TIPOS_IMPRESSORA = ['Texto' => 0, 'EscPosStar' => 8];
    private const INPUT_LIMIT = 2097152;
    private const OUTPUT_LIMIT = 33554432;
    private array $config;
    private ?string $runtimeDirectory;
    private int $timeoutMs;
    private bool $closed = false;
    private bool $active = false;

    public function __construct(array $configuracao = [])
    {
        self::keys($configuracao, ['modo','porta','impressora','tipo','colunas','linhasEntreCupons','interpretarTags','cortarPapel','runtimeDirectory','timeoutMs']);
        foreach ($configuracao as $value) if ($value === null) self::fail('CONFIGURACAO_INVALIDA', 'Campos informados não podem ser null.');
        $mode = $configuracao['modo'] ?? 'escpos'; $kind = $configuracao['tipo'] ?? 0;
        if (!in_array($mode, ['escpos','spooler'], true) || !in_array($kind, [0,8], true)) self::fail('CONFIGURACAO_INVALIDA', 'Modo ou tipo de impressora inválido.');
        $this->config = ['modo'=>$mode, 'tipo'=>$kind,
            'colunas'=>self::integer($configuracao['colunas'] ?? 48, 1, 200),
            'linhasEntreCupons'=>self::integer($configuracao['linhasEntreCupons'] ?? 0, 0, 100),
            'interpretarTags'=>self::boolean($configuracao['interpretarTags'] ?? false),
            'cortarPapel'=>self::boolean($configuracao['cortarPapel'] ?? false)];
        foreach (['porta','impressora'] as $field) if (array_key_exists($field, $configuracao)) {
            $value = $configuracao[$field];
            if (!is_string($value) || trim($value) === '' || strlen($value) > 1024 || preg_match('/[\x00-\x1f\x7f]/', $value)) self::fail('CONFIGURACAO_INVALIDA', 'Destino inválido.');
            $this->config[$field] = trim($value);
        }
        if (isset($configuracao['runtimeDirectory']) && !is_string($configuracao['runtimeDirectory'])) self::fail('CONFIGURACAO_INVALIDA', 'runtimeDirectory deve ser uma string.');
        $this->runtimeDirectory = $configuracao['runtimeDirectory'] ?? null;
        if ($this->runtimeDirectory !== null && (trim($this->runtimeDirectory) === '' || str_contains($this->runtimeDirectory, "\0"))) self::fail('CONFIGURACAO_INVALIDA', 'runtimeDirectory inválido.');
        $this->timeoutMs = self::integer($configuracao['timeoutMs'] ?? 30000, 1, 120000);
    }

    private static function fail(string $code, string $message, bool $uncertain = false): never { throw new ErroAcbrImpressora($code, $message, $uncertain); }
    private static function keys(array $values, array $allowed, string $code = 'CONFIGURACAO_INVALIDA'): void
    {
        if (array_diff(array_keys($values), $allowed)) self::fail($code, 'Há campos desconhecidos na configuração ou operação.');
    }
    private static function integer(mixed $value, int $minimum, int $maximum): int
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) self::fail('CONFIGURACAO_INVALIDA', 'Valor inteiro fora do intervalo permitido.');
        return $value;
    }
    private static function boolean(mixed $value): bool
    {
        if (!is_bool($value)) self::fail('CONFIGURACAO_INVALIDA', 'Informe um valor booleano.');
        return $value;
    }
    private static function b64(mixed $value, int $limit, bool $empty = false): string
    {
        if (!is_string($value) || (!$empty && $value === '') || strlen($value) > (int)(ceil($limit / 3) * 4) || strlen($value) % 4 !== 0) self::fail('RESPOSTA_INVALIDA', 'Conteúdo base64 inválido.');
        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) > $limit || base64_encode($decoded) !== $value) self::fail('RESPOSTA_INVALIDA', 'Conteúdo base64 inválido.');
        return $decoded;
    }
    private static function xml(string|array $value): string
    {
        if (is_array($value)) {
            try { $value = self::b64($value['xmlBase64'] ?? null, self::INPUT_LIMIT); }
            catch (ErroAcbrImpressora) { self::fail('XML_INVALIDO', 'xmlBase64 inválido.'); }
        }
        if ($value === '' || trim($value) === '' || strlen($value) > self::INPUT_LIMIT || preg_match('//u', $value) !== 1) self::fail('XML_INVALIDO', 'Informe XML UTF-8 não vazio com até 2 MiB.');
        return base64_encode($value);
    }
    private static function text(string $text): string
    {
        if ($text === '' || strlen($text) > self::INPUT_LIMIT || preg_match('//u', $text) !== 1) self::fail('TEXTO_INVALIDO', 'Informe texto UTF-8 não vazio com até 2 MiB.');
        return $text;
    }
    private static function danfe(array $options): array
    {
        self::keys($options, ['copias','larguraPapel'], 'OPERACAO_INVALIDA');
        if (isset($options['copias']) && (!is_int($options['copias']) || $options['copias'] < 1 || $options['copias'] > 99)) self::fail('OPERACAO_INVALIDA', 'copias deve ser inteiro entre 1 e 99.');
        if (array_key_exists('copias', $options) && $options['copias'] === null) self::fail('OPERACAO_INVALIDA', 'copias inválido.');
        if (array_key_exists('larguraPapel', $options) && ((!is_int($options['larguraPapel']) && !is_float($options['larguraPapel'])) || !is_finite((float)$options['larguraPapel']) || $options['larguraPapel'] < 40 || $options['larguraPapel'] > 300)) self::fail('OPERACAO_INVALIDA', 'larguraPapel deve estar entre 40 e 300 mm.');
        return $options;
    }

    public function capacidades(): array { return $this->request('capacidades'); }
    public function listarImpressoras(): array { return $this->request('listarImpressoras'); }
    public function gerarEscpos(string $texto): array { return $this->request('gerarEscpos', ['texto'=>self::text($texto)]); }
    public function imprimirTexto(string $texto): array { return $this->request('imprimirTexto', ['texto'=>self::text($texto)]); }
    public function danfePdf(string|array $xml, array $opcoes = []): array { return $this->request('danfePdf', ['xmlBase64'=>self::xml($xml), 'opcoes'=>(object)self::danfe($opcoes)]); }
    public function imprimirDanfe(string|array $xml, array $opcoes = []): array { return $this->request('imprimirDanfe', ['xmlBase64'=>self::xml($xml), 'opcoes'=>(object)self::danfe($opcoes)]); }
    public function fechar(): void { $this->closed = true; }

    private function request(string $command, array $data = []): array
    {
        if ($this->closed) self::fail('IMPRESSORA_FECHADA', 'A instância foi encerrada.');
        if ($this->active) self::fail('OPERACAO_EM_ANDAMENTO', 'Uma operação já está em andamento nesta instância síncrona.');
        $physical = in_array($command, ['imprimirTexto','imprimirDanfe'], true);
        if ($physical) {
            $destination = $this->config[$this->config['modo'] === 'spooler' ? 'impressora' : 'porta'] ?? '';
            if ($destination === '') self::fail('DESTINO_AUSENTE', 'Informe o destino da impressão.');
            if (PHP_OS_FAMILY !== 'Windows' && preg_match('~^[\\\\/]{2}~', $destination)) self::fail('DESTINO_WINDOWS_REQUER_PHP_WINDOWS', 'Execute PHP e o runtime no Windows para usar um destino UNC.');
        }
        $runtime = Runtime::resolver($this->runtimeDirectory);
        $id = bin2hex(random_bytes(16));
        $request = ['versao'=>1, 'id'=>$id, 'comando'=>$command, 'config'=>$this->config] + $data;
        try { $bytes = json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n"; }
        catch (\JsonException) { self::fail('OPERACAO_INVALIDA', 'Não foi possível codificar a operação.'); }
        $this->active = true;
        try { return $this->execute($runtime, $bytes, $id, $command, $physical); }
        finally { $this->active = false; }
    }

    private function execute(array $runtime, string $bytes, string $id, string $command, bool $physical): array
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'acbr-printer-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) self::fail('FALHA_PIPE', 'Não foi possível criar a área temporária privada.');
        $input = $directory . '/stdin'; $output = $directory . '/stdout'; $error = $directory . '/stderr';
        $process = null; $started = false; $sending = false; $response = null; $rest = ''; $offset = 0;
        try {
            if (file_put_contents($input, $bytes, LOCK_EX) !== strlen($bytes)) self::fail('FALHA_PIPE', 'Falha ao preparar a comunicação.');
            chmod($input, 0600); touch($output); chmod($output, 0600); touch($error); chmod($error, 0600);
            $env = [];
            foreach (['PATH','Path','SystemRoot','WINDIR','TEMP','TMP','HOME','LANG','LC_ALL','DISPLAY','XDG_RUNTIME_DIR'] as $name) {
                $value = getenv($name); if ($value !== false) $env[$name] = $value;
            }
            if (PHP_OS_FAMILY === 'Windows') {
                $inherited = $env['Path'] ?? $env['PATH'] ?? ''; unset($env['Path']);
                $env['PATH'] = $runtime['directory'] . '/lib;' . $runtime['directory'] . ';' . $inherited;
            } else $env['LD_LIBRARY_PATH'] = $runtime['directory'] . '/lib';
            $descriptors = [0=>['file',$input,'r'], 1=>['file',$output,'w'], 2=>['file',$error,'w']];
            $process = @proc_open([$runtime['executable']], $descriptors, $pipes, $runtime['directory'], $env, ['bypass_shell'=>true, 'suppress_errors'=>true, 'create_new_console'=>false]);
            if (!is_resource($process)) self::fail('FALHA_INICIAR_MOTOR', 'Não foi possível iniciar o motor.');
            $started = true;
            $deadline = hrtime(true) + $this->timeoutMs * 1000000;
            $exit = null;
            while (true) {
                clearstatcache(true, $output); clearstatcache(true, $error);
                $size = filesize($output); $errSize = filesize($error);
                if ($size === false || $errSize === false) self::fail('FALHA_PIPE', 'A comunicação temporária foi perdida.', $physical);
                if ($size > self::OUTPUT_LIMIT || $errSize > 1048576) self::fail('RESPOSTA_EXCESSIVA', 'O motor excedeu o limite de saída.', $physical);
                if ($size > $offset) {
                    $chunk = file_get_contents($output, false, null, $offset, $size - $offset);
                    if ($chunk === false) self::fail('FALHA_PIPE', 'Falha ao ler o motor.', $physical);
                    $offset += strlen($chunk); $rest .= $chunk;
                    while (($newline = strpos($rest, "\n")) !== false) {
                        $line = substr($rest, 0, $newline); $rest = substr($rest, $newline + 1);
                        if ($line === '') continue;
                        try { $frame = json_decode($line, true, 64, JSON_THROW_ON_ERROR); }
                        catch (\JsonException) { self::fail('RESPOSTA_INVALIDA', 'JSONL inválido do motor.', $physical); }
                        if (!is_array($frame) || ($frame['id'] ?? null) !== $id) self::fail('RESPOSTA_INVALIDA', 'Identificador inválido do motor.', $physical);
                        if (($frame['tipo'] ?? null) === 'envioIniciado') {
                            if (!$physical || $response !== null) self::fail('RESPOSTA_INVALIDA', 'Marcador de envio inválido.', $physical);
                            $sending = true; continue;
                        }
                        if ($response !== null) self::fail('RESPOSTA_INVALIDA', 'Resposta final duplicada.', $physical);
                        $response = self::result($frame, $id, $command, $physical, $sending);
                    }
                }
                $status = proc_get_status($process);
                if (!$status['running']) { $exit = $status['exitcode']; break; }
                if (hrtime(true) >= $deadline) self::fail('TIMEOUT_MOTOR', 'O motor excedeu o prazo; confira o destino antes de repetir.', $physical);
                usleep(10000);
            }
            // The process can exit after the size snapshot; drain any final frame.
            clearstatcache(true, $output);
            clearstatcache(true, $error);
            $finalSize=filesize($output); $finalErrorSize=filesize($error);
            if ($finalSize===false || $finalErrorSize===false || $finalSize>self::OUTPUT_LIMIT || $finalErrorSize>1048576) self::fail('RESPOSTA_EXCESSIVA', 'O motor excedeu o limite de saída.', $physical);
            if ($finalSize !== $offset) {
                $tail = file_get_contents($output, false, null, $offset, self::OUTPUT_LIMIT - $offset);
                if ($tail === false || $offset + strlen($tail) > self::OUTPUT_LIMIT) self::fail('RESPOSTA_INVALIDA', 'Resposta final inválida.', $physical);
                $rest .= $tail;
                while (($newline = strpos($rest, "\n")) !== false) {
                    $line = substr($rest,0,$newline); $rest = substr($rest,$newline+1); if ($line==='') continue;
                    try { $frame=json_decode($line,true,64,JSON_THROW_ON_ERROR); } catch (\JsonException) { self::fail('RESPOSTA_INVALIDA','JSONL inválido do motor.',$physical); }
                    if (!is_array($frame) || ($frame['id']??null)!==$id) self::fail('RESPOSTA_INVALIDA','Identificador inválido.',$physical);
                    if (($frame['tipo']??null)==='envioIniciado') { if (!$physical || $response!==null) self::fail('RESPOSTA_INVALIDA','Marcador inválido.',$physical); $sending=true; continue; }
                    if ($response!==null) self::fail('RESPOSTA_INVALIDA','Resposta duplicada.',$physical);
                    $response=self::result($frame,$id,$command,$physical,$sending);
                }
            }
            if ($exit !== 0 || $rest !== '' || $response === null) self::fail('FALHA_MOTOR', 'O motor encerrou sem resposta válida.', $physical && $started);
            return $response;
        } finally {
            $cleanupFailed = false;
            if (is_resource($process)) {
                $status = proc_get_status($process);
                if ($status['running']) {
                    proc_terminate($process, 9);
                    $until = hrtime(true) + 1000000000;
                    do { usleep(10000); $status = proc_get_status($process); } while ($status['running'] && hrtime(true) < $until);
                    $cleanupFailed = $status['running'];
                }
                if (!$cleanupFailed) proc_close($process);
            }
            if (!$cleanupFailed) {
                foreach ([$input,$output,$error] as $file) if (is_file($file)) @unlink($file);
                @rmdir($directory);
            } else {
                $this->closed = true;
                $failure = new ErroAcbrImpressora('FALHA_ENCERRAR_MOTOR', 'Não foi possível confirmar o encerramento do motor.', $physical && $started);
                $failure->falhaLimpeza = true; throw $failure;
            }
        }
    }

    private static function result(array $frame, string $id, string $command, bool $physical, bool $sending): array
    {
        if (($frame['versao']??null)!==1 || ($frame['sourceRevision']??null)!==48590 || !is_bool($frame['sucesso']??null) ||
            !in_array($frame['estado']??null,['PREPARADO','GERADO','ENVIADO','ERRO'],true) || !is_bool($frame['envioIniciado']??null) ||
            ($sending && !$frame['envioIniciado']) || (array_key_exists('resultadoIncerto',$frame) && !is_bool($frame['resultadoIncerto'])) ||
            (isset($frame['codigo']) && (!is_string($frame['codigo']) || strlen($frame['codigo'])>128)) ||
            (isset($frame['mensagem']) && (!is_string($frame['mensagem']) || strlen($frame['mensagem'])>8192))) self::fail('RESPOSTA_INVALIDA','Contrato de resposta inválido.',$physical);
        if (($frame['sucesso'] && ($frame['estado']==='ERRO' || ($frame['resultadoIncerto']??false))) || (!$frame['sucesso'] && $frame['estado']!=='ERRO') ||
            (!$physical && ($sending || $frame['envioIniciado'] || $frame['estado']==='ENVIADO')) ||
            ($physical && $frame['sucesso'] && ($frame['estado']!=='ENVIADO' || !$frame['envioIniciado']))) self::fail('RESPOSTA_INVALIDA','Estado de resposta inválido.',$physical);
        $result = ['versao'=>1,'id'=>$id,'sourceRevision'=>48590,'sucesso'=>$frame['sucesso'],'estado'=>$frame['estado'],'envioIniciado'=>$sending || $frame['envioIniciado']];
        foreach (['codigo','mensagem'] as $key) if (isset($frame[$key])) $result[$key]=$frame[$key];
        if (($frame['resultadoIncerto']??false) || (!$result['sucesso'] && $result['envioIniciado'])) $result['resultadoIncerto']=true;
        foreach (['dadosBase64'=>'dados','pdfBase64'=>'pdf'] as $key=>$decoded) if (array_key_exists($key,$frame)) {
            try { $result[$decoded]=self::b64($frame[$key],self::OUTPUT_LIMIT,$key==='dadosBase64'); }
            catch (ErroAcbrImpressora) { self::fail('RESPOSTA_INVALIDA','Conteúdo inválido do motor.',$physical); }
            $result[$key]=$frame[$key];
        }
        if (isset($frame['impressoras'])) {
            if (!is_array($frame['impressoras']) || !array_is_list($frame['impressoras']) || count($frame['impressoras'])>10000) self::fail('RESPOSTA_INVALIDA','Lista de impressoras inválida.',$physical);
            foreach ($frame['impressoras'] as $name) if (!is_string($name) || strlen($name)>1024 || str_contains($name,"\0")) self::fail('RESPOSTA_INVALIDA','Nome de impressora inválido.',$physical);
            $result['impressoras']=$frame['impressoras'];
        }
        if (isset($frame['capacidades'])) { if (!is_array($frame['capacidades'])) self::fail('RESPOSTA_INVALIDA','Capacidades inválidas.',$physical); $result['capacidades']=$frame['capacidades']; }
        $required=['capacidades'=>'capacidades','listarImpressoras'=>'impressoras','gerarEscpos'=>'dados','danfePdf'=>'pdf'][$command]??null;
        if ($frame['sucesso'] && $required!==null && !array_key_exists($required,$result)) self::fail('RESPOSTA_INVALIDA','Conteúdo obrigatório ausente.',$physical);
        return $result;
    }
}
