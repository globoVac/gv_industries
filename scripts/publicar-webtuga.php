<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ini_set('display_errors','0'); ini_set('log_errors','0'); ini_set('zend.exception_ignore_args','1');
require __DIR__ . '/webtuga-publicacao-inicial.php';
$summary = getenv('GITHUB_STEP_SUMMARY');
try {
    if (getenv('GITHUB_REF') !== 'refs/heads/main') { throw new RuntimeException('Apenas a main revista pode ser utilizada.'); }
    $notice = function($id) use($summary) {
        echo 'Referencia de recuperacao: '.$id.PHP_EOL;
        if ($summary) { file_put_contents($summary,'Referencia desta tentativa: `'.$id."`.\n\n",FILE_APPEND); }
    };
    $report = webtuga_publish_initial(dirname(__DIR__),getenv('WEBTUGA_FTP_PASSWORD'),getenv('WEBTUGA_PUBLICATION_MODE'),getenv('WEBTUGA_PUBLICATION_CONFIRMATION'),getenv('GITHUB_SHA'),getenv('WEBTUGA_PUBLICATION_RELEASE') ?: '',null,null,$notice);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
    if ($summary) { file_put_contents($summary,webtuga_publication_summary($report),FILE_APPEND); }
} catch (Throwable $error) {
    $report = $error instanceof WebTugaPublicationFailure ? $error->report : ['success'=>false,'stage'=>'validacao_inicial','details_redacted'=>true];
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
    if ($summary) { file_put_contents($summary,$error instanceof WebTugaPublicationFailure ? webtuga_publication_summary($report) : "### Publicacao nao iniciada\n\nA versao, o modo ou a confirmacao nao passaram na validacao inicial.\n",FILE_APPEND); }
    exit(1);
}
