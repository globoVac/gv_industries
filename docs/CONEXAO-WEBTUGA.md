# Teste manual da ligacao WebTuga

O workflow **Testar ligacao WebTuga** faz apenas login FTPS e uma listagem NLST
da raiz. Nao faz uploads, renomeacoes, exclusoes, leituras de configuracoes
privadas nem ligacoes MySQL.

## Acesso a preparar no cPanel

Criar uma conta FTP dedicada, por exemplo `github_gvi`, no dominio
`gv-industries.co.uk`, com diretorio limitado a `/home/gvindust/public_html`.
Conservar o nome de utilizador completo indicado pelo cPanel.
A raiz vista por essa conta deve ser `/`, ja correspondente ao `public_html`.

No GitHub, abrir **Settings > Secrets and variables > Actions**:

- Em **Variables**, criar `WEBTUGA_FTP_HOST`: hostname do servidor indicado na
  Informacao de Servico da WebTuga, sem `https://`, porta ou caminho do cPanel.
- Em **Secrets**, criar `WEBTUGA_FTP_USERNAME`: utilizador completo da conta dedicada.
- Em **Secrets**, criar `WEBTUGA_FTP_PASSWORD`: palavra-passe dessa conta.

Nao guardar a palavra-passe em ficheiros, commits, comentarios ou mensagens.
O teste passa a credencial apenas pelo stdin do curl, sem a colocar em argumentos
do processo ou logs. O certificado deve ser validado; o cliente exige FTPS
explicito na porta 21, com TLS 1.2 ou superior.

## Executar e interpretar

Abrir **Actions > Testar ligacao WebTuga > Run workflow**, escolher `main`
e executar. So e permitida a execucao manual na branch principal.

O resultado deve indicar `connected: true`, certificado validado, ficheiros
esperados encontrados e zero alteracoes. A listagem bruta nao e publicada.
Se o login funcionar mas mostrar `mail`, `etc` e `public_html`, a conta aponta
para a pasta principal do alojamento e deve ser limitada a pasta do site.

Um sucesso confirma a consulta; nao comprova permissao de escrita nem valida
uma publicacao. A publicacao sera uma etapa separada, depois de conferir os
ficheiros e preservar a configuracao de producao.
