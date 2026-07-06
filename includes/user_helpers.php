<?php

function user_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

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
    $digits = preg_replace('/\D+/', '', trim($value));

    if($digits === ''){
        return null;
    }

    if(strlen($digits) > 10){
        return null;
    }

    return str_pad($digits, 10, '0', STR_PAD_LEFT);
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
    $digits = preg_replace('/\D+/', '', trim($value));

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
