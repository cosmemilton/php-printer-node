# ACBr Printer PHP

Componente independente de impressão PHP 8.2+, sem frameworks, Delphi ou ACBr Pro. Não alterar os componentes fiscais. Motor nativo JSONL 0.1.2-local.1, derivado do pacote Node 0.1.1 com hotfix Fortes, fixado por hashes e fontes/licenças. O Node publicado não foi alterado.

- Não imprimir, cortar, abrir gaveta ou acessar SEFAZ nos testes automatizados. Testes físicos exigem autorização explícita e uma tentativa, sem retry automático.
- ENVIADO confirma o transporte, não o papel. Falha após início pode ter resultadoIncerto.
- Windows UNC requer PHP e worker no Windows; Fortes usa nome da fila. Margens individuais não são expostas no protocolo atual.
- Preservar hashes, fontes e licenças. Não publicar nem criar repositórios sem solicitação.
- Instalação nativa apenas por arquivo local auditado enquanto não houver publicação do bundle corrigido; nunca voltar ao download antigo Node 0.1.1.
- Texto/XML não entram em shell, argumentos ou logs; arquivos stdio temporários devem ser privados e removidos após encerramento confirmado.

- Repositório dos fontes: https://github.com/cosmemilton/php-printer-node. O nome Composer permanece `cosmemilton/acbr-printer-php`; publicação no Packagist é uma etapa separada da atualização dos fontes.
- CI deve usar somente testes sem runtime nativo e sem hardware; não chamar `test:offline` nem os exemplos com `--enviar` automaticamente.
