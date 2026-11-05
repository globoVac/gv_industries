<?php
/**
 * API helper functions for GV Industries
 * Consumes GloboVac API in production with caching
 */

define('GLOBOVAC_API', 'https://www.globovac.net/api');
define('API_CACHE_DIR', dirname(__DIR__) . '/cache/');
define('API_CACHE_MINUTES', 60); // Cache por 60 minutos

// Criar pasta de cache se não existir
if (!is_dir(API_CACHE_DIR)) {
    mkdir(API_CACHE_DIR, 0755, true);
}

function apiGet($endpoint, $params = []) {
    $url = GLOBOVAC_API . '/' . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header'  => 'Accept: application/json'
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return [];
    }

    return json_decode($response, true) ?? [];
}

function apiGetCached($endpoint, $params = [], $cache_minutes = API_CACHE_MINUTES) {
    $cache_key  = md5($endpoint . serialize($params));
    $cache_file = API_CACHE_DIR . 'gvi_' . $cache_key . '.json';

    // Se cache existe e ainda é válida
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < ($cache_minutes * 60)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached !== null) {
            return $cached;
        }
    }

    // Ir buscar à API
    $data = apiGet($endpoint, $params);

    // Guardar cache apenas se tiver dados
    if (!empty($data)) {
        file_put_contents($cache_file, json_encode($data));
        return $data;
    }

    // Falhou a chamada à API (timeout, rede, etc.) — usar cache antiga como
    // fallback, mesmo que já tenha expirado, em vez de devolver vazio.
    if (file_exists($cache_file)) {
        $stale = json_decode(file_get_contents($cache_file), true);
        if ($stale !== null) {
            return $stale;
        }
    }

    return $data;
}

function apiClearCache() {
    $files = glob(API_CACHE_DIR . 'gvi_*.json');
    if ($files) {
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

// ─── Funções públicas ─────────────────────────────────────────────────────────

function apiGetProdutos($tipo = 'all') {
    return apiGetCached('produtos.php', ['tipo' => $tipo]);
}

function apiGetProduto($id, $tipo) {
    return apiGetCached('produto.php', ['id' => $id, 'tipo' => $tipo], 30);
}

function apiGetProdutoAgrupado($subcat) {
    return apiGetCached('produto.php', ['subcat' => $subcat, 'tipo' => 'asp'], 30);
}

function apiGetCategorias($mainid = null) {
    $params = [];
    if ($mainid) $params['mainid'] = $mainid;
    return apiGetCached('categorias.php', $params);
}

function apiGetDestaque() {
    return apiGetCached('destaque.php');
}

function apiSearch($termo, $extra_params = []) {
    // Pesquisa — sem cache (resultados dinâmicos)
    return apiGet('search.php', array_merge(['q' => $termo], $extra_params));
}
