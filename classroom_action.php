<?php
session_start();
require_once __DIR__.'/app/helpers/classroom_support.php';
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');http_response_code(405);exit('Use a classroom form.');}
$classId=0;$tab='updates';
try{
    kns_portal_csrf();
    if((int)($_SERVER['CONTENT_LENGTH']??0)>65536)throw new InvalidArgumentException('This classroom form is too large.');
    $db=kns_subject_db();$user=kns_portal_user($db,['student','professor']);
    if(!kns_classroom_ready($db))throw new InvalidArgumentException('Classroom setup is not available yet.');
    $action=is_string($_POST['action']??null)?$_POST['action']:'';
    kns_portal_rate_limit($db,'classroom_'.$action,(string)$user['id'],30);
    $db->begin_transaction();
    try{
        if($action==='create_class')$classId=kns_classroom_create($db,$user,$_POST);
        elseif($action==='join_class')$classId=kns_classroom_join($db,$user,$_POST['class_code']??'');
        elseif($action==='create_code'){
            $classId=(int)($_POST['class_id']??0);
            $class=kns_classroom_section($db,$user,$classId,true);
            if($user['usertype']!=='professor')throw new InvalidArgumentException('Only the professor can create a class code.');
            if(!$class['class_code'])kns_portal_query($db,'UPDATE subject_sections SET class_code=? WHERE id=? AND class_code IS NULL',[kns_classroom_code(),$classId]);
        }elseif($action==='publish_update'||$action==='send_group_message'){
            $classId=(int)($_POST['class_id']??0);
            if($action==='publish_update')kns_classroom_post_update($db,$user,$classId,$_POST);
            else{kns_classroom_post_group($db,$user,$classId,$_POST);$tab='chat';}
        }else throw new InvalidArgumentException('Unknown classroom action.');
        $db->commit();
    }catch(Throwable $error){$db->rollback();throw $error;}
    $_SESSION['classroom_flash']='Saved. Your class is ready.';
}catch(Throwable $error){
    if($error instanceof mysqli_sql_exception&&$error->getCode()===1062)$message='That subject code or section already exists. Choose another code or section.';
    elseif($error instanceof InvalidArgumentException)$message=$error->getMessage();
    else{error_log('[classroom-action] '.$error->getMessage());$message='The change could not be saved. Please try again.';}
    $_SESSION['classroom_error']=$message;
}
header('Location: '.($classId?'classroom.php?'.http_build_query(['class'=>$classId,'tab'=>$tab]):'classes.php'),true,303);
