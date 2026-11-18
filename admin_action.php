<?php
// Retired endpoint: no authentication bootstrap or database write is reached.
http_response_code(410);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
echo json_encode(['ok'=>false,'message'=>'The Admin workspace has been retired.']);
exit;
session_start();
require_once __DIR__ . '/app/helpers/admin_support.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try {
    $db=kns_subject_db(); $admin=kns_portal_user($db,['admin']);
    $action=$_GET['action']??$_POST['action']??'bootstrap';
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if($method==='GET') {
        if($action==='bootstrap') $response=kns_admin_bootstrap($db,$admin);
        elseif(in_array($action,['report','export'],true)) {
            $year=kns_portal_text($_GET['year']??'',20,'academic year',false);
            $semester=kns_portal_text($_GET['semester']??'',40,'semester',false);
            $department=(string)(int)($_GET['department']??0); if($department==='0')$department='';
            $response=kns_portal_summary($db,$year,$semester,$department,kns_portal_text($_GET['from']??'',10,'start date',false),kns_portal_text($_GET['to']??'',10,'end date',false));
            if($action==='export') {
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="kns-faculty-report.csv"');
                $output=fopen('php://output','w');
                $safe=fn($v)=>preg_match('/^[=+@\-\t\r\n]/',(string)$v)?"'".$v:$v;
                fputcsv($output,['KNS Portal faculty report']);
                fputcsv($output,array_map($safe,['Academic year',$year?:'All','Semester',$semester?:'All','Department ID',$department?:'All']));
                foreach($response as $key=>$value)if(!is_array($value))fputcsv($output,[$key,$value]);
                fputcsv($output,['Month','Subjects created','Sections created']);
                foreach($response['activity'] as $month) fputcsv($output,[$month['label'],$month['subjects'],$month['sections']]);
                fclose($output);exit;
            }
        } else {http_response_code(404);throw new InvalidArgumentException('Action not found.');}
    } elseif($method==='POST') {
        kns_portal_csrf();
        if((int)($_SERVER['CONTENT_LENGTH']??0)>KNS_SUBJECT_MAX_POST_BYTES) {http_response_code(413);throw new InvalidArgumentException('Request exceeds the upload limit.');}
        $response=['message'=>'Saved.'];
        switch($action) {
            case 'professor_save': $response=kns_admin_professor_save($db,$admin,$_POST);break;
            case 'subject_save': kns_admin_subject_save($db,$_POST);$response['message']='Subject and section saved.';break;
            case 'enroll': kns_admin_enroll($db,$_POST);$response['message']='Student enrollment saved.';break;
            case 'message_send':kns_faculty_send($db,$admin,(int)($_POST['professor_id']??0),$_POST['message']??'',$_FILES);$response['message']='Message sent.';break;
            case 'message_read':
                kns_portal_query($db,'UPDATE admin_professor_messages SET read_at=COALESCE(read_at,NOW()) WHERE admin_id=? AND professor_id=? AND sender_id=professor_id',[$admin['id'],(int)($_POST['professor_id']??0)]);break;
            case 'profile':
                $first=kns_portal_text($_POST['first_name']??'',60,'first name');
                $last=kns_portal_text($_POST['last_name']??'',60,'last name');
                $email=kns_portal_email($_POST['email']??'');
                $contact=kns_portal_text($_POST['contact']??'',30,'contact number',false);
                $db->begin_transaction();
                try{
                    kns_portal_query($db,'UPDATE user SET first_name=?,last_name=?,email=?,contact_number=? WHERE id=?',[$first,$last,$email,$contact,$admin['id']]);
                    if((string)$admin['email']!==$email)kns_portal_query($db,'UPDATE account_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$admin['id']]);
                    $db->commit();
                }catch(Throwable $failure){$db->rollback();throw $failure;}
                $response['message']='Profile saved.';break;
            case 'preferences':
                kns_portal_query($db,'INSERT INTO notification_preferences(user_id,messages,invitations,assignments) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE messages=VALUES(messages),invitations=VALUES(invitations),assignments=VALUES(assignments)',[$admin['id'],isset($_POST['messages'])?1:0,isset($_POST['invitations'])?1:0,isset($_POST['assignments'])?1:0]);$response['message']='Notification preferences saved.';break;
            case 'notification_read':
                kns_portal_query($db,'UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL',[$admin['id']]);break;
            case 'department':
                $name=kns_portal_text($_POST['name']??'',100,'department name');
                kns_portal_query($db,'INSERT INTO departments(name) VALUES(?)',[$name]);$response['message']='Department created.';break;
            case 'course':
                $department=(int)($_POST['department_id']??0);
                if(!kns_portal_one($db,'SELECT id FROM departments WHERE id=?',[$department]))throw new InvalidArgumentException('Choose a department.');
                kns_portal_query($db,'INSERT INTO courses(code,name,department_id) VALUES(?,?,?)',[kns_portal_text($_POST['code']??'',30,'course code'),kns_portal_text($_POST['name']??'',150,'course name'),$department]);$response['message']='Course created.';break;
            case 'request_reviewed':
                kns_portal_query($db,"UPDATE portal_requests SET status='reviewed' WHERE id=?",[(int)($_POST['id']??0)]);$response['message']='Request marked reviewed.';break;
            default: http_response_code(404);throw new InvalidArgumentException('Action not found.');
        }
    } else {header('Allow: GET, POST');http_response_code(405);throw new InvalidArgumentException('Method not allowed.');}
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok'=>true,'data'=>$response],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
} catch(Throwable $error) {
    header('Content-Type: application/json; charset=UTF-8');
    if($error instanceof mysqli_sql_exception && $error->getCode()===1062) {http_response_code(409);$message='That account, code, email, or section already exists.';}
    elseif($error instanceof InvalidArgumentException) {if(http_response_code()<400)http_response_code(422);$message=$error->getMessage();}
    else {error_log('[admin] '.$error->getMessage());http_response_code(503);$message='The request could not be completed. Please try again.';}
    echo json_encode(['ok'=>false,'message'=>$message]);
}
