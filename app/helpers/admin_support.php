<?php
require_once __DIR__ . '/portal_support.php';

function kns_admin_bootstrap(mysqli $db, array $admin): array
{
    $departments=kns_portal_rows($db,'SELECT * FROM departments ORDER BY name');
    $courses=kns_portal_rows($db,'SELECT * FROM courses ORDER BY code');
    $professors=[];
    foreach(kns_portal_rows($db,"SELECT u.*,d.name AS department FROM user u LEFT JOIN departments d ON d.id=u.department_id WHERE u.usertype='professor' ORDER BY COALESCE(u.last_name,u.username),u.first_name") as $user) {
        $assignments=kns_portal_rows($db,'SELECT DISTINCT COALESCE(s.subject_code,s.subject_name) AS code FROM subjects s JOIN subject_professors p ON p.subject_id=s.id WHERE p.professor_id=?',[$user['id']]);
        $permission=kns_portal_one($db,'SELECT * FROM professor_permissions WHERE professor_id=?',[$user['id']]) ?: ['view_classes'=>1,'manage_classwork'=>1,'record_grades'=>0,'admin_messaging'=>1];
        $professors[]=['id'=>(string)$user['id'],'facultyId'=>$user['faculty_id'] ?: '', 'first'=>$user['first_name'] ?: $user['username'],'last'=>$user['last_name'] ?: '',
            'email'=>$user['email'] ?: '', 'department'=>$user['department'] ?: 'Unspecified','departmentId'=>$user['department_id'],
            'subjects'=>implode(', ',array_column($assignments,'code')) ?: 'Not assigned','status'=>['active'=>'Active','pending'=>'Invitation pending','suspended'=>'Suspended'][$user['account_status']],
            'activity'=>$user['last_activity_at'] ?: 'No activity recorded','color'=>'green','contact'=>$user['contact_number'] ?: '', 'permissions'=>$permission];
    }
    $subjects=[];
    foreach(kns_portal_rows($db,'SELECT s.*,d.name AS department FROM subjects s LEFT JOIN departments d ON d.id=s.department_id ORDER BY s.id') as $subject) {
        $sections=kns_portal_rows($db,'SELECT ss.*,(SELECT COUNT(*) FROM subject_enrollments e WHERE e.subject_id=ss.subject_id AND e.section=ss.section AND e.academic_year=ss.academic_year AND e.semester=ss.semester AND e.is_active=1) AS students FROM subject_sections ss WHERE ss.subject_id=? ORDER BY ss.academic_year DESC,ss.semester,ss.section',[$subject['id']]);
        foreach($sections ?: [null] as $section) $subjects[]=['id'=>$section?'section-'.$section['id']:'subject-'.$subject['id'],'subjectId'=>$subject['id'],'sectionId'=>$section['id']??null,
            'code'=>$subject['subject_code'] ?: 'ID '.$subject['id'],'title'=>$subject['subject_name'],'department'=>$subject['department'] ?: 'Unspecified','departmentId'=>$subject['department_id'],
            'professor'=>(string)($section['professor_id']??''),'sections'=>$section?[$section['section']]:[],'students'=>(int)($section['students']??0),
            'capacity'=>$section['capacity']??null,'year'=>$section['academic_year']??'','semester'=>$section['semester']??''];
    }
    $conversations=[];
    foreach($professors as $professor) {
        $messages=kns_portal_rows($db,'SELECT m.* FROM (SELECT * FROM admin_professor_messages WHERE admin_id=? AND professor_id=? ORDER BY id DESC LIMIT 100) m ORDER BY m.id',[$admin['id'],$professor['id']]);
        $history=[];
        foreach($messages as $message) $history[]=['id'=>$message['id'],'role'=>$message['sender_id']==$admin['id']?'Admin':'Professor','text'=>$message['message'],'time'=>$message['created_at'],'read'=>!!$message['read_at'],
            'attachments'=>kns_portal_rows($db,'SELECT id,original_name,byte_size FROM admin_message_attachments WHERE message_id=?',[$message['id']])];
        $last=end($history) ?: null;
        $unread=count(array_filter($messages,fn($m)=>$m['sender_id']==$m['professor_id']&&!$m['read_at']));
        $conversations[]=['professor'=>$professor['id'],'topic'=>'Faculty conversation','preview'=>$last?($last['text'] ?: 'Attachment'):'No messages yet','time'=>$last['time']??'','date'=>'Admin ↔ Professor','messages'=>$history,'unread'=>$unread];
    }
    $prefs=kns_portal_one($db,'SELECT * FROM notification_preferences WHERE user_id=?',[$admin['id']]) ?: ['messages'=>1,'invitations'=>1,'assignments'=>1];
    $summary=kns_portal_summary($db);
    return ['departments'=>$departments,'courses'=>$courses,'professors'=>$professors,'subjects'=>$subjects,'conversations'=>$conversations,
        'students'=>kns_portal_rows($db,"SELECT id,username,first_name,last_name FROM user WHERE usertype='student' AND account_status='active' ORDER BY username"),
        'summary'=>$summary,'profile'=>['first'=>$admin['first_name']??'','last'=>$admin['last_name']??'','email'=>$admin['email']??'','contact'=>$admin['contact_number']??'','name'=>kns_portal_name($admin),
            'passwordChanged'=>$admin['password_changed_at'],'hashed'=>!!$admin['password_hash'],'lastActivity'=>$admin['last_activity_at']],
        'preferences'=>$prefs,'notifications'=>kns_portal_rows($db,'SELECT id,title,body,read_at,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 30',[$admin['id']]),
        'requests'=>kns_portal_rows($db,'SELECT id,category,full_name,email,message,status,created_at FROM portal_requests ORDER BY id DESC LIMIT 100'),
        'mailPending'=>(int)kns_portal_one($db,'SELECT COUNT(*) AS total FROM mail_outbox WHERE sent_at IS NULL')['total'],
        'years'=>array_column(kns_portal_rows($db,'SELECT DISTINCT academic_year FROM subject_sections ORDER BY academic_year DESC'),'academic_year')];
}
function kns_admin_professor_save(mysqli $db, array $admin, array $input): array
{
    $id=(int)($input['id']??0);
    $first=kns_portal_text($input['first_name']??'',60,'first name');
    $last=kns_portal_text($input['last_name']??'',60,'last name');
    $faculty=kns_portal_text($input['faculty_id']??'',50,'faculty ID');
    $email=kns_portal_email($input['email']??'');
    $contact=kns_portal_text($input['contact']??'',30,'contact number',false);
    $department=(int)($input['department_id']??0);
    if(!kns_portal_one($db,'SELECT id FROM departments WHERE id=?',[$department])) throw new InvalidArgumentException('Choose an existing department.');
    $status=$input['status']??'pending';
    if(!in_array($status,['active','pending','suspended'],true)) throw new InvalidArgumentException('Choose a valid account status.');
    $link=null;
    $initialHash=(!$id && empty($input['activation']))?kns_portal_password($input['temporary_password']??''):null;
    $db->begin_transaction();
    try {
        if($id) {
            $old=kns_portal_one($db,"SELECT * FROM user WHERE id=? AND usertype='professor' FOR UPDATE",[$id]);
            if(!$old) throw new InvalidArgumentException('Professor account not found.');
            if(!$old['password_hash'] && $old['account_status']==='pending' && $status==='active') throw new InvalidArgumentException('The professor must activate the account before it can become active.');
            kns_portal_query($db,"UPDATE user SET first_name=?,last_name=?,faculty_id=?,email=?,contact_number=?,department_id=?,account_status=? WHERE id=?",[$first,$last,$faculty,$email,$contact,$department,$status,$id]);
            if((string)$old['email']!==$email)kns_portal_query($db,'UPDATE account_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$id]);
        } else {
            if(kns_portal_one($db,'SELECT id FROM user WHERE username=? OR faculty_id=? OR email=?',[$faculty,$faculty,$email])) throw new InvalidArgumentException('That faculty ID or email is already registered.');
            kns_portal_query($db,"INSERT INTO user(username,usertype,first_name,last_name,faculty_id,email,contact_number,department_id,account_status,created_at) VALUES(?,'professor',?,?,?,?,?,?,'pending',NOW())",[$faculty,$first,$last,$faculty,$email,$contact,$department]);
            $id=(int)$db->insert_id;
            if($initialHash) kns_portal_query($db,"UPDATE user SET password_hash=?,password_changed_at=NOW(),account_status='active' WHERE id=?",[$initialHash,$id]);
        }
        kns_portal_query($db,'INSERT INTO professor_permissions(professor_id,view_classes,manage_classwork,record_grades,admin_messaging) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE view_classes=VALUES(view_classes),manage_classwork=VALUES(manage_classwork),record_grades=VALUES(record_grades),admin_messaging=VALUES(admin_messaging)',
            [$id,!empty($input['classes'])?1:0,!empty($input['classwork'])?1:0,!empty($input['grades'])?1:0,!empty($input['messages'])?1:0]);
        if(!empty($input['activation'])) {
            $user=kns_portal_one($db,'SELECT * FROM user WHERE id=?',[$id]);
            if($user['account_status']!=='pending') throw new InvalidArgumentException('Activation links apply only to pending accounts.');
            $link=kns_portal_token($db,$user,'activation');
        }
        kns_portal_notify($db,(int)$admin['id'],'invitations','Professor account saved',$faculty.' · '.$first.' '.$last);
        $db->commit();
    } catch(Throwable $error) { $db->rollback(); throw $error; }
    return ['message'=>$link?'Professor saved. Activation email queued; delivery requires the mail worker.':'Professor account saved.','activationLink'=>$link];
}
function kns_admin_subject_save(mysqli $db, array $input): void
{
    $subjectId=(int)($input['subject_id']??0); $sectionId=(int)($input['section_id']??0);
    $code=kns_portal_text($input['code']??'',30,'subject code');
    $title=kns_portal_text($input['title']??'',100,'subject name');
    $department=(int)($input['department_id']??0);
    $professor=(int)($input['professor']??0);
    $section=kns_portal_text($input['section']??'',80,'section');
    $year=kns_portal_text($input['year']??'',20,'academic year');
    if(!preg_match('/^(\d{4})-(\d{4})$/',$year,$parts) || (int)$parts[2] !== (int)$parts[1]+1) throw new InvalidArgumentException('Use academic year YYYY-YYYY with consecutive years.');
    $semester=$input['semester']??'';
    if(!in_array($semester,['First Semester','Second Semester','1st Semester','2nd Semester','Summer'],true)) throw new InvalidArgumentException('Choose a valid semester.');
    $capacity=filter_var($input['capacity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>300]]);
    if(!$capacity) throw new InvalidArgumentException('Capacity must be 1 to 300.');
    if(!kns_portal_one($db,'SELECT id FROM departments WHERE id=?',[$department])) throw new InvalidArgumentException('Choose an existing department.');
    if($professor && !kns_portal_one($db,"SELECT id FROM user WHERE id=? AND usertype='professor' AND account_status='active'",[$professor])) throw new InvalidArgumentException('Choose an active professor.');
    $db->begin_transaction();
    try {
        if($subjectId) {
            if(!kns_portal_one($db,'SELECT id FROM subjects WHERE id=? FOR UPDATE',[$subjectId])) throw new InvalidArgumentException('Subject not found.');
            kns_portal_query($db,'UPDATE subjects SET subject_code=?,subject_name=?,department_id=? WHERE id=?',[$code,$title,$department,$subjectId]);
        } else {
            $existing=kns_portal_one($db,'SELECT id FROM subjects WHERE subject_code=?',[$code]);
            if($existing) $subjectId=(int)$existing['id'];
            else {
                $instructor=$professor?kns_portal_name(kns_portal_one($db,'SELECT * FROM user WHERE id=?',[$professor])):'Unassigned';
                kns_portal_query($db,'INSERT INTO subjects(subject_code,subject_name,instructor,department_id,created_at) VALUES(?,?,?,?,NOW())',[$code,$title,$instructor,$department]);
                $subjectId=(int)$db->insert_id;
            }
        }
        if($sectionId) {
            $old=kns_portal_one($db,'SELECT * FROM subject_sections WHERE id=? AND subject_id=? FOR UPDATE',[$sectionId,$subjectId]);
            if(!$old) throw new InvalidArgumentException('Section not found.');
            $enrolled=(int)kns_portal_one($db,'SELECT COUNT(*) AS n FROM subject_enrollments WHERE subject_id=? AND section=? AND academic_year=? AND semester=? AND is_active=1',[$subjectId,$old['section'],$old['academic_year'],$old['semester']])['n'];
            if($capacity<$enrolled) throw new InvalidArgumentException('Capacity cannot be below the active enrollment count.');
            if(($old['section']!==$section || $old['academic_year']!==$year || $old['semester']!==$semester) && (int)kns_portal_one($db,'SELECT COUNT(*) AS n FROM subject_enrollments WHERE subject_id=? AND section=? AND academic_year=? AND semester=?',[$subjectId,$old['section'],$old['academic_year'],$old['semester']])['n']) throw new InvalidArgumentException('An enrolled section cannot be renamed or moved to another term. Add a new section.');
            kns_portal_query($db,'UPDATE subject_sections SET professor_id=?,section=?,academic_year=?,semester=?,capacity=? WHERE id=?',[$professor?:null,$section,$year,$semester,$capacity,$sectionId]);
        } else kns_portal_query($db,'INSERT INTO subject_sections(subject_id,professor_id,section,academic_year,semester,capacity) VALUES(?,?,?,?,?,?)',[$subjectId,$professor?:null,$section,$year,$semester,$capacity]);
        if(isset($old) && $old['professor_id'] && (int)$old['professor_id']!==$professor) {
            kns_portal_query($db,'DELETE FROM subject_professors WHERE subject_id=? AND professor_id=? AND NOT EXISTS (SELECT id FROM subject_sections WHERE subject_id=? AND professor_id=?)',[$subjectId,$old['professor_id'],$subjectId,$old['professor_id']]);
        }
        if($professor) {
            kns_portal_query($db,'INSERT IGNORE INTO subject_professors(subject_id,professor_id) VALUES(?,?)',[$subjectId,$professor]);
            kns_portal_notify($db,$professor,'assignments','Teaching assignment updated',$code.' · '.$section.' · '.$year.' · '.$semester);
        }
        $assigned=kns_portal_rows($db,'SELECT DISTINCT u.username,u.first_name,u.last_name FROM subject_sections ss JOIN user u ON u.id=ss.professor_id WHERE ss.subject_id=?',[$subjectId]);
        kns_portal_query($db,'UPDATE subjects SET instructor=? WHERE id=?',[mb_substr(implode(', ',array_map('kns_portal_name',$assigned))?:'Unassigned',0,100),$subjectId]);
        $db->commit();
    } catch(Throwable $error) { $db->rollback(); throw $error; }
}
function kns_admin_enroll(mysqli $db, array $input): void
{
    $sectionId=(int)($input['section_id']??0); $studentId=(int)($input['student_id']??0);
    $courseId=(int)($input['course_id']??0);
    if($courseId&&!kns_portal_one($db,'SELECT id FROM courses WHERE id=?',[$courseId]))throw new InvalidArgumentException('Choose a valid course.');
    $db->begin_transaction();
    try {
        $section=kns_portal_one($db,'SELECT * FROM subject_sections WHERE id=? FOR UPDATE',[$sectionId]);
        if(!$section || !kns_portal_one($db,"SELECT id FROM user WHERE id=? AND usertype='student' AND account_status='active'",[$studentId])) throw new InvalidArgumentException('Choose a valid section and active student.');
        if($courseId)kns_portal_query($db,'UPDATE user SET course_id=? WHERE id=?',[$courseId,$studentId]);
        $params=[$section['subject_id'],$section['section'],$section['academic_year'],$section['semester']];
        $existing=kns_portal_one($db,'SELECT id,is_active FROM subject_enrollments WHERE subject_id=? AND section=? AND academic_year=? AND semester=? AND student_id=?',[...$params,$studentId]);
        $count=(int)kns_portal_one($db,'SELECT COUNT(*) AS n FROM subject_enrollments WHERE subject_id=? AND section=? AND academic_year=? AND semester=? AND is_active=1',$params)['n'];
        if(!$existing || !$existing['is_active']) {
            if($section['capacity']!==null && $count>=(int)$section['capacity']) throw new InvalidArgumentException('This section is full.');
            if($existing) kns_portal_query($db,'UPDATE subject_enrollments SET is_active=1 WHERE id=?',[$existing['id']]);
            else {
                $student=kns_portal_one($db,'SELECT course_id FROM user WHERE id=?',[$studentId]);
                kns_portal_query($db,'INSERT INTO subject_enrollments(student_id,subject_id,section,academic_year,semester,course_id) VALUES(?,?,?,?,?,?)',[$studentId,...$params,$student['course_id']]);
            }
        }
        if($courseId)kns_portal_query($db,'UPDATE subject_enrollments SET course_id=? WHERE student_id=? AND subject_id=? AND section=? AND academic_year=? AND semester=?',[$courseId,$studentId,...$params]);
        kns_portal_notify($db,$studentId,'assignments','Enrollment confirmed',$section['section'].' · '.$section['academic_year']);
        $db->commit();
    } catch(Throwable $error) {$db->rollback();throw $error;}
}
function kns_faculty_send(mysqli $db, array $sender, int $peerId, string $body, array $uploads): void
{
    $role=$sender['usertype'];
    $peer=kns_portal_one($db,'SELECT * FROM user WHERE id=?',[$peerId]);
    if(!$peer || $peer['usertype']!==($role==='admin'?'professor':'admin') || $peer['account_status']!=='active') throw new InvalidArgumentException('Choose an active admin or professor account.');
    $adminId=$role==='admin'?(int)$sender['id']:$peerId;
    $professorId=$role==='professor'?(int)$sender['id']:$peerId;
    if(!kns_portal_permission($db,$professorId,'admin_messaging')) {http_response_code(403);throw new InvalidArgumentException('Admin–professor messaging is disabled for this professor.');}
    $body=kns_portal_text($body,5000,'message',false);
    $files=kns_subject_validate_uploads($uploads);
    if(!$body && !$files) throw new InvalidArgumentException('Write a message or attach a file.');
    $paths=[];$db->begin_transaction();
    try {
        kns_portal_query($db,'INSERT INTO admin_professor_messages(admin_id,professor_id,sender_id,message) VALUES(?,?,?,?)',[$adminId,$professorId,$sender['id'],$body]);
        $id=(int)$db->insert_id;
        foreach($files as $file) {
            $name=bin2hex(random_bytes(32)).'.bin';$path=kns_subject_storage_dir(true).DIRECTORY_SEPARATOR.$name;
            if(!move_uploaded_file($file['temporary_path'],$path)) throw new RuntimeException('Could not save attachment.');
            $paths[]=$path;@chmod($path,0600);
            kns_portal_query($db,'INSERT INTO admin_message_attachments(message_id,storage_name,original_name,mime_type,byte_size,attachment_kind) VALUES(?,?,?,?,?,?)',[$id,$name,$file['original_name'],$file['mime_type'],$file['byte_size'],$file['attachment_kind']]);
        }
        kns_portal_notify($db,$peerId,'messages','New faculty message',mb_substr(kns_portal_name($sender).': '.($body ?: 'Attachment'),0,500));
        $db->commit();
    }catch(Throwable $error){$db->rollback();foreach($paths as $path)if(is_file($path))unlink($path);throw $error;}
}
