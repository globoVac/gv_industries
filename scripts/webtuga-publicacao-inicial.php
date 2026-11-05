<?php

require_once __DIR__ . '/conferir-webtuga.php';

class WebTugaPublicationFailure extends RuntimeException
{
    public $report;
    public function __construct($report) {
        $this->report = $report;
        parent::__construct('Publicacao interrompida na etapa: ' . $report['stage']);
    }
}

class WebTugaPublicationOperationFailure extends RuntimeException
{
    public $diagnostic;
    public function __construct($operation, $path, $curlCode = null, $reason = 'transport_failure') {
        $this->diagnostic = ['operation'=>$operation,'path'=>$path,'curl_code'=>is_int($curlCode) ? $curlCode : null,'reason'=>$reason];
        parent::__construct('Falha numa operacao de transferencia; detalhes do servidor omitidos.');
    }
}

function webtuga_release_id($id) {
    if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) { throw new RuntimeException('Referencia de publicacao invalida.'); }
    return $id;
}
function webtuga_release_slot($id, $index, $kind) {
    webtuga_release_id($id);
    if (!is_int($index) || $index < 0 || $index > 4 || !in_array($kind,['before','stage','restore'],true)) { throw new RuntimeException('Posicao de publicacao invalida.'); }
    return 'config/gvi-release-' . $id . '-' . $index . '.' . $kind;
}
function webtuga_release_record_path($id) { return 'config/gvi-release-' . webtuga_release_id($id) . '.json'; }
function webtuga_release_cursor_path() { return 'config/gvi-deploy.json'; }
function webtuga_release_protected_path($path) {
    return $path === webtuga_release_cursor_path() || (bool)preg_match('~^config/gvi-release-[a-f0-9]{32}(?:\.json|-[0-4]\.(?:before|stage|restore))$~D',$path);
}
function webtuga_release_command($operation, $path, $argument = null) {
    $readable = array_merge(webtuga_review_paths(),webtuga_review_support_paths(),['config/local.php', '.htaccess']);
    $command = ['curl','--disable','--config','-','--silent','--show-error','--ssl-reqd','--tlsv1.2','--proto','=ftp','--ftp-method','nocwd','--connect-timeout','15','--max-time','30'];
    $base = 'ftp://' . webtuga_connection_target('gvi')['server'] . ':21/';
    if ($operation === 'list' && $path === 'config/') { return array_merge($command,['--list-only','--url',$base.$path]); }
    if ($operation === 'get' && (in_array($path,$readable,true) || webtuga_release_protected_path($path))) {
        return array_merge($command,['--max-filesize','2097152','--url',$base.$path]);
    }
    if ($operation === 'put' && webtuga_release_protected_path($path) && $path !== webtuga_release_cursor_path() && is_string($argument) && is_file($argument)) {
        return array_merge($command,['--upload-file',$argument,'--url',$base.$path]);
    }
    if ($operation === 'rename' && (in_array($path,webtuga_review_paths(),true) || $path === webtuga_release_cursor_path()) && is_string($argument) &&
        preg_match('~^config/gvi-release-[a-f0-9]{32}-[0-4]\.(?:stage|restore)$~D',$argument)) {
        return array_merge($command,['--quote','RNFR '.$argument,'--quote','RNTO '.$path,'--list-only','--url',$base]);
    }
    throw new RuntimeException('Operacao fora dos ficheiros autorizados.');
}
function webtuga_release_http_command($path) {
    if (!in_array($path,['/','/backoffice/login.php','/config/bootstrap.php'],true)) { throw new RuntimeException('Pagina de verificacao invalida.'); }
    return ['curl','--disable','--silent','--show-error','--tlsv1.2','--proto','=https','--proto-redir','=https','--location','--max-redirs','3','--connect-timeout','10','--max-time','30',
        '--write-out',"\nGVI_HTTP:%{http_code}:%{url_effective}\n",'--url','https://gv-industries.co.uk'.$path];
}

