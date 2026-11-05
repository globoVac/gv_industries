# GV Industries

Codigo do site e backoffice de `gv-industries.co.uk`.
Repositorio privado: https://github.com/globoVac/gv_industries.

| Ambiente | Endereco | Ficheiros |
| --- | --- | --- |
| Local XAMPP | `http://gv-industries.local/` | `C:/xampp/htdocs/gv_local` |
| Producao WebTuga | `https://gv-industries.co.uk/` | `/home/gvindust/public_html` |

## Preparar uma instalacao

1. Clonar com Git e Git LFS. Executar `git lfs install --local` e `git lfs pull`.
   Os MP4 de `assets/video` devem ser videos completos, nunca apontadores LFS.
2. Executar `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
   Usar o `composer.lock` existente; uma publicacao nao faz `composer update`.
3. Preparar `config/local.php` a partir de `config/local.example.php`, preenchendo
   apenas os dados do ambiente de destino por um canal privado.
4. Preparar o MySQL e os eventuais uploads a partir das copias privadas.
5. Rever o `.htaccess` de cada ambiente. `.htaccess.example` conserva as regras
   originais de producao; o `.htaccess` real do XAMPP mantem o virtual host local.

`config/local.php` nao entra no Git. O site e o backoffice usam o mesmo perfil,
independentemente do cabecalho HTTP Host. O local conserva Stripe de teste e o
MySQL local. A WebTuga usa o seu perfil de producao, a sua base de dados e Stripe
live; o catalogo de produtos de producao e consultado pela API GloboVac.
Nao trocar estes perfis durante uma atualizacao.

O envio SMTP conserva os valores de cada instalacao. Os testes abaixo nao enviam
emails, nao criam pagamentos e nao usam bases de dados reais.

## Ficheiros versionados e copias privadas

Entram no Git o PHP, CSS, JavaScript, imagens fixas, videos, os manifestos Composer,
os modelos de configuracao, a documentacao e as ferramentas de verificacao.
Os videos MP4 usam Git LFS; o maior tem aproximadamente 74 MiB. Consultar a
[documentacao GitHub sobre ficheiros grandes](https://docs.github.com/en/repositories/working-with-files/managing-large-files/about-large-files-on-github).

Ficam fora do Git as credenciais, os `.htaccess` reais de cada ambiente, dumps SQL,
ZIPs, dependencias instaladas, cache, logs e uploads. Consultar `.gitignore`.
Os backups desta preparacao estao em `Rodrigo/gv_config_privadas_backup`, separados
em producao, local e relatorios, com um manifesto SHA-256. Consultar o `LEIA-ME.txt`
nessa pasta. O backup da base de dados nunca acompanha uma publicacao de codigo.

## Verificacoes

```sh
php -n tests/configuracao.php
php tests/conexao-webtuga.php
```

O workflow **Verificar codigo** confere a sintaxe PHP e executa estes testes sem
credenciais. Um push envia codigo para o GitHub; nao publica na WebTuga.

O workflow manual **Testar ligacao WebTuga** consulta a pasta por FTPS e valida o
certificado. Nao publica ficheiros. A configuracao desse acesso esta em
[CONEXAO-WEBTUGA.md](docs/CONEXAO-WEBTUGA.md).

A introducao do perfil privado na WebTuga e a publicacao manual das alteracoes
ainda precisam de ser preparadas e verificadas apos o teste de ligacao.
Nao copiar o repositorio inteiro para o servidor: `.git`, ferramentas, testes,
documentacao e workflows nao fazem parte dos ficheiros publicos do site.
