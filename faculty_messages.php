<?php
session_start();require_once __DIR__.'/app/helpers/professor_support.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try{
 $data=kns_subject_db();$professor=kns_portal_user($data,['professor']);
 $admins=kns_portal_rows($data,"SELECT id,username,first_name,last_name FROM user WHERE usertype='admin' AND account_status='active' ORDER BY username");
 $adminId=(int)($_GET['admin']??($admins[0]['id']??0));$selectedAdmin=null;
 foreach($admins as $candidate)if((int)$candidate['id']===$adminId)$selectedAdmin=$candidate;
 $history=$selectedAdmin?kns_portal_rows($data,'SELECT * FROM (SELECT * FROM admin_professor_messages WHERE admin_id=? AND professor_id=? ORDER BY id DESC LIMIT 100) m ORDER BY id',[$adminId,$professor['id']]):[];
 $allowed=kns_portal_permission($data,(int)$professor['id'],'admin_messaging');
}catch(Throwable $error){http_response_code(403);exit('Please sign in with an active professor account.');}
$professorView='admin-messages';$professorPageTitle='Admin messages';$professorPageDescription='Private conversations between your professor account and portal administrators.';
$professorShowPicker=false;$professorClasses=[];$professorSelectedClass=null;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin messages · KNS Portal</title><link rel="stylesheet" href="assets/css/professor-portal.css"><script src="assets/js/portal-forms.js" defer></script></head>
<?php require __DIR__.'/app/views/partials/professor_shell_start.php'; ?>
<section class="pf-panel"><div class="pf-panel-heading"><div><h2>Admin ↔ Professor only</h2><p>These conversations are separate from student subject messages.</p></div></div>
<nav class="pf-filters" aria-label="Administrators"><?php foreach($admins as $administrator):?><a class="pf-button" href="faculty_messages.php?admin=<?=(int)$administrator['id']?>" <?= $adminId===(int)$administrator['id']?'aria-current="page"':'' ?>><?=kns_subject_escape(kns_portal_name($administrator))?></a><?php endforeach;?></nav>
<?php if(!$selectedAdmin):?><p class="pf-empty">No administrator is available.</p><?php else:?>
<div class="pf-thread" aria-label="Admin conversation"><?php if(!$history):?><p class="pf-empty">No messages yet.</p><?php endif;?>
<?php foreach($history as $message):?><article class="pf-message <?=$message['sender_id']==$professor['id']?'is-mine':''?>"><div class="pf-message-meta"><strong><?=$message['sender_id']==$professor['id']?'You':'Admin'?></strong><time><?=kns_subject_escape($message['created_at'])?></time></div><p class="pf-message-text"><?=kns_subject_escape($message['message'])?></p><?php foreach(kns_portal_rows($data,'SELECT id,original_name FROM admin_message_attachments WHERE message_id=?',[$message['id']]) as $file):?><a class="pf-attachment" href="faculty_attachment.php?id=<?=(int)$file['id']?>"><?=kns_subject_escape($file['original_name'])?> · Download</a><?php endforeach;?></article><?php endforeach;?></div>
<?php if($allowed):?><form class="pf-reply-form" action="portal_action.php" method="post" enctype="multipart/form-data" data-portal-form data-reload="true"><input type="hidden" name="action" value="faculty_message"><input type="hidden" name="admin_id" value="<?=$adminId?>"><input type="hidden" name="csrf_token" value="<?=kns_subject_escape(kns_subject_csrf_token())?>"><label for="faculty-message">Message to administrator</label><textarea id="faculty-message" name="message" rows="3" maxlength="5000"></textarea><label>Attach file (maximum 5 MiB)<input type="file" name="files[]" accept=".pdf,.txt,.docx,.xlsx,.pptx"></label><p data-form-status role="status"></p><button class="pf-button pf-primary" type="submit">Send message</button></form><?php else:?><p class="pf-notice">Admin messaging is disabled for your account. Contact the school office.</p><?php endif;?>
<form action="portal_action.php" method="post" data-mark-read hidden><input type="hidden" name="action" value="faculty_read"><input type="hidden" name="admin_id" value="<?=$adminId?>"><input type="hidden" name="csrf_token" value="<?=kns_subject_escape(kns_subject_csrf_token())?>"></form>
<?php endif;?></section>
<?php require __DIR__.'/app/views/partials/professor_shell_end.php'; ?>
