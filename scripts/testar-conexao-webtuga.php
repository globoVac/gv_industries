<?php

/** Consulta FTP NLST exclusivamente; nao possui operacoes de publicacao. */
function webtuga_connection_command($server)
{
    return [
        'curl', '--disable', '--config', '-', '--silent', '--show-error',
        '--ssl-reqd', '--tlsv1.2', '--proto', '=ftp',
        '--connect-timeout', '15', '--max-time', '60', '--list-only',
        '--url', 'ftp://' . $server . ':21/',
    ];
}

function webtuga_connection_target($domain)
{
    if ($domain !== 'gvi') {
        throw new RuntimeException('O destino permitido e apenas GV Industries.');
    }
    $server = getenv('WEBTUGA_FTP_HOST');
    $account = getenv('WEBTUGA_FTP_USERNAME');
    if (!is_string($server) || !preg_match('/\A(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,63}\z/', $server)) {
        throw new RuntimeException('Preencha WEBTUGA_FTP_HOST com o hostname do alojamento.');
    }
    if (!is_string($account) || !preg_match('/\A[A-Za-z0-9_.@-]{1,254}\z/', $account)) {
        throw new RuntimeException('Preencha WEBTUGA_FTP_USERNAME com a conta FTP dedicada.');
    }
    return [
        'domain' => 'gv-industries.co.uk',
        'server' => $server,
        'account' => $account,
        'secret' => 'WEBTUGA_FTP_PASSWORD',
        'directory' => '/home/gvindust/public_html',
        'required' => ['index.php', 'products.php', 'includes', 'assets', 'backoffice'],
    ];
}
function webtuga_connection_credentials($password, $domain = 'gvi')
{
    $target = webtuga_connection_target($domain);
    if (!is_string($password) || $password === '') {
        throw new RuntimeException('O segredo ' . $target['secret'] . ' ainda nao foi preenchido.');
    }
    if (strlen($password) > 4096 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $password)) {
        throw new RuntimeException('A senha fornecida contem um formato nao suportado.');
    }
    $escaped = strtr($target['account'] . ':' . $password, [
        '\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t',
    ]);
    return 'user = "' . $escaped . '"' . "\n";
}

function webtuga_connection_run($command, $credentials)
{
    // Argumentos publicos; a credencial passa apenas pelo stdin do curl.
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Nao foi possivel iniciar o cliente FTPS.');
    }
    $written = 0;
    while ($written < strlen($credentials)) {
        $bytes = fwrite($pipes[0], substr($credentials, $written));
        if ($bytes === false || $bytes === 0) {
            fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]); proc_terminate($process); proc_close($process);
            throw new RuntimeException('Nao foi possivel fornecer a credencial ao cliente FTPS.');
        }
        $written += $bytes;
    }
    fclose($pipes[0]);
    $listing = stream_get_contents($pipes[1]); fclose($pipes[1]);
    // Nao publicar respostas brutas, pois podem conter informacao do servidor.
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($process), $listing];
}

function webtuga_connection_check($password, $runner = null, $domain = 'gvi')
{
    $target = webtuga_connection_target($domain);
    $credentials = webtuga_connection_credentials($password, $domain);
    [$exit, $listing] = ($runner ?? 'webtuga_connection_run')(webtuga_connection_command($target['server']), $credentials);
    unset($credentials);
    if ($exit !== 0) {
        $reasons = [
            6 => 'O nome do servidor nao foi resolvido.',
            7 => 'Nao foi possivel estabelecer a ligacao.',
            28 => 'O tempo limite da ligacao foi atingido.',
            35 => 'A negociacao TLS falhou.',
            60 => 'O certificado do servidor nao foi validado.',
            67 => 'O login FTP foi recusado. Confira o segredo ' . $target['secret'] . '.',
        ];
        throw new RuntimeException(($reasons[$exit] ?? 'O servidor nao concluiu a consulta FTPS.') . ' Codigo curl: ' . (int)$exit . '.');
    }
    $names = [];
    foreach (preg_split('/\r?\n/', $listing) as $entry) {
        $name = basename(rtrim(trim($entry), '/'));
        if ($name !== '' && $name !== '.' && $name !== '..') { $names[$name] = true; }
    }
    foreach ($target['required'] as $required) {
        if (!isset($names[$required])) {
            throw new RuntimeException('O login funcionou, mas a pasta consultada nao corresponde a raiz esperada do ' . $domain . '.');
        }
    }
    return [
        'connected' => true, 'domain' => $target['domain'],
        'server' => $target['server'], 'port' => 21,
        'protocol' => 'explicit-ftps', 'certificate_verified' => true,
        'account' => $target['account'],
        'ftp_root' => '/', 'expected_hosting_directory' => $target['directory'],
        'expected_site_files_found' => true, 'entries_seen' => count($names),
        'files_uploaded' => 0, 'files_changed' => 0, 'files_deleted' => 0,
        'database_connections' => 0,
    ];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
    try {
        $options = getopt('', ['dominio:']);
        $domain = $options['dominio'] ?? 'gvi';
        $target = webtuga_connection_target($domain);
        $result = webtuga_connection_check(getenv($target['secret']), null, $domain);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
        $summary = getenv('GITHUB_STEP_SUMMARY');
        if ($summary) {
            file_put_contents($summary, "### Ligacao GV Industries verificada\n\nLogin FTPS e certificado validados; raiz do site encontrada.\n\n0 uploads, 0 alteracoes, 0 exclusoes e 0 ligacoes a bases de dados.\n", FILE_APPEND);
        }
    } catch (RuntimeException $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
