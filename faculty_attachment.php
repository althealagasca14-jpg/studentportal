<?php
session_start();require_once __DIR__.'/app/helpers/portal_support.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try{
 if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){http_response_code(405);exit;}
 $db=kns_subject_db();$user=kns_portal_user($db,['admin','professor']);
 $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $file=kns_portal_one($db,'SELECT a.*,m.admin_id,m.professor_id FROM admin_message_attachments a JOIN admin_professor_messages m ON m.id=a.message_id WHERE a.id=?',[$id?:0]);
 if(!$file||($user['usertype']==='admin'?$file['admin_id']!=$user['id']:$file['professor_id']!=$user['id'])){http_response_code(404);exit('Attachment not found.');}
 if(!preg_match('/^[a-f0-9]{64}\.bin$/',$file['storage_name']))throw new RuntimeException('Invalid storage name.');
 $directory=kns_subject_storage_dir();$path=realpath($directory.DIRECTORY_SEPARATOR.$file['storage_name']);
 if(!$path||strncasecmp($path,$directory.DIRECTORY_SEPARATOR,strlen($directory)+1)!==0||!is_file($path)||filesize($path)!=(int)$file['byte_size']){http_response_code(404);exit('Attachment not found.');}
 header('Content-Type: application/octet-stream');
 header('Content-Length: '.$file['byte_size']);
 header("Content-Disposition: attachment; filename=\"attachment\"; filename*=UTF-8''".rawurlencode($file['original_name']));
 header("Content-Security-Policy: default-src 'none'; sandbox");
 session_write_close();if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($path);
}catch(Throwable $error){if(http_response_code()<400)http_response_code($error instanceof InvalidArgumentException?403:503);exit('Attachment unavailable.');}
