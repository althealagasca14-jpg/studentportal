<?php
session_start();
require_once __DIR__.'/app/helpers/portal_support.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$error='';$success=false;$valid=false;$purpose='reset';
$token=$_POST['token']??$_GET['token']??'';
try{
 $db=kns_subject_db();
 if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('This account link is invalid or expired.');
 $hash=hash('sha256',$token);
 $record=kns_portal_one($db,'SELECT t.*,u.account_status FROM account_tokens t JOIN user u ON u.id=t.user_id WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW()',[$hash]);
 if(!$record||$record['account_status']==='suspended')throw new InvalidArgumentException('This account link is invalid or expired.');
 $purpose=$record['purpose'];$valid=true;
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  kns_portal_csrf();
  if(($_POST['password']??'')!==($_POST['confirm_password']??''))throw new InvalidArgumentException('Passwords do not match.');
  $password=kns_portal_password($_POST['password']??'');
  $db->begin_transaction();
  try{
   $current=kns_portal_one($db,'SELECT t.*,u.account_status FROM account_tokens t JOIN user u ON u.id=t.user_id WHERE t.id=? AND token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE',[$record['id'],$hash]);
   if(!$current||$current['account_status']==='suspended')throw new InvalidArgumentException('This account link has already been used or expired.');
   kns_portal_query($db,"UPDATE user SET password_hash=?,password=NULL,password_changed_at=NOW(),account_status='active' WHERE id=?",[$password,$record['user_id']]);
   kns_portal_query($db,'UPDATE account_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$record['user_id']]);
   kns_portal_notify($db,(int)$record['user_id'],'security','Account password set','Your portal password was updated.');
   $db->commit();$success=true;$valid=false;
  }catch(Throwable $failure){$db->rollback();throw $failure;}
 }
}catch(Throwable $failure){$error=$failure instanceof InvalidArgumentException?$failure->getMessage():'Account access is temporarily unavailable.';if(!$valid)http_response_code(400);}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account access · KNS Portal</title><link rel="stylesheet" href="assets/css/admin-portal.css"></head><body class="admin-shell"><main style="max-width:520px;margin:8vh auto;padding:20px"><article class="panel settings-panel"><p class="eyebrow">KNS Portal</p><h1><?= $purpose==='activation'?'Activate your account':'Reset password' ?></h1>
<?php if($error):?><p class="sample-notice" role="alert"><?=kns_subject_escape($error)?></p><?php endif;?>
<?php if($success):?><p class="sample-notice">Your password was saved. You can now sign in.</p><?php endif;?>
<?php if($valid):?><form method="post"><input type="hidden" name="csrf_token" value="<?=kns_subject_escape(kns_subject_csrf_token())?>"><input type="hidden" name="token" value="<?=kns_subject_escape($token)?>"><div class="form-grid" style="margin-top:20px"><label class="span-two">New password (12–72 bytes)<input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required autofocus></label><label class="span-two">Confirm password<input name="confirm_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label></div><div class="settings-footer"><button class="button primary" type="submit">Save password</button></div></form><?php endif;?>
<p style="margin-top:20px"><a class="text-link" href="login.php">Back to sign in →</a></p></article></main></body></html>
