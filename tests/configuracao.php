<?php
declare(strict_types=1);

// Run with php -n: database functions are stubbed, with no network connections.
if (extension_loaded('mysqli')) {
    fwrite(STDERR, "Run this check with php -n tests/configuracao.php.\n");
    exit(1);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (!extension_loaded('mysqli')) {
    function mysqli_connect($host, $user, $password, $database)
    {
        $GLOBALS['connections'][] = [$host, $user, $password, $database];
        return (object)['database' => $database];
    }
    function mysqli_set_charset($connection, $charset) { return true; }
    function mysqli_connect_errno() { return 0; }
}

if (($argv[1] ?? '') !== '--worker') {
    foreach (['local', 'producao', 'missing', 'invalid'] as $environment) {
        foreach (['site', 'backoffice'] as $area) {
            $process = proc_open([PHP_BINARY, '-n', __FILE__, '--worker', $environment, $area],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            check(is_resource($process), 'Cannot start isolated configuration check.');
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            check(proc_close($process) === 0, $environment . '/' . $area . ': ' . $output . $error);
        }
    }
    echo "8 isolated configuration checks passed; no real database or external service used.\n";
    exit(0);
}

$environment = $argv[2];
$area = $argv[3];
$root = sys_get_temp_dir() . '/gvi-config-test-' . bin2hex(random_bytes(8));
check(mkdir($root, 0700), 'Cannot create private test directory.');

function removeFixture(string $path, string $root): void
{
    $absolute = realpath($path);
    $base = realpath($root);
    check($absolute !== false && $base !== false &&
        ($absolute === $base || str_starts_with($absolute, $base . DIRECTORY_SEPARATOR)),
        'Fixture cleanup escaped its test directory.');
    foreach (new DirectoryIterator($path) as $entry) {
        if ($entry->isDot()) { continue; }
        if ($entry->isLink() || !$entry->isDir()) { unlink($entry->getPathname()); }
        else { removeFixture($entry->getPathname(), $root); }
    }
    rmdir($path);
}

try {
    foreach (['config/bootstrap.php', 'config/database.php', 'includes/config.php',
        'includes/database.php', 'includes/api.php', 'includes/globovac.php',
        'backoffice/includes/config.php', 'backoffice/includes/database.php'] as $file) {
        $destination = $root . '/' . $file;
        if (!is_dir(dirname($destination))) { mkdir(dirname($destination), 0700, true); }
        check(copy(dirname(__DIR__) . '/' . $file, $destination), 'Cannot copy test source.');
    }
    $database = ['host' => 'fixture-host', 'user' => 'fixture-user', 'password' => 'fixture-only', 'name' => 'gvi_' . $environment];
    $profile = [
        'site' => ['environment' => $environment, 'base_url' => 'https://fixture.invalid/', 'base_path' => $root . '/',
            'backoffice_base_url' => 'https://fixture.invalid/backoffice/', 'backoffice_base_path' => $root . '/backoffice/'],
        'database' => ['globovac' => $environment === 'local' ? array_replace($database, ['name' => 'catalogue_fixture']) : null, 'gvi' => $database],
        'stripe' => ['public_key' => 'fixture-public', 'secret_key' => 'fixture-secret', 'mode' => $environment === 'local' ? 'test' : 'live'],
    ];
    if ($environment !== 'missing') {
        file_put_contents($root . '/config/local.php', '<?php return ' . var_export($environment === 'invalid' ? 'invalid-profile' : $profile, true) . ';');
    }
    // A contrary HTTP host must never select another installation's credentials.
    $_SERVER['HTTP_HOST'] = $environment === 'local' ? 'gv-industries.co.uk' : 'localhost';
    $GLOBALS['connections'] = [];
    if ($environment === 'missing' || $environment === 'invalid') {
        $failed = false;
        try { require $root . '/' . ($area === 'site' ? 'includes/config.php' : 'backoffice/includes/config.php'); }
        catch (RuntimeException $e) { $failed = true; }
        check($failed && $GLOBALS['connections'] === [], 'Invalid profile did not stop safely.');
    } else {
        $prefix = $area === 'site' ? 'includes/' : 'backoffice/includes/';
        require $root . '/' . $prefix . 'config.php';
        require $root . '/' . $prefix . 'database.php';
        check(AMBIENTE === $environment, 'HTTP host changed the selected environment.');
        check(BASE_URL === 'https://fixture.invalid/' . ($area === 'backoffice' ? 'backoffice/' : ''), 'Incorrect base URL.');
        check(BASE_PATH === $root . '/' . ($area === 'backoffice' ? 'backoffice/' : ''), 'Incorrect filesystem path.');
        $expected = $environment === 'local' ? ['catalogue_fixture', 'gvi_local'] : ['gvi_producao'];
        check(array_column($GLOBALS['connections'], 3) === $expected, 'Unexpected database target.');
        if ($area === 'site') {
            check(STRIPE_MODE === ($environment === 'local' ? 'test' : 'live'), 'Incorrect Stripe mode.');
            check(STRIPE_SECRET_KEY === 'fixture-secret', 'Stripe value did not come from private profile.');
        }
    }
} finally {
    removeFixture($root, $root);
}
