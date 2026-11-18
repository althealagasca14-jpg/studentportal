<?php
require_once __DIR__ . '/portal_support.php';
require_once dirname(__DIR__) . '/classwork/classwork_support.php';

function kns_classroom_ready(mysqli $db): bool
{
    return (bool)kns_portal_one($db,"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subject_sections' AND COLUMN_NAME='class_code'");
}
function kns_classroom_code(): string
{
    $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $code='';
    for($i=0;$i<10;$i++)$code.=$alphabet[random_int(0,strlen($alphabet)-1)];
    return $code;
}
function kns_classroom_section(mysqli $db,array $user,int $id,bool $lock=false): array
{
    if(!in_array($user['usertype']??'', ['student','professor'],true))throw new InvalidArgumentException('Student or professor access is required.');
    $section=kns_portal_one($db,"SELECT ss.*,s.subject_name,s.description,p.username AS professor_username
        FROM subject_sections ss JOIN subjects s ON s.id=ss.subject_id
        JOIN user p ON p.id=ss.professor_id AND p.usertype='professor' AND p.account_status='active'
        WHERE ss.id=?".($lock?' FOR UPDATE':''),[$id]);
    if(!$section)throw new InvalidArgumentException('This class is unavailable.');
    if($user['usertype']==='professor'){
        if((int)$section['professor_id']!==(int)$user['id']||!kns_portal_permission($db,(int)$user['id'],'view_classes'))throw new InvalidArgumentException('This class is not assigned to you.');
    }else{
        $enrollment=kns_portal_one($db,'SELECT id FROM subject_enrollments WHERE student_id=? AND subject_id=? AND section=? AND academic_year=? AND semester=? AND is_active=1'.($lock?' FOR UPDATE':''),[$user['id'],$section['subject_id'],$section['section'],$section['academic_year'],$section['semester']]);
        if(!$enrollment)throw new InvalidArgumentException('Join this class with the code from your professor first.');
        $section['enrollment_id']=(int)$enrollment['id'];
    }
    return $section;
}
function kns_classroom_classes(mysqli $db,array $user): array
{
    if($user['usertype']==='professor'){
        if(!kns_portal_permission($db,(int)$user['id'],'view_classes'))return [];
        return kns_portal_rows($db,'SELECT ss.*,s.subject_name FROM subject_sections ss JOIN subjects s ON s.id=ss.subject_id WHERE ss.professor_id=? ORDER BY ss.id DESC',[$user['id']]);
    }
    return kns_portal_rows($db,"SELECT ss.*,s.subject_name FROM subject_sections ss JOIN subjects s ON s.id=ss.subject_id
        JOIN subject_enrollments e ON e.subject_id=ss.subject_id AND e.section=ss.section AND e.academic_year=ss.academic_year AND e.semester=ss.semester
        JOIN user p ON p.id=ss.professor_id AND p.account_status='active' AND p.usertype='professor'
        WHERE e.student_id=? AND e.is_active=1 ORDER BY ss.id DESC",[$user['id']]);
}
// Transactions are owned by the action, allowing the complete operation to roll back.
function kns_classroom_create(mysqli $db,array $professor,array $input): int
{
    if($professor['usertype']!=='professor'||!kns_portal_permission($db,(int)$professor['id'],'view_classes'))throw new InvalidArgumentException('Professor class access is required.');
    $section=kns_portal_text($input['section']??'',80,'section');
    $year=kns_portal_text($input['academic_year']??'',20,'academic year');
    $semester=kns_portal_text($input['semester']??'',40,'semester');
    $capacity=null;
    if(($input['capacity']??'')!==''){
        $capacity=filter_var($input['capacity'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>5000]]);
        if($capacity===false)throw new InvalidArgumentException('Capacity must be from 1 to 5,000.');
    }
    $subjectId=filter_var($input['subject_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if($subjectId===false)throw new InvalidArgumentException('Choose a valid subject.');
    if($subjectId){
        if(!kns_classwork_owned_subject($db,(int)$professor['id'],$subjectId,true))throw new InvalidArgumentException('Choose one of your own subjects.');
    }else{
        $name=kns_portal_text($input['subject_name']??'',100,'subject name');
        $description=kns_portal_text($input['description']??'',5000,'description',false);
        $subjectCode=kns_portal_text($input['subject_code']??'',30,'subject code',false);
        kns_portal_query($db,'INSERT INTO subjects(subject_name,instructor,description,subject_code,created_at) VALUES(?,?,?,?,NOW())',[$name,mb_substr(kns_portal_name($professor),0,100),$description,$subjectCode?:null]);
        $subjectId=(int)$db->insert_id;
        kns_portal_query($db,'INSERT INTO subject_professors(subject_id,professor_id) VALUES(?,?)',[$subjectId,$professor['id']]);
    }
    kns_portal_query($db,'INSERT INTO subject_sections(subject_id,professor_id,section,academic_year,semester,capacity,class_code) VALUES(?,?,?,?,?,?,?)',[$subjectId,$professor['id'],$section,$year,$semester,$capacity,kns_classroom_code()]);
    return (int)$db->insert_id;
}
function kns_classroom_post_update(mysqli $db,array $user,int $classId,array $input): void
{
    kns_classroom_section($db,$user,$classId,true);
    if($user['usertype']!=='professor')throw new InvalidArgumentException('Only the professor can publish class updates.');
    kns_portal_query($db,'INSERT INTO class_updates(section_id,author_id,title,body) VALUES(?,?,?,?)',[$classId,$user['id'],kns_portal_text($input['title']??'',180,'title'),kns_portal_text($input['body']??'',8000,'update')]);
}
function kns_classroom_post_group(mysqli $db,array $user,int $classId,array $input): void
{
    $class=kns_classroom_section($db,$user,$classId,true);
    if($user['usertype']==='student'&&(int)($input['classwork_id']??0)){
        kns_classroom_record_question($db,(int)$user['id'],(int)$class['enrollment_id'],(int)$input['classwork_id'],is_string($input['question_topic']??null)?$input['question_topic']:'');
    }
    kns_portal_query($db,'INSERT INTO class_group_messages(section_id,sender_id,message) VALUES(?,?,?)',[$classId,$user['id'],kns_portal_text($input['message']??'',5000,'message')]);
}

function kns_classroom_question_topics(): array
{
    return ['instructions'=>'Instructions','deadline'=>'Deadline','submission'=>'Submitting work','concept'=>'Understanding the topic'];
}
function kns_classroom_record_question(mysqli $db,int $studentId,int $enrollmentId,int $workId,string $topic): void
{
    if(!isset(kns_classroom_question_topics()[$topic]))throw new InvalidArgumentException('Choose a listed question topic.');
    $work=kns_portal_one($db,"SELECT w.id FROM subject_classwork w
        JOIN subject_enrollments e ON e.subject_id=w.subject_id AND e.section=w.section AND e.academic_year=w.academic_year AND e.semester=w.semester
        JOIN user student ON student.id=e.student_id AND student.usertype='student' AND student.account_status='active'
        JOIN subject_sections ss ON ss.subject_id=w.subject_id AND ss.section=w.section AND ss.academic_year=w.academic_year AND ss.semester=w.semester AND ss.professor_id=w.professor_id
        JOIN user professor ON professor.id=w.professor_id AND professor.usertype='professor' AND professor.account_status='active'
        WHERE w.id=? AND e.id=? AND e.student_id=? AND e.is_active=1 AND w.status='published' FOR UPDATE",[$workId,$enrollmentId,$studentId]);
    if(!$work)throw new InvalidArgumentException('That activity is not available in your enrolled class.');
    kns_portal_query($db,'INSERT INTO class_question_signals(classwork_id,enrollment_id,topic) VALUES(?,?,?) ON DUPLICATE KEY UPDATE asked_at=CURRENT_TIMESTAMP',[$workId,$enrollmentId,$topic]);
}
function kns_classroom_question_alerts(mysqli $db,int $professorId): array
{
    if(!kns_portal_one($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='class_question_signals'"))return [];
    return kns_portal_rows($db,"SELECT w.id,w.title,ss.id AS class_id,q.topic,COUNT(DISTINCT e.student_id) AS students
        FROM class_question_signals q JOIN subject_classwork w ON w.id=q.classwork_id
        JOIN subject_enrollments e ON e.id=q.enrollment_id AND e.is_active=1
        JOIN user student ON student.id=e.student_id AND student.usertype='student' AND student.account_status='active'
        JOIN subject_sections ss ON ss.subject_id=w.subject_id AND ss.section=w.section AND ss.academic_year=w.academic_year AND ss.semester=w.semester AND ss.professor_id=w.professor_id
        WHERE w.professor_id=? AND q.asked_at>=NOW()-INTERVAL 48 HOUR AND w.status='published'
        GROUP BY w.id,w.title,ss.id,q.topic HAVING COUNT(DISTINCT e.student_id)>=3 ORDER BY students DESC,w.id DESC LIMIT 20",[$professorId]);
}

function kns_classroom_join(mysqli $db,array $student,$value): int
{
    if($student['usertype']!=='student')throw new InvalidArgumentException('Student access is required.');
    $code=strtoupper(kns_portal_text($value,10,'class code'));
    if(!preg_match('/^[A-HJ-NP-Z2-9]{10}$/D',$code))throw new InvalidArgumentException('Enter the 10-character class code provided by your professor.');
    $section=kns_portal_one($db,"SELECT ss.* FROM subject_sections ss JOIN user p ON p.id=ss.professor_id AND p.usertype='professor' AND p.account_status='active' WHERE ss.class_code=? FOR UPDATE",[$code]);
    if(!$section)throw new InvalidArgumentException('This class code is unavailable.');
    $existing=kns_portal_one($db,'SELECT id,is_active FROM subject_enrollments WHERE student_id=? AND subject_id=? AND section=? AND academic_year=? AND semester=? FOR UPDATE',[$student['id'],$section['subject_id'],$section['section'],$section['academic_year'],$section['semester']]);
    if($existing){
        if(!(int)$existing['is_active'])throw new InvalidArgumentException('This enrollment is inactive. Contact your professor.');
        return (int)$section['id'];
    }
    $count=kns_portal_one($db,'SELECT COUNT(*) AS total FROM subject_enrollments WHERE subject_id=? AND section=? AND academic_year=? AND semester=? AND is_active=1',[$section['subject_id'],$section['section'],$section['academic_year'],$section['semester']]);
    if($section['capacity']!==null&&(int)$count['total']>=(int)$section['capacity'])throw new InvalidArgumentException('This section has reached its capacity.');
    kns_portal_query($db,'INSERT INTO subject_enrollments(student_id,subject_id,section,academic_year,semester,is_active,course_id) VALUES(?,?,?,?,?,1,?)',[$student['id'],$section['subject_id'],$section['section'],$section['academic_year'],$section['semester'],$student['course_id']??null]);
    return (int)$section['id'];
}
