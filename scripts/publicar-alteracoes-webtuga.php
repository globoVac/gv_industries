<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
ini_set('display_errors','0'); ini_set('log_errors','0'); ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/webtuga-incremental-assets.php';

function webtuga_inc_display($path) { return htmlspecialchars(str_replace(["\r","\n",'`'],['','',''],$path),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function webtuga_inc_cli_failure($error,$mode,$stage) {
    if ($error instanceof WebTugaIncrementalFailure) { return $error->report; }
    $failure=['reason'=>'validation_not_confirmed','details_redacted'=>true];
    if ($error instanceof WebTugaPublicationOperationFailure) { $failure=$error->diagnostic; $failure['details_redacted']=true; }
    elseif ($error->getMessage()==='A previa ou a versao mudou; executar PREVIA de novo e copiar o codigo.') { $failure['reason']='preview_not_confirmed'; }
    elseif ($error->getMessage()==='A previa tem alteracoes bloqueadas.') { $failure['reason']='preview_blocked'; }
    // Somente localizacao do nosso codigo; nunca mensagem, stack trace ou resposta bruta.
    $source=realpath($error->getFile());
    foreach (['publicar-alteracoes-webtuga.php','webtuga-incremental.php','webtuga-incremental-plan.php','webtuga-incremental-client.php','webtuga-incremental-assets.php','webtuga-publicacao-inicial.php','conferir-webtuga.php','testar-conexao-webtuga.php'] as $name) {
        if ($source!==false && $source===realpath(__DIR__.'/'.$name)) { $failure['source']='scripts/'.$name; $failure['line']=$error->getLine(); break; }
    }
    return ['success'=>false,'mode'=>$mode,'stage'=>$stage,'application_files_changed'=>0,
        'private_configuration_changed'=>false,'database_restores'=>0,'failure'=>$failure];
}
function webtuga_inc_summary($report) {
    $stages=['previa_concluida'=>'Previa concluida','sem_alteracoes'=>'Nenhuma alteracao de aplicacao a publicar',
        'publicacao_concluida'=>'Publicacao concluida','recuperacao_concluida'=>'Recuperacao concluida'];
    $text="### Alteracoes GV Industries — ".($report['mode'] ?? 'VALIDACAO')."\n\nResultado: **".($stages[$report['stage']] ?? 'Operacao interrompida')."**.\n\n";
    if (isset($report['base'])) { $text.='Base publicada: `'.$report['base'].'`. Versao selecionada: `'.$report['head']."`.\n\n"; }
    if (!empty($report['release'])) { $text.='Referencia de recuperacao: `'.$report['release']."`.\n\n"; }
    if (!empty($report['files'])) {
        $operations=['add'=>'Novo','update'=>'Atualizar','delete'=>'Retirar do site'];
        $statuses=['already_equal'=>'Ja corresponde ao Git','pending'=>'Alteracao prevista','unexpected'=>'Diferenca inesperada: bloqueada'];
        $text.="| Ficheiro | Acao | Conferencia WebTuga | Bytes novos |\n| --- | --- | --- | --- |\n";
        foreach ($report['files'] as $file) { $text.='| `'.webtuga_inc_display($file['path']).'` | '.$operations[$file['operation']].' | '.$statuses[$file['status']].' | '.($file['after']['size'] ?? 0)." |\n"; }
        $text.="\n";
    } else { $text.="Nenhum ficheiro de aplicacao selecionado.\n\n"; }
    if (!empty($report['blocked'])) {
        $text.="**Publicacao bloqueada.** Rever estes caminhos antes de continuar:\n\n";
        foreach ($report['blocked'] as $blocked) { $text.='- `'.webtuga_inc_display($blocked['path']).'`: `'.$blocked['reason']."`.\n"; }
        $text.="\n";
    }
    if (($report['mode'] ?? '')==='PREVIA' && empty($report['blocked']) && !empty($report['preview_code'])) {
        $text.="Codigo desta previa (copiar para codigo_previa em PUBLICAR):\n\n```text\n".$report['preview_code']."\n```\n\n";
    }
    if (!empty($report['current_file'])) { $text.='Ficheiro em preparacao: `'.webtuga_inc_display($report['current_file'])."`.\n\n"; }
    if (!empty($report['failure'])) {
        $failure=$report['failure'];
        $text.='Etapa interrompida: `'.webtuga_inc_display($report['stage']).'`. Falha: `'.($failure['reason'] ?? 'verification_failed').'`';
        if(isset($failure['operation'])) { $text.='; operacao `'.$failure['operation'].'`, caminho `'.webtuga_inc_display($failure['path']).'`, curl `'.($failure['curl_code'] ?? 'nao disponivel').'`'; }
        $text.=". Respostas brutas e dados privados omitidos.\n\n";
        if (isset($failure['source'],$failure['line'])) { $text.='Localizacao no publicador: `'.$failure['source'].':'.$failure['line']."`.\n\n"; }
    }
    $text.='Ficheiros da aplicacao alterados: **'.($report['application_files_changed'] ?? 0)."**.\n\n";
    if (!empty($report['rollback_attempted'])) { $text.='Recuperacao: **'.(!empty($report['rollback_completed']) ? 'confirmada' : 'nao confirmada; usar a referencia e conferir o site').'**. Ficheiros recuperados: '.($report['restored_files'] ?? 0).".\n\n"; }
    $text.="Configuracoes privadas e uploads geridos pelo backoffice preservados. Sem importacoes ou restauracoes de banco. \n\n";
    $text.="Transferencias FTPS com TLS 1.2 ou superior e certificado validado. Os testes HTTPS visitam a pagina inicial e o login, sem autenticar nem enviar formularios.\n\n";
    $text.="As trocas sao sequenciais; o sucesso dos testes de transporte nao substitui o teste das funcoes da aplicacao. As copias ficam na pasta protegida config.\n";
    return $text;
}

function webtuga_inc_cli() {
    $pool=null; $summary=getenv('GITHUB_STEP_SUMMARY'); $stage='validar_versao';
    try {
        $options=getopt('',['fase:']); $phase=$options['fase'] ?? '';
        $root=dirname(__DIR__); $git=new WebTugaIncrementalGit($root); $head=webtuga_inc_revision(getenv('GITHUB_SHA'));
        if (getenv('GITHUB_REF')!=='refs/heads/main' || $git->head()!==$head) { throw new RuntimeException('Versao ou branch nao aprovada.'); }
        $stage='validar_formulario';
        $mode=getenv('WEBTUGA_INCREMENTAL_MODE'); $confirmation=getenv('WEBTUGA_INCREMENTAL_CONFIRMATION');
        $approved=getenv('WEBTUGA_INCREMENTAL_PREVIEW') ?: ''; $reference=getenv('WEBTUGA_INCREMENTAL_REFERENCE') ?: '';
        if (!in_array($mode,['PREVIA','PUBLICAR','REVERTER'],true) || ($mode==='PUBLICAR' && $confirmation!=='PUBLICAR GV') || ($mode==='REVERTER' && $confirmation!=='REVERTER GV')) { throw new RuntimeException('Modo ou confirmacao invalido.'); }
        if ($reference!=='') { webtuga_release_id($reference); }
        $stage='validar_temporarios';
        $temporary=realpath(getenv('RUNNER_TEMP') ?: sys_get_temp_dir());
        if ($temporary===false) { throw new RuntimeException('Pasta temporaria ausente.'); }
        $planFile=$temporary.'/gvi-incremental-plan.json';
        if ($phase==='materiais') {
            $stage='verificar_materiais';
            if ($mode!=='PUBLICAR') { throw new RuntimeException('Materiais so sao preparados para PUBLICAR.'); }
            $plan=json_decode(file_get_contents($planFile),true,64,JSON_THROW_ON_ERROR);
            $assets=webtuga_inc_assets_directory($head,$temporary);
            echo webtuga_inc_json(webtuga_inc_materials($root,$git,$plan,$assets,getenv('GH_LFS_TOKEN'))),PHP_EOL;
            return 0;
        }
        if ($phase==='preparar' && $mode==='REVERTER') { echo "Recuperacao manual selecionada; nenhuma alteracao iniciada.\n"; return 0; }
        if (!in_array($phase,['preparar','executar'],true)) { throw new RuntimeException('Fase invalida.'); }
        $stage='confirmar_ligacao_ftps';
        $pool=new WebTugaIncrementalFiles(); $client=new WebTugaIncrementalClient(getenv('WEBTUGA_FTP_PASSWORD'),$pool);
        if ($phase==='preparar' && $mode==='PUBLICAR') {
            $stage='reconferir_previa'; $plan=webtuga_inc_preview($git,$client,$head);
            $stage='validar_codigo_previa'; webtuga_inc_validate_digest($plan,$approved);
            $stage='guardar_plano_local';
            if (is_link($planFile) || file_put_contents($planFile,webtuga_inc_json($plan),LOCK_EX)===false) { throw new RuntimeException('Nao foi possivel guardar o plano local.'); }
            @chmod($planFile,0600);
            echo 'Previa e versao confirmadas; '.count($plan['files'])." ficheiros selecionados para verificacao.\n";
            return 0;
        }
        if (($phase==='preparar' && $mode!=='PREVIA') || ($phase==='executar' && $mode==='PREVIA')) { throw new RuntimeException('Fase diferente da operacao selecionada.'); }
        $stage='preparar_operacao';
        $assets=$mode==='PUBLICAR' ? webtuga_inc_assets_directory($head,$temporary) : '';
        $notice=function($stage,$path,$index,$total) use($summary) {
            if ($stage==='referencia') {
                echo 'Referencia de recuperacao: '.$path.PHP_EOL;
                if ($summary) { file_put_contents($summary,'Referencia desta tentativa: `'.$path."`.\n\n",FILE_APPEND); }
            } else { echo $stage.' '.$index.'/'.$total.': '.$path.PHP_EOL; }
        };
        $report=webtuga_inc_publish($root,$git,$client,$pool,$head,$mode,$confirmation,$approved,$reference,$assets,null,$notice);
        echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),PHP_EOL;
        if ($summary) { file_put_contents($summary,webtuga_inc_summary($report),FILE_APPEND); }
        return $report['success'] ? 0 : 1;
    } catch (Throwable $error) {
        $report=webtuga_inc_cli_failure($error,getenv('WEBTUGA_INCREMENTAL_MODE'),$stage);
        echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
        if ($summary) { file_put_contents($summary,webtuga_inc_summary($report),FILE_APPEND); }
        return 1;
    } finally { if ($pool!==null) { $pool->close(); } }
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===realpath(__FILE__)) { exit(webtuga_inc_cli()); }