class WebTugaReleaseClient
{
    private $runner;
    private $credentials;
    private $preparationTransfers = [];
    public function __construct($password, $runner = null) {
        $this->credentials = webtuga_connection_credentials($password,'gvi');
        $this->runner = $runner ?? 'webtuga_connection_run';
        try { webtuga_connection_check($password,$this->runner,'gvi'); }
        catch (Throwable $error) { throw new RuntimeException('A raiz FTPS nao foi confirmada.'); }
    }
    public function read($path, $optional = false) {
        try { [$exit,$data] = ($this->runner)(webtuga_release_command('get',$path),$this->credentials); }
        catch (Throwable $error) { throw new WebTugaPublicationOperationFailure('get',$path,null,'transport_exception'); }
        if ($optional && $exit === 78) { return null; }
        if ($exit !== 0 || !is_string($data)) { throw new WebTugaPublicationOperationFailure('get',$path,is_int($exit) ? $exit : null); }
        return $data;
    }
    public function configNames() {
        try { [$exit,$listing] = ($this->runner)(webtuga_release_command('list','config/'),$this->credentials); }
        catch (Throwable $error) { throw new WebTugaPublicationOperationFailure('list','config/',null,'transport_exception'); }
        if ($exit !== 0) { throw new WebTugaPublicationOperationFailure('list','config/',$exit); }
        return array_map(fn($line)=>basename(rtrim(trim($line),'/')),preg_split('/\r?\n/',$listing));
    }
    public function preparationTransfers() { return $this->preparationTransfers; }
    private function notePreparation($path, $attempt, $code, $expectedBytes, $observedBytes, $result) {
        $this->preparationTransfers[] = ['path'=>$path,'attempt'=>$attempt,'curl_code'=>is_int($code) ? $code : null,
            'expected_bytes'=>$expectedBytes,'observed_bytes'=>$observedBytes,'result'=>$result];
    }
    public function putVerified($path, $data, $retryPreparation = false) {
        if (!is_string($data) || strlen($data) > 2097152) { throw new RuntimeException('Tamanho de copia invalido.'); }
        if (!is_bool($retryPreparation) || ($retryPreparation && !preg_match('~^config/gvi-release-[a-f0-9]{32}-[0-4]\.(?:before|stage)$~D',$path))) {
            throw new RuntimeException('Repeticao permitida apenas para copias de preparacao.');
        }
        $temporary = tempnam(sys_get_temp_dir(),'gvi-release-');
        if ($temporary === false) { throw new RuntimeException('Ficheiro temporario indisponivel.'); }
        try {
            if (file_put_contents($temporary,$data,LOCK_EX) !== strlen($data)) { throw new RuntimeException('Copia temporaria incompleta.'); }
            @chmod($temporary,0600);
            clearstatcache(true,$temporary);
            if (filesize($temporary) !== strlen($data)) { throw new RuntimeException('Tamanho temporario diferente do esperado.'); }
            $expectedHash = hash('sha256',$data);
            for ($attempt = 1; $attempt <= ($retryPreparation ? 2 : 1); $attempt++) {
                try { [$exit] = ($this->runner)(webtuga_release_command('put',$path,$temporary),$this->credentials); }
                catch (Throwable $error) { throw new WebTugaPublicationOperationFailure('put',$path,null,'transport_exception'); }
                if ($exit === 0) {
                    $remote = $this->read($path);
                    $matches = hash_equals($expectedHash,hash('sha256',$remote));
                    if ($attempt > 1) { $this->notePreparation($path,$attempt,0,strlen($data),strlen($remote),$matches ? 'verified_after_retry' : 'blocked_by_content'); }
                    unset($remote);
                    if (!$matches) { throw new WebTugaPublicationOperationFailure('verify',$path,null,'sha256_mismatch'); }
                    return;
                }
                // Uma nova ligacao so pode repetir um PUT de preparacao em erros de rede selecionados.
                // Nao repetir autenticacao, certificado, renomeacoes, manifesto ou registo de versao.
                if (!$retryPreparation || !in_array($exit,[6,7,18,28,55,56],true)) {
                    if ($retryPreparation) { $this->notePreparation($path,$attempt,$exit,strlen($data),null,'blocked_by_error'); }
                    throw new WebTugaPublicationOperationFailure('put',$path,$exit);
                }
                try { $remote = $this->read($path,true); }
                catch (WebTugaPublicationOperationFailure $error) {
                    $this->notePreparation($path,$attempt,$exit,strlen($data),null,'readback_failed');
                    throw $error;
                }
                $observedBytes = $remote === null ? null : strlen($remote);
                if ($remote !== null && hash_equals($expectedHash,hash('sha256',$remote))) {
                    $this->notePreparation($path,$attempt,$exit,strlen($data),$observedBytes,'confirmed_after_error');
                    unset($remote);
                    return;
                }
                // So substituir uma copia ausente ou um prefixo incompleto dos mesmos bytes.
                $partial = $remote === null || (strlen($remote) < strlen($data) && str_starts_with($data,$remote));
                unset($remote);
                if (!$partial) {
                    $this->notePreparation($path,$attempt,$exit,strlen($data),$observedBytes,'blocked_by_content');
                    throw new WebTugaPublicationOperationFailure('verify',$path,$exit,'sha256_mismatch_after_upload_error');
                }
                $this->notePreparation($path,$attempt,$exit,strlen($data),$observedBytes,$attempt === 1 ? 'retry_from_start' : 'retry_exhausted');
                if ($attempt === 2) { throw new WebTugaPublicationOperationFailure('put',$path,$exit,'retry_exhausted'); }
            }
        } finally { if (is_file($temporary)) { unlink($temporary); } }
    }
    public function activate($staged, $target, $expectedHash) {
        if (hash('sha256',$this->read($staged)) !== $expectedHash) { throw new RuntimeException('O candidato mudou antes da ativacao.'); }
        [$exit] = ($this->runner)(webtuga_release_command('rename',$target,$staged),$this->credentials);
        if ($exit !== 0) { throw new WebTugaPublicationOperationFailure('rename',$target,$exit); }
        if (hash('sha256',$this->read($target)) !== $expectedHash) { throw new WebTugaPublicationOperationFailure('verify',$target,null,'sha256_mismatch'); }
    }
    public function http($path, $expectedStatus) {
        [$exit,$output] = ($this->runner)(webtuga_release_http_command($path),'');
        if ($exit !== 0 || !preg_match('~\nGVI_HTTP:(\d{3}):(https://[^\r\n]+)\r?\n?$~',$output,$match)) { throw new RuntimeException('Verificacao HTTPS nao concluida.'); }
        $url = parse_url($match[2]);
        if ((int)$match[1] !== $expectedStatus || !in_array($url['host'] ?? '',['gv-industries.co.uk','www.gv-industries.co.uk'],true) || ($url['path'] ?? '/') !== $path || isset($url['query']) ||
            ($expectedStatus === 200 && preg_match('/(?:Fatal error|Parse error|Uncaught\s+\w*Exception|<\?php)/i',substr($output,0,-strlen($match[0]))))) {
            throw new RuntimeException('A pagina nao passou na verificacao HTTPS.');
        }
    }
}

