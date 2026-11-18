<?php
// Targeted checks on the existing local database. Every fixture is rolled back.
if(PHP_SAPI!=='cli'||!in_array('--local-rollback-test',$argv,true)){http_response_code(404);exit("Use CLI --local-rollback-test. No fixture data is committed.\n");}
require_once dirname(__DIR__).'/app/helpers/classroom_support.php';
require_once dirname(__DIR__).'/app/helpers/registration_support.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$checks=0;$inTransaction=false;
function check(bool $valid,string $label): void{global $checks;if(!$valid)throw new RuntimeException('Failed: '.$label);$checks++;echo "PASS: ".$label."\n";}
function rejected(callable $call,string $label,?int $code=null): void{
 try{$call();}catch(Throwable $error){check($code!==null?($error instanceof mysqli_sql_exception&&(int)$error->getCode()===$code):$error instanceof InvalidArgumentException,$label);return;}
 throw new RuntimeException('Expected rejection: '.$label);
}
function fixture_work(mysqli $db,array $class,array $professor,string $mode,string $json): int{
 kns_portal_query($db,"INSERT INTO subject_classwork(subject_id,professor_id,section,academic_year,semester,category,title,instructions,questions_json,status,published_at,submission_mode,file_points) VALUES(?,?,?,?,?,'activities','Transactional activity','Review the instructions.',?,'published',NOW(),?,50)",[$class['subject_id'],$professor['id'],$class['section'],$class['academic_year'],$class['semester'],$json,$mode]);return (int)$db->insert_id;
}
try{
 $host=getenv('KNS_SUBJECT_DB_HOST')?:'localhost';$name=getenv('KNS_SUBJECT_DB_NAME')?:'althea_database';
 if(!in_array(strtolower($host),['localhost','127.0.0.1','::1'],true)||$name!=='althea_database')throw new RuntimeException('Only the existing local database is permitted.');
 $db=new mysqli('127.0.0.1',getenv('KNS_SUBJECT_DB_USER')?:'root',getenv('KNS_SUBJECT_DB_PASSWORD')!==false?getenv('KNS_SUBJECT_DB_PASSWORD'):'','althea_database',3306);
 $server=$db->query('SELECT DATABASE() AS name,@@datadir AS directory')->fetch_assoc();
 if($server['name']!=='althea_database'||strtolower(rtrim(str_replace(chr(92),'/',$server['directory']),'/'))!=='c:/xampp/mysql/data')throw new RuntimeException('The local XAMPP server could not be verified.');
 $db->set_charset('utf8mb4');
 $tables=$db->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetch_all(MYSQLI_ASSOC);$counts=[];
 foreach($tables as $table){if($table['ENGINE']!=='InnoDB')throw new RuntimeException('Transactional engine required.');$counts[$table['TABLE_NAME']]=(int)$db->query('SELECT COUNT(*) FROM `'.$table['TABLE_NAME'].'`')->fetch_row()[0];}
 check(kns_classroom_ready($db),'verified classroom schema is available');
 $db->begin_transaction();$inTransaction=true;$nonce=strtoupper(bin2hex(random_bytes(6)));$password='Rollback-'.$nonce.'-Only!';
 $faculty='VERIFY-'.$nonce;
 $id=kns_register_professor($db,['faculty_id'=>$faculty,'full_name'=>'Transactional Professor','email'=>'prof-'.$nonce.'@example.invalid','password'=>$password,'role'=>'admin','usertype'=>'admin']);
 $prof=kns_portal_one($db,'SELECT * FROM user WHERE id=?',[$id]);
 check($prof['usertype']==='professor'&&$prof['account_status']==='active','server role and immediate professor activation');
 check($prof['password']===null&&password_verify($password,$prof['password_hash']),'password hash only, compatible with current login');
 check(kns_portal_permission($db,$id,'record_grades'),'new professor can grade without admin approval');
 rejected(fn()=>kns_portal_query($db,"INSERT INTO user(username,faculty_id,usertype,password_hash) VALUES(?,?,'professor',?)",['UNIQUE-'.$nonce,strtolower($faculty),$prof['password_hash']]),'database unique faculty index rejects duplicates with a different username',1062);
 rejected(fn()=>kns_register_professor($db,['faculty_id'=>strtolower($faculty),'full_name'=>'Duplicate','email'=>'duplicate-'.$nonce.'@example.invalid','password'=>$password]),'professor registration rejects duplicate account identifiers');
 rejected(fn()=>kns_register_professor($db,['faculty_id'=>str_repeat('X',51),'full_name'=>'Invalid','email'=>'invalid-'.$nonce.'@example.invalid','password'=>$password]),'faculty ID cannot exceed verified column capacity');
 $otherId=kns_register_professor($db,['faculty_id'=>'OTHER-'.$nonce,'full_name'=>'Other Professor','email'=>'other-'.$nonce.'@example.invalid','password'=>$password]);
 $other=kns_portal_one($db,'SELECT * FROM user WHERE id=?',[$otherId]);
 $students=[];$year=random_int(5000,9999);$hash=kns_portal_password($password);
 for($i=0;$i<4;$i++){kns_portal_query($db,"INSERT INTO user(username,usertype,account_status,student_number,email,password_hash,created_at) VALUES(?,'student','active',?,?,?,NOW())",['VERIFY-S'.$i.'-'.$nonce,$year.'-'.str_pad((string)$i,5,'0',STR_PAD_LEFT),'student'.$i.'-'.$nonce.'@example.invalid',$hash]);$students[]=kns_portal_one($db,'SELECT * FROM user WHERE id=?',[(int)$db->insert_id]);}
 rejected(fn()=>kns_register_professor($db,['faculty_id'=>$students[0]['student_number'],'full_name'=>'Conflicting Professor','email'=>'conflict-'.$nonce.'@example.invalid','password'=>$password]),'faculty ID cannot conflict with a legacy student-number identifier');
 $legacyLogin='legacy-'.$nonce.'@example.invalid';
 kns_portal_query($db,"INSERT INTO user(username,usertype,account_status,email,password_hash) VALUES(?,'student','active',?,?)",[$legacyLogin,'legacy-mail-'.$nonce.'@example.invalid',$hash]);
 rejected(fn()=>kns_register_professor($db,['faculty_id'=>'EMAIL-'.$nonce,'full_name'=>'Conflicting Email','email'=>$legacyLogin,'password'=>$password]),'professor email cannot conflict with a legacy login username');
 $classId=kns_classroom_create($db,$prof,['subject_name'=>'Transactional subject','subject_code'=>'VERIFY-'.$nonce,'description'=>'Rollback-only verification','section'=>'A','academic_year'=>'2098-2099','semester'=>'First','capacity'=>'30','professor_id'=>$otherId]);
 $class=kns_classroom_section($db,$prof,$classId);check((int)$class['professor_id']===$id&&strlen($class['class_code'])===10,'subject and section ownership assigned on server with a class code');
 rejected(fn()=>kns_classroom_section($db,$other,$classId),'another professor cannot access the class');
 rejected(fn()=>kns_classroom_create($db,$other,['subject_id'=>$class['subject_id'],'section'=>'B','academic_year'=>'2098-2099','semester'=>'First']),'another professor cannot create a section under this subject');
 rejected(fn()=>kns_classroom_post_update($db,$students[3],$classId,['title'=>'Blocked','body'=>'Blocked']),'unenrolled student cannot publish updates');
 foreach(array_slice($students,0,3) as $student)check(kns_classroom_join($db,$student,strtolower($class['class_code']))===$classId,'student joins the correct section by class code');
 $studentClass=kns_classroom_section($db,$students[0],$classId);
 check(kns_classroom_join($db,$students[0],$class['class_code'])===$classId,'repeat join preserves the original enrollment');
 rejected(fn()=>kns_classroom_section($db,$students[3],$classId),'unenrolled student cannot access class data');
 rejected(fn()=>kns_classroom_post_update($db,$students[0],$classId,['title'=>'Blocked','body'=>'Blocked']),'enrolled student cannot publish professor updates');
 kns_classroom_post_update($db,$prof,$classId,['title'=>'Verified update','body'=>'Class-only content.']);
 kns_classroom_post_group($db,$students[0],$classId,['message'=>'Visible group content.']);
 check((int)kns_portal_one($db,'SELECT COUNT(*) AS total FROM class_updates WHERE section_id=?',[$classId])['total']===1,'professor update is saved to the class');
 $questions=kns_classwork_questions(json_encode([['type'=>'short_answer','text'=>'Explain the result.','points'=>10,'choices'=>[],'answer'=>'PRIVATE-KEY-'.$nonce]]),true);
 $onlineId=fixture_work($db,$class,$prof,'online',json_encode($questions));
 $fileId=fixture_work($db,$class,$prof,'files','[]');
 $online=kns_portal_one($db,'SELECT * FROM subject_classwork WHERE id=?',[$onlineId]);$fileWork=kns_portal_one($db,'SELECT * FROM subject_classwork WHERE id=?',[$fileId]);
 check(kns_classwork_answers($online,['answers'=>['A valid response']],[])===['A valid response'],'existing online answer submission remains available');
 rejected(fn()=>kns_classwork_answers($online,['answers'=>[]],[]),'missing online answers are rejected');
 rejected(fn()=>kns_classwork_answers($fileWork,[],[]),'file-only submissions require an attachment');
 check(kns_classwork_answers($fileWork,[],[['attachment_kind'=>'file']])===[]&&kns_classwork_max_score($fileWork,[])===50.0,'file-only submissions have a separate grading maximum');
 $published=kns_classwork_for_student($db,array_merge($studentClass,['id'=>$studentClass['enrollment_id']]));
 check(strpos(json_encode($published),'PRIVATE-KEY-'.$nonce)===false,'student classwork output excludes answer keys');
 kns_portal_query($db,'INSERT INTO subject_classwork_submissions(classwork_id,enrollment_id,answers_json) VALUES(?,?,?)',[$onlineId,$studentClass['enrollment_id'],'["A valid response"]']);$submissionId=(int)$db->insert_id;
 rejected(fn()=>kns_portal_query($db,'INSERT INTO subject_classwork_submissions(classwork_id,enrollment_id,answers_json) VALUES(?,?,?)',[$onlineId,$studentClass['enrollment_id'],'[]']),'database preserves one submission per enrollment',1062);
 kns_portal_query($db,"INSERT INTO classwork_submission_files(submission_id,storage_name,original_name,mime_type,byte_size,attachment_kind) VALUES(?,?,'verification.pdf','application/pdf',100,'file')",[$submissionId,strtolower(bin2hex(random_bytes(32))).'.bin']);$attachmentId=(int)$db->insert_id;
 check(kns_classwork_file_access($db,$students[0],'submission',$attachmentId)!==null,'student can access their own submitted file');
 check(kns_classwork_file_access($db,$students[1],'submission',$attachmentId)===null,'another enrolled student cannot access private submission files');
 check(kns_classwork_file_access($db,$prof,'submission',$attachmentId)!==null&&kns_classwork_file_access($db,$other,'submission',$attachmentId)===null,'submission files are restricted to the owning professor');
 rejected(fn()=>kns_subject_validate_uploads(['files'=>['name'=>['verification.txt'],'tmp_name'=>[__FILE__],'error'=>[UPLOAD_ERR_OK],'size'=>[100]]]),'arbitrary server files cannot be passed as uploads');
 $private='PRIVATE-MESSAGE-'.$nonce;
 kns_portal_query($db,'INSERT INTO subject_messages(enrollment_id,sender_id,recipient_name,subject_module,message) VALUES(?,?,?,?,?)',[$studentClass['enrollment_id'],$students[0]['id'],$prof['username'],'Activity',$private]);
 for($i=0;$i<2;$i++){$enrollment=kns_classroom_section($db,$students[$i],$classId);kns_classroom_record_question($db,(int)$students[$i]['id'],(int)$enrollment['enrollment_id'],$onlineId,'instructions');}
 kns_classroom_record_question($db,(int)$students[0]['id'],(int)$studentClass['enrollment_id'],$onlineId,'instructions');
 check(kns_classroom_question_alerts($db,$id)===[],'repeat messages from one student do not inflate the alert threshold');
 $third=kns_classroom_section($db,$students[2],$classId);kns_classroom_record_question($db,(int)$students[2]['id'],(int)$third['enrollment_id'],$onlineId,'instructions');
 $alerts=kns_classroom_question_alerts($db,$id);check(count($alerts)===1&&(int)$alerts[0]['students']===3,'three distinct students trigger the professor alert');
 check(strpos(json_encode($alerts),$private)===false&&kns_classroom_question_alerts($db,$otherId)===[],'alerts expose no private contents and stay with the owning professor');
 $public=kns_portal_rows($db,'SELECT message FROM class_group_messages WHERE section_id=?',[$classId]);check(strpos(json_encode($public),$private)===false,'private messages remain separate from group chat');
 $limitedId=kns_classroom_create($db,$prof,['subject_id'=>$class['subject_id'],'section'=>'Limited','academic_year'=>'2098-2099','semester'=>'First','capacity'=>'1']);$limited=kns_classroom_section($db,$prof,$limitedId);
 kns_classroom_join($db,$students[0],$limited['class_code']);
 rejected(fn()=>kns_classroom_join($db,$students[1],$limited['class_code']),'section capacity is enforced');
 kns_portal_query($db,'UPDATE subject_enrollments SET is_active=0 WHERE student_id=? AND subject_id=? AND section=?',[$students[0]['id'],$class['subject_id'],'Limited']);
 rejected(fn()=>kns_classroom_join($db,$students[0],$limited['class_code']),'class code cannot reactivate a revoked enrollment');
 $db->rollback();$inTransaction=false;
 foreach($counts as $table=>$count)check((int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetch_row()[0]===$count,'no fixture records persist in '.$table);
 echo "Completed ".$checks." checks. All fixture writes rolled back.\n";
}catch(Throwable $error){if($inTransaction&&isset($db))$db->rollback();file_put_contents('php://stderr',($error instanceof mysqli_sql_exception?'Database verification failed (code '.(int)$error->getCode().').':$error->getMessage())." All fixture writes rolled back.\n");exit(1);}
