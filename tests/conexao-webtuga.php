<?php
require_once dirname(__DIR__) . '/scripts/testar-conexao-webtuga.php';

function expect_connection($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
}

putenv('WEBTUGA_FTP_HOST=fixture.invalid');
putenv('WEBTUGA_FTP_USERNAME=github_fixture@fixture.invalid');
$password = "fixture-password-with-\"quote\\and\nnewline";
$calls = 0;
$runner = function ($command, $credentials) use ($password, &$calls) {
    $calls++;
    expect_connection($command[0] === 'curl' && $command[1] === '--disable', 'Unexpected client configuration.');
    foreach (['--ssl-reqd', '--tlsv1.2', '--list-only', '--proto'] as $flag) {
        expect_connection(in_array($flag, $command, true), 'Missing read-only FTPS option.');
    }
    expect_connection(!in_array('--insecure', $command, true), 'Certificate validation was disabled.');
    expect_connection($command[array_search('--proto', $command, true) + 1] === '=ftp', 'Unexpected protocol.');
    expect_connection(!str_contains(implode(' ', $command), $password), 'Password reached command arguments.');
    expect_connection(str_starts_with($credentials, 'user = "github_fixture@fixture.invalid:'), 'Credentials were not sent through stdin.');
    expect_connection(substr_count($credentials, "\n") === 1, 'Credential escaping added a config directive.');
    expect_connection(end($command) === 'ftp://fixture.invalid:21/', 'Unexpected FTP destination.');
    return [0, "index.php\nproducts.php\nincludes\nassets\nbackoffice\n"];
};
$result = webtuga_connection_check($password, $runner);
expect_connection($calls === 1 && $result['connected'] === true, 'Connection check failed.');
foreach (['files_uploaded', 'files_changed', 'files_deleted', 'database_connections'] as $key) {
    expect_connection($result[$key] === 0, 'Connection check reported a mutation.');
}
expect_connection(!str_contains(json_encode($result), $password), 'Password reached the result.');

foreach ([
    ['', $runner],
    [$password, static fn() => [60, 'private server error']],
    [$password, static fn() => [0, "mail\netc\npublic_html\n"]],
] as [$candidate, $failureRunner]) {
    $failed = false;
    try { webtuga_connection_check($candidate, $failureRunner); }
    catch (RuntimeException $error) {
        $failed = true;
        expect_connection(!str_contains($error->getMessage(), $password), 'Password reached an error.');
        expect_connection(!str_contains($error->getMessage(), 'private server error'), 'Raw server error reached logs.');
    }
    expect_connection($failed, 'Invalid credentials, certificate or directory were accepted.');
}
putenv("WEBTUGA_FTP_HOST=fixture.invalid/\ninjected");
$failed = false;
try { webtuga_connection_check($password, $runner); }
catch (RuntimeException $error) { $failed = true; }
expect_connection($failed && $calls === 1, 'Invalid host reached the transfer client.');
echo "5 WebTuga connection checks passed without network access.\n";
