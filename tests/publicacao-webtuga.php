<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/scripts/webtuga-publicacao-inicial.php';
putenv('WEBTUGA_FTP_HOST=fixture.invalid'); putenv('WEBTUGA_FTP_USERNAME=github_fixture@fixture.invalid');
$checks = 0;
function publication_check($condition) { global $checks; if (!$condition) { throw new RuntimeException('Falha numa verificacao da publicacao.'); } $checks++; }
function publication_fixture_write($fixture,$relative,$data) {
    $path = $fixture->root.'/'.$relative;
    $parent = dirname($path); $missing = [];
    while (!is_dir($parent)) { $missing[] = $parent; $parent = dirname($parent); }
    foreach (array_reverse($missing) as $directory) { mkdir($directory,0700); $fixture->directories[] = $directory; }
    file_put_contents($path,$data); $fixture->localFiles[] = $path;
}
function publication_fixture() {
    $root = str_replace('\\','/',sys_get_temp_dir()).'/gvi-publicacao-'.bin2hex(random_bytes(8));
    mkdir($root,0700);
    $root = str_replace('\\','/',realpath($root));
    $fixture = (object)['root'=>$root,'directories'=>[$root],'localFiles'=>[],'before'=>[],'after'=>[]];
    $manifest = ['version'=>1,'domain'=>'gv-industries.co.uk','files'=>[]];
    foreach (webtuga_review_paths() as $path) {
        $old = "<?php // TEST_SECRET original ".$path."\n";
        $new = "<?php // candidato revisto ".$path."\n";
        if ($path === 'includes/mail.php') {
            $old = str_pad($old,62679,'0123456789abcdef');
            $new = str_pad(rtrim($new),62687,'fedcba9876543210');
        }
        $fixture->before[$path] = $old; $fixture->after[$path] = $new;
        publication_fixture_write($fixture,$path,$new);
        $manifest['files'][] = ['path'=>$path,'kind'=>'application','backup_sha256'=>hash('sha256',$old),'git_sha256'=>hash('sha256',$new)];
    }
    foreach (webtuga_review_support_paths() as $path) {
        $data = $path === 'config/.htaccess' ? "Require all denied\n" : "<?php // suporte ".$path."\n";
        $fixture->before[$path] = $data; $fixture->after[$path] = $data;
        publication_fixture_write($fixture,$path,$data);
        $manifest['files'][] = ['path'=>$path,'kind'=>'configuration_support','backup_sha256'=>null,'git_sha256'=>hash('sha256',$data)];
    }
    $fixture->before['.htaccess'] = $fixture->after['.htaccess'] = "RewriteEngine On\n";
    $manifest['files'][] = ['path'=>'.htaccess','kind'=>'preserved','backup_sha256'=>hash('sha256',$fixture->before['.htaccess']),'git_sha256'=>null];
    $fixture->private = "<?php return ['fixture'=>'TEST_SECRET'];\n";
    $settings = json_decode(file_get_contents(dirname(__DIR__).'/deploy/publicacao-webtuga.json'),true,512,JSON_THROW_ON_ERROR);
    $settings['private_config_sha256'] = hash('sha256',$fixture->private);
    publication_fixture_write($fixture,'deploy/conferencia-webtuga.json',webtuga_release_json($manifest));
    publication_fixture_write($fixture,'deploy/publicacao-webtuga.json',webtuga_release_json($settings));
    return $fixture;
}
function publication_server($fixture) {
    $server = (object)['files'=>$fixture->before + ['config/local.php'=>$fixture->private], 'calls'=>[], 'writes'=>[], 'temps'=>[], 'activationCount'=>0,
        'failActivation'=>0,'failAfterRename'=>false,'failedOnce'=>false,'badBackup'=>false,'badStage'=>false,'badProfile'=>false,'httpBeforeFailure'=>false,
        'httpAfterFailure'=>false,'httpPrivateStatus'=>403,'unknownStateFailure'=>false,'mutateDuringActivation'=>false,'lateStageCorruption'=>false,
        'uploadFaultPath'=>null,'uploadFaultCodes'=>[],'uploadFaultMode'=>'partial','uploadAttempts'=>[],
        'readFaultPath'=>null,'readFaultCodes'=>[],'enforcePreparation'=>false];
    $server->runner = function($command,$credentials) use($server,$fixture) {
        $server->calls[] = $command;
        publication_check($command[1] === '--disable' && !in_array('--insecure',$command,true) && !in_array('-k',$command,true));
        publication_check(!str_contains(implode(' ',$command),'TEST_SECRET'));
        $url = $command[array_search('--url',$command,true)+1];
        if (str_starts_with($url,'https://')) {
            publication_check($credentials === '' && !in_array('--config',$command,true));
            $path = parse_url($url,PHP_URL_PATH) ?: '/';
            publication_check(in_array($path,['/','/backoffice/login.php','/config/bootstrap.php'],true));
            $isNew = $server->files['backoffice/includes/database.php'] === $fixture->after['backoffice/includes/database.php'];
            $status = $path === '/config/bootstrap.php' ? $server->httpPrivateStatus : (($server->httpBeforeFailure || ($server->httpAfterFailure && $isNew)) ? 500 : 200);
            return [0,"pagina de teste\nGVI_HTTP:".$status.':'.$url."\n"];
        }
        publication_check(str_starts_with($url,'ftp://fixture.invalid:21/'));
        publication_check(str_contains($credentials,'github_fixture@fixture.invalid:TEST_SECRET'));
        publication_check(in_array('--ssl-reqd',$command,true) && !in_array('--ftp-create-dirs',$command,true));
        $path = substr($url,strlen('ftp://fixture.invalid:21/'));
        $quotes = [];
        foreach ($command as $index=>$part) { if ($part === '--quote') { $quotes[] = $command[$index+1]; } }
        if ($quotes) {
            publication_check(count($quotes) === 2 && str_starts_with($quotes[0],'RNFR config/gvi-release-') && str_starts_with($quotes[1],'RNTO '));
            $source = substr($quotes[0],5); $target = substr($quotes[1],5);
            publication_check(isset($server->files[$source]));
            publication_check(in_array($target,webtuga_review_paths(),true) || $target === webtuga_release_cursor_path());
            $application = in_array($target,webtuga_review_paths(),true) && str_ends_with($source,'.stage');
            if ($application) {
                if ($server->enforcePreparation && $server->activationCount === 0) {
                    preg_match('~gvi-release-([a-f0-9]{32})-~',$source,$match);
                    $settings = json_decode(file_get_contents($fixture->root.'/deploy/publicacao-webtuga.json'),true);
                    foreach ($settings['activation_order'] as $index=>$preparedPath) {
                        publication_check(($server->files[webtuga_release_slot($match[1],$index,'before')] ?? null) === $fixture->before[$preparedPath]);
                        publication_check(($server->files[webtuga_release_slot($match[1],$index,'stage')] ?? null) === $fixture->after[$preparedPath]);
                    }
                    publication_check(isset($server->files[webtuga_release_record_path($match[1])]));
                }
                $server->activationCount++;
            }
            $fail = $application && !$server->failedOnce && $server->failActivation === $server->activationCount;
            if ($fail && !$server->failAfterRename) { $server->failedOnce = true; return [21,'TEST_SECRET resposta']; }
            $server->files[$target] = $server->files[$source]; unset($server->files[$source]);
            $server->writes[] = ['operation'=>'rename','target'=>$target];
            if ($fail || ($target === webtuga_release_cursor_path() && $server->unknownStateFailure && !$server->failedOnce)) {
                $server->failedOnce = true; return [28,'TEST_SECRET resposta'];
            }
            return [0,''];
        }
        if (in_array('--upload-file',$command,true)) {
            publication_check(webtuga_release_protected_path($path) && $path !== webtuga_release_cursor_path());
            $local = $command[array_search('--upload-file',$command,true)+1]; $server->temps[] = $local;
            $data = file_get_contents($local);
            $server->uploadAttempts[$path] = ($server->uploadAttempts[$path] ?? 0) + 1;
            if ($path === $server->uploadFaultPath && $server->uploadFaultCodes) {
                $code = array_shift($server->uploadFaultCodes);
                if ($server->uploadFaultMode === 'exception') { throw new RuntimeException('TEST_SECRET detalhe privado'); }
                if ($server->uploadFaultMode !== 'absent') {
                    $server->files[$path] = $server->uploadFaultMode === 'complete' ? $data :
                        ($server->uploadFaultMode === 'foreign' ? 'TEST_SECRET conteudo externo' : substr($data,0,min(32768,max(0,strlen($data)-1))));
                    $server->writes[] = ['operation'=>'put','target'=>$path];
                }
                return [$code,'TEST_SECRET resposta de falha'];
            }
            if (($server->badBackup && str_ends_with($path,'.before')) || ($server->badStage && str_ends_with($path,'.stage'))) { $data .= 'corrompido'; }
            $server->files[$path] = $data; $server->writes[] = ['operation'=>'put','target'=>$path];
            return [0,''];
        }
        if ($path === '') { return [0,"index.php\nproducts.php\nincludes\nassets\nbackoffice/\nconfig/\n"]; }
        if ($path === 'config/' && in_array('--list-only',$command,true)) {
            $names=[]; foreach(array_keys($server->files) as $name) { if(dirname($name)==='config') { $names[]=basename($name); } }
            return [0,implode("\n",$names)."\n"];
        }
        if ($server->mutateDuringActivation && $server->activationCount >= 1 && $path === 'includes/config.php') {
            $server->files[$path] = 'TEST_SECRET alteracao externa';
        }
        if ($server->lateStageCorruption && $server->activationCount >= 1 && str_ends_with($path,'-1.stage')) {
            $server->files[$path] = 'TEST_SECRET candidato alterado apos preparacao';
        }
        if ($path === 'config/local.php' && $server->badProfile) { return [0,'TEST_SECRET perfil diferente']; }
        if ($path === $server->readFaultPath && $server->readFaultCodes && isset($server->uploadAttempts[$path])) {
            return [array_shift($server->readFaultCodes),'TEST_SECRET leitura interrompida'];
        }
        return isset($server->files[$path]) ? [0,$server->files[$path]] : [78,'TEST_SECRET indisponivel'];
    };
    return $server;
}
function publication_run($fixture,$server,$mode='PUBLICAR',$confirmation='PUBLICAR GV',$id=null,$release='') {
    return webtuga_publish_initial($fixture->root,'TEST_SECRET',$mode,$confirmation,str_repeat('c',40),$release,$server->runner,$id ?? str_repeat('1',32));
}
function publication_failure($fixture,$server,$stage,$mode='PUBLICAR',$confirmation='PUBLICAR GV',$release='') {
    try { publication_run($fixture,$server,$mode,$confirmation,null,$release); throw new LogicException('expected_failure'); }
    catch (WebTugaPublicationFailure $error) {
        publication_check($error->report['stage'] === $stage && !$error->report['success']);
        publication_check(!str_contains(json_encode($error->report).webtuga_publication_summary($error->report),'TEST_SECRET'));
        return $error->report;
    }
}
function publication_originals_equal($fixture,$server) {
    foreach ($fixture->before as $path=>$data) { publication_check($server->files[$path] === $data); }
    publication_check($server->files['config/local.php'] === $fixture->private);
    foreach ($server->temps as $temporary) { publication_check(!file_exists($temporary)); }
}

