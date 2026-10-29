# Validação local PHP

12 grupos de testes automatizados passaram no PHP Linux.

## Impressora real confirmada em 29/10/2026

O usuário confirmou os cinco testes de cada linguagem na fila Windows `ELGIN`,
driver `ELGIN i9(USB)`, porta USB001 e compartilhamento `\\localhost\elgin`:

| Teste | Confirmação do usuário |
| --- | --- |
| Texto tipo 0 | Legível |
| Tipo 8: negrito, sublinhado, fontes e alinhamentos | Corretos |
| QR Code, Code128, EAN-13 e corte total | Códigos lidos e corte correto |
| Texto gráfico com FortesReport | Legível e completo |
| DANFE NFC-e por ESC/POS tipo 8 | Correto |
| DANFE NFC-e por FortesReport/spooler | Correto, `larguraPapel: 72` |

Foi enviada uma única via de cada teste. Uma primeira tentativa de texto Fortes
em PHP falhou antes de `envioIniciado`; após corrigir o JobTitle no motor local,
essa etapa foi executada e confirmada. Os trabalhos bem-sucedidos não foram
repetidos. Não houve abertura de gaveta nem beep.

O XML da NFC-e é sintético, modelo 65/ambiente 2, R$ 10,00, com protocolo 100
explicitamente simulado. Os testes não transmitiram documentos à SEFAZ nem
usaram certificados reais. O XML modelo 55 foi usado somente para PDF offline.

## Ocorrência do driver e recuperação

Apesar da saída correta no papel, o Windows registrou falhas de `spoolsv.exe`
no módulo `DrvRender_x64_ELGIN.dll` após a sequência de testes gráficos. A causa
exata dentro da DLL fechada não foi comprovada. A impressão de texto usa um
formato curto customizado no Fortes; isso é diferente do formulário 72 x 297 mm
do driver, mas não comprova, por si só, a causa da falha.

Com autorização do usuário e elevação de administrador, foram identificados
três trabalhos sintéticos já confirmados, por nomes, horários e SHA256 do RAW.
Seus arquivos foram preservados em quarentena, e o spooler foi restaurado com
a fila vazia. Não houve reenvio durante a recuperação. O serviço permaneceu
ativo nas verificações finais e os testes offline voltaram a passar.

A estabilidade do driver Elgin em sequências gráficas continua uma limitação
observada; papel legível e `ENVIADO` não são prova dessa estabilidade. O SDK
não altera drivers ou configurações globais e não repete trabalhos após erro.

## Instalação e execução externas

- PHP: instalado com Composer em consumidor separado, sem symlinks e sem scripts.
- Python: wheel instalado em ambiente virtual separado.
- Ambos executaram o motor sem Node.js no PATH e geraram PDFs 55/65.
- Os comandos avançados ficaram byte a byte iguais aos gerados pelo Node.
- Os runtimes Windows/Linux foram verificados por inventário e SHA256, com fontes,
  patch, licenças e receita de reconstrução preservados.
- Coexistência conferida com `cosmemilton/acbr-php:0.1.0-alpha2` e
  `acbr-python==0.1.0a5`, sem colisões de autoload/import e sem iniciar operação fiscal.
- Testes negativos adicionais no Windows: 13 por cliente, com JSON/UTF-8 inválidos,
  limites de saída, timeout, ID/revisão, duplicação e limpeza; nenhum processo órfão.

## Distribuição local

Cliente `0.1.0`; runtime `0.1.2-local.1`. O bundle tem 71.442.624 bytes e SHA256
`0aba5fb78b40099427a74076a70d4e8edcda3b8fa243a0f31a5db5c8d2e64093`.
O instalador exige arquivo local e não acessa uma URL ainda inexistente.
Nenhum repositório remoto foi criado e nenhum pacote foi publicado nesta etapa.
