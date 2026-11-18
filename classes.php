<?php
session_start();
require_once __DIR__.'/app/helpers/classroom_support.php';
header('Cache-Control: private, no-store');
try{$db=kns_subject_db();$user=kns_portal_user($db,['student','professor']);$ready=kns_classroom_ready($db);$classes=$ready?kns_classroom_classes($db,$user):[];
$subjects=$user['usertype']==='professor'?kns_portal_rows($db,'SELECT s.id,s.subject_name FROM subjects s JOIN subject_professors p ON p.subject_id=s.id WHERE p.professor_id=? ORDER BY s.subject_name',[$user['id']]):[];
}catch(Throwable $error){http_response_code(403);exit('Sign in with an active student or professor account.');}
$csrf=kns_subject_csrf_token();$professor=$user['usertype']==='professor';
$flash=$_SESSION['classroom_flash']??'';$error=$_SESSION['classroom_error']??'';unset($_SESSION['classroom_flash'],$_SESSION['classroom_error']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My classes | KNS Portal</title><link rel="stylesheet" href="assets/css/classroom.css"></head>
<body class="classroom-shell"><aside class="cr-sidebar"><a class="cr-brand" href="<?= $professor?'professorhome.php':'studenthome.php' ?>">KNS Portal<small><?= $professor?'Professor':'Student' ?> workspace</small></a><nav aria-label="Workspace"><a href="<?= $professor?'professorhome.php':'studenthome.php' ?>">Dashboard</a><a href="classes.php" aria-current="page">My classes</a></nav><div class="cr-account"><strong><?= kns_subject_escape($user['username']) ?></strong><a href="logout.php">Sign out</a></div></aside>
<main class="cr-main"><header class="cr-heading"><p class="cr-eyebrow">Your learning space</p><h1><?= $professor?'Subjects & sections':'My classes' ?></h1><p><?= $professor?'Create a subject, organize its sections, and share the class code.':'Join your professor’s class and keep your coursework and conversations together.' ?></p></header>
<?php if($flash):?><p class="cr-notice" role="status"><?= kns_subject_escape($flash) ?></p><?php endif;?>
<?php if($error):?><p class="cr-notice cr-error" role="alert"><?= kns_subject_escape($error) ?></p><?php endif;?>
<?php if(!$ready):?><p class="cr-notice">Classroom setup is not available yet.</p><?php else:?>
<section class="cr-panel" aria-labelledby="cr-entry"><h2 id="cr-entry"><?= $professor?'Create a subject or section':'Join a class' ?></h2>
<form method="post" action="classroom_action.php" class="cr-form"><input type="hidden" name="csrf_token" value="<?= kns_subject_escape($csrf) ?>"><input type="hidden" name="action" value="<?= $professor?'create_class':'join_class' ?>">
<?php if($professor):?>
<label>Subject<select name="subject_id"><option value="0">Create a new subject</option><?php foreach($subjects as $subject):?><option value="<?= (int)$subject['id'] ?>"><?= kns_subject_escape($subject['subject_name']) ?></option><?php endforeach;?></select></label>
<div class="cr-grid-two"><label>New subject name<input name="subject_name" maxlength="100" placeholder="Required for a new subject"></label><label>New subject code (optional)<input name="subject_code" maxlength="30"></label></div><label>Description for a new subject<textarea name="description" maxlength="5000" rows="2"></textarea></label>
<div class="cr-grid-two"><label>Section<input name="section" maxlength="80" required></label><label>Academic year<input name="academic_year" maxlength="20" placeholder="2026-2027" required></label><label>Semester<input name="semester" maxlength="40" required></label><label>Capacity (optional)<input type="number" name="capacity" min="1" max="5000"></label></div><button class="cr-button" type="submit">Create subject / section</button>
<?php else:?><label>Class code<input name="class_code" maxlength="10" minlength="10" autocomplete="off" autocapitalize="characters" spellcheck="false" required placeholder="10-character code"></label><button class="cr-button" type="submit">Join class</button><?php endif;?></form></section>
<section aria-labelledby="cr-classes"><h2 id="cr-classes">Your classes</h2><?php if(!$classes):?><p class="cr-empty"><?= $professor?'Create your first class above.':'Your joined classes will appear here.' ?></p><?php endif;?>
<div class="cr-class-grid"><?php foreach($classes as $class):?><article class="cr-class-card"><div class="cr-class-cover"><span aria-hidden="true">&#10022;</span><small><?= kns_subject_escape($class['academic_year'].' · '.$class['semester']) ?></small></div><div class="cr-card-body"><h3><?= kns_subject_escape($class['subject_name']) ?></h3><p>Section <?= kns_subject_escape($class['section']) ?></p><?php if($professor):?><p>Class code: <strong><?= kns_subject_escape($class['class_code']??'Not created yet') ?></strong></p><?php endif;?><a class="cr-text-link" href="classroom.php?class=<?= (int)$class['id'] ?>">Open class &rarr;</a></div></article><?php endforeach;?></div></section>
<?php endif;?></main></body></html>
