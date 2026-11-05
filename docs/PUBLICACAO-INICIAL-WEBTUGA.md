# Primeiro alinhamento manual WebTuga

Este workflow ativa apenas os cinco PHP de configuracao confirmados no manifesto.
Os tres suportes em `config` e o perfil privado de producao ja devem estar
instalados. O `.htaccess` da raiz e apenas conferido e preservado.

Um push envia codigo para o GitHub. Para este primeiro alinhamento, abrir
**Actions > Publicacao manual WebTuga > Run workflow**, mantendo a branch `main`.

1. Escolher modo `PREVIA`, confirmacao `NAO` e referencia vazia. A previa apenas
   compara ficheiros e o registo de versao: nao faz uploads nem alteracoes.
2. Rever a tabela. Antes da primeira publicacao, devem existir cinco candidatos
   previstos, tres suportes iguais e as regras de raiz preservadas, sem bloqueios.
3. Abrir uma nova execucao, escolher `PUBLICAR`, confirmacao `PUBLICAR GV` e deixar
   a referencia vazia. Rever o resumo e testar o site e o login do backoffice.

O workflow le em memoria os PHP antigos, que podem conter credenciais. PUBLICAR
tambem verifica por SHA-256 o perfil privado instalado contra o perfil preparado.
Nenhum desses conteudos e executado, incluido nos logs ou publicado como artefacto.
O perfil privado nao e substituido pelo workflow.

## Copias e ativacao

Antes de ligar ao servidor, os candidatos passam na verificacao de sintaxe PHP.
Na hospedagem, os originais devem corresponder ao backup revisto e os suportes
devem coincidir com o Git. A protecao de `config` e conferida por HTTPS, e a pagina
inicial e o login devem responder sem erros antes de ativar os ficheiros.

As copias antigas e novas sao guardadas e verificadas por SHA-256 em
`config/gvi-release-<referencia>-<indice>.before` e `.stage`, dentro da pasta
protegida. O manifesto de recuperacao e guardado antes da primeira substituicao.
Os cinco PHP entram por renomeacao, com nova conferencia antes e depois de cada
troca. A referencia publicada fica em `config/gvi-deploy.json`.

As trocas sao sequenciais. Se falharem apos comecar a ativacao, o fluxo tenta
repor os originais. Uma interrupcao do runner ou perda persistente da ligacao
pode impedir a recuperacao automatica; guardar a referencia apresentada no resumo.

As copias de preparacao podem ser reenviadas uma vez em falhas de rede selecionadas,
somente quando estao ausentes ou contêm um prefixo dos mesmos bytes. Autenticacao,
certificados, renomeacoes e registos de versao nunca sao repetidos automaticamente.

## Recuperacao e diagnostico

Para consultar uma tentativa sem escritas: `DIAGNOSTICO`, confirmacao `NAO` e
referencia do resumo. A tabela indica se as copias antigas e os candidatos estao
completos; nao imprime os seus conteudos.

Para recuperar: `REVERTER`, confirmacao `REVERTER GV` e referencia do resumo.
Sem referencia, usa a ultima publicacao registada. A reversao verifica os hashes
e recusa sobrescrever ficheiros alterados externamente ou outra versao publicada.
O perfil privado e os suportes permanecem disponiveis durante a recuperacao.

O workflow nao importa nem restaura bancos, nao envia emails ou formularios e
nao cria pagamentos. Os pedidos HTTPS de verificacao visitam apenas paginas
publicas. As copias de seguranca da pasta Rodrigo continuam a ser conservadas.

Este fluxo serve o primeiro alinhamento. Alteracoes futuras que mudem os hashes
revistos exigem um fluxo incremental proprio; nao atualizar o manifesto para
forcar uma publicacao sobre alteracoes inesperadas na hospedagem.
