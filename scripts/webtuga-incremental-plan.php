<?php

require_once __DIR__ . '/webtuga-publicacao-inicial.php';

function webtuga_inc_revision($value) {
    if (!is_string($value) || !preg_match('/^[a-f0-9]{40}$/D',$value)) { throw new RuntimeException('Versao Git invalida.'); }
    return $value;
}
function webtuga_inc_path($path) {
    if (!is_string($path) || strlen($path)>512 || !preg_match('~^[\p{L}\p{N}_][\p{L}\p{N}_. /-]*$~uD',$path)) { throw new RuntimeException('Caminho de aplicacao invalido.'); }
    foreach (explode('/',$path) as $part) {
        if ($part==='' || $part==='.' || $part==='..' || $part[0]==='.' || trim($part)!==$part || strlen($part)>150) { throw new RuntimeException('Componente de caminho invalido.'); }
    }
    return $path;
}
function webtuga_inc_policy($path) {
    // Classificar os caminhos de ferramentas antes de os tratar como aplicacao.
    if (preg_match('~^(?:docs|scripts|tests|deploy|\.github|\.local-[^/]+|\.git|\.agents|\.codex)(?:/|$)~i',$path) ||
        in_array($path,['.gitattributes','.gitignore','README.md','LICENSE'],true)) { return 'ignored'; }
    $lower=strtolower($path);
    if (preg_match('~(?:^|/)\.~',$lower) || preg_match('~(?:^|/)(?:config|vendor|node_modules|logs?|cache|cache_pdf|tmp|uploads?)(?:/|$)~',$lower) ||
        preg_match('~^backoffice/(?:assets/downloads|assets/img/(?:categoria_[^/]*|subcategoria_[^/]*|descricao_[^/]*|produto_[^/]*|noticias|orcamentos|reparacoes)|marcas_(?:asp|lj|vent)|tabelas/(?:cache_pdf|tmp))(?:/|$)~',$lower) ||
        preg_match('~(?:^|/)(?:\.htaccess|\.env[^/]*|auth\.json|composer\.(?:json|lock)|package(?:-lock)?\.json|error_log|\.ftpquota)$~',$lower) ||
        preg_match('~\.(?:sql|sql\.gz|csv|log|tmp|bak|old|key|pem|zip|tar|gz|7z|rar)$~',$lower)) { return 'protected'; }
    webtuga_inc_path($path);
    if (str_contains($path,'/') && !in_array(explode('/',$path,2)[0],['assets','backoffice','includes'],true)) { return 'blocked'; }
    return in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),
        ['php','css','js','json','html','htm','svg','txt','xml','webmanifest','ico','png','jpg','jpeg','gif','webp','avif','woff','woff2','ttf','eot','pdf','mp4','webm','mov','mp3','ogg'],true) ? 'application' : 'blocked';
}
function webtuga_inc_json($value) { return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
function webtuga_inc_run($command,$cwd,$input='',$environment=null,$outputFile=null) {
    $descriptors=[['pipe','r'],$outputFile===null ? ['pipe','w'] : ['file',$outputFile,'wb'],['pipe','w']];
    $process=proc_open($command,$descriptors,$pipes,$cwd,$environment,['bypass_shell'=>true]);
    if (!is_resource($process)) { throw new RuntimeException('Nao foi possivel iniciar a verificacao local.'); }
    $position=0;
    while ($position<strlen($input)) {
        $written=fwrite($pipes[0],substr($input,$position));
        if ($written===false || $written===0) { fclose($pipes[0]); if(isset($pipes[1])) { fclose($pipes[1]); } fclose($pipes[2]); proc_terminate($process); proc_close($process); throw new RuntimeException('Entrada local interrompida.'); }
        $position+=$written;
    }
    fclose($pipes[0]);
    $output=$outputFile===null ? stream_get_contents($pipes[1]) : '';
    if (isset($pipes[1])) { fclose($pipes[1]); }
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($process),$output];
}
class WebTugaIncrementalGit
{
    private $root;
    private $binary;
    private $metadata=[];
    public function __construct($root) {
        $this->root=realpath($root);
        if ($this->root===false) { throw new RuntimeException('Repositorio local ausente.'); }
        $this->binary=PHP_OS_FAMILY==='Windows' && is_file('C:/Program Files/Git/cmd/git.exe') ? 'C:/Program Files/Git/cmd/git.exe' : 'git';
    }
    public function command($args,$input='',$environment=null,$outputFile=null) {
        [$exit,$output]=webtuga_inc_run(array_merge([$this->binary,'--no-pager'], $args),$this->root,$input,$environment,$outputFile);
        if ($exit!==0) { throw new RuntimeException('A verificacao Git nao foi concluida.'); }
        return $output;
    }
    public function head() { return webtuga_inc_revision(trim($this->command(['rev-parse','HEAD']))); }
    public function changes($base,$head) {
        webtuga_inc_revision($base); webtuga_inc_revision($head);
        $this->command(['merge-base','--is-ancestor',$base,$head]);
        $raw=$this->command(['diff','--no-ext-diff','--no-textconv','--no-renames','--ignore-submodules=none','--name-status','-z',$base,$head,'--']);
        if ($raw==='') { return []; }
        $parts=explode("\0",$raw); if (array_pop($parts)!=='' || count($parts)%2!==0 || count($parts)>40000) { throw new RuntimeException('Lista de alteracoes Git invalida.'); }
        $changes=[];
        for($index=0;$index<count($parts);$index+=2) {
            if (!in_array($parts[$index],['A','M','D','T'],true)) { throw new RuntimeException('Tipo de alteracao nao suportado.'); }
            $changes[]=['git_status'=>$parts[$index],'path'=>$parts[$index+1]];
        }
        return $changes;
    }
    public function file($revision,$path) {
        webtuga_inc_revision($revision); if ($path!=='config/.htaccess') { webtuga_inc_path($path); }
        $entry=$this->command(['ls-tree','-z',$revision,'--',$path]);
        if ($entry==='') { return null; }
        if (!preg_match('~^(100644|100755) blob ([a-f0-9]{40})\t([^\x00]+)\x00$~D',$entry,$match) || $match[3]!==$path) { throw new RuntimeException('Links, submodulos e arvores nao podem ser publicados.'); }
        $oid=$match[2];
        $key=$path.':'.$oid;
        if (isset($this->metadata[$key])) { return $this->metadata[$key]; }
        $length=trim($this->command(['cat-file','-s',$oid]));
        if (!ctype_digit($length) || (int)$length>536870912) { throw new RuntimeException('Ficheiro Git maior que o limite de 512 MiB.'); }
        // A saida do blob e calculada por fluxo; nao imprimir codigo nem dados.
        $process=proc_open([$this->binary,'cat-file','blob',$oid],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,$this->root,null,['bypass_shell'=>true]);
        if (!is_resource($process)) { throw new RuntimeException('Blob Git indisponivel.'); }
        fclose($pipes[0]); $hash=hash_init('sha256'); $bytes=0; $small=''; $failed=false;
        while (!feof($pipes[1])) {
            $chunk=fread($pipes[1],65536); if ($chunk===false) { $failed=true; break; }
            hash_update($hash,$chunk); $bytes+=strlen($chunk); if ($bytes<=1024) { $small.=$chunk; }
        }
        fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]); $exit=proc_close($process);
        if ($failed || $exit!==0 || $bytes!==(int)$length) { throw new RuntimeException('Blob Git incompleto.'); }
        $info=['sha256'=>hash_final($hash),'size'=>$bytes,'lfs'=>false,'blob'=>$oid];
        if (str_starts_with($small,'version https://git-lfs.github.com/spec/v1')) {
            if (!preg_match('~^version https://git-lfs\.github\.com/spec/v1\noid sha256:([a-f0-9]{64})\nsize ([0-9]+)\n$~D',$small,$pointer) || !in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['pdf','mp4','webm','mov','png','jpg','jpeg','gif','webp','avif','woff','woff2','ttf','eot','mp3','ogg'],true)) { throw new RuntimeException('Ponteiro LFS invalido para este ficheiro.'); }
            $info=['sha256'=>$pointer[1],'size'=>(int)$pointer[2],'lfs'=>true,'blob'=>$oid];
        }
        if ($info['size']>536870912 || (strtolower(pathinfo($path,PATHINFO_EXTENSION))==='php' && $info['size']>2097152)) { throw new RuntimeException('Ficheiro acima do limite permitido.'); }
        $this->metadata[$key]=$info;
        return $info;
    }
    public function blob($oid) {
        if (!preg_match('/^[a-f0-9]{40}$/D',$oid)) { throw new RuntimeException('Blob invalido.'); }
        return $this->command(['cat-file','blob',$oid]);
    }
}
function webtuga_inc_git_plan($git,$base,$head) {
    $files=[]; $ignored=[]; $blocked=[]; $total=0;
    foreach ($git->changes($base,$head) as $change) {
        $path=$change['path']; $policy=webtuga_inc_policy($path);
        if ($policy==='ignored') { $ignored[]=$path; continue; }
        if ($policy!=='application') { $blocked[]=['path'=>$path,'reason'=>$policy]; continue; }
        $before=$git->file($base,$path); $after=$git->file($head,$path);
        if ($before===null && $after===null) { throw new RuntimeException('Alteracao sem ficheiro Git.'); }
        if ($after===null && in_array($path,['index.php','backoffice/login.php'],true)) { $blocked[]=['path'=>$path,'reason'=>'essential_entrypoint']; continue; }
        $operation=$before===null ? 'add' : ($after===null ? 'delete' : 'update');
        $total+=($before['size'] ?? 0)+($after['size'] ?? 0);
        $files[]=['path'=>$path,'operation'=>$operation,'before'=>$before,'after'=>$after];
        if (count($files)>50 || $total>1073741824) { throw new RuntimeException('Publicacao excede 50 ficheiros ou 1 GiB de copias selecionadas.'); }
    }
    usort($files,static function($a,$b) {
        $rank=['add'=>0,'update'=>1,'delete'=>2];
        return ($rank[$a['operation']]<=>$rank[$b['operation']]) ?: strcmp($a['path'],$b['path']);
    });
    return ['version'=>1,'domain'=>'gvi','base'=>$base,'head'=>$head,'files'=>$files,'ignored'=>$ignored,'blocked'=>$blocked,'selected_bytes'=>$total];
}
function webtuga_inc_meta_equal($left,$right) {
    foreach ([$left,$right] as $meta) {
        if ($meta!==null && (!is_array($meta) || !is_int($meta['size'] ?? null) || $meta['size']<0 || $meta['size']>536870912 || !is_string($meta['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$meta['sha256']))) { throw new RuntimeException('Metadados de ficheiro invalidos.'); }
    }
    return $left===null || $right===null ? $left===$right : ($left['size']===$right['size'] && hash_equals($left['sha256'],$right['sha256']));
}
function webtuga_inc_record_path($id) { return 'config/gvi-update-'.webtuga_release_id($id).'.json'; }
function webtuga_inc_slot($id,$index,$kind) {
    if (!is_int($index) || $index<0 || $index>=50 || !in_array($kind,['before','stage','restore','removed','undo','cursor'],true)) { throw new RuntimeException('Posicao incremental invalida.'); }
    return 'config/gvi-update-'.webtuga_release_id($id).'-'.$index.'.'.$kind;
}
function webtuga_inc_protected($path) {
    return (bool)preg_match('~^config/gvi-update-[a-f0-9]{32}(?:\.json|-(?:[0-9]|[1-4][0-9])\.(?:before|stage|restore|removed|undo|cursor))$~D',$path);
}
