<?php

require_once __DIR__ . '/webtuga-incremental-plan.php';

class WebTugaIncrementalFiles
{
    public $root;
    private $files=[];
    public function __construct() {
        $this->root=rtrim(sys_get_temp_dir(),'/\\').'/gvi-incremental-'.bin2hex(random_bytes(16));
        if (!mkdir($this->root,0700)) { throw new RuntimeException('Pasta temporaria indisponivel.'); }
    }
    public function create($data=null) {
        $path=tempnam($this->root,'part-');
        if ($path===false) { throw new RuntimeException('Temporario indisponivel.'); }
        $this->files[]=$path; @chmod($path,0600);
        if ($data!==null && file_put_contents($path,$data,LOCK_EX)!==strlen($data)) { throw new RuntimeException('Temporario incompleto.'); }
        return $path;
    }
    public function metadata($file) {
        if (!in_array($file,$this->files,true) || !is_file($file) || is_link($file)) { throw new RuntimeException('Temporario fora da pasta privada.'); }
        clearstatcache(true,$file);
        return ['file'=>$file,'size'=>filesize($file),'sha256'=>hash_file('sha256',$file)];
    }
    public function close() {
        foreach ($this->files as $path) { if (is_file($path)) { unlink($path); } }
        $this->files=[];
        if (is_dir($this->root)) { rmdir($this->root); }
    }
}
class WebTugaIncrementalClient
{
    private $credentials;
    private $runner;
    private $files;
    private $paths=[];
    private $directories=[''=>true,'config'=>true];
    public $events=[];
    public function __construct($password,$files,$runner=null) {
        $this->files=$files;
        $this->credentials=webtuga_connection_credentials($password,'gvi');
        $this->runner=$runner ?? 'webtuga_connection_run';
        // A consulta inicial usa o mesmo perfil do teste de ligacao e do publicador anterior.
        // As operacoes de ficheiros exigem TLS 1.2 ou superior.
        $rootRunner=function($command,$credentials) {
            try { [$exit,$listing]=($this->runner)($command,$credentials); }
            catch (Throwable $error) { throw new WebTugaPublicationOperationFailure('list','/',null,'transport_exception'); }
            if ($exit!==0 || !is_string($listing)) { throw new WebTugaPublicationOperationFailure('list','/',$exit,'root_listing_failed'); }
            return [$exit,$listing];
        };
        webtuga_connection_check($password,$rootRunner,'gvi');
    }
    public function allow($paths) {
        foreach ($paths as $path) {
            if (webtuga_inc_policy($path)!=='application') { throw new RuntimeException('Ficheiro fora da aplicacao GV Industries.'); }
            $this->paths[$path]=true;
            for($directory=dirname($path);$directory!=='.';$directory=dirname($directory)) { $this->directories[$directory]=true; }
        }
    }
    private function control($path) {
        return in_array($path,array_merge(webtuga_review_support_paths(),['config/local.php',webtuga_release_cursor_path()]),true) || webtuga_inc_protected($path);
    }
    public function command($operation,$path,$argument=null) {
        $command=['curl','--disable','--config','-','--silent','--show-error','--ssl-reqd','--tlsv1.2','--proto','=ftp','--ftp-method','nocwd','--connect-timeout','15','--max-time','30'];
        $base='ftp://' . webtuga_connection_target('gvi')['server'] . ':21/';
        $encoded=implode('/',array_map('rawurlencode',explode('/',$path)));
        if ($operation==='list' && isset($this->directories[rtrim($path,'/')])) { return array_merge($command,['--list-only','--url',$base.$encoded]); }
        if ($operation==='get' && ($this->control($path) || isset($this->paths[$path]))) {
            if ($argument!==null && (!is_array($argument) || !isset($argument['file'],$argument['limit']))) { throw new RuntimeException('Destino temporario invalido.'); }
            if ($argument!==null) {
                $this->files->metadata($argument['file']); if($path==='config/local.php') { throw new RuntimeException('A configuracao privada so pode ser lida em memoria.'); }
                if (!is_int($argument['limit']) || $argument['limit']<1 || $argument['limit']>536870912) { throw new RuntimeException('Limite de leitura invalido.'); }
                if ($argument['limit']>2097152) { $command[array_search('--max-time',$command,true)+1]='300'; }
            }
            return array_merge($command,['--max-filesize',(string)($argument['limit'] ?? 1048576)],$argument===null ? [] : ['--output',$argument['file']],['--url',$base.$encoded]);
        }
        if ($operation==='put' && webtuga_inc_protected($path) && is_string($argument) && is_file($argument)) {
            if ($this->files->metadata($argument)['size']>2097152) { $command[array_search('--max-time',$command,true)+1]='300'; }
            return array_merge($command,['--upload-file',$argument,'--url',$base.$encoded]);
        }
        if ($operation==='mkdir' && isset($this->directories[$path]) && $path!=='' && $path!=='config') { return array_merge($command,['--quote','MKD '.$path,'--list-only','--url',$base]); }
        if ($operation==='move' && is_string($argument)) {
            $from=$argument;
            $activate=isset($this->paths[$path]) && preg_match('~^config/gvi-update-[a-f0-9]{32}-(?:[0-9]|[1-4][0-9])\.(?:stage|restore)$~D',$from);
            $retain=isset($this->paths[$from]) && preg_match('~^config/gvi-update-[a-f0-9]{32}-(?:[0-9]|[1-4][0-9])\.(?:removed|undo)$~D',$path);
            $cursor=$path===webtuga_release_cursor_path() && preg_match('~^config/gvi-update-[a-f0-9]{32}-0\.cursor$~D',$from);
            if ($activate || $retain || $cursor) { return array_merge($command,['--quote','RNFR '.$from,'--quote','RNTO '.$path,'--list-only','--url',$base]); }
        }
        throw new RuntimeException('Operacao incremental fora dos destinos autorizados.');
    }
    private function run($operation,$path,$argument=null) {
        // Erros de validacao devem parar antes de chamar o transporte.
        $command=$this->command($operation,$path,$argument);
        try { return ($this->runner)($command,$this->credentials); }
        catch (Throwable $error) { throw new WebTugaPublicationOperationFailure($operation,$path,null,'transport_exception'); }
    }
    public function names($directory) {
        $directory=rtrim($directory,'/');
        [$exit,$raw]=$this->run('list',$directory==='' ? '' : $directory.'/');
        if ($exit!==0) { throw new WebTugaPublicationOperationFailure('list',$directory.'/',$exit); }
        $names=[];
        foreach (preg_split('/\r?\n/',$raw) as $line) { $name=basename(rtrim(trim($line),'/')); if($name!=='' && $name!=='.' && $name!=='..') { $names[$name]=true; } }
        return $names;
    }
    public function present($path) {
        if (!$this->control($path) && !isset($this->paths[$path])) { throw new RuntimeException('Presenca fora da selecao.'); }
        $parts=explode('/',$path); $directory='';
        foreach ($parts as $part) {
            if (!isset($this->names($directory)[$part])) { return false; }
            $directory=$directory==='' ? $part : $directory.'/'.$part;
        }
        return true;
    }
    public function readControl($path,$optional=false) {
        if (!$this->control($path)) { throw new RuntimeException('Leitura de controlo fora da selecao.'); }
        [$exit,$data]=$this->run('get',$path);
        if ($optional && in_array($exit,[9,78],true) && !$this->present($path)) { return null; }
        if ($exit!==0 || !is_string($data)) { throw new WebTugaPublicationOperationFailure('get',$path,$exit); }
        return $data;
    }
    public function snapshot($path,$optional=false,$maximum=2097152) {
        $file=$this->files->create();
        [$exit]=$this->run('get',$path,['file'=>$file,'limit'=>$maximum]);
        if ($optional && in_array($exit,[9,78],true) && !$this->present($path)) { return null; }
        if ($exit!==0) { throw new WebTugaPublicationOperationFailure('get',$path,$exit); }
        return $this->files->metadata($file);
    }
    public function assertState($path,$expected) {
        $actual=$this->snapshot($path,true,min(536870912,max(2097152,($expected['size'] ?? 0)+1)));
        if (!webtuga_inc_meta_equal($actual,$expected)) { throw new WebTugaPublicationOperationFailure('verify',$path,null,'unexpected_live_content'); }
        return $actual;
    }
    public function ensureParents($path) {
        $parts=explode('/',$path); array_pop($parts); $parent='';
        foreach ($parts as $part) {
            $next=$parent==='' ? $part : $parent.'/'.$part;
            if (!isset($this->names($parent)[$part])) {
                [$exit]=$this->run('mkdir',$next);
                if ($exit!==0 && !isset($this->names($parent)[$part])) { throw new WebTugaPublicationOperationFailure('mkdir',$next,$exit); }
            }
            $this->names($next); // Confirma que e uma pasta legivel, nao um ficheiro.
            $parent=$next;
        }
    }
    private function prefix($source,$partial) {
        $left=fopen($source,'rb'); $right=fopen($partial,'rb');
        if ($left===false || $right===false) { if(is_resource($left)){fclose($left);} if(is_resource($right)){fclose($right);} throw new RuntimeException('Comparacao temporaria indisponivel.'); }
        try {
            while (!feof($right)) { $data=fread($right,65536); if($data===false) { return false; } if($data==='') { break; } if(fread($left,strlen($data))!==$data) { return false; } }
            return true;
        } finally { fclose($left); fclose($right); }
    }
    public function put($path,$source,$retry=false) {
        if (!webtuga_inc_protected($path) || !is_array($source) || !isset($source['file'],$source['size'],$source['sha256'])) { throw new RuntimeException('Copia de preparacao invalida.'); }
        $local=$this->files->metadata($source['file']);
        if (!webtuga_inc_meta_equal($local,$source)) { throw new RuntimeException('A copia local mudou.'); }
        if ($retry && !preg_match('~\.(?:before|stage)$~D',$path)) { throw new RuntimeException('Repeticao fora da preparacao.'); }
        for($attempt=1;$attempt<=($retry ? 2 : 1);$attempt++) {
            [$exit]=$this->run('put',$path,$source['file']);
            if ($exit!==0 && (!$retry || !in_array($exit,[6,7,18,28,55,56],true))) { throw new WebTugaPublicationOperationFailure('put',$path,$exit); }
            $received=$this->snapshot($path,$exit!==0,min(536870912,max(2097152,$source['size']+1)));
            if (webtuga_inc_meta_equal($received,$source)) {
                if ($exit!==0 || $attempt>1) { $this->events[]=['path'=>$path,'attempt'=>$attempt,'curl_code'=>$exit,'result'=>'copy_verified']; }
                return;
            }
            if ($exit===0) { throw new WebTugaPublicationOperationFailure('verify',$path,null,'sha256_mismatch'); }
            $partial=$received===null || ($received['size']<$source['size'] && $this->prefix($source['file'],$received['file']));
            $this->events[]=['path'=>$path,'attempt'=>$attempt,'curl_code'=>$exit,'expected_bytes'=>$source['size'],'observed_bytes'=>$received['size'] ?? null,'result'=>$partial && $attempt===1 ? 'retry_from_start' : 'blocked'];
            if (!$partial || $attempt===2) { throw new WebTugaPublicationOperationFailure('put',$path,$exit,'preparation_not_confirmed'); }
        }
    }
    public function putJson($path,$data) {
        $this->put($path,$this->files->metadata($this->files->create($data)));
    }
    public function move($from,$to) {
        [$exit]=$this->run('move',$to,$from);
        if ($exit!==0) { throw new WebTugaPublicationOperationFailure('move',$to,$exit); }
    }
    public function http($path,$expectedStatus) {
        [$exit,$output]=($this->runner)(webtuga_release_http_command($path),'');
        if ($exit!==0 || !preg_match('~\nGVI_HTTP:(\d{3}):(https://[^\r\n]+)\r?\n?$~',$output,$match)) { throw new RuntimeException('Verificacao HTTPS incompleta.'); }
        $url=parse_url($match[2]);
        if ((int)$match[1]!==$expectedStatus || !in_array($url['host'] ?? '',['gv-industries.co.uk','www.gv-industries.co.uk'],true) || ($url['path'] ?? '/')!==$path || isset($url['query']) ||
            ($expectedStatus===200 && preg_match('/(?:Fatal error|Parse error|Uncaught\s+\w*Exception|<\?php)/i',substr($output,0,-strlen($match[0]))))) { throw new RuntimeException('A pagina nao passou na verificacao HTTPS.'); }
    }
}
