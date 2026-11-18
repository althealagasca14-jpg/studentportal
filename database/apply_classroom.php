<?php
// Local CLI only. This runner never reads or runs migrations 001-003.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$options=getopt('', ['apply','backup:']);
$manifest=['004'=>'004_class_codes.sql','005'=>'005_class_communication.sql','006'=>'006_classwork_files.sql','007'=>'007_question_signals.sql'];
$locked=false;
try{
 $host=getenv('KNS_SUBJECT_DB_HOST')?:'localhost';$name=getenv('KNS_SUBJECT_DB_NAME')?:'althea_database';
 if(!in_array(strtolower($host),['localhost','127.0.0.1','::1'],true)||$name!=='althea_database')throw new RuntimeException('Only the existing local althea_database is permitted.');
 $db=new mysqli('127.0.0.1',getenv('KNS_SUBJECT_DB_USER')?:'root',getenv('KNS_SUBJECT_DB_PASSWORD')!==false?getenv('KNS_SUBJECT_DB_PASSWORD'):'','althea_database',3306);
 $server=$db->query('SELECT DATABASE() AS name,@@datadir AS directory')->fetch_assoc();
 if($server['name']!=='althea_database'||strtolower(rtrim(str_replace(chr(92),'/',$server['directory']),'/'))!=='c:/xampp/mysql/data')throw new RuntimeException('The local XAMPP data directory could not be verified.');
 $db->set_charset('utf8mb4');
 $recorded=[];$registry=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='classroom_migrations'")->num_rows>0;
 if($registry)foreach($db->query('SELECT version,checksum FROM classroom_migrations')->fetch_all(MYSQLI_ASSOC) as $row)$recorded[$row['version']]=$row['checksum'];
 $pending=[];
 foreach($manifest as $version=>$file){
  $path=__DIR__.'/migrations/'.$file;$hash=hash_file('sha256',$path);
  if(isset($recorded[$version])){if(!hash_equals($recorded[$version],$hash))throw new RuntimeException('An applied migration checksum changed: '.$version);continue;}
  $pending[$version]=[$path,$hash];
 }
 if(!$pending){echo "Migrations 004-007 are already recorded. No migration was rerun.\n";exit;}
 $backup=isset($options['backup'])?realpath($options['backup']):false;
 if(!$backup||!is_file($backup)||!is_readable($backup))throw new RuntimeException('Supply --backup with the verified full SQL backup outside the web folder and repository.');
 $normalized=strtolower(str_replace(chr(92),'/',$backup));
 $public=strtolower(str_replace(chr(92),'/',realpath(dirname(__DIR__,2))));
 $repository=strtolower(str_replace(chr(92),'/',realpath(dirname(__DIR__))));
 foreach([$public,$repository] as $forbidden)if($normalized===$forbidden||str_starts_with($normalized,$forbidden.'/'))throw new RuntimeException('Backup must be outside the public web folder and repository.');
 if(time()-filemtime($backup)>86400||filemtime($backup)>time()+300)throw new RuntimeException('A full backup from the last 24 hours is required.');
 $dump=file_get_contents($backup);
 if(!preg_match('/(?:-- Database:\\s*`althea_database`|-- Host:[^\\r\\n]*Database:\\s*althea_database)/i',$dump)||!preg_match('/(?:^COMMIT;|^-- Dump completed on)/mi',$dump)||!str_ends_with(rtrim($dump),';'))throw new RuntimeException('Backup database identity or completion marker is missing.');
 preg_match_all('/^CREATE TABLE\\s+(?:IF NOT EXISTS\\s+)?`([^`]+)`/mi',$dump,$created);
 $backupCounts=array_fill_keys($created[1],0);
 preg_match_all('/^INSERT INTO\\s+`([^`]+)`\\s*\\([^;]*?\\)\\s*VALUES\\s*/mi',$dump,$inserts,PREG_OFFSET_CAPTURE);
 foreach($inserts[0] as $index=>$match){
  $table=$inserts[1][$index][0];$offset=$match[1]+strlen($match[0]);$quote=null;$depth=0;$count=0;
  for($i=$offset,$len=strlen($dump);$i<$len;$i++){
   $ch=$dump[$i];
   if($quote!==null){if($ch===chr(92)){$i++;continue;}if($ch===$quote){if($i+1<$len&&$dump[$i+1]===$quote)$i++;else$quote=null;}continue;}
   if($ch==="'"||$ch==='"'||$ch===chr(96)){$quote=$ch;continue;}
   if($ch===';')break;if($ch==='('){if($depth===0)$count++;$depth++;}elseif($ch===')')$depth--;
  }
  $backupCounts[$table]=($backupCounts[$table]??0)+$count;
 }
 $tables=$db->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=\'BASE TABLE\'')->fetch_all(MYSQLI_ASSOC);
 foreach($tables as $table){
  $id=$table['TABLE_NAME'];if($table['ENGINE']!=='InnoDB')throw new RuntimeException('A table engine needs review: '.$id);
  if(!array_key_exists($id,$backupCounts))throw new RuntimeException('The current table is absent from the full backup: '.$id);
  $count=(int)$db->query('SELECT COUNT(*) FROM `'.str_replace('`','``',$id).'`')->fetch_row()[0];
  if($count!==$backupCounts[$id])throw new RuntimeException('The backup row coverage is out of date for table: '.$id);
 }
 $introduced=['004'=>['columns'=>[['subject_sections','class_code']],'tables'=>[]],'005'=>['columns'=>[],'tables'=>['class_updates','class_group_messages']],'006'=>['columns'=>[['subject_classwork','submission_mode'],['subject_classwork','file_points']],'tables'=>['classwork_attachments','classwork_submission_files']],'007'=>['columns'=>[],'tables'=>['class_question_signals']]];
 foreach($pending as $version=>$info){
  foreach($introduced[$version]['tables'] as $table)if($db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$table."'")->num_rows)throw new RuntimeException('Unrecorded existing structure needs review: '.$table);
  foreach($introduced[$version]['columns'] as [$table,$column])if($db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$table."' AND COLUMN_NAME='".$column."'")->num_rows)throw new RuntimeException('Unrecorded existing column needs review: '.$table.'.'.$column);
 }
 if(!isset($options['apply'])){echo "Read-only preflight passed. Local database, fresh full backup, table coverage and pending additive migrations verified. Add --apply to run only 004-007.\n";exit;}
 $locked=(int)$db->query("SELECT GET_LOCK('kns_classroom_migrations',5)")->fetch_row()[0]===1;
 if(!$locked)throw new RuntimeException('Another migration session is active.');
 $db->query('CREATE TABLE IF NOT EXISTS classroom_migrations(version CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
 foreach($pending as $version=>[$path,$hash]){
  $sql=preg_replace('/^--[^\\r\\n]*$/m','',file_get_contents($path));
  foreach(explode(';',$sql) as $statement){
   $statement=trim($statement);if($statement==='')continue;
   if(!preg_match('/\\A(?:CREATE TABLE|CREATE UNIQUE INDEX|ALTER TABLE [a-z_]+\\s+ADD COLUMN)\\b/i',$statement)||preg_match('/\\b(?:DROP|TRUNCATE|DELETE|REPLACE|RENAME)\\b/i',$statement))throw new RuntimeException('Only reviewed additive DDL is permitted.');
   $db->query($statement);
  }
  $insert=$db->prepare('INSERT INTO classroom_migrations(version,checksum) VALUES(?,?)');$version=(string)$version;$insert->bind_param('ss',$version,$hash);$insert->execute();
  echo 'Applied '.$version.". Existing records retained.\n";
 }
}catch(Throwable $error){file_put_contents('php://stderr',"Stopped: ".($error instanceof mysqli_sql_exception?'Database schema operation failed (code '.(int)$error->getCode().'). No destructive recovery was attempted.':$error->getMessage())."\n");exit(1);}
finally{if($locked&&isset($db))$db->query("SELECT RELEASE_LOCK('kns_classroom_migrations')");}
