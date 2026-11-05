<?php

require_once __DIR__ . '/webtuga-incremental.php';

function webtuga_inc_assets_directory($head,$temporary) {
    webtuga_inc_revision($head);
    $parent=realpath($temporary);
    if ($parent===false || !is_dir($parent)) { throw new RuntimeException('Pasta do runner indisponivel.'); }
    $directory=$parent.'/gvi-incremental-assets-'.$head;
    if (is_link($directory)) { throw new RuntimeException('Pasta de materiais nao pode ser um link.'); }
    if (!is_dir($directory) && !mkdir($directory,0700)) { throw new RuntimeException('Pasta de materiais indisponivel.'); }
    return $directory;
}
function webtuga_inc_materials($root,$git,$plan,$assets,$token=null,$resolver=null) {
    if (($plan['head'] ?? null)!==$git->head() || ($plan['domain'] ?? null)!=='gvi' || ($plan['version'] ?? null)!==1) { throw new RuntimeException('Plano local diferente do checkout.'); }
    $digest=$plan['digest'] ?? ''; $unsigned=$plan; unset($unsigned['digest']);
    if (!is_string($digest) || !hash_equals(hash('sha256',webtuga_inc_json($unsigned)),$digest) || $plan['blocked']) { throw new RuntimeException('Plano local nao confirmado.'); }
    $downloaded=0; $bytes=0;
    foreach ($plan['files'] as $file) {
        $meta=$file['after'];
        if ($file['status']!=='pending' || $meta===null || !$meta['lfs']) { continue; }
        if (webtuga_inc_policy($file['path'])!=='application' || !webtuga_inc_meta_equal($meta,$git->file($plan['head'],$file['path']))) { throw new RuntimeException('Objeto LFS diferente do commit aprovado.'); }
        $target=$assets.'/'.$meta['sha256'].'.bin';
        if (is_link($target)) { throw new RuntimeException('Material LFS com link nao permitido.'); }
        if (is_file($target)) {
            if (filesize($target)===$meta['size'] && hash_file('sha256',$target)===$meta['sha256']) { continue; }
            throw new RuntimeException('Material LFS local inesperado.');
        }
        $partial=tempnam($assets,'fetch-');
        if ($partial===false) { throw new RuntimeException('Temporario LFS indisponivel.'); }
        @chmod($partial,0600);
        try {
            $local=$root.'/'.$file['path'];
            if (is_file($local) && !is_link($local) && filesize($local)===$meta['size'] && hash_file('sha256',$local)===$meta['sha256']) {
                if (!copy($local,$partial)) { throw new RuntimeException('Nao foi possivel isolar o objeto LFS.'); }
            } else {
                if (!is_string($token) || $token==='' || strlen($token)>4096 || preg_match('/[\x00-\x20\x7f]/',$token)) { throw new RuntimeException('Token de leitura LFS indisponivel.'); }
                $environment=getenv(); unset($environment['GIT_LFS_SKIP_SMUDGE']);
                $environment['GIT_TERMINAL_PROMPT']='0'; $environment['GCM_INTERACTIVE']='Never';
                $environment['GIT_CONFIG_COUNT']='2';
                $environment['GIT_CONFIG_KEY_0']='http.https://github.com/.extraheader';
                $environment['GIT_CONFIG_VALUE_0']='AUTHORIZATION: basic '.base64_encode('x-access-token:'.$token);
                unset($environment['WEBTUGA_FTP_PASSWORD'],$environment['WEBTUGA_FTP_USERNAME'],$environment['WEBTUGA_FTP_HOST'],$environment['GH_LFS_TOKEN']);
                $environment['GIT_CONFIG_KEY_1']='credential.helper'; $environment['GIT_CONFIG_VALUE_1']='';
                $endpoint='https://github.com/globoVac/gv_industries.git/info/lfs';
                $args=['-c','lfs.url='.$endpoint,'-c','remote.origin.lfsurl='.$endpoint,'-c','lfs.storage='.$assets.'/.lfs-cache',
                    '-c','lfs.basictransfersonly=true','-c','lfs.fetchinclude=','-c','lfs.fetchexclude=','-c','lfs.skipdownloaderrors=false','-c','http.sslVerify=true','-c','credential.helper=',
                    'lfs','smudge',$file['path']];
                $pointer=$git->blob($meta['blob']);
                if ($resolver) { $resolver($git,$args,$pointer,$environment,$partial); }
                else { $git->command($args,$pointer,$environment,$partial); }
                unset($environment,$pointer);
                $downloaded++;
            }
            clearstatcache(true,$partial);
            if (filesize($partial)!==$meta['size'] || hash_file('sha256',$partial)!==$meta['sha256']) { throw new RuntimeException('O objeto LFS recebido nao passou na verificacao.'); }
            if (!rename($partial,$target)) { throw new RuntimeException('Nao foi possivel guardar o material LFS.'); }
            $bytes+=$meta['size'];
        } finally { if(is_file($partial)) { unlink($partial); } }
    }
    unset($token);
    return ['lfs_objects_downloaded'=>$downloaded,'lfs_bytes_prepared'=>$bytes];
}
