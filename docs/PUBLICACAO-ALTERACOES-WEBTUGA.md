# Publicar alteracoes do GV Industries

O primeiro alinhamento foi concluido em 05/11/2026: cinco PHP ativados, perfil
privado preservado e paginas inicial, produtos e login verificadas sem erros.
Referencia de recuperacao: `89728c40747f64e1bd636c742951d77d`.
[Execucao inicial](https://github.com/globoVac/gv_industries/actions/runs/42070323091).

O fluxo seguinte e manual: editar no XAMPP, verificar, fazer commit/push, executar
PREVIA, rever os ficheiros e so depois PUBLICAR. Um push nao publica na WebTuga.
O registo protegido `config/gvi-deploy.json` identifica o commit realmente publicado.
A comparacao usa essa base mesmo que existam varios commits novos no GitHub.

## Formulario

Abrir **Actions > Publicar alteracoes WebTuga > Run workflow**, na branch `main`.

1. Escolher `PREVIA`, confirmacao `NAO`; deixar `codigo_previa` e `referencia` vazios.
2. Rever a tabela de criacoes, atualizacoes e retiradas. Diferencas inesperadas
   bloqueiam o envio. Renomeacoes aparecem como criacao e retirada.
3. Copiar o codigo de 64 caracteres mostrado no resumo.
4. Abrir uma nova execucao, escolher `PUBLICAR`, confirmacao `PUBLICAR GV`, colar
   o codigo em `codigo_previa` e manter a referencia vazia.
5. Rever o resumo e testar as funcoes alteradas no site e no backoffice.

O codigo vincula a decisao ao commit selecionado, a base publicada e ao estado
dos ficheiros comparados. Se algum deles mudar, executar nova PREVIA. Repetir
uma execucao antiga pode conservar a versao e os campos anteriores: usar Run workflow.

Sem alteracoes de aplicacao, a previa deve mostrar zero ficheiros. Nao e preciso
PUBLICAR alteracoes apenas de documentacao ou das ferramentas do repositorio.

## Ficheiros e protecoes

Podem ser selecionados ficheiros regulares na raiz, em `assets`, `includes` e
`backoffice`: PHP, HTML, CSS, JavaScript, imagens, fontes, textos, PDFs e videos.
Links, submodulos e caminhos de outras pastas nao sao publicados.

Ferramentas (`scripts`, `tests`, `deploy`, `.github`), documentacao e metadados Git
sao ignorados. Credenciais, configuracoes privadas, `.htaccess`, dependencias,
manifestos Composer, dumps, logs, cache e uploads ficam protegidos. Uma alteracao
versionada nesses caminhos bloqueia a publicacao e precisa de tratamento proprio.
Alterar o `composer.lock` nao instala dependencias no servidor.

Os tres suportes de `config` sao conferidos contra a base publicada. PUBLICAR
tambem compara em memoria o hash do perfil privado instalado com o preparado.
O seu conteudo nao e executado, guardado em temporarios ou mostrado nos logs.
As credenciais FTP sao fornecidas apenas aos passos que consultam a hospedagem.

## Copias e recuperacao

Antes da ativacao, o fluxo confere os candidatos, a sintaxe PHP, os estados da
hospedagem e a protecao HTTPS de `config`. Guarda e verifica as copias antigas,
os candidatos novos e um manifesto em `config/gvi-update-<referencia>...`.

As trocas sao sequenciais por renomeacao, com nova verificacao antes e depois.
Retirar um ficheiro move-o para uma copia protegida. Se a ativacao falhar, o fluxo
tenta recuperar os originais; um cancelamento do runner ou falha persistente da
ligacao pode impedir essa recuperacao automatica. Guardar a referencia do resumo.

Para recuperar uma publicacao incremental: `REVERTER`, confirmacao `REVERTER GV`
e a referencia indicada. Sem referencia, usa a ultima publicacao se for incremental.
Nao e preciso codigo de previa em REVERTER. A recuperacao verifica hashes e recusa
sobrescrever alteracoes externas ou outra versao publicada. Para recuperar o
primeiro alinhamento, usar o workflow inicial, com a sua referencia propria.

O fluxo nao importa ou restaura bases de dados, nao envia emails nem formularios
e nao cria pagamentos. Os pedidos HTTPS visitam apenas a pagina inicial e o login.
As copias protegidas no servidor nao sao apagadas automaticamente.

## Videos e limites

Os ficheiros novos em Git LFS sao recuperados individualmente, com o token de
leitura do GitHub isolado do passo que recebe a senha FTP. Cada objeto e conferido
por tamanho e SHA-256: um apontador LFS nunca substitui o video ou PDF real.
Os testes simulam esta recuperacao; ainda nao houve download LFS incremental real.

Limites por execucao: 50 ficheiros, 512 MiB por ficheiro, 2 MiB para PHP e 1 GiB
somando copias anteriores e novas. Transferencias pequenas usam 30 segundos;
as maiores podem usar 300 segundos. FTPS exige TLS 1.2 ou superior e certificado
validado. As copias da pasta Rodrigo e os backups da base de dados continuam
separados das publicacoes de codigo.
