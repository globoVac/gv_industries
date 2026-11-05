<?php
require_once dirname(__DIR__) . '/scripts/conferir-webtuga.php';

putenv('WEBTUGA_FTP_HOST=fixture.invalid');
putenv('WEBTUGA_FTP_USERNAME=github_fixture@fixture.invalid');
$checks = 0;
function expect_review($condition)
{
    global $checks;
    if (!$condition) { throw new RuntimeException('Falha num teste da conferencia WebTuga.'); }
    $checks++;
}
function review_fixture()
{
    $entries = []; $remote = [];
    foreach (array_merge(webtuga_review_paths(), webtuga_review_support_paths(), ['.htaccess']) as $path) {
        $kind = in_array($path, webtuga_review_paths(), true) ? 'application' : ($path === '.htaccess' ? 'preserved' : 'configuration_support');
        $old = 'PRIVATE_FIXTURE legacy ' . $path;
        $new = "<?php throw new RuntimeException('MUST_NOT_EXECUTE'); // " . $path;
        $entries[] = ['path' => $path, 'kind' => $kind,
            'backup_sha256' => $kind !== 'configuration_support' ? hash('sha256', $old) : null,
            'git_sha256' => $kind !== 'preserved' ? hash('sha256', $new) : null];
        $remote[$path] = $kind === 'configuration_support' ? $new : $old;
    }
    return (object)['entries' => $entries, 'remote' => $remote, 'config' => true,
        'private' => true, 'failures' => [], 'wrong_root' => false, 'calls' => []];
}
function review_runner($fixture)
{
    return function ($command, $credentials) use ($fixture) {
        $fixture->calls[] = $command;
        foreach (['--upload-file', '--quote', '--request', '--ftp-create-dirs', '--insecure', '-k'] as $flag) {
            expect_review(!in_array($flag, $command, true));
        }
        expect_review(in_array('--ssl-reqd', $command, true) && in_array('--tlsv1.2', $command, true));
        expect_review(!str_contains(implode(' ', $command), 'PRIVATE_FIXTURE'));
        expect_review(str_contains($credentials, 'github_fixture@fixture.invalid:PRIVATE_FIXTURE'));
        $url = $command[array_search('--url', $command, true) + 1];
        $base = 'ftp://fixture.invalid:21/';
        expect_review(str_starts_with($url, $base));
        $path = substr($url, strlen($base));
        expect_review($path !== 'config/local.php');
        if ($path === '') {
            return [0, $fixture->wrong_root ? "mail\npublic_html\n" :
                "index.php\nproducts.php\nincludes\nassets\nbackoffice\n" . ($fixture->config ? "config\n" : '')];
        }
        if ($path === 'config/') {
            return [$fixture->failures[$path] ?? 0, "bootstrap.php\ndatabase.php\n" . ($fixture->private ? "local.php\n" : '')];
        }
        expect_review(in_array($path, array_column($fixture->entries, 'path'), true));
        expect_review(in_array('--max-filesize', $command, true) && in_array('nocwd', $command, true));
        return [$fixture->failures[$path] ?? 0, $fixture->remote[$path]];
    };
}
function review_redacted($result)
{
    $text = json_encode($result) . webtuga_review_summary($result);
    expect_review(!str_contains($text, 'PRIVATE_FIXTURE') && !str_contains($text, 'MUST_NOT_EXECUTE'));
    foreach (['server_uploads', 'server_deletions', 'application_files_changed', 'database_connections'] as $key) {
        expect_review($result[$key] === 0);
    }
    expect_review(!$result['private_config_content_read'] && !$result['downloaded_php_executed']);
}

$fixture = review_fixture();
$result = webtuga_review_check('PRIVATE_FIXTURE', $fixture->entries, review_runner($fixture));
expect_review($result['comparison_completed'] && !$result['unexpected_changes']);
expect_review($result['counts'] === ['identical' => 3, 'expected_configuration_difference' => 5,
    'new_support' => 0, 'preserved' => 1, 'new_divergence' => 0, 'unread' => 0]);
review_redacted($result);

$fixture = review_fixture(); $fixture->config = false; $fixture->private = false;
$result = webtuga_review_check('PRIVATE_FIXTURE', $fixture->entries, review_runner($fixture));
expect_review($result['comparison_completed'] && !$result['unexpected_changes'] && $result['configuration_preparation_required']);
expect_review($result['counts']['new_support'] === 3 && !$result['private_config_present']);
review_redacted($result);

$fixture = review_fixture();
foreach (webtuga_review_paths() as $path) { $fixture->remote[$path] = "<?php throw new RuntimeException('MUST_NOT_EXECUTE'); // " . $path; }
$result = webtuga_review_check('PRIVATE_FIXTURE', $fixture->entries, review_runner($fixture));
expect_review($result['counts']['identical'] === 8 && $result['counts']['expected_configuration_difference'] === 0);
review_redacted($result);

$fixture = review_fixture();
$fixture->remote['includes/mail.php'] = 'PRIVATE_FIXTURE new live change';
$fixture->remote['.htaccess'] = 'PRIVATE_FIXTURE new hosting rule';
$result = webtuga_review_check('PRIVATE_FIXTURE', $fixture->entries, review_runner($fixture));
expect_review($result['unexpected_changes'] && $result['counts']['new_divergence'] === 2);
review_redacted($result);

$fixture = review_fixture();
$fixture->failures = ['includes/config.php' => 28, 'config/database.php' => 78];
$result = webtuga_review_check('PRIVATE_FIXTURE', $fixture->entries, review_runner($fixture));
expect_review(!$result['comparison_completed'] && $result['counts']['unread'] === 1 && $result['counts']['new_support'] === 1);
review_redacted($result);

foreach (['wrong_root', 'bad_path', 'bad_hash', 'missing_password', 'config_unreadable'] as $case) {
    $fixture = review_fixture();
    if ($case === 'wrong_root') { $fixture->wrong_root = true; }
    if ($case === 'bad_path') { $fixture->entries[0]['path'] = 'config/local.php'; }
    if ($case === 'bad_hash') { $fixture->entries[0]['git_sha256'] = 'invalid'; }
    if ($case === 'config_unreadable') { $fixture->failures['config/'] = 28; }
    $failed = false;
    try { webtuga_review_check($case === 'missing_password' ? '' : 'PRIVATE_FIXTURE', $fixture->entries, review_runner($fixture)); }
    catch (RuntimeException $error) { $failed = true; expect_review(!str_contains($error->getMessage(), 'PRIVATE_FIXTURE')); }
    expect_review($failed);
}
foreach (['config/local.php', '../index.php', 'assets/video/ventilacao.mp4'] as $path) {
    $failed = false;
    try { webtuga_review_command($path); } catch (RuntimeException $error) { $failed = true; }
    expect_review($failed);
}
expect_review(count(webtuga_review_plan(dirname(__DIR__))) === 9);
echo json_encode(['offline_checks_passed' => $checks, 'server_writes' => 0, 'database_connections' => 0]), PHP_EOL;
