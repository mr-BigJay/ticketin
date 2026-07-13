<?php

function user_to_english_digits(string $value): string
{
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $english = ['0','1','2','3','4','5','6','7','8','9'];

    $value = str_replace($persian, $english, $value);

    return str_replace($arabic, $english, $value);
}

function user_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    $columns = [
        "ALTER TABLE users ADD COLUMN national_code VARCHAR(10) NULL",
        "ALTER TABLE users ADD COLUMN job_title_id INT NULL",
        "ALTER TABLE users ADD COLUMN job_title VARCHAR(255) NULL",
        "ALTER TABLE users ADD COLUMN organization_node_id INT NULL",
    ];

    foreach($columns as $sql){
        try{
            $pdo->exec($sql);
        }catch(PDOException $e){
        }
    }

    try{
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_organization_rel (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                center_id INT NOT NULL,
                node_id INT NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user_org_user (user_id),
                KEY idx_user_org_node (node_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }catch(PDOException $e){
    }

    $drops = [
        'uniq_users_mobile',
        'uniq_users_national_code',
        'mobile',
    ];

    foreach($drops as $index){
        try{
            $pdo->exec("ALTER TABLE users DROP INDEX `{$index}`");
        }catch(PDOException $e){
        }
    }

    $indexes = [
        "ALTER TABLE users ADD UNIQUE KEY uniq_users_mobile_role (mobile, role)",
        "ALTER TABLE users ADD UNIQUE KEY uniq_users_national_code_role (national_code, role)",
    ];

    foreach($indexes as $sql){
        try{
            $pdo->exec($sql);
        }catch(PDOException $e){
        }
    }
}

function user_registration_exists(PDO $pdo, string $mobile, string $nationalCode): bool
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE role='user'
        AND (mobile=? OR national_code=?)
        LIMIT 1
    ");

    $stmt->execute([$mobile, $nationalCode]);

    return (bool)$stmt->fetch();
}

function user_is_persian_name(string $name): bool
{
    $name = trim($name);

    if($name === '' || mb_strlen($name, 'UTF-8') < 2){
        return false;
    }

    if(preg_match('/[0-9a-zA-Z]/', $name)){
        return false;
    }

    return (bool)preg_match('/^[\p{Arabic}\s\x{200c}]+$/u', $name);
}

function user_validate_persian_name(string $name, string $label): ?string
{
    if(!user_is_persian_name($name)){
        return $label . ' باید فقط با حروف فارسی وارد شود';
    }

    return null;
}

function user_normalize_national_code(string $value): ?string
{
    $digits = preg_replace(
        '/\D+/',
        '',
        user_to_english_digits(trim($value))
    );

    if($digits === ''){
        return null;
    }

    if(strlen($digits) > 10){
        return null;
    }

    return str_pad($digits, 10, '0', STR_PAD_LEFT);
}

