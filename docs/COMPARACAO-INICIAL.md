# Comparacao inicial - 05/11/2026

Origem: `gv-industries-2026-11-05.zip`, descarregado do `public_html` da WebTuga.
Destino de comparacao: `C:/xampp/htdocs/gv_local`, antes da preparacao do Git.
Os ficheiros foram comparados integralmente por SHA-256 sem executar o codigo
do ZIP e sem substituir os ficheiros locais pelos de producao.

| Resultado | Ficheiros |
| --- | ---: |
| No ZIP do servidor | 1372 |
| Na copia local | 1368 |
| Identicos | 1357 |
| Diferentes | 11 |
| Apenas no servidor | 4 |
| Apenas no local | 0 |

As diferencas de codigo estavam apenas em `.htaccess`, `includes/config.php`,
`includes/database.php`, `backoffice/includes/config.php` e
`backoffice/includes/database.php`: caminhos locais, nome do banco local do
catalogo, Stripe de teste no local e Stripe live no servidor.
As outras seis diferencas e os quatro ficheiros adicionais eram de cache.
Os 100 ficheiros de assets e as 559 dependencias eram identicos.

Durante a preparacao foram retiradas as credenciais literais do codigo comum e
criadas configuracoes privadas separadas, conservando os valores de cada ambiente.
O perfil de producao preparado ainda nao foi publicado na WebTuga.

## Exportacao SQL

Ficheiro original: `gvindust_dbgvindustries (1).sql`, 50856 bytes, exportado
em 05/11/2026 as 13:45 de `gvindust_dbgvindustries`.
Servidor indicado no dump: MariaDB 11.4.13.

| Tabela | Registos no dump |
| --- | ---: |
| carrinho | 0 |
| clientes | 23 |
| clientes_moradas | 0 |
| delivery_zones | 39 |
| disponibilidade_override | 216 |
| encomendas | 24 |
| encomendas_items | 48 |
| users | 3 |
| Total | 353 |

Verificados por leitura: fim dos comandos e das strings, numero de valores de
cada linha INSERT, tabelas e colunas referidas, chaves primarias e unicas e as
tres chaves estrangeiras declaradas. Nao foram encontradas duplicacoes nessas
chaves nem referencias orfas. O ficheiro inclui START TRANSACTION e COMMIT.

O SQL nao foi importado. Esta conferencia estrutural nao substitui um teste de
restauracao num banco isolado, nem comprova que o servidor nao recebeu novas
encomendas depois da exportacao. ZIP e SQL foram recolhidos separadamente.

O dump, os dados de clientes e as configuracoes privadas estao na pasta de backup
indicada no README, fora do Git. Os relatorios privados conservam os hashes e os
resultados da comparacao inicial.
