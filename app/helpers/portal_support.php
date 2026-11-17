<?php
require_once __DIR__ . '/subject_support.php';
date_default_timezone_set('Asia/Manila');

function kns_portal_query(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $statement = $db->prepare($sql);
    if ($params) $statement->bind_param(str_repeat('s', count($params)), ...$params);
    $statement->execute();
    return $statement;
}
function kns_portal_rows(mysqli $db, string $sql, array $params = []): array
{
    return kns_portal_query($db, $sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function kns_portal_one(mysqli $db, string $sql, array $params = []): ?array
{
    return kns_portal_rows($db, $sql, $params)[0] ?? null;
}
function kns_portal_user(mysqli $db, array $roles = []): array
{
    $rows = kns_portal_rows($db, 'SELECT * FROM user WHERE username=?', [$_SESSION['username'] ?? '']);
    if (count($rows) !== 1 || $rows[0]['account_status'] !== 'active'
        || $rows[0]['usertype'] !== ($_SESSION['usertype'] ?? '')
        || ($roles && !in_array($rows[0]['usertype'], $roles, true))) {
        http_response_code(403);
        throw new InvalidArgumentException('Please sign in with an authorized active account.');
    }
    if (isset($_SESSION['portal_password_version']) && !hash_equals((string)$_SESSION['portal_password_version'], (string)$rows[0]['password_hash'])) {
        http_response_code(403); throw new InvalidArgumentException('Your password changed. Please sign in again.');
    }
    $_SESSION['portal_password_version']=(string)$rows[0]['password_hash'];
    return $rows[0];
}
function kns_portal_text($value, int $max, string $label, bool $required = true): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) throw new InvalidArgumentException($label . ' is invalid.');
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max) throw new InvalidArgumentException('Check ' . $label . ' (maximum ' . $max . ' characters).');
    return $value;
}
function kns_portal_email($value): string
{
    $value = strtolower(kns_portal_text($value, 191, 'email'));
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
    return $value;
}
function kns_portal_password($value): string
{
    if (!is_string($value) || strlen($value) < 12 || strlen($value) > 72) throw new InvalidArgumentException('Use a password of 12 to 72 bytes.');
    return password_hash($value, PASSWORD_DEFAULT);
}
function kns_portal_csrf(): void
{
    if (!kns_subject_csrf_valid($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(403);
        throw new InvalidArgumentException('Your form expired. Reload the page and try again.');
    }
}
function kns_portal_name(array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'];
}
function kns_portal_notify(mysqli $db, int $id, string $category, string $title, string $body): void
{
    $preference = kns_portal_one($db, 'SELECT * FROM notification_preferences WHERE user_id=?', [$id]);
    if ($category !== 'security' && $preference && empty($preference[$category])) return;
    kns_portal_query($db, 'INSERT INTO notifications(user_id,category,title,body) VALUES(?,?,?,?)', [$id,$category,$title,$body]);
}
function kns_portal_key(): string
{
    $path = dirname(kns_subject_storage_dir(true)) . DIRECTORY_SEPARATOR . 'portal-mail.key';
    if (!is_file($path)) {
        $handle = @fopen($path, 'x');
        if ($handle) { fwrite($handle, random_bytes(32)); fclose($handle); @chmod($path, 0600); }
    }
    $key = @file_get_contents($path);
    if ($key === false || strlen($key) !== 32) throw new RuntimeException('Private email key is unavailable.');
    return $key;
}
function kns_portal_rate_limit(mysqli $db, string $action, string $identifier, int $limit = 10): void
{
    $identifierHash = hash_hmac('sha256', strtolower($identifier), kns_portal_key());
    $ipHash = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local', kns_portal_key());
    $count = kns_portal_one($db, "SELECT COUNT(*) AS total FROM auth_attempts WHERE action=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND (identifier_hash=? OR ip_hash=?)", [$action,$identifierHash,$ipHash]);
    if ((int)$count['total'] >= $limit) { http_response_code(429); throw new InvalidArgumentException('Too many attempts. Please wait 15 minutes.'); }
    kns_portal_query($db,'INSERT INTO auth_attempts(identifier_hash,ip_hash,action) VALUES(?,?,?)',[$identifierHash,$ipHash,$action]);
}
function kns_portal_token(mysqli $db, array $user, string $purpose): string
{
    if (empty($user['email'])) throw new InvalidArgumentException('Set a valid account email before requesting an account link.');
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $hours = $purpose === 'activation' ? 48 : 1;
    kns_portal_query($db, 'UPDATE account_tokens SET used_at=NOW() WHERE user_id=? AND purpose=? AND used_at IS NULL', [$user['id'],$purpose]);
    kns_portal_query($db, "INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL $hours HOUR))", [$user['id'],$purpose,$hash]);
    // A configured base URL avoids trusting the incoming Host header.
    $base = rtrim(getenv('KNS_PORTAL_URL') ?: 'http://localhost/studentportal', '/');
    $link = $base . '/account_access.php?token=' . $token;
    $body = ($purpose === 'activation' ? 'Activate your KNS Portal account' : 'Reset your KNS Portal password')
        . "\n\nOpen this link within " . $hours . " hour(s):\n" . $link . "\n\nIf you did not request this, contact the school office.";
    $iv = random_bytes(12);
    $encrypted = openssl_encrypt($body, 'aes-256-gcm', kns_portal_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) throw new RuntimeException('Could not protect the account email.');
    kns_portal_query($db, 'INSERT INTO mail_outbox(user_id,recipient,subject,encrypted_body) VALUES(?,?,?,?)',
        [$user['id'],$user['email'],$purpose === 'activation' ? 'KNS Portal account activation' : 'KNS Portal password reset',base64_encode($iv . $tag . $encrypted)]);
    return $link;
}
function kns_portal_permission(mysqli $db, int $professorId, string $permission): bool
{
    if (!in_array($permission, ['view_classes','manage_classwork','record_grades','admin_messaging'], true)) return false;
    $row = kns_portal_one($db, 'SELECT * FROM professor_permissions WHERE professor_id=?', [$professorId]);
    return $row ? (bool)$row[$permission] : $permission !== 'record_grades';
}
function kns_portal_summary(mysqli $db, string $year = '', string $semester = '', string $department = '', string $from = '', string $to = ''): array
{
    $sections = kns_portal_rows($db, 'SELECT ss.*,s.subject_name,s.subject_code,s.department_id FROM subject_sections ss JOIN subjects s ON s.id=ss.subject_id');
    $filtered = array_values(array_filter($sections, fn($s) => (!$year || $s['academic_year']===$year) && (!$semester || $s['semester']===$semester) && (!$department || (string)$s['department_id']===$department)));
    $subjectRows = kns_portal_rows($db, 'SELECT id,department_id FROM subjects');
    if ($year || $semester) $subjectRows = array_values(array_filter($subjectRows, fn($s)=> in_array($s['id'],array_column($filtered,'subject_id'))));
    if ($department) $subjectRows=array_values(array_filter($subjectRows,fn($s)=>(string)$s['department_id']===$department));
    $covered = array_unique(array_column(array_filter($filtered,fn($s)=>!empty($s['professor_id'])),'subject_id'));
    $professors = kns_portal_rows($db, "SELECT id,account_status,department_id FROM user WHERE usertype='professor'");
    if ($department) $professors=array_values(array_filter($professors,fn($p)=>(string)$p['department_id']===$department));
    $messages=kns_portal_rows($db,'SELECT m.*,p.department_id FROM admin_professor_messages m JOIN user p ON p.id=m.professor_id ORDER BY m.id');
    if ($department) $messages=array_values(array_filter($messages,fn($m)=>(string)$m['department_id']===$department));
    $start=null;$monthsCount=6;
    if($from!=='' || $to!=='') {
        $fromDate=DateTimeImmutable::createFromFormat('!Y-m-d',$from);
        $toDate=DateTimeImmutable::createFromFormat('!Y-m-d',$to);
        if(!$fromDate || !$toDate || $fromDate->format('Y-m-d')!==$from || $toDate->format('Y-m-d')!==$to || $toDate<$fromDate || $fromDate->diff($toDate)->days>730)
            throw new InvalidArgumentException('Choose both message dates in order, within a two-year range.');
        $end=$toDate->modify('+1 day')->format('Y-m-d');
        $messages=array_values(array_filter($messages,fn($m)=>$m['created_at'] >= $from && $m['created_at'] < $end));
        $start=$fromDate->modify('first day of this month')->format('Y-m-d');
        $monthsCount=($toDate->format('Y')-$fromDate->format('Y'))*12+(int)$toDate->format('m')-(int)$fromDate->format('m')+1;
    }
    $received=array_filter($messages,fn($m)=>$m['sender_id']===$m['professor_id']);
    $replied=0;
    foreach ($received as $incoming) foreach($messages as $reply) if($reply['admin_id']===$incoming['admin_id'] && $reply['professor_id']===$incoming['professor_id'] && $reply['sender_id']===$reply['admin_id'] && (int)$reply['id']>(int)$incoming['id']) { $replied++;break; }
    $months=[];
    for($i=0;$i<$monthsCount;$i++) {
        $time=$start?strtotime($start." +$i months"):strtotime('first day of -'.(5-$i).' month');
        $key=date('Y-m',$time);$months[$key]=['label'=>date('M Y',$time),'subjects'=>0,'sections'=>0];
    }
    foreach($filtered as $section) { $key=substr($section['created_at'],0,7); if(isset($months[$key])) $months[$key]['sections']++; }
    $subjectDates=kns_portal_rows($db,'SELECT id,created_at FROM subjects WHERE created_at IS NOT NULL');
    foreach($subjectDates as $subject) { $key=substr($subject['created_at'],0,7); if(isset($months[$key]) && in_array($subject['id'],array_column($subjectRows,'id'))) $months[$key]['subjects']++; }
    return ['professors'=>count($professors),'active'=>count(array_filter($professors,fn($p)=>$p['account_status']==='active')),
        'pending'=>count(array_filter($professors,fn($p)=>$p['account_status']==='pending')),'subjects'=>count($subjectRows),'covered'=>count($covered),
        'sections'=>count($filtered),'unassigned'=>count(array_filter($filtered,fn($s)=>!$s['professor_id'])),
        'conversations'=>count(array_unique(array_map(fn($m)=>$m['admin_id'].':'.$m['professor_id'],$messages))),
        'message_scope'=>$from!==''?'Message dates: '.$from.' to '.$to:'Message activity: all dates','messages'=>count($messages),'unread'=>count(array_filter($received,fn($m)=>!$m['read_at'])),
        'reply_rate'=>count($received)?round($replied/count($received)*100):null,'activity'=>array_values($months)];
}
