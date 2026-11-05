<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/scripts/publicar-alteracoes-webtuga.php';
putenv('WEBTUGA_FTP_HOST=fixture.invalid'); putenv('WEBTUGA_FTP_USERNAME=github_fixture@fixture.invalid');
$checks=0;
function inc_check($condition) { global $checks; if(!$condition) { throw new RuntimeException('Falha numa verificacao incremental.'); } $checks++; }
function inc_fixture_write($fixture,$path,$data) {
    $full=$fixture->root.'/'.$path;
    if(!is_dir(dirname($full))) { mkdir(dirname($full),0700,true); }
    file_put_contents($full,$data);
}
function inc_fixture() {
    $root=rtrim(sys_get_temp_dir(),'/\\').'/gvi-inc-test-'.bin2hex(random_bytes(12)); mkdir($root,0700);
    $root=str_replace('\\','/',realpath($root));
    $fixture=(object)['root'=>$root,'before'=>[],'after'=>[],'private'=>"<?php return ['test'=>'TEST_SECRET'];\n",'oldPdf'=>str_repeat("PDF_OLD\0",280000),'newPdf'=>str_repeat("PDF_NEW\0",300000)];
    $binary=PHP_OS_FAMILY==='Windows' ? 'C:/Program Files/Git/cmd/git.exe' : 'git';
    [$exit]=webtuga_inc_run([$binary,'init','--initial-branch=main'],$root); inc_check($exit===0);
    $fixture->git=new WebTugaIncrementalGit($root);
    $fixture->before=[
        'products.php'=>"<?php echo 'PRODUCTS';\n",

        'index.php'=>"<?php echo 'SITE';\n",'about.php'=>"<?php echo 'EMPRESA';\n",'backoffice/login.php'=>"<?php echo 'LOGIN';\n",
        'contact.php'=>"<?php echo 'ANTERIOR';\n",'includes/antigo.php'=>"<?php echo 'ANTIGO';\n",'assets/css/style.css'=>'body { color: black; }',
        'config/.htaccess'=>"Require all denied\n",'config/bootstrap.php'=>"<?php // bootstrap\n",'config/database.php'=>"<?php // paths\n",'config/local.example.php'=>"<?php return [];\n",
        'assets/catalog.pdf'=>$fixture->oldPdf,
    ];
    foreach($fixture->before as $path=>$data) {
        if($path==='assets/catalog.pdf') { $data="version https://git-lfs.github.com/spec/v1\noid sha256:".hash('sha256',$data)."\nsize ".strlen($data)."\n"; }
        inc_fixture_write($fixture,$path,$data);
    }
    inc_fixture_write($fixture,'.gitattributes',"* -text\n");
    inc_fixture_write($fixture,'.gitignore',"materials-*/\n");
    $settings=json_decode(file_get_contents(dirname(__DIR__).'/deploy/publicacao-webtuga.json'),true);
    $settings['private_config_sha256']=hash('sha256',$fixture->private);
    inc_fixture_write($fixture,'deploy/publicacao-webtuga.json',webtuga_release_json($settings));
    inc_commit($fixture,'base'); $fixture->base=$fixture->git->head();
    $fixture->after=$fixture->before;
    $fixture->after['contact.php']="<?php echo 'NOVO';\n";
    $fixture->after['includes/novo.php']="<?php function nova_funcao() { return 1; }\n";
    $fixture->after['assets/nova pasta/texto.txt']='texto novo';
    $fixture->after['assets/css/style.css']='body { color: blue; }';
    $fixture->after['assets/catalog.pdf']=$fixture->newPdf;
    unset($fixture->after['includes/antigo.php']); unlink($root.'/includes/antigo.php');
    foreach($fixture->after as $path=>$data) {
        if($path==='assets/catalog.pdf') { $data="version https://git-lfs.github.com/spec/v1\noid sha256:".hash('sha256',$data)."\nsize ".strlen($data)."\n"; }
        inc_fixture_write($fixture,$path,$data);
    }
    inc_fixture_write($fixture,'docs/nota.md','apenas documentacao');
    inc_fixture_write($fixture,'.local-backup/index.php',"<?php echo 'COM';\n");
    inc_commit($fixture,'alteracoes'); $fixture->head=$fixture->git->head();
    $fixture->cursor=webtuga_release_json(['version'=>1,'initialized'=>true,'domain'=>'gvi','commit'=>$fixture->base,'release'=>str_repeat('a',32)]);
    return $fixture;
}
function inc_commit($fixture,$message) {
    $fixture->git->command(['add','--all']);
    $fixture->git->command(['-c','user.name=Teste local','-c','user.email=teste@example.invalid','-c','core.hooksPath=','commit','-m',$message]);
}
function inc_server($fixture) {
    $server=(object)['files'=>$fixture->before+['config/local.php'=>$fixture->private,webtuga_release_cursor_path()=>$fixture->cursor],
        'directories'=>[''=>true,'config'=>true,'backoffice'=>true,'includes'=>true,'assets'=>true,'assets/css'=>true],
        'calls'=>[],'writes'=>[],'activationCount'=>0,'failAt'=>0,'failAfter'=>false,'failed'=>false,'uploadFault'=>'','uploadFailed'=>false,
        'httpFail'=>false,'externalChange'=>false,'cursorFail'=>false,'badBackup'=>false,'badProfile'=>false,'badRead'=>false];
    $server->runner=function($command,$credentials) use($server,$fixture) {
        $server->calls[]=$command;
        inc_check($command[1]==='--disable' && !in_array('-k',$command,true) && !in_array('--insecure',$command,true));
        inc_check(!str_contains(implode(' ',$command),'TEST_SECRET'));
        $url=$command[array_search('--url',$command,true)+1];
        if(str_starts_with($url,'https://')) {
            inc_check($credentials===''); $path=parse_url($url,PHP_URL_PATH);
            $changed=($server->files['contact.php'] ?? null)!==$fixture->before['contact.php'];
            $status=$path==='/config/bootstrap.php' ? 403 : ($server->httpFail && $changed ? 500 : 200);
            return [0,"pagina\nGVI_HTTP:".$status.':'.$url."\n"];
        }
        inc_check(str_starts_with($url,'ftp://fixture.invalid:21/'));
        inc_check(str_contains($credentials,'github_fixture@fixture.invalid:TEST_SECRET'));
        inc_check(in_array('--ssl-reqd',$command,true) && in_array('--tlsv1.2',$command,true));
        $rootQuery=$url==='ftp://fixture.invalid:21/' && $command[array_search('--max-time',$command,true)+1]==='60' && !in_array('--quote',$command,true);
        if ($rootQuery) { inc_check($command===webtuga_connection_command('fixture.invalid')); }
        else { $maximum=array_search('--tls-max',$command,true); inc_check($maximum===false); }
        inc_check(!in_array('--ftp-create-dirs',$command,true) && !in_array('--append',$command,true) && !in_array('--continue-at',$command,true));
        $path=rawurldecode(substr($url,strlen('ftp://fixture.invalid:21/')));
        $quotes=[]; foreach($command as $index=>$part) { if($part==='--quote') { $quotes[]=$command[$index+1]; } }
        if($quotes) {
            if(count($quotes)===1 && str_starts_with($quotes[0],'MKD ')) {
                $directory=substr($quotes[0],4); inc_check(webtuga_inc_path($directory)===$directory);
                $server->directories[$directory]=true; $server->writes[]=['kind'=>'mkdir','path'=>$directory]; return [0,''];
            }
            inc_check(count($quotes)===2 && str_starts_with($quotes[0],'RNFR ') && str_starts_with($quotes[1],'RNTO '));
            $from=substr($quotes[0],5); $to=substr($quotes[1],5);
            inc_check(isset($server->files[$from]));
            $activate=!str_starts_with($to,'config/') && str_ends_with($from,'.stage');
            if($activate) { $server->activationCount++; }
            $fail=$activate && !$server->failed && $server->activationCount===$server->failAt;
            if($fail && !$server->failAfter) { $server->failed=true; return [21,'TEST_SECRET erro']; }
            $server->files[$to]=$server->files[$from]; unset($server->files[$from]); $server->writes[]=['kind'=>'move','from'=>$from,'to'=>$to];
            if($fail || ($to===webtuga_release_cursor_path() && $server->cursorFail && !$server->failed)) { $server->failed=true; return [18,'TEST_SECRET erro']; }
            return [0,''];
        }
        if(in_array('--upload-file',$command,true)) {
            inc_check(webtuga_inc_protected($path));
            $local=$command[array_search('--upload-file',$command,true)+1]; $data=file_get_contents($local);
            $fail=$server->uploadFault!=='' && str_ends_with($path,'.before') && (!$server->uploadFailed || $server->uploadFault==='always');
            if($fail) { $server->uploadFailed=true; $data=substr($data,0,intdiv(strlen($data),2)); }
            if($server->badBackup && str_ends_with($path,'.before')) { $data.='TEST_SECRET corrupto'; }
            $server->files[$path]=$data; $server->writes[]=['kind'=>'put','path'=>$path];
            return [$fail ? 18 : 0,''];
        }
        if(in_array('--list-only',$command,true)) {
            $directory=rtrim($path,'/');
            if(!isset($server->directories[$directory])) { return [9,'TEST_SECRET pasta inacessivel']; }
            $names=[];
            foreach(array_keys($server->directories) as $name) { if($name!=='' && (dirname($name)==='.' ? '' : dirname($name))===$directory) { $names[basename($name)]=true; } }
            foreach(array_keys($server->files) as $name) { if((dirname($name)==='.' ? '' : dirname($name))===$directory) { $names[basename($name)]=true; } }
            return [0,implode("\n",array_keys($names))."\n"];
        }
        if($server->externalChange && $server->activationCount>=1 && $path==='contact.php') { $server->files[$path]='TEST_SECRET alteracao manual'; }
        if($path==='config/local.php') { inc_check(!in_array('--output',$command,true)); }
        if(!array_key_exists($path,$server->files)) { return [isset($server->directories[dirname($path)==='.' ? '' : dirname($path)]) ? 78 : 9,'TEST_SECRET ausente']; }
        $data=$path==='config/local.php' && $server->badProfile ? 'TEST_SECRET outro perfil' : $server->files[$path];
        if(in_array('--output',$command,true)) {
            $destination=$command[array_search('--output',$command,true)+1]; file_put_contents($destination,$data);
            return [$server->badRead && str_ends_with($path,'.stage') ? 18 : 0,''];
        }
        return [0,$data];
    };
    return $server;
}
function inc_context($fixture,$server) {
    $pool=new WebTugaIncrementalFiles();
    $client=new WebTugaIncrementalClient('TEST_SECRET',$pool,$server->runner);
    return [$pool,$client];
}
function inc_preview_for($fixture,$server) {
    [$pool,$client]=inc_context($fixture,$server);
    try { return webtuga_inc_preview($fixture->git,$client,$fixture->head); }
    finally { $pool->close(); }
}
function inc_materials_for($fixture,$plan,$suffix='ok',$bad=false) {
    $directory=$fixture->root.'/materials-'.$suffix; if(!is_dir($directory)) { mkdir($directory,0700); }
    $resolver=function($git,$args,$pointer,$environment,$destination) use($fixture,$bad) {
        inc_check(!str_contains(implode(' ',$args),'TEST_TOKEN'));
        inc_check(str_contains($environment['GIT_CONFIG_VALUE_0'],base64_encode('x-access-token:TEST_TOKEN')));
        inc_check(!isset($environment['WEBTUGA_FTP_PASSWORD']));
        inc_check(in_array('lfs.url=https://github.com/globoVac/gv_industries.git/info/lfs',$args,true));
        file_put_contents($destination,$bad ? 'TEST_SECRET outro binario' : $fixture->newPdf);
    };
    webtuga_inc_materials($fixture->root,$fixture->git,$plan,$directory,'TEST_TOKEN',$resolver);
    return $directory;
}
function inc_publish_for($fixture,$server,$code,$assets,$mode='PUBLICAR',$reference='',$id=null) {
    [$pool,$client]=inc_context($fixture,$server); $temporary=$pool->root;
    try { return webtuga_inc_publish($fixture->root,$fixture->git,$client,$pool,$fixture->head,$mode,$mode==='REVERTER' ? 'REVERTER GV' : 'PUBLICAR GV',$code,$reference,$assets,$id ?? str_repeat('b',32)); }
    finally { $pool->close(); inc_check(!is_dir($temporary)); }
}
function inc_expected_failure($fixture,$server,$code,$assets,$stage=null) {
    try { inc_publish_for($fixture,$server,$code,$assets); throw new LogicException('expected_failure'); }
    catch(WebTugaIncrementalFailure $error) {
        inc_check(!$error->report['success'] && ($stage===null || $error->report['stage']===$stage));
        inc_check(!str_contains(webtuga_inc_json($error->report).webtuga_inc_summary($error->report),'TEST_SECRET'));
        return $error->report;
    }
}
function inc_before_preserved($fixture,$server) {
    foreach($fixture->before as $path=>$data) { inc_check(($server->files[$path] ?? null)===$data); }
    foreach(array_diff_key($fixture->after,$fixture->before) as $path=>$data) { inc_check(!isset($server->files[$path])); }
    inc_check($server->files['config/local.php']===$fixture->private);
    inc_check($server->files[webtuga_release_cursor_path()]===$fixture->cursor);
}
function inc_fixture_remove($fixture) {
    $root=realpath($fixture->root); $parent=realpath(sys_get_temp_dir());
    inc_check($root!==false && $parent!==false && dirname($root)===$parent && preg_match('/^gvi-inc-test-[a-f0-9]{24}$/D',basename($root)));
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $entry) {
        $path=$entry->getPathname(); if($entry->isLink()) { unlink($path); } elseif(!$entry->isDir()) { @chmod($path,0600); unlink($path); } else { rmdir($path); }
    }
    rmdir($root);
}

