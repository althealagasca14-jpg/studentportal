<?php
session_start();
require_once __DIR__ . '/app/helpers/admin_support.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST') {header('Allow: POST');http_response_code(405);throw new InvalidArgumentException('Use the portal form.');}
    kns_portal_csrf();
    if((int)($_SERVER['CONTENT_LENGTH']??0)>KNS_SUBJECT_MAX_POST_BYTES) {http_response_code(413);throw new InvalidArgumentException('Request is too large.');}
    $db=kns_subject_db();$action=$_POST['action']??'';$message='Saved.';
    if($action==='register') {
        $number=kns_portal_text($_POST['student_number']??'',10,'student number');
        if(!preg_match('/^\d{4}-\d{5}$/',$number))throw new InvalidArgumentException('Use student number YYYY-12345.');
        kns_portal_rate_limit($db,'register',$number,5);
        $name=kns_portal_text($_POST['full_name']??'',120,'full name');
        $names=preg_split('/\s+/',$name,2);$first=$names[0];$last=$names[1]??'';
        if(mb_strlen($first)>60||mb_strlen($last)>60)throw new InvalidArgumentException('Use up to 60 characters for each name.');
        $email=kns_portal_email($_POST['email']??'');$hash=kns_portal_password($_POST['password']??'');
        $course=(int)($_POST['course_id']??0);
        if($course&&!kns_portal_one($db,'SELECT id FROM courses WHERE id=?',[$course]))throw new InvalidArgumentException('Choose a valid course.');
        kns_portal_query($db,"INSERT INTO user(username,usertype,student_number,first_name,last_name,email,password_hash,course_id,created_at,password_changed_at) VALUES(?,'student',?,?,?,?,?,?,NOW(),NOW())",[$number,$number,$first,$last,$email,$hash,$course?:null]);
        $id=(int)$db->insert_id;
        kns_portal_notify($db,$id,'security','Account created','Your portal account is ready. Class access requires enrollment confirmed by the school.');
        $message='Student account created. You can sign in using your student number. Class access requires verified enrollment.';
    } elseif($action==='professor_register') {
        require_once __DIR__ . '/app/helpers/registration_support.php';
        $faculty=kns_portal_text($_POST['faculty_id']??'',50,'faculty ID');
        kns_portal_rate_limit($db,'professor_register',$faculty,5);
        $db->begin_transaction();
        try { kns_register_professor($db,$_POST); $db->commit(); }
        catch(Throwable $error) { $db->rollback(); throw $error; }
        $message='Professor account created. You can now sign in with your faculty ID.';
    } elseif($action==='recover') {
        $identifier=kns_portal_text($_POST['identifier']??'',191,'account identifier');
        kns_portal_rate_limit($db,'recover',$identifier,5);
        $rows=kns_portal_rows($db,"SELECT * FROM user WHERE (username=? OR email=? OR faculty_id=? OR student_number=?) AND account_status<>'suspended'",[$identifier,$identifier,$identifier,$identifier]);
        if(count($rows)===1&&!empty($rows[0]['email'])) {
            $db->begin_transaction();
            try {kns_portal_token($db,$rows[0],$rows[0]['account_status']==='pending'?'activation':'reset');$db->commit();}
            catch(Throwable $error){$db->rollback();throw $error;}
        }
        $message='If the account has a registered email, an account link has been queued. Delivery depends on the school email service. Contact the school office if it does not arrive.';
    } elseif($action==='password') {
        $user=kns_portal_user($db);
        kns_portal_rate_limit($db,'password',(string)$user['id'],8);
        $current=$_POST['current_password']??'';
        $valid=$user['password_hash']?password_verify($current,$user['password_hash']):(is_string($user['password'])&&hash_equals($user['password'],$current));
        if(!$valid)throw new InvalidArgumentException('Current password is incorrect.');
        if(($_POST['new_password']??'')!==($_POST['confirm_password']??''))throw new InvalidArgumentException('New passwords do not match.');
        $hash=kns_portal_password($_POST['new_password']??'');
        kns_portal_query($db,'UPDATE user SET password_hash=?,password=NULL,password_changed_at=NOW() WHERE id=?',[$hash,$user['id']]);
        kns_portal_query($db,'UPDATE account_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$user['id']]);
        session_regenerate_id(true);
        kns_portal_notify($db,(int)$user['id'],'security','Password changed','Your portal password was changed.');
        $_SESSION['portal_password_version']=$hash;
        $message='Password changed.';
    } elseif($action==='notifications_read') {
        $user=kns_portal_user($db);
        kns_portal_query($db,'UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL',[$user['id']]);
        $message='Notifications marked read.';
    } elseif($action==='faculty_message') {
        $user=kns_portal_user($db,['professor']);
        http_response_code(410);
        throw new InvalidArgumentException('Admin messaging is archived. Existing messages are preserved.');
    } elseif($action==='faculty_read') {
        $user=kns_portal_user($db,['professor']);
        kns_portal_query($db,'UPDATE admin_professor_messages SET read_at=COALESCE(read_at,NOW()) WHERE professor_id=? AND admin_id=? AND sender_id=admin_id',[$user['id'],(int)($_POST['admin_id']??0)]);
    } elseif($action==='grade') {
        require_once __DIR__.'/app/classwork/classwork_files.php';
        $user=kns_portal_user($db,['professor']);
        if(!kns_portal_permission($db,(int)$user['id'],'record_grades')) {http_response_code(403);throw new InvalidArgumentException('Grading permission is required.');}
        $submission=kns_portal_one($db,'SELECT sub.id,w.questions_json,w.submission_mode,w.file_points FROM subject_classwork_submissions sub JOIN subject_classwork w ON w.id=sub.classwork_id JOIN subject_sections ss ON ss.subject_id=w.subject_id AND ss.section=w.section AND ss.academic_year=w.academic_year AND ss.semester=w.semester AND ss.professor_id=w.professor_id WHERE sub.id=? AND w.professor_id=?',[(int)($_POST['submission_id']??0),$user['id']]);
        if(!$submission) {http_response_code(403);throw new InvalidArgumentException('Submission is not assigned to you.');}
        $max=kns_classwork_max_score($submission,json_decode($submission['questions_json'],true,64,JSON_THROW_ON_ERROR));
        $score=$_POST['score']??null;
        if(!is_scalar($score)||!is_numeric($score)||!is_finite((float)$score)||(float)$score<0||(float)$score>$max||round((float)$score,2)!=(float)$score)throw new InvalidArgumentException('Enter a score from 0 to '.$max.' with at most two decimal places.');
        $feedback=kns_portal_text($_POST['feedback']??'',5000,'feedback',false);
        kns_portal_query($db,'INSERT INTO classwork_grades(submission_id,graded_by,score,max_score,feedback) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE graded_by=VALUES(graded_by),score=VALUES(score),max_score=VALUES(max_score),feedback=VALUES(feedback)',[$submission['id'],$user['id'],$score,$max,$feedback]);
        $message='Grade and feedback saved.';
    } elseif($action==='contact') {
        $category=$_POST['category']??'';
        if(!in_array($category,['contact','admission'],true))throw new InvalidArgumentException('Choose Contact or Admission.');
        $email=kns_portal_email($_POST['email']??'');kns_portal_rate_limit($db,'contact',$email,5);
        kns_portal_query($db,'INSERT INTO portal_requests(category,full_name,email,message) VALUES(?,?,?,?)',[$category,kns_portal_text($_POST['full_name']??'',120,'full name'),$email,kns_portal_text($_POST['message']??'',5000,'message')]);
        $message='Your request was saved for school staff to review.';
    } else {http_response_code(404);throw new InvalidArgumentException('Action not found.');}
    echo json_encode(['ok'=>true,'message'=>$message]);
}catch(Throwable $error){
    if($error instanceof mysqli_sql_exception&&$error->getCode()===1062){http_response_code(409);$message=($action??'')==='professor_register'?'That faculty ID, account identifier or email is already registered.':'That student number or email is already registered.';}
    elseif($error instanceof InvalidArgumentException){if(http_response_code()<400)http_response_code(422);$message=$error->getMessage();}
    else{error_log('[portal-action] '.$error->getMessage());http_response_code(503);$message='The request could not be completed. Please try again.';}
    echo json_encode(['ok'=>false,'message'=>$message]);
}