function webtuga_publication_settings($root) {
    $settings = json_decode(file_get_contents($root.'/deploy/publicacao-webtuga.json'),true,512,JSON_THROW_ON_ERROR);
    $order = $settings['activation_order'] ?? [];
    $sorted = $order; sort($sorted); $expected = webtuga_review_paths(); sort($expected);
    if (($settings['version'] ?? null) !== 1 || ($settings['domain'] ?? '') !== 'gvi' || count($order) !== 5 || $sorted !== $expected ||
        !is_string($settings['private_config_sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$settings['private_config_sha256'])) {
        throw new RuntimeException('Definicoes de publicacao invalidas.');
    }
    return $settings;
}
function webtuga_cursor_validate($raw) {
    if ($raw === null) { return null; }
    $cursor = json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if (($cursor['version'] ?? null) !== 1 || !is_bool($cursor['initialized'] ?? null)) { throw new RuntimeException('Registo de versao invalido.'); }
    if ($cursor['initialized'] && (!preg_match('/^[a-f0-9]{40}$/D',$cursor['commit'] ?? '') || !preg_match('/^[a-f0-9]{32}$/D',$cursor['release'] ?? ''))) {
        throw new RuntimeException('Versao publicada invalida.');
    }
    return $cursor;
}
function webtuga_publication_preview($root, $client) {
    $entries = webtuga_review_plan($root);
    $settings = webtuga_publication_settings($root);
    $pending = []; $files = []; $blocked = false;
    foreach ($entries as $entry) {
        $remoteHash = hash('sha256',$client->read($entry['path']));
        $status = $entry['kind'] === 'preserved'
            ? ($remoteHash === $entry['backup_sha256'] ? 'preservado' : 'bloqueado_por_divergencia')
            : ($remoteHash === $entry['git_sha256'] ? 'igual_ao_git' : ($entry['kind'] === 'application' && $remoteHash === $entry['backup_sha256'] ? 'atualizacao_prevista' : 'bloqueado_por_divergencia'));
        if ($status === 'bloqueado_por_divergencia') { $blocked = true; }
        if ($status === 'atualizacao_prevista') { $pending[$entry['path']] = $entry; }
        $files[] = ['path'=>$entry['path'],'status'=>$status];
    }
    $rawCursor = $client->read(webtuga_release_cursor_path(),true);
    webtuga_cursor_validate($rawCursor);
    $ordered = [];
    foreach ($settings['activation_order'] as $path) { if (isset($pending[$path])) { $ordered[] = $pending[$path]; } }
    return ['files'=>$files,'blocked'=>$blocked,'pending'=>$ordered,'previous_cursor'=>$rawCursor,'settings'=>$settings];
}
function webtuga_release_json($data) { return json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"; }
function webtuga_release_cursor_write($client, $raw, $attempt) {
    $data = $raw ?? webtuga_release_json(['version'=>1,'initialized'=>false]);
    webtuga_cursor_validate($data);
    $stage = webtuga_release_slot($attempt,0,'stage');
    $client->putVerified($stage,$data);
    $client->activate($stage,webtuga_release_cursor_path(),hash('sha256',$data));
}
function webtuga_release_record_validate($record, $id) {
    if (($record['version'] ?? null) !== 1 || ($record['domain'] ?? '') !== 'gvi' || ($record['release'] ?? '') !== webtuga_release_id($id) ||
        !preg_match('/^[a-f0-9]{40}$/D',$record['commit'] ?? '') || !is_array($record['files'] ?? null) || count($record['files']) < 1 || count($record['files']) > 5 ||
        !preg_match('/^[a-f0-9]{64}$/D',$record['published_cursor_sha256'] ?? '') || !array_key_exists('previous_cursor',$record)) { throw new RuntimeException('Copia de recuperacao invalida.'); }
    $seen = [];
    foreach ($record['files'] as $index=>$file) {
        if (!in_array($file['path'] ?? '',webtuga_review_paths(),true) || isset($seen[$file['path']]) ||
            !preg_match('/^[a-f0-9]{64}$/D',$file['before_sha256'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D',$file['after_sha256'] ?? '') ||
            ($file['backup'] ?? '') !== webtuga_release_slot($id,$index,'before')) { throw new RuntimeException('Ficheiro de recuperacao invalido.'); }
        $seen[$file['path']] = true;
    }
    if ($record['previous_cursor'] !== null && (!is_string($record['previous_cursor']) || base64_decode($record['previous_cursor'],true) === false)) { throw new RuntimeException('Versao anterior invalida.'); }
}
function webtuga_release_restore($client, $record, $attempt) {
    webtuga_release_record_validate($record,$record['release'] ?? '');
    $previous = $record['previous_cursor'] === null ? null : base64_decode($record['previous_cursor'],true);
    webtuga_cursor_validate($previous);
    $currentCursor = $client->read(webtuga_release_cursor_path(),true);
    $parsed = webtuga_cursor_validate($currentCursor);
    $cursorIsBefore = $currentCursor === $previous || ($previous === null && $parsed !== null && !$parsed['initialized']);
    if (!$cursorIsBefore && ($currentCursor === null || hash('sha256',$currentCursor) !== $record['published_cursor_sha256'])) { throw new RuntimeException('Outra versao foi publicada; reversao bloqueada.'); }
    $restore = [];
    foreach ($record['files'] as $index=>$file) {
        $current = hash('sha256',$client->read($file['path']));
        if ($current === $file['before_sha256']) { continue; }
        if ($current !== $file['after_sha256']) { throw new RuntimeException('O ficheiro mudou depois da publicacao.'); }
        $backup = $client->read($file['backup']);
        if (hash('sha256',$backup) !== $file['before_sha256']) { throw new RuntimeException('Copia de recuperacao nao confirmada.'); }
        $restore[] = ['index'=>$index,'file'=>$file,'data'=>$backup];
    }
    // Inverte a ordem de ativacao, mantendo o carregador disponivel ate ao fim.
    foreach (array_reverse($restore) as $item) {
        $file = $item['file'];
        if (hash('sha256',$client->read($file['path'])) !== $file['after_sha256']) { throw new RuntimeException('O ficheiro mudou durante a reversao.'); }
        $stage = webtuga_release_slot($attempt,$item['index'],'restore');
        $client->putVerified($stage,$item['data']);
        $client->activate($stage,$file['path'],$file['before_sha256']);
    }
    if (!$cursorIsBefore) { webtuga_release_cursor_write($client,$previous,$attempt); }
    return count($restore);
}

function webtuga_preparation_diagnostic($root, $client, $reference, $settings) {
    webtuga_release_id($reference);
    $manifest = json_decode(file_get_contents($root.'/deploy/conferencia-webtuga.json'),true,512,JSON_THROW_ON_ERROR);
    $entries = array_column($manifest['files'],null,'path');
    $names = $client->configNames();
    $rows = [];
    foreach ($settings['activation_order'] as $index=>$path) {
        $row = ['path'=>$path];
        foreach (['before'=>'backup_sha256','stage'=>'git_sha256'] as $kind=>$hashKey) {
            $remotePath = webtuga_release_slot($reference,$index,$kind);
            $status = 'ausente'; $failure = null;
            if (in_array(basename($remotePath),$names,true)) {
                try {
                    $contents = $client->read($remotePath);
                    $status = hash('sha256',$contents) === $entries[$path][$hashKey] ? 'confirmado' : 'conteudo_diferente';
                    unset($contents);
                } catch (WebTugaPublicationOperationFailure $error) { $status='erro_leitura'; $failure=$error->diagnostic; }
            }
            $row[$kind] = ['status'=>$status];
            if ($failure !== null) { $row[$kind]['failure']=$failure; }
        }
        $rows[] = $row;
    }
    $recordPath = webtuga_release_record_path($reference);
    $recordStatus = 'ausente';
    if (in_array(basename($recordPath),$names,true)) {
        try {
            $record = json_decode($client->read($recordPath),true,64,JSON_THROW_ON_ERROR);
            webtuga_release_record_validate($record,$reference);
            $recordStatus='confirmado';
        } catch (Throwable $error) { $recordStatus='nao_confirmado'; }
    }
    return ['reference'=>$reference,'copies'=>$rows,'recovery_record'=>$recordStatus,'server_writes'=>0];
}

function webtuga_publication_lint($root) {
    foreach (array_merge(webtuga_review_paths(),webtuga_review_support_paths()) as $path) {
        if (!str_ends_with($path,'.php')) { continue; }
        $process = proc_open([PHP_BINARY,'-l',$root.'/'.$path],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        if (!is_resource($process)) { throw new RuntimeException('Nao foi possivel verificar a sintaxe dos candidatos.'); }
        fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Um candidato PHP tem sintaxe invalida.'); }
    }
}
function webtuga_publish_initial($root, $password, $mode, $confirmation, $commit, $release = '', $runner = null, $identifier = null, $notice = null) {
    if (!in_array($mode,['PREVIA','DIAGNOSTICO','PUBLICAR','REVERTER'],true) || !is_string($commit) || !preg_match('/^[a-f0-9]{40}$/D',$commit)) { throw new RuntimeException('Modo ou versao invalido.'); }
    if (($mode === 'PUBLICAR' && $confirmation !== 'PUBLICAR GV') || ($mode === 'REVERTER' && $confirmation !== 'REVERTER GV')) { throw new RuntimeException('Confirmacao explicita em falta.'); }
    if ($release !== '') { webtuga_release_id($release); }
    if ($mode === 'DIAGNOSTICO' && $release === '') { throw new RuntimeException('Informe a referencia da tentativa para diagnosticar.'); }
    $attempt = webtuga_release_id($identifier ?? bin2hex(random_bytes(16)));
    $report = ['success'=>false,'mode'=>$mode,'commit'=>$commit,'release'=>null,'stage'=>'preparar','scope'=>'alinhamento-inicial-cinco-ficheiros','files'=>[],
        'candidate_files'=>0,'application_files_activated'=>0,'rollback_attempted'=>false,'rollback_completed'=>false,'originals_restored'=>0,'private_configuration_changed'=>false,'database_restores'=>0,'preparation_transfers'=>[]];
    $client = null; $record = null; $activationStarted = false;
    try {
        $settings = webtuga_publication_settings($root);
        webtuga_review_plan($root);
        webtuga_publication_lint($root);
        $client = new WebTugaReleaseClient($password,$runner);
        if ($mode === 'REVERTER') {
            $report['stage'] = 'ler_copia_de_recuperacao';
            $cursor = webtuga_cursor_validate($client->read(webtuga_release_cursor_path(),true));
            $release = $release !== '' ? $release : ($cursor['release'] ?? '');
            webtuga_release_id($release); $report['release'] = $release;
            $record = json_decode($client->read(webtuga_release_record_path($release)),true,64,JSON_THROW_ON_ERROR);
            webtuga_release_record_validate($record,$release);
            $client->http('/config/bootstrap.php',403);
            $report['stage'] = 'repor_originais';
            $report['rollback_attempted'] = true;
            $report['originals_restored'] = webtuga_release_restore($client,$record,$attempt);
            $report['rollback_completed'] = true;
            $client->http('/',200); $client->http('/backoffice/login.php',200);
            $report['success'] = true; $report['stage'] = 'reversao_concluida';
            return $report;
        }
        $report['stage'] = 'comparar_versoes';
        $preview = webtuga_publication_preview($root,$client);
        $report['files'] = $preview['files']; $report['candidate_files'] = count($preview['pending']);
        $report['blocked'] = $preview['blocked'];
        if ($mode === 'PREVIA') { $report['success'] = true; $report['stage'] = 'previa_concluida'; return $report; }
        if ($mode === 'DIAGNOSTICO') {
            $report['stage']='diagnosticar_preparacao';
            $report['release']=$release;
            $report['diagnostic']=webtuga_preparation_diagnostic($root,$client,$release,$settings);
            $report['success']=true; $report['stage']='diagnostico_concluido';
            return $report;
        }
        if ($preview['blocked']) { throw new RuntimeException('Existe uma divergencia nao aprovada.'); }
        $report['stage'] = 'validar_configuracao_privada';
        $private = $client->read('config/local.php');
        $privateMatches = hash_equals($settings['private_config_sha256'],hash('sha256',$private));
        unset($private);
        if (!$privateMatches) { throw new RuntimeException('Configuracao privada diferente da preparada.'); }
        $client->http('/config/bootstrap.php',403);
        $client->http('/',200); $client->http('/backoffice/login.php',200);
        if (!$preview['pending']) { $report['success'] = true; $report['stage'] = 'nenhuma_alteracao_necessaria'; return $report; }
        $report['release'] = $attempt;
        if ($notice) { $notice($attempt); }
        $report['stage'] = 'guardar_originais_e_preparar';
        if ($client->read(webtuga_release_record_path($attempt),true) !== null) { throw new RuntimeException('Referencia de publicacao ja existente.'); }
        $record = ['version'=>1,'domain'=>'gvi','release'=>$attempt,'commit'=>$commit,'previous_cursor'=>$preview['previous_cursor'] === null ? null : base64_encode($preview['previous_cursor']),'files'=>[]];
        foreach ($preview['pending'] as $index=>$entry) {
            $report['current_file']=$entry['path'];
            $old = $client->read($entry['path']);
            if (hash('sha256',$old) !== $entry['backup_sha256']) { throw new RuntimeException('Um original mudou antes da copia.'); }
            $new = file_get_contents($root.'/'.$entry['path']);
            if (hash('sha256',$new) !== $entry['git_sha256']) { throw new RuntimeException('Um candidato local mudou.'); }
            $backupPath = webtuga_release_slot($attempt,$index,'before');
            $client->putVerified($backupPath,$old,true);
            $client->putVerified(webtuga_release_slot($attempt,$index,'stage'),$new,true);
            $record['files'][] = ['path'=>$entry['path'],'before_sha256'=>$entry['backup_sha256'],'after_sha256'=>$entry['git_sha256'],'backup'=>$backupPath];
            unset($old,$new);
        }
        $report['preparation_transfers'] = $client->preparationTransfers();
        $newCursor = webtuga_release_json(['version'=>1,'initialized'=>true,'domain'=>'gvi','commit'=>$commit,'release'=>$attempt]);
        $record['published_cursor_sha256'] = hash('sha256',$newCursor);
        webtuga_release_record_validate($record,$attempt);
        $client->putVerified(webtuga_release_record_path($attempt),webtuga_release_json($record));
        unset($report['current_file']);
        $report['stage'] = 'ativar_ficheiros';
        // Todas as copias foram verificadas antes da primeira substituicao.
        $activationStarted = true;
        foreach ($record['files'] as $index=>$file) {
            if (hash('sha256',$client->read($file['path'])) !== $file['before_sha256']) { throw new RuntimeException('Um original mudou durante a publicacao.'); }
            $client->activate(webtuga_release_slot($attempt,$index,'stage'),$file['path'],$file['after_sha256']);
            $report['application_files_activated']++;
        }
        $report['stage'] = 'verificar_paginas';
        $client->http('/',200); $client->http('/backoffice/login.php',200);
        $report['stage'] = 'registar_versao';
        if ($client->read(webtuga_release_cursor_path(),true) !== $preview['previous_cursor']) { throw new RuntimeException('Outra versao foi registada durante a publicacao.'); }
        webtuga_release_cursor_write($client,$newCursor,$attempt);
        $report['success'] = true; $report['stage'] = 'publicacao_concluida';
        return $report;
    } catch (Throwable $error) {
        if ($client !== null) { $report['preparation_transfers']=$client->preparationTransfers(); }
        if ($error instanceof WebTugaPublicationOperationFailure) { $report['failure']=$error->diagnostic; }
        if ($activationStarted && $record !== null && $client !== null) {
            $report['rollback_attempted'] = true;
            try {
                $report['originals_restored'] = webtuga_release_restore($client,$record,bin2hex(random_bytes(16)));
                $client->http('/',200); $client->http('/backoffice/login.php',200);
                $report['rollback_completed'] = true;
            } catch (Throwable $ignored) { $report['rollback_completed'] = false; }
        }
        throw new WebTugaPublicationFailure($report);
    }
}

function webtuga_publication_summary($report) {
    $stages = ['preparar'=>'Preparacao','ler_copia_de_recuperacao'=>'Leitura da copia de recuperacao','repor_originais'=>'Reposicao dos originais',
        'reversao_concluida'=>'Reversao concluida','comparar_versoes'=>'Comparacao de versoes','previa_concluida'=>'Previa concluida',
        'validar_configuracao_privada'=>'Validacao da configuracao','nenhuma_alteracao_necessaria'=>'Nenhuma alteracao necessaria',
        'guardar_originais_e_preparar'=>'Copia dos originais e preparacao','ativar_ficheiros'=>'Ativacao dos ficheiros',
        'verificar_paginas'=>'Verificacao das paginas','registar_versao'=>'Registo da versao','publicacao_concluida'=>'Publicacao concluida',
        'diagnosticar_preparacao'=>'Consulta das copias preparadas','diagnostico_concluido'=>'Diagnostico em modo apenas de leitura concluido'];
    $statuses = ['preservado'=>'Conservar sem alteracoes','igual_ao_git'=>'Ja esta igual ao GitHub','atualizacao_prevista'=>'Atualizacao prevista','bloqueado_por_divergencia'=>'Diferenca nova: envio bloqueado'];
    $text = "### Publicacao manual GV Industries — " . $report['mode'] . "\n\n";
    $stage = $stages[$report['stage']] ?? $report['stage'];
    $text .= 'Resultado: **' . ($report['success'] ? $stage : 'Interrompida: '.$stage) . "**.\n\n";
    if (!empty($report['blocked'])) { $text .= "**A publicacao esta bloqueada por diferencas que precisam de revisao.**\n\n"; }
    if ($report['release']) { $text .= 'Referencia de recuperacao: `' . $report['release'] . "`.\n\n"; }
    if (isset($report['current_file'])) { $text .= 'Ficheiro em preparacao: `'.$report['current_file']."`.\n\n"; }
    if (isset($report['failure'])) {
        $failure=$report['failure'];
        $text .= 'Operacao interrompida: `'.$failure['operation'].'`; ficheiro: `'.$failure['path'].'`; codigo curl: `'.($failure['curl_code'] ?? 'nao disponivel').'`; motivo: `'.$failure['reason']."`. Nenhuma resposta bruta do servidor foi divulgada.\n\n";
    }
    if (!empty($report['preparation_transfers'])) {
        $results = ['retry_from_start'=>'Reenvio completo autorizado','verified_after_retry'=>'Reenvio conferido por SHA-256',
            'confirmed_after_error'=>'Copia completa confirmada por SHA-256','blocked_by_error'=>'Erro sem repeticao',
            'readback_failed'=>'Leitura de confirmacao falhou','blocked_by_content'=>'Conteudo diferente: bloqueado','retry_exhausted'=>'Segunda tentativa falhou: bloqueado'];
        $text .= "### Interrupcoes durante a preparacao\n\n| Copia protegida | Tentativa | Codigo curl | Bytes esperados | Bytes recebidos | Resultado |\n| --- | --- | --- | --- | --- | --- |\n";
        foreach ($report['preparation_transfers'] as $transfer) {
            $text .= '| `'.$transfer['path'].'` | '.$transfer['attempt'].' | '.($transfer['curl_code'] ?? 'nao disponivel').' | '.$transfer['expected_bytes'].' | '.($transfer['observed_bytes'] ?? 'ausente ou nao confirmado').' | '.$results[$transfer['result']]." |\n";
        }
        $text .= "\nApenas copias de preparacao podem ser reenviadas uma vez; cada copia tem de passar em SHA-256 antes da ativacao.\n\n";
    }
    if ($report['files']) {
        $text .= "| Ficheiro | Resultado da comparacao |\n| --- | --- |\n";
        foreach ($report['files'] as $file) { $text .= '| `'.$file['path'].'` | '.$statuses[$file['status']]." |\n"; }
    }
    if (isset($report['diagnostic'])) {
        $text .= "\n| Ficheiro | Copia original | Candidato preparado |\n| --- | --- | --- |\n";
        foreach($report['diagnostic']['copies'] as $row) { $text .= '| `'.$row['path'].'` | '.$row['before']['status'].' | '.$row['stage']['status']." |\n"; }
        $text .= "\nManifesto de recuperacao: **".$report['diagnostic']['recovery_record']."**. Diagnostico realizado sem uploads, exclusoes ou ativacoes.\n\n";
    }
    $text .= "\nFicheiros candidatos: " . $report['candidate_files'] . '. Ativados: ' . $report['application_files_activated'] . ".\n\n";
    if ($report['rollback_attempted']) { $text .= 'Reposicao dos originais: **' . ($report['rollback_completed'] ? 'confirmada' : 'nao confirmada; conferir a referencia de recuperacao') . "**.\n\n"; }
    $text .= "Configuracao privada preservada. Nenhuma restauracao ou importacao de banco de dados. Os testes HTTPS visitam apenas a pagina inicial e o login, sem autenticar nem enviar formularios.\n\n";
    $text .= "Escopo desta versao: os cinco PHP do primeiro alinhamento. A referencia gravada serve de base para o fluxo incremental das alteracoes seguintes.\n";
    return $text;
}
