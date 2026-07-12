<?php

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 30 * 60);
define('MAX_DAILY_REGISTRATIONS', 50);

function security_init_login_attempts(): void
{
    if(!isset($_SESSION['login_attempts'])){
        $_SESSION['login_attempts'] = [];
    }
}

function security_get_login_attempt(string $mobile): array
{
    security_init_login_attempts();

    return $_SESSION['login_attempts'][$mobile] ?? [
        'count' => 0,
        'locked_until' => 0
    ];
}

function security_is_login_locked(string $mobile): bool
{
    $attempt = security_get_login_attempt($mobile);

    if(
        $attempt['locked_until'] > 0
        &&
        $attempt['locked_until'] > time()
    ){
        return true;
    }

    if(
        $attempt['locked_until'] > 0
        &&
        $attempt['locked_until'] <= time()
    ){
        security_clear_login_attempts($mobile);
    }

    return false;
}

function security_get_lock_remaining_minutes(string $mobile): int
{
    $attempt = security_get_login_attempt($mobile);
    $remaining = $attempt['locked_until'] - time();

    if($remaining <= 0){
        return 0;
    }

    return (int)ceil($remaining / 60);
}

function security_record_failed_login(string $mobile): void
{
    security_init_login_attempts();

    $attempt = security_get_login_attempt($mobile);
    $attempt['count']++;

    if($attempt['count'] >= MAX_LOGIN_ATTEMPTS){
        $attempt['locked_until'] = time() + LOGIN_LOCKOUT_SECONDS;
    }

    $_SESSION['login_attempts'][$mobile] = $attempt;
}

function security_clear_login_attempts(string $mobile): void
{
    security_init_login_attempts();
    unset($_SESSION['login_attempts'][$mobile]);
}

function security_get_tehran_day_range(): array
{
    $timezone = new DateTimeZone('Asia/Tehran');
    $start = new DateTime('today', $timezone);
    $end = new DateTime('tomorrow', $timezone);

    return [
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s')
    ];
}

function security_get_today_registration_count(PDO $pdo): int
{
    [$start, $end] = security_get_tehran_day_range();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE role='user'
        AND created_at >= ?
        AND created_at < ?
    ");

    $stmt->execute([$start, $end]);

    return (int)$stmt->fetchColumn();
}

function security_can_register_today(PDO $pdo): bool
{
    return security_get_today_registration_count($pdo) < MAX_DAILY_REGISTRATIONS;
}