$fixture=inc_fixture();
try {
    // A raiz usa exatamente o teste de ligacao existente; falhas param sem repeticao.
    foreach ([6,7,18,28,35,60,67] as $curlCode) {
        $rootCalls=0; $pool=new WebTugaIncrementalFiles();
        try {
            $runner=function($command,$credentials) use (&$rootCalls,$curlCode) {
                $rootCalls++; inc_check($command===webtuga_connection_command('fixture.invalid'));
                inc_check(!str_contains(implode(' ',$command),'TEST_SECRET'));
                return [$curlCode,'TEST_SECRET resposta privada'];
            };
            try { new WebTugaIncrementalClient('TEST_SECRET',$pool,$runner); throw new LogicException('expected_failure'); }
            catch (WebTugaPublicationOperationFailure $error) {
                $report=webtuga_inc_cli_failure($error,'PREVIA','confirmar_ligacao_ftps');
                inc_check($report['failure']['operation']==='list' && $report['failure']['path']==='/' && $report['failure']['curl_code']===$curlCode);
                inc_check($report['application_files_changed']===0 && $rootCalls===1);
                inc_check(!str_contains(webtuga_inc_json($report).webtuga_inc_summary($report),'TEST_SECRET'));
            }
        } finally { $pool->close(); }
    }
    $pool=new WebTugaIncrementalFiles();
    try {
        $runner=function(){throw new RuntimeException('TEST_SECRET erro de transporte');};
        try { new WebTugaIncrementalClient('TEST_SECRET',$pool,$runner); throw new LogicException('expected_failure'); }
        catch (WebTugaPublicationOperationFailure $error) {
            $report=webtuga_inc_cli_failure($error,'PREVIA','confirmar_ligacao_ftps');
            inc_check($report['failure']['reason']==='transport_exception' && $report['failure']['curl_code']===null);
            inc_check(!str_contains(webtuga_inc_json($report).webtuga_inc_summary($report),'TEST_SECRET'));
        }
        $runner=fn()=>[0,"index.php\ncompany.php\ncontacts.php\n"];
        try { new WebTugaIncrementalClient('TEST_SECRET',$pool,$runner); throw new LogicException('expected_failure'); }
        catch (RuntimeException $error) { inc_check(str_contains($error->getMessage(),'raiz esperada do gvi')); }
    } finally { $pool->close(); }
    $server=inc_server($fixture); $plan=inc_preview_for($fixture,$server);
    inc_check(count($plan['files'])===6 && count($plan['ignored'])===2 && $plan['blocked']===[] && $server->writes===[]);
    inc_check(count(array_filter($plan['files'],fn($file)=>$file['status']==='pending'))===6);
    // Repetir a consulta nao muda a aprovacao quando o Git e o servidor sao iguais.
    $rechecked=inc_preview_for($fixture,$server);
    inc_check(hash_equals($plan['digest'],$rechecked['digest']) && $server->writes===[]);
    // Uma falha anterior ao envio deve identificar a etapa sem expor a resposta privada.
    $unknown=webtuga_inc_cli_failure(new RuntimeException('TEST_SECRET resposta privada'),'PUBLICAR','reconferir_previa');
    inc_check($unknown['stage']==='reconferir_previa' && $unknown['application_files_changed']===0);
    inc_check(!str_contains(webtuga_inc_json($unknown).webtuga_inc_summary($unknown),'TEST_SECRET'));
    $transport=webtuga_inc_cli_failure(new WebTugaPublicationOperationFailure('get','config/gvi-deploy.json',28),'PUBLICAR','reconferir_previa');
    inc_check($transport['failure']['operation']==='get' && $transport['failure']['curl_code']===28);
    inc_check(str_contains(webtuga_inc_summary($transport),'curl `28`'));
    try { webtuga_inc_validate_digest($plan,str_repeat('0',64)); throw new LogicException('expected_failure'); }
    catch (RuntimeException $error) {
        $failure=webtuga_inc_cli_failure($error,'PUBLICAR','validar_codigo_previa');
        inc_check($failure['failure']['reason']==='preview_not_confirmed' && $failure['failure']['source']==='scripts/webtuga-incremental.php');
        inc_check($failure['application_files_changed']===0 && $server->writes===[]);
    }
    try { webtuga_inc_validate_digest(['digest'=>$plan['digest'],'blocked'=>[['path'=>'config/local.php','reason'=>'protected']]],$plan['digest']); throw new LogicException('expected_failure'); }
    catch (RuntimeException $error) {
        $failure=webtuga_inc_cli_failure($error,'PUBLICAR','validar_codigo_previa');
        inc_check($failure['failure']['reason']==='preview_blocked' && $server->writes===[]);
    }
    $assets=inc_materials_for($fixture,$plan);
    $published=inc_publish_for($fixture,$server,$plan['digest'],$assets);
    inc_check($published['success'] && $published['application_files_changed']===6 && !$published['rollback_attempted']);
    foreach($fixture->after as $path=>$data) { inc_check(($server->files[$path] ?? null)===$data); }
    inc_check(!isset($server->files['includes/antigo.php']) && isset($server->directories['assets/nova pasta']));
    $cursor=webtuga_inc_cursor($server->files[webtuga_release_cursor_path()]); inc_check($cursor['commit']===$fixture->head && $cursor['kind']==='incremental');
    inc_check(!str_contains(webtuga_inc_json($published).webtuga_inc_summary($published),'TEST_SECRET'));
    $writes=count($server->writes); $noChanges=inc_preview_for($fixture,$server);
    inc_check($noChanges['files']===[]);
    $noOp=inc_publish_for($fixture,$server,$noChanges['digest'],$assets,'PUBLICAR','',str_repeat('d',32));
    inc_check($noOp['success'] && $noOp['stage']==='sem_alteracoes' && count($server->writes)===$writes);
    $restored=inc_publish_for($fixture,$server,'','', 'REVERTER','',str_repeat('c',32));
    inc_check($restored['success'] && $restored['rollback_completed'] && $restored['restored_files']===6);
    inc_before_preserved($fixture,$server);

    // Adotar uma versao que ja foi enviada manualmente nao deve reenviar ficheiros.
    $server=inc_server($fixture); $server->files=$fixture->after+['config/local.php'=>$fixture->private,webtuga_release_cursor_path()=>$fixture->cursor];
    $server->directories['assets/nova pasta']=true;
    $already=inc_preview_for($fixture,$server);
    inc_check(count(array_filter($already['files'],fn($file)=>$file['status']==='already_equal'))===6);
    $adopted=inc_publish_for($fixture,$server,$already['digest'],$assets);
    inc_check($adopted['success'] && $adopted['application_files_changed']===0);
    foreach($server->writes as $write) { inc_check(!isset($write['to']) || str_starts_with($write['to'],'config/')); }

    // Copia anterior corrompida ou outro cursor impedem recuperar por cima de dados novos.
    foreach(['backup','cursor'] as $fault) {
        $server=inc_server($fixture); $plan=inc_preview_for($fixture,$server);
        $published=inc_publish_for($fixture,$server,$plan['digest'],$assets);
        $record=json_decode($server->files[webtuga_inc_record_path($published['release'])],true);
        if($fault==='backup') {
            foreach($record['files'] as $file) { if($file['backup']!==null) { $server->files[$file['backup']]='TEST_SECRET corrompido'; break; } }
        } else { $server->files[webtuga_release_cursor_path()]=webtuga_release_json(['version'=>1,'initialized'=>true,'commit'=>$fixture->head,'release'=>str_repeat('e',32)]); }
        $writes=count($server->writes);
        try { inc_publish_for($fixture,$server,'','','REVERTER',$published['release'],str_repeat('c',32)); throw new LogicException('expected_failure'); }
        catch(WebTugaIncrementalFailure $error) { inc_check(!$error->report['success'] && !$error->report['rollback_completed']); }
        inc_check(count($server->writes)===$writes);
    }

    foreach(['rename_before','rename_after','http_after','cursor_after','backup_bad','upload_once','upload_always','read_failed','profile_bad'] as $fault) {
        $server=inc_server($fixture); $plan=inc_preview_for($fixture,$server);
        if($fault==='upload_once' || $fault==='upload_always') { $server->uploadFault=$fault==='upload_once' ? 'once' : 'always'; }
        if($fault==='rename_before' || $fault==='rename_after') { $server->failAt=2; $server->failAfter=$fault==='rename_after'; }
        if($fault==='http_after') { $server->httpFail=true; }
        if($fault==='cursor_after') { $server->cursorFail=true; }
        if($fault==='backup_bad') { $server->badBackup=true; }
        if($fault==='read_failed') { $server->badRead=true; }
        if($fault==='profile_bad') { $server->badProfile=true; }
        if($fault==='upload_once') {
            $result=inc_publish_for($fixture,$server,$plan['digest'],$assets);
            inc_check($result['success'] && count($result['transfer_events'])>=1); continue;
        }
        $result=inc_expected_failure($fixture,$server,$plan['digest'],$assets);
        if(in_array($fault,['rename_before','rename_after','http_after','cursor_after'],true)) { inc_check($result['rollback_attempted'] && $result['rollback_completed']); }
        else { inc_check($result['application_files_changed']===0 && !$result['rollback_attempted']); }
        inc_before_preserved($fixture,$server);
    }
    $server=inc_server($fixture); $plan=inc_preview_for($fixture,$server);
    $result=inc_expected_failure($fixture,$server,str_repeat('0',64),$assets); inc_check($server->writes===[]);
    $server->files['contact.php']='TEST_SECRET mudanca fora do Git';
    $result=inc_expected_failure($fixture,$server,$plan['digest'],$assets); inc_check($server->writes===[]);

    $server=inc_server($fixture); $plan=inc_preview_for($fixture,$server); $server->externalChange=true;
    $result=inc_expected_failure($fixture,$server,$plan['digest'],$assets);
    inc_check($result['rollback_attempted'] && !$result['rollback_completed'] && $server->files['contact.php']==='TEST_SECRET alteracao manual');

    $server=inc_server($fixture); $plan=inc_preview_for($fixture,$server);
    file_put_contents($fixture->root.'/contact.php',"<?php echo 'ALTERACAO SEM COMMIT';\n");
    $result=inc_expected_failure($fixture,$server,$plan['digest'],$assets); inc_check($server->writes===[]);
    file_put_contents($fixture->root.'/contact.php',$fixture->after['contact.php']);
    try { inc_materials_for($fixture,$plan,'bad',true); throw new LogicException('expected_failure'); }
    catch(RuntimeException $error) { inc_check($error->getMessage()!=='expected_failure'); }

    foreach(['config/local.php','assets/vendor/biblioteca.php','backoffice/assets/downloads/nota.pdf','backoffice/assets/img/produto_1/foto.png','backoffice/cache_pdf/um.pdf','api/data.sql','api/auth.json','api/.env'] as $path) { inc_check(webtuga_inc_policy($path)==='protected'); }
    foreach(['config/database.php','composer.lock','uploads/cliente.pdf','assets/uploads/cliente.jpg','cache/resposta.json'] as $path) { inc_check(webtuga_inc_policy($path)==='protected'); }
    foreach(['includes/api.php','backoffice/users.php','assets/video/exemplo.mp4','contact.php'] as $path) { inc_check(webtuga_inc_policy($path)==='application'); }
    foreach(['api/rota.php','functions/mail.php','cliente/pagina.php','outro-site/index.php'] as $path) { inc_check(webtuga_inc_policy($path)==='blocked'); }
    foreach(['.local-backup/index.php','docs/texto.md','scripts/teste.php','.github/workflows/teste.yml'] as $path) { inc_check(webtuga_inc_policy($path)==='ignored'); }
    foreach(['../index.php','/index.php','api/../../config/local.php','api/teste.php;type=a','api/teste%0a.php','api/teste' . "\n" . '.php','api//teste.php'] as $path) {
        try { webtuga_inc_path($path); throw new LogicException('expected_failure'); }
        catch(RuntimeException $error) { inc_check($error->getMessage()!=='expected_failure'); }
    }
    [$pool,$client]=inc_context($fixture,inc_server($fixture));
    try {
        foreach([['put','index.php','x'],['get','../index.php',null],['move','index.php','contact.php'],['mkdir','config',null]] as [$op,$path,$arg]) {
            try { $client->command($op,$path,$arg); throw new LogicException('expected_failure'); }
            catch(RuntimeException $error) { inc_check($error->getMessage()!=='expected_failure'); }
        }
    } finally { $pool->close(); }

    // PHP invalido e recusado antes de preparar copias na hospedagem.
    inc_fixture_write($fixture,'includes/invalido.php','<?php function (');
    inc_commit($fixture,'sintaxe invalida'); $fixture->head=$fixture->git->head();
    $server=inc_server($fixture); $invalidPhp=inc_preview_for($fixture,$server);
    inc_expected_failure($fixture,$server,$invalidPhp['digest'],$assets,'validar'); inc_check($server->writes===[]);

    // Alteracoes protegidas, versao diferente da previa e links Git nao podem ser publicadas.
    inc_fixture_write($fixture,'config/local.php',"<?php return ['secret'=>'TEST_SECRET'];\n");
    inc_fixture_write($fixture,'backoffice/assets/downloads/cliente.pdf','TEST_SECRET documento');
    inc_commit($fixture,'protegidos'); $fixture->head=$fixture->git->head();
    $server=inc_server($fixture); $blocked=inc_preview_for($fixture,$server);
    inc_check(count($blocked['blocked'])===2 && $server->writes===[]);
    inc_expected_failure($fixture,$server,$blocked['digest'],$assets); inc_check($server->writes===[]);
    $blob=trim($fixture->git->command(['hash-object','-w','--stdin'],'../config/local.php'));
    $fixture->git->command(['update-index','--add','--cacheinfo','120000,'.$blob.',includes/link.php']);
    $fixture->git->command(['-c','user.name=Teste local','-c','user.email=teste@example.invalid','-c','core.hooksPath=','commit','-m','link bloqueado']);
    $fixture->head=$fixture->git->head(); $server=inc_server($fixture);
    try { inc_preview_for($fixture,$server); throw new LogicException('expected_failure'); }
    catch(RuntimeException $error) { inc_check($error->getMessage()!=='expected_failure' && $server->writes===[]); }

    $result=['offline_checks_passed'=>$checks,'real_connections'=>0,'real_hosting_writes'=>0,'real_lfs_downloads'=>0];
} finally { inc_fixture_remove($fixture); }
echo webtuga_inc_json($result),PHP_EOL;
