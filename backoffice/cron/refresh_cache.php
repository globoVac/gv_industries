<?php
/**
 * Cron job — renova a cache da API do Globovac antes que expire,
 * evitando que visitantes apanhem uma chamada lenta/falhada à API.
 *
 * Colocar em: public_html/backoffice/cron/refresh_cache.php
 * Agendar para correr a cada 50 minutos (a cache dura 60 min).
 */

// Só permite execução via linha de comandos (cron) — bloqueia acesso via browser
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Acesso negado');
}

require_once __DIR__ . '/../../includes/api.php';

apiGetProdutos('asp');
apiGetProdutos('vent');
apiGetProdutos('ljet');
apiGetProdutos('prad');
apiGetCategorias();
apiGetDestaque();

echo "Cache renovada em " . date('Y-m-d H:i:s') . "\n";
