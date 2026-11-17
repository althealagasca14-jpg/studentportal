<?php
// CLI only. Existing data is backed up outside the web root before a live migration.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/helpers/portal_support.php';
function kns_portal_migrate(mysqli $db): void {
    $sql=file_get_contents(__DIR__.'/migrations/003_portal_management.sql');
    $db->multi_query($sql);
    do {if($result=$db->store_result())$result->free();}while($db->more_results()&&$db->next_result());
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__)return;
if(!in_array('--apply',$argv,true)){echo "Usage: php database/migrate.php --apply\n";exit(1);}
try {
    $db=kns_subject_db();
    $name=$db->query('SELECT DATABASE()')->fetch_row()[0];
    if(!preg_match('/^kns_portal_test_[a-f0-9]{12}$/',$name)){
        $directory=dirname(kns_subject_storage_dir(true)).DIRECTORY_SEPARATOR.'database-backups';
        if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('Cannot create private backup directory.');
        $backup=$directory.DIRECTORY_SEPARATOR.$name.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
        $binary=getenv('KNS_MYSQLDUMP')?:'C:/xampp/mysql/bin/mysqldump.exe';
        $command=[$binary,'--single-transaction','--routines','--triggers','--host='.(getenv('KNS_SUBJECT_DB_HOST')?:'localhost'),'--user='.(getenv('KNS_SUBJECT_DB_USER')?:'root'),'--result-file='.$backup,'--databases',$name];
        $environment=getenv();$environment['MYSQL_PWD']=getenv('KNS_SUBJECT_DB_PASSWORD')?:'';
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);
        if(!is_resource($process))throw new RuntimeException('Backup process could not start.');
        fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);
        if(proc_close($process)!==0||!is_file($backup)||filesize($backup)<100)throw new RuntimeException('Backup failed. Migration was not started.');
        @chmod($backup,0600);echo "Backup created outside the public web directory.\n";
    }
    kns_portal_migrate($db);
    echo "Portal management migration applied. Existing records preserved.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");exit(1);}
