<?php
session_start();
require_once __DIR__.'/app/classwork/classwork_files.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
try{
 $db=kns_subject_db();$user=kns_portal_user($db,['student','professor']);
 $kind=is_string($_GET['type']??null)?$_GET['type']:'work';$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $file=$id?kns_classwork_file_access($db,$user,$kind,$id):null;
 if(!$file||!preg_match('/\\A[a-f0-9]{64}\\.bin\\z/',$file['storage_name']))throw new InvalidArgumentException('Attachment not found.');
 $directory=kns_classwork_storage();$path=realpath($directory.DIRECTORY_SEPARATOR.$file['storage_name']);
 if(!$path||strncasecmp($path,$directory.DIRECTORY_SEPARATOR,strlen($directory)+1)!==0||!is_file($path)||!is_readable($path)||filesize($path)!==(int)$file['byte_size'])throw new InvalidArgumentException('Attachment not found.');
 $type=kns_subject_file_types()[strtolower(pathinfo($file['original_name'],PATHINFO_EXTENSION))]??null;
 if(!$type||$type['mime']!==$file['mime_type']||$type['kind']!==$file['attachment_kind'])throw new InvalidArgumentException('Attachment not found.');
 $stream=fopen($path,'rb');if(!$stream)throw new RuntimeException('Cannot open attachment.');
 $download=($_GET['download']??'')==='1'||$file['attachment_kind']!=='photo';
 $fallback=preg_replace('/[^A-Za-z0-9._-]/','_',$file['original_name']);
 header('Content-Type: '.$file['mime_type']);header('Content-Length: '.$file['byte_size']);
 header('Content-Disposition: '.($download?'attachment':'inline').'; filename="'.$fallback.'"; filename*=UTF-8'.chr(39).chr(39).rawurlencode($file['original_name']));
 header("Content-Security-Policy: default-src 'none'; sandbox; frame-ancestors 'none'");header('X-Frame-Options: DENY');
 session_write_close();if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')fpassthru($stream);fclose($stream);
}catch(InvalidArgumentException $error){http_response_code(404);exit('Attachment not found.');}
catch(Throwable $error){error_log('[classwork-attachment] '.$error->getMessage());http_response_code(503);exit('Attachment unavailable.');}
