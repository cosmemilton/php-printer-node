# Publicação no Packagist

O pacote Composer é `cosmemilton/acbr-printer-php`; o repositório é
`https://github.com/cosmemilton/php-printer-node`. Não adicionar `version` ao
`composer.json`: o Packagist obtém a versão das tags Git, como `v0.1.0`.

## Cadastro inicial

1. Entrar no [Packagist](https://packagist.org/login/) com a conta que mantém
   `cosmemilton/acbr-php`. O vendor `cosmemilton` já existe e o cadastro de outro
   pacote nesse vendor exige ser mantenedor de um pacote dele.
2. Conectar o GitHub à conta e permitir acesso ao repositório público.
3. Em [Submit](https://packagist.org/packages/submit), informar
   `https://github.com/cosmemilton/php-printer-node`, conferir o nome detectado e
   concluir o cadastro.
4. Conferir se a página do pacote informa atualização automática. A integração
   GitHub do Packagist configura o webhook; esse caminho não exige guardar token
   do Packagist no GitHub Actions.

## Primeira versão

Antes de criar a tag, conferir o CI Windows/Linux, executar
`composer validate --strict` e revisar o arquivo distribuído. A tag `v0.1.0`
deve apontar para o commit revisado. Com o hook ativo, o Packagist detecta a tag;
também é possível solicitar atualização na página do pacote como mantenedor.

Depois que a versão estiver visível no catálogo, conferir em um projeto novo:

```sh
composer require cosmemilton/acbr-printer-php:^0.1.0
php vendor/bin/acbr-printer --help
```

O pacote Composer contém somente o cliente PHP. O motor nativo é distribuído
separadamente, com tamanho, hashes, fontes e licenças preservados. Disponibilizar
o bundle auditado e documentar seu endereço público antes de anunciar a versão;
instalar o cliente não baixa ou executa esse motor automaticamente.

Essa conferência não imprime. Os testes físicos já registrados e a limitação
observada no driver Elgin estão em [VALIDACAO.md](VALIDACAO.md).

Referências oficiais: [publicação e hooks no Packagist](https://packagist.org/about),
[versão inferida do VCS](https://getcomposer.org/doc/04-schema.md#version).
