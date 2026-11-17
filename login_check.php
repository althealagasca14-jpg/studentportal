<?php
session_start();
require_once __DIR__.'/app/helpers/portal_support.php';
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Location: login.php');exit;}
try{
 kns_portal_csrf();$db=kns_subject_db();
 $identifier=kns_portal_text($_POST['username']??'',191,'account identifier');
 $password=$_POST['password']??'';
 if(!is_string($password)||strlen($password)>1024)throw new InvalidArgumentException('Invalid credentials.');
 kns_portal_rate_limit($db,'login',$identifier);
 $rows=kns_portal_rows($db,'SELECT * FROM user WHERE username=? OR email=? OR faculty_id=? OR student_number=?',[$identifier,$identifier,$identifier,$identifier]);
 $user=count($rows)===1?$rows[0]:null;
 $valid=$user&&$user['account_status']==='active'&&($user['password_hash']?password_verify($password,$user['password_hash']):(is_string($user['password'])&&hash_equals($user['password'],$password)));
 if(!$valid)throw new InvalidArgumentException('Account identifier or password is incorrect, or the account is not active.');
 if(!in_array($user['usertype'],['student','professor','admin'],true))throw new InvalidArgumentException('Invalid account role.');
 // Upgrade an existing legacy password only after a successful login.
 $hash=$user['password_hash'];
 if(!$hash||password_needs_rehash($hash,PASSWORD_DEFAULT))$hash=password_hash($password,PASSWORD_DEFAULT);
 kns_portal_query($db,'UPDATE user SET password_hash=?,password=NULL,last_activity_at=NOW() WHERE id=?',[$hash,$user['id']]);
 kns_portal_query($db,"DELETE FROM auth_attempts WHERE action='login' AND identifier_hash=?",[hash_hmac('sha256',strtolower($identifier),kns_portal_key())]);
 session_regenerate_id(true);$_SESSION['username']=$user['username'];$_SESSION['usertype']=$user['usertype'];$_SESSION['portal_password_version']=$hash;
 header('Location: '.['student'=>'studenthome.php','professor'=>'professorhome.php','admin'=>'adminhome.php'][$user['usertype']],true,303);exit;
}catch(Throwable $error){
 error_log($error instanceof InvalidArgumentException?'[login] rejected':'[login] '.$error->getMessage());
 $_SESSION['loginMessage']=$error instanceof InvalidArgumentException?$error->getMessage():'Sign-in is temporarily unavailable.';
 header('Location: login.php',true,303);exit;
}
