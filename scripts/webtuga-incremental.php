<?php

require_once __DIR__ . '/webtuga-incremental-client.php';

class WebTugaIncrementalFailure extends RuntimeException
{
    public $report;
    public function __construct($report) { $this->report=$report; parent::__construct('Operacao incremental interrompida.'); }
}
function webtuga_inc_public_meta($meta) { return $meta===null ? null : ['sha256'=>$meta['sha256'],'size'=>$meta['size']]; }
function webtuga_inc_cursor($raw) {
    $cursor=webtuga_cursor_validate($raw);
    if ($cursor===null || !$cursor['initialized'] || (isset($cursor['domain']) && $cursor['domain']!=='gvi')) { throw new RuntimeException('Concluir o primeiro alinhamento GV Industries antes do envio incremental.'); }
    return $cursor;
}
function webtuga_inc_preview($git,$client,$head) {
    $raw=$client->readControl(webtuga_release_cursor_path()); $cursor=webtuga_inc_cursor($raw);
    $plan=webtuga_inc_git_plan($git,$cursor['commit'],$head);
    $plan['previous_cursor']=base64_encode($raw);
    $plan['cursor_sha256']=hash('sha256',$raw);
    $client->allow(array_column($plan['files'],'path'));
    foreach (webtuga_review_support_paths() as $path) {
        $expected=$git->file($cursor['commit'],$path);
        $actual=$client->readControl($path);
        if ($expected===null || hash('sha256',$actual)!==$expected['sha256'] || strlen($actual)!==$expected['size']) { $plan['blocked'][]=['path'=>$path,'reason'=>'configuration_support_changed']; }
        unset($actual);
    }
    if (!isset($client->names('config')['local.php'])) { $plan['blocked'][]=['path'=>'config/local.php','reason'=>'private_configuration_absent']; }
    foreach ($plan['files'] as &$file) {
        $actual=$client->snapshot($file['path'],true,min(536870912,max(2097152,($file['before']['size'] ?? 0)+1,($file['after']['size'] ?? 0)+1)));
        $file['observed']=webtuga_inc_public_meta($actual);
        $file['status']=webtuga_inc_meta_equal($actual,$file['after']) ? 'already_equal' : (webtuga_inc_meta_equal($actual,$file['before']) ? 'pending' : 'unexpected');
        if ($file['status']==='unexpected') { $plan['blocked'][]=['path'=>$file['path'],'reason'=>'unexpected_live_content']; }
    }
    unset($file);
    $plan['digest']=hash('sha256',webtuga_inc_json($plan));
    return $plan;
}
function webtuga_inc_validate_digest($plan,$digest) {
    if (!is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D',$digest) || !hash_equals($plan['digest'],$digest)) { throw new RuntimeException('A previa ou a versao mudou; executar PREVIA de novo e copiar o codigo.'); }
    if ($plan['blocked']) { throw new RuntimeException('A previa tem alteracoes bloqueadas.'); }
}
function webtuga_inc_candidate($root,$file,$assets,$pool) {
    if ($file['after']===null) { return null; }
    $expected=$file['after']; $source=$root.'/'.$file['path'];
    // Nao seguir links locais, mesmo que o blob Git tenha um modo regular.
    for($part=dirname($source);strlen($part)>=strlen($root);$part=dirname($part)) { if(is_link($part)) { throw new RuntimeException('Pasta local com link nao permitida.'); } if($part===$root || dirname($part)===$part) { break; } }
    if ($expected['lfs']) { $source=$assets.'/'.$expected['sha256'].'.bin'; }
    if (!is_file($source) || is_link($source)) { throw new RuntimeException('Material de publicacao ausente.'); }
    $copy=$pool->create();
    if (!copy($source,$copy)) { throw new RuntimeException('Nao foi possivel isolar o candidato.'); }
    $actual=$pool->metadata($copy);
    if (!webtuga_inc_meta_equal($actual,$expected)) { throw new RuntimeException('O candidato difere do commit aprovado; verificar LFS ou alteracoes locais.'); }
    if (strtolower(pathinfo($file['path'],PATHINFO_EXTENSION))==='php') {
        [$exit]=webtuga_inc_run([PHP_BINARY,'-n','-l',$copy],$root);
        if ($exit!==0) { throw new RuntimeException('Sintaxe PHP invalida num candidato.'); }
    }
    return $actual;
}
function webtuga_inc_new_cursor($record,$rawRecord) {
    return webtuga_release_json(['version'=>1,'initialized'=>true,'domain'=>'gvi','kind'=>'incremental','commit'=>$record['head'],'release'=>$record['release'],'record_sha256'=>hash('sha256',$rawRecord)]);
}
function webtuga_inc_write_cursor($client,$raw,$attempt,$pool) {
    webtuga_inc_cursor($raw);
    $stage=webtuga_inc_slot($attempt,0,'cursor');
    $client->putJson($stage,$raw);
    $client->move($stage,webtuga_release_cursor_path());
    if (!hash_equals(hash('sha256',$raw),hash('sha256',$client->readControl(webtuga_release_cursor_path())))) { throw new WebTugaPublicationOperationFailure('verify',webtuga_release_cursor_path(),null,'cursor_not_confirmed'); }
}
function webtuga_inc_record_validate($git,$record,$reference) {
    if (($record['version'] ?? null)!==2 || ($record['domain'] ?? '')!=='gvi' || ($record['kind'] ?? '')!=='incremental' || ($record['release'] ?? '')!==webtuga_release_id($reference) ||
        !is_array($record['files'] ?? null) || count($record['files'])>50 || array_keys($record['files'])!==(count($record['files']) ? range(0,count($record['files'])-1) : []) || !is_string($record['previous_cursor'] ?? null)) { throw new RuntimeException('Manifesto de recuperacao invalido.'); }
    webtuga_inc_revision($record['head'] ?? ''); webtuga_inc_revision($record['base'] ?? '');
    $git->command(['merge-base','--is-ancestor',$record['base'],$record['head']]);
    $git->command(['merge-base','--is-ancestor',$record['head'],$git->head()]);
    $previous=base64_decode($record['previous_cursor'],true);
    if ($previous===false || webtuga_inc_cursor($previous)['commit']!==$record['base']) { throw new RuntimeException('Base de recuperacao invalida.'); }
    $seen=[];
    foreach ($record['files'] as $index=>$file) {
        $path=$file['path'] ?? '';
        if (!is_int($index) || webtuga_inc_policy($path)!=='application' || isset($seen[$path]) || !in_array($file['operation'] ?? '',['add','update','delete'],true)) { throw new RuntimeException('Ficheiro de recuperacao fora da politica.'); }
        foreach (['before'=>$record['base'],'after'=>$record['head']] as $key=>$revision) {
            if (!array_key_exists($key,$file) || !webtuga_inc_meta_equal($file[$key],$git->file($revision,$path))) { throw new RuntimeException('Hash de recuperacao diferente do historico Git.'); }
        }
        $op=$file['before']===null ? 'add' : ($file['after']===null ? 'delete' : 'update');
        if ($file['operation']!==$op || ($file['before']===null && $file['after']===null) || ($file['backup'] ?? null)!==($file['before']===null ? null : webtuga_inc_slot($reference,$index,'before'))) { throw new RuntimeException('Operacao de recuperacao inconsistente.'); }
        $seen[$path]=true;
    }
    return $previous;
}
function webtuga_inc_restore($git,$client,$record,$rawRecord,$attempt,$pool,$notice=null) {
    $previous=webtuga_inc_record_validate($git,$record,$record['release'] ?? '');
    $client->allow(array_column($record['files'],'path'));
    $current=$client->readControl(webtuga_release_cursor_path());
    $expected=webtuga_inc_new_cursor($record,$rawRecord);
    if ($current!==$previous && $current!==$expected) { throw new RuntimeException('Outra versao foi registada; recuperacao bloqueada.'); }
    $restore=[];
    foreach ($record['files'] as $index=>$file) {
        $live=$client->snapshot($file['path'],true,min(536870912,max(2097152,($file['before']['size'] ?? 0)+1,($file['after']['size'] ?? 0)+1)));
        if (webtuga_inc_meta_equal($live,$file['before'])) { continue; }
        if (!webtuga_inc_meta_equal($live,$file['after'])) { throw new RuntimeException('O ficheiro mudou por fora; recuperacao bloqueada.'); }
        $backup=null;
        if ($file['before']!==null) {
            $backup=$client->snapshot($file['backup'],false,min(536870912,max(2097152,$file['before']['size']+1)));
            if (!webtuga_inc_meta_equal($backup,$file['before'])) { throw new RuntimeException('Copia anterior nao confirmada.'); }
        }
        $restore[]=['index'=>$index,'file'=>$file,'backup'=>$backup];
    }
    foreach (array_reverse($restore) as $item) {
        $file=$item['file']; $client->assertState($file['path'],$file['after']);
        if ($notice) { $notice('recuperar',$file['path'],$item['index']+1,count($record['files'])); }
        if ($file['before']===null) {
            $retained=webtuga_inc_slot($attempt,$item['index'],'undo');
            if ($client->present($retained)) { throw new RuntimeException('Destino de recuperacao ja existe.'); }
            $client->move($file['path'],$retained);
            $client->assertState($file['path'],null);
        } else {
            $stage=webtuga_inc_slot($attempt,$item['index'],'restore');
            $client->put($stage,$item['backup']); $client->ensureParents($file['path']);
            $client->assertState($file['path'],$file['after']);
            $client->move($stage,$file['path']); $client->assertState($file['path'],$file['before']);
        }
    }
    if ($current!==$previous) { webtuga_inc_write_cursor($client,$previous,$attempt,$pool); }
    return count($restore);
}
function webtuga_inc_publish($root,$git,$client,$pool,$head,$mode,$confirmation,$approved='',$reference='',$assets='',$identifier=null,$notice=null) {
    if (!in_array($mode,['PREVIA','PUBLICAR','REVERTER'],true) || ($mode==='PUBLICAR' && $confirmation!=='PUBLICAR GV') || ($mode==='REVERTER' && $confirmation!=='REVERTER GV')) { throw new RuntimeException('Modo ou confirmacao invalido.'); }
    if ($git->head()!==webtuga_inc_revision($head)) { throw new RuntimeException('O checkout difere da versao selecionada.'); }
    $attempt=webtuga_release_id($identifier ?? bin2hex(random_bytes(16)));
    $report=['success'=>false,'mode'=>$mode,'head'=>$head,'stage'=>'comparar','release'=>null,'files'=>[],'blocked'=>[],
        'application_files_changed'=>0,'rollback_attempted'=>false,'rollback_completed'=>false,'restored_files'=>0,'private_configuration_changed'=>false,'database_restores'=>0];
    $record=null; $rawRecord=null; $started=false;
    try {
        if ($mode==='REVERTER') {
            $report['stage']='ler_recuperacao';
            $cursor=webtuga_inc_cursor($client->readControl(webtuga_release_cursor_path()));
            if ($reference==='') { if(($cursor['kind'] ?? '')!=='incremental') { throw new RuntimeException('A ultima publicacao nao e incremental.'); } $reference=$cursor['release']; }
            webtuga_release_id($reference); $report['release']=$reference;
            $rawRecord=$client->readControl(webtuga_inc_record_path($reference));
            $record=json_decode($rawRecord,true,64,JSON_THROW_ON_ERROR);
            $client->http('/config/bootstrap.php',403);
            $report['stage']='recuperar'; $report['rollback_attempted']=true;
            $report['restored_files']=webtuga_inc_restore($git,$client,$record,$rawRecord,$attempt,$pool,$notice);
            $report['rollback_completed']=true;
            $client->http('/',200); $client->http('/backoffice/login.php',200);
            $report['success']=true; $report['stage']='recuperacao_concluida'; return $report;
        }
        $plan=webtuga_inc_preview($git,$client,$head);
        $report['base']=$plan['base']; $report['preview_code']=$plan['digest']; $report['files']=$plan['files']; $report['blocked']=$plan['blocked']; $report['ignored_count']=count($plan['ignored']);
        if ($mode==='PREVIA') { $report['success']=!$plan['blocked']; $report['stage']='previa_concluida'; return $report; }
        webtuga_inc_validate_digest($plan,$approved);
        if (!$plan['files']) { $report['success']=true; $report['stage']='sem_alteracoes'; return $report; }
        $report['stage']='validar';
        $settings=webtuga_publication_settings($root);
        $private=$client->readControl('config/local.php'); $matches=hash_equals($settings['private_config_sha256'],hash('sha256',$private)); unset($private);
        if (!$matches) { throw new RuntimeException('Configuracao privada diferente da preparada.'); }
        $client->http('/config/bootstrap.php',403); $client->http('/',200); $client->http('/backoffice/login.php',200);
        $pending=array_values(array_filter($plan['files'],static fn($file)=>$file['status']==='pending'));
        $candidates=[];
        foreach ($pending as $index=>$file) { $candidates[$index]=webtuga_inc_candidate($root,$file,$assets,$pool); }
        if ($client->readControl(webtuga_inc_record_path($attempt),true)!==null) { throw new RuntimeException('Referencia incremental ja utilizada.'); }
        $existingNames=$client->names('config');
        $report['release']=$attempt; $report['stage']='preparar_copias';
        if ($notice) { $notice('referencia',$attempt,0,count($pending)); }
        $record=['version'=>2,'domain'=>'gvi','kind'=>'incremental','release'=>$attempt,'base'=>$plan['base'],'head'=>$head,'previous_cursor'=>$plan['previous_cursor'],'files'=>[]];
        foreach ($pending as $index=>$file) {
            if ($notice) { $notice('preparar',$file['path'],$index+1,count($pending)); }
            $report['current_file']=$file['path'];
            $old=$client->assertState($file['path'],$file['before']);
            foreach (['before','stage','removed'] as $kind) { if (isset($existingNames[basename(webtuga_inc_slot($attempt,$index,$kind))])) { throw new RuntimeException('Uma posicao de preparacao ja existe.'); } }
            $backup=$old===null ? null : webtuga_inc_slot($attempt,$index,'before');
            if ($old!==null) { $client->put($backup,$old,true); }
            if ($candidates[$index]!==null) { $client->put(webtuga_inc_slot($attempt,$index,'stage'),$candidates[$index],true); }
            $record['files'][]=['path'=>$file['path'],'operation'=>$file['operation'],'before'=>webtuga_inc_public_meta($file['before']),'after'=>webtuga_inc_public_meta($file['after']),'backup'=>$backup];
        }
        unset($report['current_file']);
        webtuga_inc_record_validate($git,$record,$attempt);
        $rawRecord=webtuga_release_json($record); $client->putJson(webtuga_inc_record_path($attempt),$rawRecord);
        if ($client->readControl(webtuga_release_cursor_path())!==base64_decode($plan['previous_cursor'],true)) { throw new RuntimeException('A versao publicada mudou durante a preparacao.'); }
        $report['stage']='ativar'; $started=true;
        foreach ($record['files'] as $index=>$file) {
            if ($notice) { $notice('ativar',$file['path'],$index+1,count($record['files'])); }
            $client->assertState($file['path'],$file['before']);
            if ($file['after']===null) {
                $client->move($file['path'],webtuga_inc_slot($attempt,$index,'removed')); $client->assertState($file['path'],null);
            } else {
                $client->assertState(webtuga_inc_slot($attempt,$index,'stage'),$file['after']);
                if ($file['operation']==='add') { $client->ensureParents($file['path']); } $client->assertState($file['path'],$file['before']);
                $client->move(webtuga_inc_slot($attempt,$index,'stage'),$file['path']); $client->assertState($file['path'],$file['after']);
            }
            $report['application_files_changed']++;
        }
        $report['stage']='verificar_paginas'; $client->http('/',200); $client->http('/backoffice/login.php',200);
        if ($client->readControl(webtuga_release_cursor_path())!==base64_decode($plan['previous_cursor'],true)) { throw new RuntimeException('Outra publicacao alterou o registo.'); }
        $report['stage']='registar_versao'; webtuga_inc_write_cursor($client,webtuga_inc_new_cursor($record,$rawRecord),$attempt,$pool);
        $report['success']=true; $report['stage']='publicacao_concluida'; $report['transfer_events']=$client->events;
        return $report;
    } catch (Throwable $error) {
        if ($error instanceof WebTugaPublicationOperationFailure) { $report['failure']=$error->diagnostic; }
        else { $report['failure']=['reason'=>'validation_or_verification_failed','details_redacted'=>true]; }
        $report['transfer_events']=$client->events;
        if ($started && $record!==null && $rawRecord!==null && $mode==='PUBLICAR') {
            $report['rollback_attempted']=true;
            try {
                $report['restored_files']=webtuga_inc_restore($git,$client,$record,$rawRecord,bin2hex(random_bytes(16)),$pool,$notice);
                $report['rollback_completed']=true;
                $client->http('/',200); $client->http('/backoffice/login.php',200);
            } catch (Throwable $ignored) { $report['rollback_completed']=false; }
        }
        throw new WebTugaIncrementalFailure($report);
    }
}
