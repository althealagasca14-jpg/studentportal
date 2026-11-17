<?php
// Isolated integration checks. Never insert test records in the portal database.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/database/migrate.php';
$root=dirname(__DIR__);$source=kns_subject_db();$sourceName=$source->query('SELECT DATABASE()')->fetch_row()[0];
$before=[];foreach($source->query('SHOW TABLES')->fetch_all(MYSQLI_NUM) as $table)$before[$table[0]]=$source->query('CHECKSUM TABLE '.$table[0])->fetch_row()[1];
$name='kns_portal_test_'.bin2hex(random_bytes(6));
$private=sys_get_temp_dir().'/'.$name;mkdir($private,0700,true);mkdir($private.'/sessions',0700,true);
putenv('KNS_SUBJECT_STORAGE_DIR='.$private.'/attachments');
$source->query('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$db=kns_subject_db();$db->select_db($name);$server=null;$checks=0;$keep=in_array('--keep',$argv,true);
function test_assert(bool $condition,string $message):void{global $checks;$checks++;if(!$condition)throw new RuntimeException($message);echo "PASS ".$message.PHP_EOL;}
function test_http(string $path,?string $cookie=null,?array $post=null):array{
 global $base;$curl=curl_init($base.'/'.$path);
 curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>3]);
 if($cookie)curl_setopt($curl,CURLOPT_COOKIE,'PHPSESSID='.$cookie);
 if($post!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$post);
 $raw=curl_exec($curl);if($raw===false)throw new RuntimeException(curl_error($curl));
 $size=curl_getinfo($curl,CURLINFO_HEADER_SIZE);$result=['status'=>curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'headers'=>substr($raw,0,$size),'body'=>substr($raw,$size),'cookie'=>$cookie];curl_close($curl);
 if(preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i',$result['headers'],$match))$result['cookie']=$match[1];
 return $result;
}
function test_csrf(array $response):string{
 if(!preg_match('/name="csrf_token"\s+value="([^"]+)"/',$response['body'],$match))throw new RuntimeException('CSRF field is missing: '.substr($response['body'],0,300));
 return html_entity_decode($match[1],ENT_QUOTES,'UTF-8');
}
function test_login(string $username,string $password):array{
 $page=test_http('login.php');$token=test_csrf($page);
 $login=test_http('login_check.php',$page['cookie'],['username'=>$username,'password'=>$password,'csrf_token'=>$token]);
 test_assert($login['status']===303&&!str_contains($login['headers'],'Location: login.php'),'Login: '.$username);
 return ['cookie'=>$login['cookie'],'csrf'=>$token];
}
function test_post(string $path,array $session,array $values):array{
 $response=test_http($path,$session['cookie'],['csrf_token'=>$session['csrf'],...$values]);
 $json=json_decode($response['body'],true);
 if(!is_array($json))throw new RuntimeException('Non-JSON response: '.$response['body']);
 return ['status'=>$response['status'],...$json];
}
try{
 // Preserve table types and existing nullable defaults; fixture rows are local to this schema.
 foreach(['departments','courses','user','subjects'] as $table){
  if($source->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".$table."'")->fetch_row()[0])$db->query($source->query('SHOW CREATE TABLE '.$table)->fetch_row()[1]);
 }
 foreach(['001_subject_features.sql','002_classwork.sql'] as $file){$db->multi_query(file_get_contents($root.'/database/migrations/'.$file));do{if($result=$db->store_result())$result->free();}while($db->more_results()&&$db->next_result());}
 foreach(['test-admin'=>'admin','test-professor'=>'professor','test-other-professor'=>'professor','test-student'=>'student'] as $username=>$role){
  kns_portal_query($db,'INSERT INTO user(username,usertype,password) VALUES(?,?,?)',[$username,$role,'LegacyPassword!2026']);
 }
 $ids=[];foreach(kns_portal_rows($db,'SELECT id,username FROM user') as $row)$ids[$row['username']]=(int)$row['id'];
 kns_portal_query($db,"INSERT INTO subjects(subject_name,instructor,description) VALUES('TEST verified legacy subject','TEST professor','Isolated integration fixture')");
 $legacySubject=(int)$db->insert_id;
 kns_portal_query($db,'INSERT INTO subject_professors(subject_id,professor_id) VALUES(?,?)',[$legacySubject,$ids['test-professor']]);
 kns_portal_query($db,"INSERT INTO subject_enrollments(student_id,subject_id,section,academic_year,semester) VALUES(?,?,'TEST-LEGACY','2026-2027','First Semester')",[$ids['test-student'],$legacySubject]);
 kns_portal_migrate($db);kns_portal_migrate($db);
 test_assert((int)kns_portal_one($db,'SELECT COUNT(*) AS n FROM user')['n']===4,'Migration preserves existing account rows');
 test_assert((int)kns_portal_one($db,'SELECT COUNT(*) AS n FROM subject_sections')['n']===1,'Migration backfills only verified section context and is repeatable');
 test_assert((int)kns_portal_one($db,"SELECT COUNT(*) AS n FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND constraint_name='portal_user_department'")['n']===1,'New profile foreign key is enforced');
 $listen=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($listen,false);fclose($listen);$port=(int)substr(strrchr($address,':'),1);$base='http://127.0.0.1:'.$port;
 $environment=getenv();$environment['KNS_SUBJECT_DB_NAME']=$name;$environment['KNS_PORTAL_URL']=$base;$environment['KNS_SUBJECT_STORAGE_DIR']=$private.'/attachments';
 $server=proc_open([PHP_BINARY,'-d','session.save_path='.$private.'/sessions','-d','display_errors=1','-S','127.0.0.1:'.$port,'-t',$root],[0=>['pipe','r'],1=>['file',$private.'/http.log','a'],2=>['file',$private.'/http.log','a']],$pipes,$root,$environment);
 if(!is_resource($server))throw new RuntimeException('Test server could not start.');fclose($pipes[0]);usleep(400000);
 $admin=test_login('test-admin','LegacyPassword!2026');$prof=test_login('test-professor','LegacyPassword!2026');$other=test_login('test-other-professor','LegacyPassword!2026');$student=test_login('test-student','LegacyPassword!2026');
 $stored=kns_portal_one($db,'SELECT password,password_hash FROM user WHERE id=?',[$ids['test-admin']]);
 test_assert($stored['password']===null&&password_verify('LegacyPassword!2026',$stored['password_hash']),'Successful legacy login upgrades password storage');
 test_assert(test_http('admin_action.php')['status']===403,'Anonymous admin API denied');
 test_assert(test_http('admin_action.php',$student['cookie'])['status']===403,'Student admin API denied');
 test_assert(test_http('admin_action.php?action=department',$admin['cookie'],['name'=>'Forged'])['status']===403,'CSRF write denied');
 $result=test_post('admin_action.php?action=department',$admin,['name'=>'TEST Computing']);test_assert($result['ok'],'Create department');
 $department=(int)kns_portal_one($db,"SELECT id FROM departments WHERE name='TEST Computing'")['id'];
 test_assert(test_post('admin_action.php?action=department',$admin,['name'=>'TEST Computing'])['status']===409,'Duplicate department rejected');
 test_assert(test_post('admin_action.php?action=course',$admin,['name'=>'TEST Computing course','code'=>'TEST-CS','department_id'=>$department])['ok'],'Create course');
 $course=(int)kns_portal_one($db,"SELECT id FROM courses WHERE code='TEST-CS'")['id'];
 $public=test_http('login.php');$publicSession=['cookie'=>$public['cookie'],'csrf'=>test_csrf($public)];
 $registration=test_post('portal_action.php',$publicSession,['action'=>'register','full_name'=>'TEST Registered Student','student_number'=>'2099-00001','email'=>'student-test@example.org','password'=>'StudentPassword!2026','course_id'=>$course]);
 test_assert($registration['ok'],'Student registration persists account');
 test_assert(test_post('portal_action.php',$publicSession,['action'=>'register','full_name'=>'TEST Duplicate','student_number'=>'2099-00001','email'=>'student-test@example.org','password'=>'StudentPassword!2026'])['status']===409,'Duplicate student registration rejected');
 $registered=test_login('2099-00001','StudentPassword!2026');$registeredId=(int)kns_portal_one($db,"SELECT id FROM user WHERE username='2099-00001'")['id'];
 $create=test_post('admin_action.php?action=professor_save',$admin,['first_name'=>'TEST','last_name'=>'Invited','faculty_id'=>'TEST-FAC-1','email'=>'professor-test@example.org','department_id'=>$department,'classes'=>1,'classwork'=>1,'messages'=>1,'activation'=>1]);
 test_assert($create['ok']&&!empty($create['data']['activationLink']),'Professor account and activation link created');
 $invited=kns_portal_one($db,"SELECT * FROM user WHERE faculty_id='TEST-FAC-1'");
 test_assert($invited['account_status']==='pending'&&$invited['password']===null,'Invitation starts pending without plaintext password');
 $outbox=kns_portal_one($db,'SELECT * FROM mail_outbox ORDER BY id DESC LIMIT 1');
 $token=parse_url($create['data']['activationLink'],PHP_URL_QUERY);
 test_assert(!str_contains($outbox['encrypted_body'],substr($token,6)),'Queued email protects the activation token');
 $originalToken=$token;
 $rename=test_post('admin_action.php?action=professor_save',$admin,['id'=>$invited['id'],'first_name'=>'TEST','last_name'=>'Invited','faculty_id'=>'TEST-FAC-1','email'=>'professor-renamed@example.org','department_id'=>$department,'status'=>'pending','classes'=>1,'classwork'=>1,'messages'=>1]);
 test_assert($rename['ok']&&test_http('account_access.php?'.$originalToken)['status']===400,'Changing professor email revokes the old activation link');
 $reinvite=test_post('admin_action.php?action=professor_save',$admin,['id'=>$invited['id'],'first_name'=>'TEST','last_name'=>'Invited','faculty_id'=>'TEST-FAC-1','email'=>'professor-renamed@example.org','department_id'=>$department,'status'=>'pending','classes'=>1,'classwork'=>1,'messages'=>1,'activation'=>1]);
 test_assert($reinvite['ok']&&!empty($reinvite['data']['activationLink']),'Reissued invitation targets the updated email');
 $token=parse_url($reinvite['data']['activationLink'],PHP_URL_QUERY);
 $access=test_http('account_access.php?'.$token);
 $activate=test_http('account_access.php',$access['cookie'],['csrf_token'=>test_csrf($access),'token'=>substr($token,6),'password'=>'ProfessorPassword!2026','confirm_password'=>'ProfessorPassword!2026']);
 test_assert($activate['status']===200&&str_contains($activate['body'],'Your password was saved'),'Activation saves a hashed password');
 test_assert(test_http('account_access.php?'.$token)['status']===400,'Used activation token rejected');
 test_login('TEST-FAC-1','ProfessorPassword!2026');
 $profSave=['id'=>$ids['test-professor'],'first_name'=>'TEST','last_name'=>'Professor','faculty_id'=>'TEST-FAC-ORIGINAL','email'=>'original-professor@example.org','department_id'=>$department,'status'=>'active','classes'=>1,'classwork'=>1,'grades'=>1,'messages'=>1];
 test_assert(test_post('admin_action.php?action=professor_save',$admin,$profSave)['ok'],'Professor edit and permissions persist');
 $subjectInput=['code'=>'TEST 101','title'=>'TEST persistent subject','department_id'=>$department,'professor'=>$ids['test-professor'],'section'=>'TEST-A','year'=>'2099-2100','semester'=>'First Semester','capacity'=>1];
 test_assert(test_post('admin_action.php?action=subject_save',$admin,$subjectInput)['ok'],'Create persistent subject and section');
 $subject=(int)kns_portal_one($db,"SELECT id FROM subjects WHERE subject_code='TEST 101'")['id'];
 $section=(int)kns_portal_one($db,"SELECT id FROM subject_sections WHERE subject_id=? AND section='TEST-A'",[$subject])['id'];
 test_assert(test_post('admin_action.php?action=subject_save',$admin,$subjectInput)['status']===409,'Duplicate section rejected transactionally');
 test_assert(test_post('admin_action.php?action=subject_save',$admin,[...$subjectInput,'professor'=>$ids['test-other-professor'],'section'=>'TEST-B'])['ok'],'Same subject can have separately assigned sections');
 test_assert(test_post('admin_action.php?action=enroll',$admin,['student_id'=>$registeredId,'section_id'=>$section,'course_id'=>$course])['ok'],'Verified enrollment persists');
 test_assert(test_post('admin_action.php?action=enroll',$admin,['student_id'=>$ids['test-student'],'section_id'=>$section])['status']===422,'Full section rejects extra enrollment');
 test_assert(test_http('professor_classwork.php?id='.$subject.'&class='.$section,$other['cookie'])['status']===403,'Professor cannot open another professor section');
 test_assert(test_post('admin_action.php?action=message_send',$admin,['professor_id'=>$ids['test-professor'],'message'=>'TEST saved faculty message'])['ok'],'Admin message persists');
 test_assert(test_post('portal_action.php',$prof,['action'=>'faculty_message','admin_id'=>$ids['test-admin'],'message'=>'TEST saved professor reply'])['ok'],'Professor reply persists');
 test_assert(test_post('admin_action.php?action=message_send',$admin,['professor_id'=>$registeredId,'message'=>'Must fail'])['status']===422,'Faculty messaging rejects student recipient');
 test_assert(test_post('portal_action.php',$prof,['action'=>'faculty_read','admin_id'=>$ids['test-admin']])['ok'],'Professor marks received messages read');
 $file=$private.'/attachment.txt';file_put_contents($file,'TEST private faculty attachment');
 test_assert(test_post('admin_action.php?action=message_send',$admin,['professor_id'=>$ids['test-professor'],'message'=>'TEST attachment','files[0]'=>new CURLFile($file,'text/plain','attachment.txt')])['ok'],'Faculty attachment stored privately');
 $attachmentId=(int)kns_portal_one($db,'SELECT id FROM admin_message_attachments ORDER BY id DESC LIMIT 1')['id'];
 $download=test_http('faculty_attachment.php?id='.$attachmentId,$prof['cookie']);
 test_assert($download['status']===200&&$download['body']==='TEST private faculty attachment','Intended professor can download attachment');
 test_assert(test_http('faculty_attachment.php?id='.$attachmentId,$other['cookie'])['status']===404,'Unrelated professor attachment access denied');
 test_assert(test_http('faculty_attachment.php?id='.$attachmentId,$student['cookie'])['status']===403,'Student faculty attachment access denied');
 test_assert(test_post('admin_action.php?action=profile',$admin,['first_name'=>'TEST','last_name'=>'Admin','email'=>'admin-test@example.org','contact'=>'0900 000 0000'])['ok'],'Admin profile persists');
 test_assert(test_post('admin_action.php?action=preferences',$admin,['messages'=>1])['ok'],'Notification preferences persist');
 $bootstrap=json_decode(test_http('admin_action.php',$admin['cookie'])['body'],true)['data'];
 test_assert($bootstrap['profile']['name']==='TEST Admin'&&(int)$bootstrap['preferences']['invitations']===0,'Reload reads saved profile and preference values');
 $questions=json_encode([['text'=>'TEST question?','type'=>'multiple_choice','points'=>2,'choices'=>['Yes','No'],'answer'=>'0']]);
 $publish=test_http('professor_classwork.php?id='.$subject.'&class='.$section,$prof['cookie'],['csrf_token'=>$prof['csrf'],'work_id'=>0,'action'=>'publish','reviewed'=>1,'title'=>'TEST published quiz','instructions'=>'TEST instructions','category'=>'quizzes','context'=>json_encode(['TEST-A','2099-2100','First Semester']),'questions_json'=>$questions,'due_at'=>'2099-08-10T12:00']);
 test_assert($publish['status']===303,'Professor publishes classwork with deadline');
 $work=kns_portal_one($db,"SELECT * FROM subject_classwork WHERE title='TEST published quiz'");
 test_assert($work&&$work['due_at']==='2099-08-10 12:00:00','Deadline persists');
 $enrollment=(int)kns_portal_one($db,'SELECT id FROM subject_enrollments WHERE student_id=? AND subject_id=?',[$registeredId,$subject])['id'];
 $answer=test_http('classwork_submit.php',$registered['cookie'],['csrf_token'=>$registered['csrf'],'subject_id'=>$subject,'enrollment_id'=>$enrollment,'classwork_id'=>$work['id'],'answers[0]'=>'0']);
 test_assert($answer['status']===303,'Student answers persist');
 $submission=(int)kns_portal_one($db,'SELECT id FROM subject_classwork_submissions WHERE classwork_id=?',[$work['id']])['id'];
 test_assert(test_post('portal_action.php',$prof,['action'=>'grade','submission_id'=>$submission,'score'=>'2','feedback'=>'TEST reviewed'])['ok'],'Professor grade and feedback persist');
 test_assert(test_post('portal_action.php',$prof,['action'=>'grade','submission_id'=>$submission,'score'=>'3','feedback'=>'Invalid'])['status']===422,'Grade cannot exceed available points');
 test_assert(test_post('portal_action.php',$other,['action'=>'grade','submission_id'=>$submission,'score'=>'1','feedback'=>'Forbidden'])['status']===403,'Unauthorized grading denied');
 $view=test_http('subjectview.php?id='.$subject.'&enrollment='.$enrollment,$registered['cookie']);
 test_assert(str_contains($view['body'],'Grade: 2.00 / 2.00')&&str_contains($view['body'],'TEST reviewed'),'Student sees saved grade and feedback');
 $dashboard=test_http('studenthome.php',$registered['cookie']);
 test_assert($dashboard['status']===200&&str_contains($dashboard['body'],'100%')&&!str_contains($dashboard['body'],'Submission data not connected'),'Student dashboard computes real completion');
 $disabled=[...$profSave,'classwork'=>0];
 test_assert(test_post('admin_action.php?action=professor_save',$admin,$disabled)['ok'],'Classwork management permission can be revoked');
 test_assert(test_http('professor_classwork.php?id='.$subject.'&class='.$section,$prof['cookie'])['status']===403,'Revoked professor cannot open the classwork builder');
 test_assert(test_http('classwork_import.php',$prof['cookie'],['csrf_token'=>$prof['csrf'],'subject_id'=>$subject])['status']===403,'Revoked professor cannot bypass permission through the importer');
 test_assert(test_post('admin_action.php?action=professor_save',$admin,$profSave)['ok'],'Classwork management permission can be restored');
 $report=json_decode(test_http('admin_action.php?action=report&year=2099-2100&semester=First%20Semester',$admin['cookie'])['body'],true);
 test_assert($report['ok']&&$report['data']['sections']===2,'Report filters aggregate actual section records');
 $invalidRange=test_http('admin_action.php?action=report&from=invalid&to=2099-01-01',$admin['cookie']);
 test_assert($invalidRange['status']===422,'Report rejects invalid date bounds');
 $dated=json_decode(test_http('admin_action.php?action=report&from=2000-01-01&to=2000-12-31',$admin['cookie'])['body'],true);
 test_assert($dated['ok']&&$dated['data']['messages']===0,'Report message activity honors explicit dates');
 $gradingPage=test_http('professor_classwork.php?id='.$subject.'&responses='.$work['id'],$prof['cookie']);
 test_assert($gradingPage['status']===200&&str_contains($gradingPage['body'],'name="score"'),'Professor submission view exposes authorized grading form');
 $export=test_http('admin_action.php?action=export',$admin['cookie']);
 test_assert($export['status']===200&&str_contains($export['headers'],'text/csv')&&str_contains($export['body'],'KNS Portal faculty report'),'Report exports actual CSV data');
 test_assert(test_post('portal_action.php',$publicSession,['action'=>'contact','category'=>'admission','full_name'=>'TEST Applicant','email'=>'applicant@example.org','message'=>'TEST admission inquiry'])['ok'],'Admission request persists');
 $request=(int)kns_portal_one($db,'SELECT id FROM portal_requests ORDER BY id DESC LIMIT 1')['id'];
 test_assert(test_post('admin_action.php?action=request_reviewed',$admin,['id'=>$request])['ok'],'Admin reviews saved admission request');
 test_assert(test_post('portal_action.php',$publicSession,['action'=>'recover','identifier'=>'student-test@example.org'])['ok'],'Recovery request queues an account link');
 $mail=kns_portal_one($db,'SELECT * FROM mail_outbox ORDER BY id DESC LIMIT 1');$packed=base64_decode($mail['encrypted_body']);
 $body=openssl_decrypt(substr($packed,28),'aes-256-gcm',kns_portal_key(),OPENSSL_RAW_DATA,substr($packed,0,12),substr($packed,12,16));
 preg_match('/token=([a-f0-9]{64})/',$body,$match);
 $reset=test_http('account_access.php?token='.$match[1]);
 $changed=test_http('account_access.php',$reset['cookie'],['csrf_token'=>test_csrf($reset),'token'=>$match[1],'password'=>'ResetStudentPassword!2026','confirm_password'=>'ResetStudentPassword!2026']);
 test_assert($changed['status']===200&&str_contains($changed['body'],'Your password was saved'),'Recovery token resets password');
 test_assert(test_http('account_access.php?token='.$match[1])['status']===400,'Reset token cannot be replayed');
 test_assert(test_http('studenthome.php',$registered['cookie'])['status']===403,'Password reset invalidates prior authenticated session');
 test_login('2099-00001','ResetStudentPassword!2026');
 $sourceCheck=kns_subject_db();
 foreach($before as $table=>$checksum)test_assert((string)$sourceCheck->query('CHECKSUM TABLE '.$table)->fetch_row()[1]===(string)$checksum,'Production table unchanged: '.$table);
 if($keep){
  $state=['database'=>$name,'private'=>$private,'base'=>$base,'port'=>$port,'cookie'=>$admin['cookie'],'csrf'=>$admin['csrf'],'professor'=>$ids['test-professor'],'section'=>$section,'subject'=>$subject];
  file_put_contents($private.'/state.json',json_encode($state,JSON_PRETTY_PRINT));
  echo 'STATE: '.$private.'/state.json'.PHP_EOL;
 }
 echo 'Passed '.$checks.' integration checks.'.PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'FAIL: '.$error->getMessage().PHP_EOL.'Test log: '.$private.'/http.log'.PHP_EOL);$keep=true;$failed=true;}
finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}
 if(!$keep){$db->query('SET FOREIGN_KEY_CHECKS=0');foreach(array_reverse($db->query('SHOW TABLES')->fetch_all(MYSQLI_NUM)) as $table)$db->query('DELETE FROM '.$table[0]);$db->query('SET FOREIGN_KEY_CHECKS=1');}
}
if(!empty($failed))exit(1);