$fixture = publication_fixture();
try {
    $server = publication_server($fixture);
    $report = publication_run($fixture,$server,'PREVIA','NAO');
    publication_check($report['success'] && $report['candidate_files'] === 5 && $server->writes === []);
    publication_check(!in_array('config/local.php',array_map(fn($cmd)=>substr($cmd[array_search('--url',$cmd,true)+1],strlen('ftp://fixture.invalid:21/')),$server->calls),true));
    publication_originals_equal($fixture,$server);

    $server = publication_server($fixture);
    $report = publication_run($fixture,$server);
    publication_check($report['success'] && $report['application_files_activated'] === 5 && !$report['rollback_attempted']);
    foreach (webtuga_review_paths() as $path) { publication_check($server->files[$path] === $fixture->after[$path]); }
    $cursor = webtuga_cursor_validate($server->files[webtuga_release_cursor_path()]);
    publication_check($cursor['commit'] === str_repeat('c',40) && $cursor['release'] === str_repeat('1',32));
    $record = json_decode($server->files[webtuga_release_record_path($report['release'])],true,64,JSON_THROW_ON_ERROR);
    foreach ($record['files'] as $file) { publication_check(hash('sha256',$server->files[$file['backup']]) === $file['before_sha256']); }
    publication_check(!str_contains(json_encode($report).webtuga_publication_summary($report),'TEST_SECRET'));
    $writeCount = count($server->writes);
    $again = publication_run($fixture,$server,'PUBLICAR','PUBLICAR GV',str_repeat('3',32));
    publication_check($again['success'] && $again['candidate_files'] === 0 && count($server->writes) === $writeCount);
    $reverted = publication_run($fixture,$server,'REVERTER','REVERTER GV',str_repeat('2',32));
    publication_check($reverted['success'] && $reverted['originals_restored'] === 5 && $reverted['rollback_completed']);
    publication_originals_equal($fixture,$server);
    publication_check(webtuga_cursor_validate($server->files[webtuga_release_cursor_path()])['initialized'] === false);
    $republished = publication_run($fixture,$server,'PUBLICAR','PUBLICAR GV',str_repeat('3',32));
    publication_check($republished['success'] && $republished['application_files_activated'] === 5);
    $revertedAgain = publication_run($fixture,$server,'REVERTER','REVERTER GV',str_repeat('4',32));
    publication_check($revertedAgain['success'] && $revertedAgain['originals_restored'] === 5);
    publication_originals_equal($fixture,$server);

    foreach (['bad_profile','unexpected_original','http_before','http_unprotected','bad_backup','bad_stage'] as $fault) {
        $server = publication_server($fixture);
        if ($fault === 'bad_profile') { $server->badProfile = true; $stage='validar_configuracao_privada'; }
        if ($fault === 'unexpected_original') { $server->files['includes/config.php']='TEST_SECRET mudanca inesperada'; $stage='comparar_versoes'; }
        if ($fault === 'http_before') { $server->httpBeforeFailure = true; $stage='validar_configuracao_privada'; }
        if ($fault === 'http_unprotected') { $server->httpPrivateStatus = 200; $stage='validar_configuracao_privada'; }
        if ($fault === 'bad_backup') { $server->badBackup = true; $stage='guardar_originais_e_preparar'; }
        if ($fault === 'bad_stage') { $server->badStage = true; $stage='guardar_originais_e_preparar'; }
        $report = publication_failure($fixture,$server,$stage);
        publication_check($report['application_files_activated'] === 0 && !$report['rollback_attempted']);
        if ($fault === 'bad_backup' || $fault === 'bad_stage') {
            publication_check($report['failure']['operation']==='verify' && $report['failure']['reason']==='sha256_mismatch');
            publication_check($report['current_file']==='backoffice/includes/database.php');
        }
        if ($fault !== 'unexpected_original') { publication_originals_equal($fixture,$server); }
        else { publication_check($server->writes === []); }
    }

    $copy = webtuga_release_slot(str_repeat('1',32),3,'before');
    foreach ([6,7,18,28,55,56] as $code) {
        $server = publication_server($fixture); $server->enforcePreparation=true;
        $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[$code];
        if (in_array($code,[6,7],true)) { $server->uploadFaultMode='absent'; }
        $report = publication_run($fixture,$server);
        publication_check($report['success'] && $report['application_files_activated']===5 && $server->uploadAttempts[$copy]===2);
        publication_check($server->files[$copy] === $fixture->before['includes/mail.php']);
        publication_check($report['preparation_transfers'][0] === ['path'=>$copy,'attempt'=>1,'curl_code'=>$code,'expected_bytes'=>62679,
            'observed_bytes'=>in_array($code,[6,7],true) ? null : 32768,'result'=>'retry_from_start']);
        publication_check($report['preparation_transfers'][1]['attempt']===2 && $report['preparation_transfers'][1]['result']==='verified_after_retry');
        publication_check(str_contains(webtuga_publication_summary($report),'Reenvio conferido por SHA-256'));
        publication_check(!str_contains(json_encode($report).webtuga_publication_summary($report),'TEST_SECRET'));
        foreach ($server->calls as $command) {
            publication_check(!in_array('--retry',$command,true) && !in_array('--append',$command,true) && !in_array('--continue-at',$command,true));
        }
        foreach ($server->temps as $temporary) { publication_check(!file_exists($temporary)); }
    }
    foreach (['before','stage'] as $kind) {
        $server=publication_server($fixture); $server->enforcePreparation=true;
        $path=webtuga_release_slot(str_repeat('1',32),3,$kind);
        $server->uploadFaultPath=$path; $server->uploadFaultCodes=[28]; $server->uploadFaultMode='complete';
        $report=publication_run($fixture,$server);
        publication_check($report['success'] && $server->uploadAttempts[$path]===1);
        publication_check(count($report['preparation_transfers'])===1 && $report['preparation_transfers'][0]['result']==='confirmed_after_error');
        publication_check(str_contains(webtuga_publication_summary($report),'Copia completa confirmada por SHA-256'));
    }
    $server=publication_server($fixture); $server->enforcePreparation=true;
    $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[7]; $server->uploadFaultMode='absent';
    $report=publication_run($fixture,$server);
    publication_check($report['success'] && $server->uploadAttempts[$copy]===2);
    publication_check($report['preparation_transfers'][0]['observed_bytes']===null);

    $server=publication_server($fixture); $server->enforcePreparation=true;
    $candidate=webtuga_release_slot(str_repeat('1',32),3,'stage');
    $server->uploadFaultPath=$candidate; $server->uploadFaultCodes=[28];
    $report=publication_run($fixture,$server);
    publication_check($report['success'] && $server->uploadAttempts[$candidate]===2);
    publication_check($server->files['includes/mail.php']===$fixture->after['includes/mail.php']);
    publication_check($report['preparation_transfers'][0]['expected_bytes']===62687 && $report['preparation_transfers'][0]['observed_bytes']===32768);

    // A segunda tentativa pode chegar completa e perder apenas a confirmacao do PUT.
    $server=publication_server($fixture); $server->enforcePreparation=true;
    $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[28,28];
    $delegate=$server->runner;
    $server->runner=function($command,$credentials) use($server,$copy,$delegate) {
        if (in_array('--upload-file',$command,true) && ($server->uploadAttempts[$copy] ?? 0)===1) { $server->uploadFaultMode='complete'; }
        return $delegate($command,$credentials);
    };
    $report=publication_run($fixture,$server);
    publication_check($report['success'] && $server->uploadAttempts[$copy]===2);
    publication_check($report['preparation_transfers'][1]['attempt']===2 && $report['preparation_transfers'][1]['result']==='confirmed_after_error');

    $server=publication_server($fixture); $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[28,28,28];
    $report=publication_failure($fixture,$server,'guardar_originais_e_preparar');
    publication_check($server->uploadAttempts[$copy]===2 && $report['application_files_activated']===0 && !$report['rollback_attempted']);
    publication_check($report['failure']['curl_code']===28 && $report['failure']['reason']==='retry_exhausted');
    publication_check($report['preparation_transfers'][1]['result']==='retry_exhausted');
    publication_check(!isset($server->files[webtuga_release_record_path(str_repeat('1',32))]) && $server->activationCount===0);
    publication_originals_equal($fixture,$server);

    foreach ([9,23,25,26,35,60,67,77] as $code) {
        $server=publication_server($fixture); $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[$code];
        $report=publication_failure($fixture,$server,'guardar_originais_e_preparar');
        publication_check($server->uploadAttempts[$copy]===1 && $report['failure']['curl_code']===$code);
        publication_check($report['preparation_transfers'][0]['result']==='blocked_by_error' && $server->activationCount===0);
        publication_originals_equal($fixture,$server);
    }
    $server=publication_server($fixture); $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[28]; $server->uploadFaultMode='foreign';
    $report=publication_failure($fixture,$server,'guardar_originais_e_preparar');
    publication_check($server->uploadAttempts[$copy]===1 && $report['failure']['reason']==='sha256_mismatch_after_upload_error');
    publication_check($server->files[$copy]==='TEST_SECRET conteudo externo' && $report['preparation_transfers'][0]['result']==='blocked_by_content');
    publication_originals_equal($fixture,$server);

    $server=publication_server($fixture); $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[28];
    $server->readFaultPath=$copy; $server->readFaultCodes=[56];
    $report=publication_failure($fixture,$server,'guardar_originais_e_preparar');
    publication_check($server->uploadAttempts[$copy]===1 && $report['failure']['operation']==='get' && $report['failure']['curl_code']===56);
    publication_check($report['preparation_transfers'][0]['result']==='readback_failed');
    publication_originals_equal($fixture,$server);

    foreach (['partial','exception'] as $faultMode) {
        $server=publication_server($fixture); $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[0]; $server->uploadFaultMode=$faultMode;
        $report=publication_failure($fixture,$server,'guardar_originais_e_preparar');
        publication_check($server->uploadAttempts[$copy]===1 && $server->activationCount===0);
        publication_check($report['failure']['reason']===($faultMode==='partial' ? 'sha256_mismatch' : 'transport_exception'));
        publication_originals_equal($fixture,$server);
    }

    // O metodo sem permissao explicita continua a fazer apenas uma tentativa.
    $server=publication_server($fixture); $client=new WebTugaReleaseClient('TEST_SECRET',$server->runner);
    $server->uploadFaultPath=$copy; $server->uploadFaultCodes=[28];
    try { $client->putVerified($copy,$fixture->before['includes/mail.php']); throw new LogicException('expected_failure'); }
    catch (WebTugaPublicationOperationFailure $error) { publication_check($error->diagnostic['curl_code']===28); }
    publication_check($server->uploadAttempts[$copy]===1 && $client->preparationTransfers()===[]);
    publication_originals_equal($fixture,$server);
    foreach (['config/local.php','includes/config.php',webtuga_release_record_path(str_repeat('1',32)),webtuga_release_cursor_path(),webtuga_release_slot(str_repeat('1',32),0,'restore')] as $invalid) {
        $server=publication_server($fixture); $client=new WebTugaReleaseClient('TEST_SECRET',$server->runner); $calls=count($server->calls);
        try { $client->putVerified($invalid,'TEST_SECRET',true); throw new LogicException('expected_failure'); }
        catch (RuntimeException $error) { publication_check(count($server->calls)===$calls && $server->writes===[]); }
    }
    $server = publication_server($fixture); $server->badStage=true;
    $failed = publication_failure($fixture,$server,'guardar_originais_e_preparar');
    $server->badStage=false;
    $writes=count($server->writes); $callStart=count($server->calls);
    $diagnosis=publication_run($fixture,$server,'DIAGNOSTICO','NAO',str_repeat('6',32),$failed['release']);
    publication_check($diagnosis['success'] && $diagnosis['stage']==='diagnostico_concluido' && $diagnosis['application_files_activated']===0);
    publication_check($diagnosis['diagnostic']['copies'][0]['before']['status']==='confirmado');
    publication_check($diagnosis['diagnostic']['copies'][0]['stage']['status']==='conteudo_diferente');
    publication_check($diagnosis['diagnostic']['copies'][1]['before']['status']==='ausente');
    publication_check($diagnosis['diagnostic']['recovery_record']==='ausente' && count($server->writes)===$writes);
    foreach(array_slice($server->calls,$callStart) as $command) {
        publication_check(!in_array('--upload-file',$command,true) && !in_array('--quote',$command,true));
        publication_check(!str_ends_with($command[array_search('--url',$command,true)+1],'/config/local.php'));
    }
    publication_check(!str_contains(json_encode($diagnosis).webtuga_publication_summary($diagnosis),'TEST_SECRET'));
    publication_originals_equal($fixture,$server);

    $server=publication_server($fixture);
    try { publication_run($fixture,$server,'DIAGNOSTICO','NAO'); throw new LogicException('expected_failure'); }
    catch(RuntimeException $error) { publication_check($server->calls===[]); }
    foreach (['rename_before','rename_after','http_after','cursor_after','late_stage_corruption'] as $fault) {
        $server = publication_server($fixture);
        $stage = 'ativar_ficheiros';
        if ($fault === 'rename_before' || $fault === 'rename_after') { $server->failActivation=3; $server->failAfterRename=$fault === 'rename_after'; }
        if ($fault === 'http_after') { $server->httpAfterFailure=true; $stage='verificar_paginas'; }
        if ($fault === 'cursor_after') { $server->unknownStateFailure=true; $stage='registar_versao'; }
        if ($fault === 'late_stage_corruption') { $server->lateStageCorruption=true; }
        $report = publication_failure($fixture,$server,$stage);
        publication_check($report['rollback_attempted'] && $report['rollback_completed']);
        publication_originals_equal($fixture,$server);
    }
    $server = publication_server($fixture); $server->mutateDuringActivation=true;
    $report = publication_failure($fixture,$server,'ativar_ficheiros');
    publication_check($report['rollback_attempted'] && !$report['rollback_completed']);
    publication_check($server->files['includes/config.php'] === 'TEST_SECRET alteracao externa');

    $server = publication_server($fixture); publication_run($fixture,$server);
    $server->files[webtuga_release_slot(str_repeat('1',32),0,'before')] .= 'danificado';
    $writes = count($server->writes);
    $report = publication_failure($fixture,$server,'repor_originais','REVERTER','REVERTER GV');
    publication_check(!$report['rollback_completed'] && count($server->writes) === $writes);

    $server = publication_server($fixture); publication_run($fixture,$server);
    $server->files[webtuga_release_cursor_path()] = webtuga_release_json(['version'=>1,'initialized'=>true,'commit'=>str_repeat('d',40),'release'=>str_repeat('e',32)]);
    $writes = count($server->writes);
    $report = publication_failure($fixture,$server,'repor_originais','REVERTER','REVERTER GV',str_repeat('1',32));
    publication_check(!$report['rollback_completed'] && count($server->writes) === $writes);

    // Simular cancelamento apos duas ativacoes, antes de registar a versao.
    $server = publication_server($fixture); $published = publication_run($fixture,$server);
    $record = json_decode($server->files[webtuga_release_record_path($published['release'])],true,64,JSON_THROW_ON_ERROR);
    foreach ($record['files'] as $index=>$file) { if ($index >= 2) { $server->files[$file['path']] = $fixture->before[$file['path']]; } }
    unset($server->files[webtuga_release_cursor_path()]);
    $interrupted = publication_run($fixture,$server,'REVERTER','REVERTER GV',str_repeat('5',32),$published['release']);
    publication_check($interrupted['success'] && $interrupted['originals_restored'] === 2);
    publication_originals_equal($fixture,$server);

    foreach (['PUBLICAR','REVERTER'] as $mode) {
        $server=publication_server($fixture);
        try { publication_run($fixture,$server,$mode,'NAO'); throw new LogicException('expected_failure'); }
        catch (RuntimeException $error) { publication_check($server->calls === []); }
    }
    foreach ([['get','../index.php',null],['put','config/local.php','x'],['rename','config/local.php',webtuga_release_slot(str_repeat('a',32),0,'stage')],['rename','index.php',webtuga_release_slot(str_repeat('a',32),0,'stage')]] as $invalid) {
        try { webtuga_release_command(...$invalid); throw new LogicException('expected_failure'); }
        catch (RuntimeException $error) { publication_check(true); }
    }
    // Alteracoes externas nas regras da raiz bloqueiam a publicacao sem escritas.
    $server=publication_server($fixture);
    $server->files['.htaccess']='TEST_SECRET regra externa';
    publication_failure($fixture,$server,'comparar_versoes');
    publication_check($server->writes===[] && $server->files['.htaccess']==='TEST_SECRET regra externa');

    // Um candidato com sintaxe invalida e recusado antes da primeira ligacao FTPS.
    $candidatePath=$fixture->root.'/includes/config.php';
    $manifestPath=$fixture->root.'/deploy/conferencia-webtuga.json';
    $savedCandidate=file_get_contents($candidatePath); $savedManifest=file_get_contents($manifestPath);
    try {
        $invalid="<?php function broken( TEST_SECRET";
        file_put_contents($candidatePath,$invalid);
        $manifest=json_decode($savedManifest,true);
        foreach ($manifest['files'] as &$entry) {
            if ($entry['path']==='includes/config.php') { $entry['git_sha256']=hash('sha256',$invalid); }
        }
        unset($entry);
        file_put_contents($manifestPath,webtuga_release_json($manifest));
        $server=publication_server($fixture);
        publication_failure($fixture,$server,'preparar');
        publication_check($server->calls===[] && $server->writes===[]);
    } finally {
        file_put_contents($candidatePath,$savedCandidate); file_put_contents($manifestPath,$savedManifest);
    }

    // Conferir as opcoes no curl instalado; --help e sem URL impedem a rede.
    $temporary=tempnam(sys_get_temp_dir(),'gvi-flags-'); file_put_contents($temporary,'teste');
    try {
        foreach ([webtuga_release_command('list','config/'),webtuga_release_command('get','config/local.php'),webtuga_release_command('put',webtuga_release_slot(str_repeat('a',32),0,'stage'),$temporary),webtuga_release_command('rename','includes/config.php',webtuga_release_slot(str_repeat('a',32),0,'stage')),webtuga_release_http_command('/')] as $command) {
            $url=$command[array_search('--url',$command,true)+1];
            if (str_starts_with($url,'ftp://')) {
                publication_check(in_array('--ssl-reqd',$command,true) && in_array('--tlsv1.2',$command,true));
                $maximum=array_search('--tls-max',$command,true);
                publication_check($maximum===false);
            } else { publication_check(!in_array('--tls-max',$command,true)); }
            $command=array_slice($command,0,-2); $command[]='--help'; $command[]='all';
            [$exit,$help]=webtuga_connection_run($command,webtuga_connection_credentials('TEST_ONLY'));
            publication_check($exit === 0 && strlen($help)>100);
        }
    } finally { unlink($temporary); }
    $testResult = ['offline_checks_passed'=>$checks,'real_connections'=>0,'real_hosting_writes'=>0];
} finally {
    // Apenas os caminhos criados por este teste, dentro da sua pasta temporaria.
    $resolved=str_replace('\\','/',realpath($fixture->root) ?: '');
    $tempRoot=rtrim(str_replace('\\','/',realpath(sys_get_temp_dir()) ?: ''),'/').'/';
    if (!str_starts_with($resolved,$tempRoot) || !preg_match('/^gvi-publicacao-[a-f0-9]{16}$/D',basename($resolved))) { throw new RuntimeException('Limpeza temporaria fora do destino permitido.'); }
    foreach ($fixture->localFiles as $path) { if (!str_starts_with($path,$resolved.'/')) { throw new RuntimeException('Caminho temporario invalido.'); } unlink($path); }
    foreach (array_reverse($fixture->directories) as $directory) { rmdir($directory); }
}
echo json_encode($testResult),PHP_EOL;
