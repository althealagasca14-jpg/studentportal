<?php
require_once __DIR__ . '/portal_support.php';

// The public action owns the transaction; callers cannot choose an account role.
function kns_register_professor(mysqli $db, array $input): int
{
    $faculty = kns_portal_text($input['faculty_id'] ?? '', 50, 'faculty ID');
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,49}$/D', $faculty)) {
        throw new InvalidArgumentException('Use a faculty ID of 2 to 50 letters, numbers, dots, underscores or hyphens.');
    }
    $name = kns_portal_text($input['full_name'] ?? '', 120, 'full name');
    $names = preg_split('/\\s+/', $name, 2);
    $first = $names[0]; $last = $names[1] ?? '';
    if (mb_strlen($first) > 60 || mb_strlen($last) > 60) throw new InvalidArgumentException('Use up to 60 characters for each name.');
    $email = kns_portal_email($input['email'] ?? '');
    // Login accepts all four identifiers; reject ambiguous legacy cross-column matches.
    $existing=kns_portal_one($db,'SELECT id FROM user WHERE username IN (?,?) OR email IN (?,?) OR faculty_id IN (?,?) OR student_number IN (?,?) LIMIT 1 FOR UPDATE',[$faculty,$email,$faculty,$email,$faculty,$email,$faculty,$email]);
    if($existing)throw new InvalidArgumentException('That faculty ID or email conflicts with an existing account identifier.');
    $hash = kns_portal_password($input['password'] ?? '');
    kns_portal_query($db, "INSERT INTO user(username,faculty_id,usertype,account_status,first_name,last_name,email,password,password_hash,created_at,password_changed_at)
        VALUES(?,?,'professor','active',?,?,?,NULL,?,NOW(),NOW())", [$faculty,$faculty,$first,$last,$email,$hash]);
    $id = (int)$db->insert_id;
    kns_portal_query($db, 'INSERT INTO professor_permissions(professor_id,view_classes,manage_classwork,record_grades,admin_messaging) VALUES(?,1,1,1,1)', [$id]);
    kns_portal_notify($db,$id,'security','Account created','Your professor account is ready. Sign in with your faculty ID.');
    return $id;
}
