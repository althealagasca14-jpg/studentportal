<?php
session_start();
require_once __DIR__.'/app/helpers/classroom_support.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try{
 $db=kns_subject_db();$user=kns_portal_user($db,['student','professor']);
 if(!kns_classroom_ready($db)){http_response_code(503);exit('Classroom setup is not available yet.');}
 $classId=filter_var($_GET['class']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$classId)throw new InvalidArgumentException('Choose one of your classes.');
 $class=kns_classroom_section($db,$user,$classId);$professor=$user['usertype']==='professor';
 $tab=in_array($_GET['tab']??'', ['updates','chat','classwork'],true)?$_GET['tab']:'updates';
 $workUrl=$professor?'professorhome.php?'.http_build_query(['class'=>$classId,'view'=>'classwork']):'subjectview.php?'.http_build_query(['id'=>$class['subject_id'],'enrollment'=>$class['enrollment_id']]);
 $privateUrl=$professor?'professorhome.php?'.http_build_query(['class'=>$classId,'view'=>'messages']):$workUrl.'&view=message';
 if($tab==='classwork'){header('Location: '.$workUrl,true,303);exit;}
 $questionOptions=kns_portal_rows($db,"SELECT id,title FROM subject_classwork WHERE subject_id=? AND section=? AND academic_year=? AND semester=? AND professor_id=? AND status='published' ORDER BY id DESC",[$class['subject_id'],$class['section'],$class['academic_year'],$class['semester'],$class['professor_id']]);
 $updates=$tab==='updates'?kns_portal_rows($db,'SELECT u.*,p.username FROM class_updates u JOIN user p ON p.id=u.author_id WHERE u.section_id=? ORDER BY u.id DESC LIMIT 100',[$classId]):[];
 $messages=$tab==='chat'?array_reverse(kns_portal_rows($db,'SELECT m.*,p.username,p.usertype FROM class_group_messages m JOIN user p ON p.id=m.sender_id WHERE m.section_id=? ORDER BY m.id DESC LIMIT 100',[$classId])):[];
}catch(InvalidArgumentException $error){http_response_code(403);exit(kns_subject_escape($error->getMessage()));}
catch(Throwable $error){error_log('[classroom] '.$error->getMessage());http_response_code(503);exit('The classroom is temporarily unavailable.');}
$csrf=kns_subject_csrf_token();$flash=$_SESSION['classroom_flash']??'';$error=$_SESSION['classroom_error']??'';unset($_SESSION['classroom_flash'],$_SESSION['classroom_error']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= kns_subject_escape($class['subject_name']) ?> | KNS Portal</title><link rel="stylesheet" href="assets/css/classroom.css"></head>
<body class="classroom-shell"><aside class="cr-sidebar"><a class="cr-brand" href="<?= $professor?'professorhome.php':'studenthome.php' ?>">KNS Portal<small><?= $professor?'Professor':'Student' ?> workspace</small></a><nav aria-label="Workspace"><a href="<?= $professor?'professorhome.php':'studenthome.php' ?>">Dashboard</a><a href="classes.php">My classes</a></nav><div class="cr-account"><strong><?= kns_subject_escape($user['username']) ?></strong><a href="logout.php">Sign out</a></div></aside>
<main class="cr-main"><a class="cr-text-link" href="classes.php">&larr; All classes</a><header class="cr-hero"><p class="cr-eyebrow">Your class space</p><h1><?= kns_subject_escape($class['subject_name']) ?></h1><p>Section <?= kns_subject_escape($class['section'].' · '.$class['academic_year'].' · '.$class['semester']) ?></p><p><?= kns_subject_escape($class['description']??'') ?></p></header>
<nav class="cr-tabs" aria-label="Subject navigation"><a href="?class=<?= $classId ?>&amp;tab=updates" <?= $tab==='updates'?'aria-current="page"':'' ?>>Updates</a><a href="<?= kns_subject_escape($workUrl) ?>">Classwork</a><a href="?class=<?= $classId ?>&amp;tab=chat" <?= $tab==='chat'?'aria-current="page"':'' ?>>Official group chat</a><a href="<?= kns_subject_escape($privateUrl) ?>">Private messages</a></nav>
<?php if($flash):?><p class="cr-notice" role="status"><?= kns_subject_escape($flash) ?></p><?php endif;?><?php if($error):?><p class="cr-notice cr-error" role="alert"><?= kns_subject_escape($error) ?></p><?php endif;?>
<div class="cr-columns"><section>
<?php if($tab==='updates'):?>
<?php if($professor):?><details class="cr-panel"><summary>Publish an update</summary><form method="post" action="classroom_action.php" class="cr-form"><input type="hidden" name="csrf_token" value="<?= kns_subject_escape($csrf) ?>"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="action" value="publish_update"><label>Title<input name="title" maxlength="180" required></label><label>Update<textarea name="body" maxlength="8000" rows="5" required></textarea></label><button class="cr-button" type="submit">Publish update</button></form></details><?php endif;?>
<h2>Class updates</h2><?php if(!$updates):?><p class="cr-empty">Updates from your professor will appear here.</p><?php endif;?>
<?php foreach($updates as $update):?><article class="cr-panel"><p class="cr-meta"><?= kns_subject_escape($update['username'].' · '.$update['created_at']) ?></p><h2><?= kns_subject_escape($update['title']) ?></h2><p class="cr-prose"><?= kns_subject_escape($update['body']) ?></p></article><?php endforeach;?>
<?php else:?>
<section class="cr-panel"><header class="cr-chat-header"><div><h2>Official class group chat</h2><p class="cr-meta">Messages here are visible to this class. Private messages stay separate.</p></div><a class="cr-text-link" href="?class=<?= $classId ?>&amp;tab=chat">Refresh</a></header><div class="cr-chat-history" aria-label="Latest 100 group messages">
<?php if(!$messages):?><p class="cr-empty">Start a class conversation.</p><?php endif;?>
<?php foreach($messages as $message):?><article class="cr-chat-message <?= (int)$message['sender_id']===(int)$user['id']?'is-mine':'' ?>"><div class="cr-meta"><strong><?= kns_subject_escape($message['username']) ?></strong><?= $message['usertype']==='professor'?' · Professor':'' ?><time><?= kns_subject_escape($message['created_at']) ?></time></div><p class="cr-prose"><?= kns_subject_escape($message['message']) ?></p></article><?php endforeach;?>
</div><form method="post" action="classroom_action.php" class="cr-form"><input type="hidden" name="csrf_token" value="<?= kns_subject_escape($csrf) ?>"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="action" value="send_group_message"><?php if(!$professor):?><label>Related activity (optional)<select name="classwork_id"><option value="0">General conversation</option><?php foreach($questionOptions as $option):?><option value="<?= (int)$option['id'] ?>"><?= kns_subject_escape($option['title']) ?></option><?php endforeach;?></select></label><label>Question topic<select name="question_topic"><?php foreach(kns_classroom_question_topics() as $key=>$label):?><option value="<?= $key ?>"><?= kns_subject_escape($label) ?></option><?php endforeach;?></select></label><?php endif;?><label>Message to this class<textarea name="message" maxlength="5000" rows="3" required></textarea></label><button class="cr-button" type="submit">Send to group</button></form></section>
<?php endif;?>
</section><aside>
<section class="cr-panel cr-about"><p class="cr-eyebrow">About this class</p><h2>Learn together.</h2><p>Professor: <?= kns_subject_escape($class['professor_username']) ?></p><p>Section <?= kns_subject_escape($class['section']) ?></p>
<?php if($professor):?><?php if($class['class_code']):?><p>Share this class code</p><code class="cr-code"><?= kns_subject_escape($class['class_code']) ?></code><?php else:?><form method="post" action="classroom_action.php"><input type="hidden" name="csrf_token" value="<?= kns_subject_escape($csrf) ?>"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="action" value="create_code"><button class="cr-button" type="submit">Create class code</button></form><?php endif;?><?php endif;?></section>
<section class="cr-panel cr-private"><h2>A private question?</h2><p>Contact your professor in the separate private conversation.</p><a class="cr-text-link" href="<?= kns_subject_escape($privateUrl) ?>">Open private messages &rarr;</a></section>
</aside></div></main></body></html>
