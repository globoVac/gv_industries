# Conferir o primeiro alinhamento WebTuga

O workflow manual **Conferir ficheiros WebTuga** compara os cinco PHP que passam
a usar a configuracao privada, os tres suportes novos em `config` e as regras
originais de `.htaccess`. O manifesto `deploy/conferencia-webtuga.json` conserva
os hashes dos originais do backup de 05/11/2026 e dos candidatos revistos no Git.

Esta conferencia faz apenas consultas FTPS. Nunca descarrega `config/local.php`:
a sua presenca e verificada pelo nome na listagem da pasta. Os PHP antigos podem
conter credenciais e sao lidos apenas em memoria para calcular SHA-256, sem os
executar ou mostrar nos registos.

Resultados esperados antes do primeiro alinhamento:

- Cinco diferencas previstas: os PHP antigos ainda correspondem ao backup.
- Tres suportes novos: `.htaccess`, `bootstrap.php` e `database.php` em `config`.
- Configuracao privada `config/local.php` ainda por preparar na hospedagem.
- O `.htaccess` da raiz conserva as regras atuais de producao.

Uma diferenca inesperada ou falha de leitura exige rever os originais antes de
publicar. O workflow nao atualiza o site nem importa bases de dados.

Abrir **Actions > Conferir ficheiros WebTuga > Run workflow**, escolher `main`
e consultar a tabela no resumo. A mesma senha FTPS fica limitada a esse passo;
os testes locais e o checkout nao recebem as credenciais de hospedagem.
