<?php
require_once __DIR__ . '/testar-conexao-webtuga.php';

function webtuga_review_paths()
{
    return ['includes/config.php', 'includes/database.php', 'includes/mail.php',
        'backoffice/includes/config.php', 'backoffice/includes/database.php'];
}

function webtuga_review_support_paths()
{
    return ['config/.htaccess', 'config/bootstrap.php', 'config/database.php'];
}

function webtuga_review_plan($root, $manifest = null)
{
    $manifest = $manifest ?? json_decode(file_get_contents($root . '/deploy/conferencia-webtuga.json'), true, 512, JSON_THROW_ON_ERROR);
    $allowed = array_merge(webtuga_review_paths(), webtuga_review_support_paths(), ['.htaccess']);
    if (($manifest['version'] ?? null) !== 1 || ($manifest['domain'] ?? null) !== 'gv-industries.co.uk' ||
        array_column($manifest['files'] ?? [], 'path') !== $allowed) {
        throw new RuntimeException('Manifesto fora dos ficheiros revistos.');
    }
    foreach ($manifest['files'] as $entry) {
        $path = $entry['path'];
        $kind = in_array($path, webtuga_review_paths(), true) ? 'application' : ($path === '.htaccess' ? 'preserved' : 'configuration_support');
        if (($entry['kind'] ?? null) !== $kind) { throw new RuntimeException('Tipo de ficheiro invalido.'); }
        foreach (['backup_sha256', 'git_sha256'] as $key) {
            $required = $key === 'backup_sha256' ? $kind !== 'configuration_support' : $kind !== 'preserved';
            if ($required && (!is_string($entry[$key] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/', $entry[$key]))) {
                throw new RuntimeException('Hash de revisao invalido.');
            }
            if (!$required && ($entry[$key] ?? null) !== null) { throw new RuntimeException('Hash inesperado.'); }
        }
        if ($kind !== 'preserved' && (!is_file($root . '/' . $path) || is_link($root . '/' . $path) ||
            hash_file('sha256', $root . '/' . $path) !== $entry['git_sha256'])) {
            throw new RuntimeException('Um candidato mudou depois da revisao.');
        }
    }
    return $manifest['files'];
}

function webtuga_review_command($path)
{
    $allowed = array_merge(webtuga_review_paths(), webtuga_review_support_paths(), ['.htaccess', '', 'config/']);
    if (!in_array($path, $allowed, true)) { throw new RuntimeException('Leitura fora dos caminhos autorizados.'); }
    $target = webtuga_connection_target('gvi');
    $command = ['curl', '--disable', '--config', '-', '--silent', '--show-error',
        '--ssl-reqd', '--tlsv1.2', '--proto', '=ftp', '--connect-timeout', '15', '--max-time', '45'];
    $command = array_merge($command, $path === '' || $path === 'config/' ? ['--list-only'] :
        ['--ftp-method', 'nocwd', '--max-filesize', '2097152']);
    return array_merge($command, ['--url', 'ftp://' . $target['server'] . ':21/' . $path]);
}

function webtuga_review_names($listing)
{
    return array_values(array_filter(array_map(static fn($line) => basename(rtrim(trim($line), '/')),
        preg_split('/\r?\n/', $listing)), static fn($name) => $name !== '' && $name !== '.' && $name !== '..'));
}

function webtuga_review_check($password, $entries, $runner = null)
{
    $allowed = array_merge(webtuga_review_paths(), webtuga_review_support_paths(), ['.htaccess']);
    if (!is_array($entries) || array_column($entries, 'path') !== $allowed) { throw new RuntimeException('Lista de leitura invalida.'); }
    foreach ($entries as $entry) {
        $kind = in_array($entry['path'], webtuga_review_paths(), true) ? 'application' : ($entry['path'] === '.htaccess' ? 'preserved' : 'configuration_support');
        if (($entry['kind'] ?? null) !== $kind) { throw new RuntimeException('Metadados de leitura invalidos.'); }
        foreach (['backup_sha256', 'git_sha256'] as $key) {
            $required = $key === 'backup_sha256' ? $kind !== 'configuration_support' : $kind !== 'preserved';
            if ($required && (!is_string($entry[$key] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/', $entry[$key]))) {
                throw new RuntimeException('Hash de leitura invalido.');
            }
        }
    }
    $transport = $runner ?? 'webtuga_connection_run';
    $credentials = webtuga_connection_credentials($password);
    try { webtuga_connection_check($password, $transport); }
    catch (Throwable $error) { throw new RuntimeException('A raiz FTPS nao foi confirmada.'); }
    [$exit, $listing] = $transport(webtuga_review_command(''), $credentials);
    if ($exit !== 0 || !is_string($listing)) { throw new RuntimeException('Nao foi possivel confirmar as pastas da raiz.'); }
    $configDirectory = in_array('config', webtuga_review_names($listing), true);
    $privatePresent = false;
    if ($configDirectory) {
        [$exit, $listing] = $transport(webtuga_review_command('config/'), $credentials);
        if ($exit !== 0 || !is_string($listing)) { throw new RuntimeException('Nao foi possivel listar config.'); }
        $privatePresent = in_array('local.php', webtuga_review_names($listing), true);
    }
    unset($listing);
    $counts = ['identical' => 0, 'expected_configuration_difference' => 0, 'new_support' => 0,
        'preserved' => 0, 'new_divergence' => 0, 'unread' => 0];
    $files = [];
    foreach ($entries as $entry) {
        $remoteHash = null;
        $status = 'erro_de_leitura';
        if ($entry['kind'] === 'configuration_support' && !$configDirectory) {
            $status = 'novo_previsto'; $counts['new_support']++;
        } else {
            try {
                [$exit, $contents] = $transport(webtuga_review_command($entry['path']), $credentials);
                if ($exit === 78 && $entry['kind'] === 'configuration_support') {
                    $status = 'novo_previsto'; $counts['new_support']++;
                } elseif ($exit !== 0 || !is_string($contents) || strlen($contents) > 2097152) {
                    $counts['unread']++;
                } else {
                    $remoteHash = hash('sha256', $contents);
                    if ($entry['kind'] === 'preserved' && $remoteHash === $entry['backup_sha256']) {
                        $status = 'preservado'; $counts['preserved']++;
                    } elseif ($entry['kind'] !== 'preserved' && $remoteHash === $entry['git_sha256']) {
                        $status = 'igual_ao_git'; $counts['identical']++;
                    } elseif ($entry['kind'] === 'application' && $remoteHash === $entry['backup_sha256']) {
                        $status = 'diferenca_prevista'; $counts['expected_configuration_difference']++;
                    } else {
                        $status = 'divergencia_nova'; $counts['new_divergence']++;
                    }
                }
                unset($contents);
            } catch (Throwable $error) { unset($contents); $counts['unread']++; }
        }
        $files[] = ['path' => $entry['path'], 'kind' => $entry['kind'], 'status' => $status,
            'remote_sha256' => $remoteHash, 'git_sha256' => $entry['git_sha256']];
    }
    unset($credentials);
    return ['comparison_completed' => $counts['unread'] === 0, 'domain' => 'gv-industries.co.uk',
        'certificate_verified' => true, 'private_config_present' => $privatePresent, 'private_config_content_read' => false,
        'counts' => $counts, 'files' => $files,
        'unexpected_changes' => $counts['new_divergence'] > 0 || $counts['unread'] > 0,
        'configuration_preparation_required' => $counts['new_support'] > 0 || !$privatePresent,
        'server_uploads' => 0, 'server_deletions' => 0, 'application_files_changed' => 0,
        'database_connections' => 0, 'downloaded_php_executed' => false, 'remote_file_contents_saved' => false];
}

function webtuga_review_summary($report)
{
    $labels = ['igual_ao_git' => 'Igual ao GitHub', 'diferenca_prevista' => 'Atualizacao de configuracao prevista',
        'novo_previsto' => 'Suporte novo a preparar', 'preservado' => 'Conservar sem alteracoes',
        'divergencia_nova' => 'Diferenca inesperada: rever', 'erro_de_leitura' => 'Leitura nao concluida'];
    $text = "### Conferencia GV Industries\n\n| Ficheiro | Resultado |\n| --- | --- |\n";
    foreach ($report['files'] as $file) { $text .= '| `' . $file['path'] . '` | ' . $labels[$file['status']] . " |\n"; }
    $text .= "\nconfig/local.php: " . ($report['private_config_present'] ? 'presente; conteudo nao lido' : 'a preparar') . ".\n\n";
    $text .= "0 uploads, 0 alteracoes, 0 exclusoes e 0 ligacoes a bases de dados.\n";
    return $text;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
    ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
    try {
        $report = webtuga_review_check(getenv('WEBTUGA_FTP_PASSWORD'), webtuga_review_plan(dirname(__DIR__)));
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
        if ($summary = getenv('GITHUB_STEP_SUMMARY')) { file_put_contents($summary, webtuga_review_summary($report), FILE_APPEND); }
        exit($report['comparison_completed'] && !$report['unexpected_changes'] ? 0 : 1);
    } catch (Throwable $error) {
        echo json_encode(['comparison_completed' => false, 'details_redacted' => true,
            'server_uploads' => 0, 'server_deletions' => 0, 'database_connections' => 0]), PHP_EOL;
        exit(1);
    }
}