function user_find_by_national_code(PDO $pdo, string $loginInput): ?array
{
    $nationalCode = user_normalize_national_code($loginInput);

    if($nationalCode === null){
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE role='user'
        AND national_code=?
        LIMIT 1
    ");

    $stmt->execute([$nationalCode]);
    $user = $stmt->fetch();

    if($user){
        return $user;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE role='user'
        AND national_code IS NOT NULL
        AND national_code <> ''
    ");

    $stmt->execute();
    $candidates = $stmt->fetchAll();

    foreach($candidates as $candidate){
        $stored = user_normalize_national_code((string)($candidate['national_code'] ?? ''));

        if($stored !== null && $stored === $nationalCode){
            return $candidate;
        }
    }

    return null;
}

function user_validate_national_code(string $value): ?string
{
    $normalized = user_normalize_national_code($value);

    if($normalized === null){
        return 'کد ملی باید فقط عدد و حداکثر ۱۰ رقم باشد';
    }

    if(!preg_match('/^\d{10}$/', $normalized)){
        return 'کد ملی معتبر نیست';
    }

    return null;
}

function user_normalize_mobile(string $value): ?string
{
    $digits = preg_replace(
        '/\D+/',
        '',
        user_to_english_digits(trim($value))
    );

    if(!preg_match('/^09\d{9}$/', $digits)){
        return null;
    }

    return $digits;
}

function user_validate_mobile(string $value): ?string
{
    if(user_normalize_mobile($value) === null){
        return 'شماره موبایل باید با 09 شروع شود و دقیقاً ۱۱ رقم باشد';
    }

    return null;
}

function user_split_fullname(string $fullname): array
{
    $fullname = trim(preg_replace('/\s+/u', ' ', $fullname));

    if($fullname === ''){
        return ['', ''];
    }

    $parts = preg_split('/\s+/u', $fullname, 2);

    return [
        (string)($parts[0] ?? ''),
        (string)($parts[1] ?? ''),
    ];
}

function user_identity_lookup_excluding(
    PDO $pdo,
    string $mobile,
    string $nationalCode,
    int $excludeUserId
): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, status, mobile, national_code, fullname
        FROM users
        WHERE role='user'
        AND id <> ?
        AND (mobile=? OR national_code=?)
        LIMIT 1
    ");

    $stmt->execute([$excludeUserId, $mobile, $nationalCode]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function user_identity_conflict_message(?array $existing): ?string
{
    if(!$existing){
        return null;
    }

    $status = (string)($existing['status'] ?? '');

    if($status === 'pending'){
        return 'کاربر دیگری با این موبایل یا کد ملی در انتظار تایید است';
    }

    if($status === 'inactive'){
        return 'کاربر دیگری با این اطلاعات وجود دارد و غیرفعال است';
    }

    return 'کاربر دیگری با این شماره موبایل یا کد ملی ثبت شده است';
}

function user_validate_identity_fields(
    string $firstname,
    string $lastname,
    string $mobileRaw,
    string $nationalCodeRaw
): array
{
    $firstname = trim($firstname);
    $lastname = trim($lastname);

    if($firstname === '' || $lastname === ''){
        return ['error' => 'نام و نام خانوادگی الزامی است'];
    }

    if($msg = user_validate_persian_name($firstname, 'نام')){
        return ['error' => $msg];
    }

    if($msg = user_validate_persian_name($lastname, 'نام خانوادگی')){
        return ['error' => $msg];
    }

    $nationalCode = user_normalize_national_code($nationalCodeRaw);

    if($nationalCode === null){
        return [
            'error' => user_validate_national_code($nationalCodeRaw) ?? 'کد ملی معتبر نیست',
        ];
    }

    $mobile = user_normalize_mobile($mobileRaw);

    if($mobile === null){
        return [
            'error' => user_validate_mobile($mobileRaw) ?? 'شماره موبایل معتبر نیست',
        ];
    }

    return [
        'error' => null,
        'fullname' => trim($firstname . ' ' . $lastname),
        'mobile' => $mobile,
        'national_code' => $nationalCode,
    ];
}

function user_validate_password(string $password): ?string
{
    if(strlen($password) < 8){
        return 'رمز عبور باید حداقل ۸ کاراکتر باشد';
    }

    return null;
}

function user_update_password(PDO $pdo, int $userId, string $password): ?string
{
    if($msg = user_validate_password($password)){
        return $msg;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='user'");
    $stmt->execute([$userId]);

    if(!$stmt->fetch()){
        return 'کاربر یافت نشد';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->prepare("
        UPDATE users
        SET password=?
        WHERE id=? AND role='user'
    ")->execute([$hash, $userId]);

    return null;
}

function user_delete_account(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if(!$user || $user['role'] !== 'user'){
        return false;
    }

    $pdo->beginTransaction();

    try{
        $ticketIds = $pdo->prepare("SELECT id FROM tickets WHERE user_id=?");
        $ticketIds->execute([$userId]);
        $ids = $ticketIds->fetchAll(PDO::FETCH_COLUMN);

        if($ids){
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $pdo->prepare("
                DELETE FROM ticket_replies
                WHERE ticket_id IN ({$placeholders})
            ")->execute($ids);
        }

        $pdo->prepare("DELETE FROM ticket_replies WHERE user_id=?")->execute([$userId]);
        $pdo->prepare("DELETE FROM tickets WHERE user_id=?")->execute([$userId]);
        $pdo->prepare("DELETE FROM user_organization_rel WHERE user_id=?")->execute([$userId]);

        $delete = $pdo->prepare("DELETE FROM users WHERE id=? AND role='user'");
        $delete->execute([$userId]);

        if($delete->rowCount() < 1){
            $pdo->rollBack();
            return false;
        }

        $pdo->commit();
        return true;

    }catch(PDOException $e){
        $pdo->rollBack();
        return false;
    }
}
