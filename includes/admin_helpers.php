<?php

function admin_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    $columns = [
        "ALTER TABLE users ADD COLUMN username VARCHAR(16) NULL",
        "ALTER TABLE users ADD COLUMN support_department VARCHAR(120) NULL",
        "ALTER TABLE users ADD COLUMN admin_type VARCHAR(20) NULL",
        "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0",
    ];

    foreach($columns as $sql){
        try{
            $pdo->exec($sql);
        }catch(PDOException $e){
        }
    }

    try{
        $pdo->exec("
            UPDATE users
            SET admin_type='super'
            WHERE role='admin'
            AND (admin_type IS NULL OR admin_type='')
        ");
    }catch(PDOException $e){
    }

    try{
        $stmt = $pdo->query("
            SELECT id
            FROM users
            WHERE role='admin'
            AND (username IS NULL OR username='')
            ORDER BY id ASC
        ");

        foreach($stmt->fetchAll() as $admin){
            $username = 'admin' . str_pad((string)$admin['id'], 3, '0', STR_PAD_LEFT);

            $update = $pdo->prepare("UPDATE users SET username=? WHERE id=?");
            $update->execute([$username, $admin['id']]);
        }
    }catch(PDOException $e){
    }
}

function admin_is_super(): bool
{
    return ($_SESSION['admin_type'] ?? 'super') === 'super';
}

function admin_is_support(): bool
{
    return ($_SESSION['admin_type'] ?? '') === 'support';
}

function admin_require_super(): void
{
    if(!admin_is_super()){
        http_response_code(403);
        die('دسترسی غیر مجاز');
    }
}

function admin_support_allowed_pages(): array
{
    return [
        'index.php',
        'tickets.php',
        'view-ticket.php',
        'closed-tickets.php',
        'announcements.php',
        'announcement-view.php',
        'announcement-list.php',
        'reminders.php',
        'trainings.php',
        'users.php',
        'user-view.php',
        'change-password.php',
        'push-subscribe.php',
    ];
}

function admin_can_access_page(string $page): bool
{
    if(admin_is_super()){
        return true;
    }

    return in_array($page, admin_support_allowed_pages(), true);
}

function admin_require_page_access(string $page): void
{
    if(!admin_can_access_page($page)){
        http_response_code(403);
        die('دسترسی غیر مجاز');
    }
}

function admin_load_session_user(PDO $pdo): void
{
    if(empty($_SESSION['user_id'])){
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, fullname, role, admin_type, support_department,
               username, must_change_password, status
        FROM users
        WHERE id=?
    ");

    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if(!$user || $user['role'] !== 'admin'){
        return;
    }

    $_SESSION['role'] = 'admin';
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['admin_type'] = $user['admin_type'] ?: 'super';
    $_SESSION['support_department'] = $user['support_department'] ?? '';
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['must_change_password'] = (int)$user['must_change_password'];
}

function admin_validate_username(string $username): ?string
{
    if(!preg_match('/^[a-zA-Z0-9._-]{6,16}$/', $username)){
        return 'نام کاربری باید ۶ تا ۱۶ کاراکتر لاتین باشد و فقط شامل حروف، اعداد و . _ - باشد';
    }

    return null;
}

function admin_validate_password(string $password): ?string
{
    if(strlen($password) < 8){
        return 'رمز عبور باید حداقل ۸ کاراکتر باشد';
    }

    if(!preg_match('/[a-zA-Z]/', $password)){
        return 'رمز عبور باید حداقل یک حرف لاتین داشته باشد';
    }

    if(!preg_match('/[0-9]/', $password)){
        return 'رمز عبور باید حداقل یک عدد داشته باشد';
    }

    if(!preg_match('/^[a-zA-Z0-9+@_\-.]+$/', $password)){
        return 'رمز عبور فقط می‌تواند شامل حروف لاتین، عدد و کاراکترهای + @ _ - . باشد';
    }

    preg_match_all('/[+@_\-.]/', $password, $matches);
    $specialCount = count($matches[0] ?? []);

    if($specialCount > 5){
        return 'حداکثر ۵ کاراکتر خاص (+ @ _ - .) مجاز است';
    }

    return null;
}

function admin_display_name(array $user): string
{
    $name = trim($user['fullname'] ?? '');

    if(($user['sender'] ?? '') === 'admin' || ($user['role'] ?? '') === 'admin'){
        $dept = trim($user['support_department'] ?? '');

        if($name && $dept){
            return $name . ' - ' . $dept;
        }

        return $name ?: 'پشتیبان';
    }

    return $name ?: 'کاربر';
}

function admin_users_readonly(): bool
{
    return admin_is_support();
}

function admin_announcements_readonly(): bool
{
    return admin_is_support();
}

function admin_require_write_access(): void
{
    if(admin_is_support()){
        http_response_code(403);
        die('دسترسی غیر مجاز');
    }
}
