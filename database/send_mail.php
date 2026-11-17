<?php
// Explicit CLI delivery only. Configure KNS_MAIL_FROM and PHP's SMTP transport first.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/helpers/portal_support.php';
if(!in_array('--send',$argv,true)){echo "Usage: php database/send_mail.php --send\n";exit(1);}
$from=getenv('KNS_MAIL_FROM')?:'';
if(!filter_var($from,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Set KNS_MAIL_FROM and configure PHP SMTP before delivery.\n");exit(1);}
$db=kns_subject_db();$sent=0;
foreach(kns_portal_rows($db,'SELECT id FROM mail_outbox WHERE sent_at IS NULL AND attempts<5 ORDER BY id LIMIT 20') as $entry){
 $db->begin_transaction();
 try{
  $mail=kns_portal_one($db,'SELECT * FROM mail_outbox WHERE id=? AND sent_at IS NULL AND attempts<5 FOR UPDATE',[$entry['id']]);
  if(!$mail){$db->rollback();continue;}
  $packed=base64_decode($mail['encrypted_body'],true);
  $body=$packed===false?false:openssl_decrypt(substr($packed,28),'aes-256-gcm',kns_portal_key(),OPENSSL_RAW_DATA,substr($packed,0,12),substr($packed,12,16));
  if($body===false)throw new RuntimeException('Email could not be decrypted.');
  preg_match('/[?&]token=([a-f0-9]{64})/',$body,$match);
  $valid=isset($match[1])?kns_portal_one($db,'SELECT t.id,u.email FROM account_tokens t JOIN user u ON u.id=t.user_id WHERE t.user_id=? AND t.token_hash=? AND t.used_at IS NULL AND t.expires_at>NOW()',[$mail['user_id'],hash('sha256',$match[1])]):null;
  if(!$valid||strcasecmp((string)$valid['email'],$mail['recipient'])!==0){
   kns_portal_query($db,"UPDATE mail_outbox SET attempts=5,last_error='Account link expired, used, replaced, or email changed' WHERE id=?",[$mail['id']]);
   $db->commit();continue;
  }
  $ok=mail($mail['recipient'],$mail['subject'],$body,['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8']);
  kns_portal_query($db,'UPDATE mail_outbox SET attempts=attempts+1,sent_at=?,last_error=? WHERE id=?',[$ok?date('Y-m-d H:i:s'):null,$ok?null:'SMTP transport rejected delivery',$mail['id']]);
  $db->commit();if($ok)$sent++;
 }catch(Throwable $error){$db->rollback();fwrite(STDERR,"Delivery failed for queued message ID ".$entry['id'].".\n");}
}
echo $sent." queued message(s) accepted by the email transport.\n";
