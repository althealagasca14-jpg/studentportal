<?php
require_once dirname(__DIR__).'/helpers/portal_support.php';

function kns_classwork_file_table(string $kind): array
{
    return match($kind){'work'=>['classwork_attachments','classwork_id'],'submission'=>['classwork_submission_files','submission_id'],default=>throw new InvalidArgumentException('Unknown attachment type.')};
}
function kns_classwork_file_rows(mysqli $db,string $kind,int $ownerId): array
{
    [$table,$column]=kns_classwork_file_table($kind);
    return kns_portal_rows($db,"SELECT id,original_name,mime_type,byte_size,attachment_kind FROM ".$table." WHERE ".$column."=? ORDER BY id",[$ownerId]);
}
function kns_classwork_storage(bool $create=false): string
{
    $directory=dirname(kns_subject_storage_dir($create)).DIRECTORY_SEPARATOR.'classwork-attachments';
    if(!is_dir($directory)&&(!$create||!mkdir($directory,0700,true)))throw new RuntimeException('Private classwork storage is unavailable.');
    $resolved=realpath($directory);
    $parent=dirname(kns_subject_storage_dir());
    if(!$resolved||strncasecmp($resolved,$parent.DIRECTORY_SEPARATOR,strlen($parent)+1)!==0)throw new RuntimeException('Invalid private classwork storage.');
    return $resolved;
}
// The caller owns the transaction and removes only newly created files on rollback.
function kns_classwork_store_files(mysqli $db,string $kind,int $ownerId,array $attachments,array &$createdPaths): void
{
    if(!$attachments)return;
    [$table,$column]=kns_classwork_file_table($kind);
    $existing=kns_portal_one($db,'SELECT COUNT(*) AS total FROM '.$table.' WHERE '.$column.'=?',[$ownerId]);
    if((int)$existing['total']+count($attachments)>KNS_SUBJECT_MAX_ATTACHMENTS)throw new InvalidArgumentException('Use at most 3 attachments in total.');
    $directory=kns_classwork_storage(true);
    foreach($attachments as $file){
        do{$name=bin2hex(random_bytes(32)).'.bin';$path=$directory.DIRECTORY_SEPARATOR.$name;}while(file_exists($path));
        if(!move_uploaded_file($file['temporary_path'],$path))throw new RuntimeException('The attachment could not be stored.');
        $createdPaths[]=$path;@chmod($path,0600);
        kns_portal_query($db,'INSERT INTO '.$table.'('.$column.',storage_name,original_name,mime_type,byte_size,attachment_kind) VALUES(?,?,?,?,?,?)',[$ownerId,$name,$file['original_name'],$file['mime_type'],$file['byte_size'],$file['attachment_kind']]);
    }
}
function kns_classwork_cleanup_files(array $paths): void
{
    foreach($paths as $path)if(is_file($path))@unlink($path);
}
function kns_classwork_max_score(array $work,array $questions): float
{
    return ($work['submission_mode']??'online')==='files'?(float)$work['file_points']:(float)array_sum(array_column($questions,'points'));
}
function kns_classwork_answers(array $work,array $input,array $attachments): array
{
    $mode=$work['submission_mode']??'online';
    if($mode==='online'&&$attachments)throw new InvalidArgumentException('This activity accepts online answers only.');
    if(in_array($mode,['files','online_and_files'],true)&&!$attachments)throw new InvalidArgumentException('Attach at least one photo or file before submitting.');
    $questions=json_decode($work['questions_json'],true,64,JSON_THROW_ON_ERROR);
    if($mode==='files')return [];
    $posted=$input['answers']??null;
    if(!is_array($posted)||count($posted)!==count($questions))throw new InvalidArgumentException('Answer every question before submitting.');
    $answers=[];
    foreach($questions as $index=>$question){
        $answer=kns_portal_text($posted[$index]??null,10000,'Answer '.($index+1));
        if($question['type']==='multiple_choice'&&(!ctype_digit($answer)||!isset($question['choices'][(int)$answer])))throw new InvalidArgumentException('Choose a listed choice for question '.($index+1).'.');
        $answers[]=$answer;
    }
    return $answers;
}
function kns_classwork_file_access(mysqli $db,array $user,string $kind,int $id): ?array
{
    if(!in_array($user['usertype']??'', ['student','professor'],true))return null;
    [$table,$column]=kns_classwork_file_table($kind);
    $join=$kind==='submission'?'JOIN subject_classwork_submissions sub ON sub.id=a.submission_id JOIN subject_enrollments owner ON owner.id=sub.enrollment_id JOIN subject_classwork w ON w.id=sub.classwork_id':'JOIN subject_classwork w ON w.id=a.classwork_id';
    $file=kns_portal_one($db,'SELECT a.*,w.subject_id,w.professor_id,w.section,w.academic_year,w.semester,w.status'.($kind==='submission'?',owner.student_id AS owner_student_id':'').' FROM '.$table.' a '.$join."
        JOIN subject_sections ss ON ss.subject_id=w.subject_id AND ss.section=w.section AND ss.academic_year=w.academic_year AND ss.semester=w.semester AND ss.professor_id=w.professor_id
        JOIN user p ON p.id=w.professor_id AND p.usertype='professor' AND p.account_status='active' WHERE a.id=?",[$id]);
    if(!$file)return null;
    if($user['usertype']==='professor'){
        return (int)$file['professor_id']===(int)$user['id']&&kns_portal_permission($db,(int)$user['id'],'view_classes')?$file:null;
    }
    if($file['status']!=='published'||($kind==='submission'&&(int)$file['owner_student_id']!==(int)$user['id']))return null;
    $enrollment=kns_portal_one($db,'SELECT id FROM subject_enrollments WHERE student_id=? AND subject_id=? AND section=? AND academic_year=? AND semester=? AND is_active=1',[$user['id'],$file['subject_id'],$file['section'],$file['academic_year'],$file['semester']]);
    return $enrollment?$file:null;
}
