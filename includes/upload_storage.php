<?php

require_once __DIR__ . '/jdatetime.class.php';

function upload_storage_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    try{
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN upload_code VARCHAR(6) NULL
        ");
    }catch(PDOException $e){
    }

    try{
        $pdo->exec("
            ALTER TABLE users
            ADD UNIQUE KEY uniq_users_upload_code (upload_code)
        ");
    }catch(PDOException $e){
    }
}

function upload_storage_to_english_digits(string $value): string
{
    return str_replace(
        ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
        ['0','1','2','3','4','5','6','7','8','9'],
        $value
    );
}

function upload_storage_jalali_date_folder(?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();

    $jDate = new jDateTime(true, true, 'Asia/Tehran');

    return upload_storage_to_english_digits(
        $jDate->date('Ymd', $timestamp)
    );
}

function upload_storage_base_dir(): string
{
    return dirname(__DIR__) . '/uploads';
}

function upload_storage_ensure_date_dir(string $dateFolder): string
{
    if(!preg_match('/^\d{8}$/', $dateFolder)){
        throw InvalidArgumentException('Invalid upload date folder');
    }

    $dir = upload_storage_base_dir() . '/' . $dateFolder;

    if(!is_dir($dir)){
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function upload_storage_generate_user_code(): string
{
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $letterCount = random_int(2, 4);
    $chars = [];

    for($i = 0; $i < $letterCount; $i++){
        $chars[] = $letters[random_int(0, strlen($letters) - 1)];
    }

    for($i = 0; $i < (6 - $letterCount); $i++){
        $chars[] = (string)random_int(0, 9);
    }

    shuffle($chars);

    return implode('', $chars);
}

function upload_storage_get_user_code(PDO $pdo, int $userId): string
{
    upload_storage_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT upload_code
        FROM users
        WHERE id=?
        LIMIT 1
    ");

    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!empty($row['upload_code'])){
        return strtoupper($row['upload_code']);
    }

    do{
        $code = upload_storage_generate_user_code();

        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE upload_code=?
            LIMIT 1
        ");

        $check->execute([$code]);

    }while($check->fetch());

    $update = $pdo->prepare("
        UPDATE users
        SET upload_code=?
        WHERE id=?
    ");

    $update->execute([$code, $userId]);

    return $code;
}

function upload_storage_extension_from_upload(
    string $originalName,
    string $mime
): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowed = [
        'jpg','jpeg','png','gif','webp',
        'heic','heif','mp4','webm','mov','3gp'
    ];

    if(in_array($ext, $allowed, true)){
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/3gpp' => '3gp',
    ];

    return $mimeMap[$mime] ?? 'bin';
}

function upload_storage_next_sequence(
    string $dateDir,
    string $userCode,
    string $dateFolder,
    array $pendingFiles = []
): int
{
    $max = 0;
    $pattern =
    '/^' .
    preg_quote($userCode, '/') .
    '-' .
    preg_quote($dateFolder, '/') .
    '-(\d{3})\.[^.]+$/i';

    if(is_dir($dateDir)){

        foreach(scandir($dateDir) as $file){

            if($file === '.' || $file === '..'){
                continue;
            }

            if(preg_match($pattern, $file, $matches)){
                $max = max($max, (int)$matches[1]);
            }

        }

    }

    foreach($pendingFiles as $pendingFile){

        $basename = basename((string)$pendingFile);

        if(preg_match($pattern, $basename, $matches)){
            $max = max($max, (int)$matches[1]);
        }

    }

    $next = $max + 1;

    if($next > 999){
        throw new RuntimeException('حداکثر ۹۹۹ فایل در روز مجاز است');
    }

    return $next;
}

function upload_storage_build_filename(
    string $userCode,
    string $dateFolder,
    int $sequence,
    string $extension
): string
{
    return sprintf(
        '%s-%s-%03d.%s',
        strtoupper($userCode),
        $dateFolder,
        $sequence,
        strtolower($extension)
    );
}

function upload_storage_build_relative_path(
    string $dateFolder,
    string $filename
): string
{
    return $dateFolder . '/' . $filename;
}

function upload_storage_normalize_relative_path(string $stored): ?string
{
    $stored = str_replace('\\', '/', trim($stored));

    if(
        $stored === '' ||
        strpos($stored, '..') !== false
    ){
        return null;
    }

    if(preg_match('/^\d{8}\/[A-Z0-9]{6}-\d{8}-\d{3}\.[a-z0-9]+$/i', $stored)){
        return $stored;
    }

    $basename = basename($stored);

    if(
        $basename !== '' &&
        strpos($basename, '/') === false
    ){
        return $basename;
    }

    return null;
}

function upload_storage_absolute_path(string $stored): ?string
{
    $relative = upload_storage_normalize_relative_path($stored);

    if($relative === null){
        return null;
    }

    $absolute = upload_storage_base_dir() . '/' . $relative;
    $realBase = realpath(upload_storage_base_dir());
    $realFile = realpath($absolute);

    if(
        $realBase &&
        $realFile &&
        strpos($realFile, $realBase . DIRECTORY_SEPARATOR) === 0
    ){
        return $realFile;
    }

    if(
        is_file($absolute) &&
        strpos($relative, '..') === false
    ){
        return $absolute;
    }

    return null;
}

function upload_storage_public_url(string $stored): string
{
    $relative = upload_storage_normalize_relative_path($stored);

    if($relative === null){
        return '';
    }

    $parts = explode('/', $relative);

    return '/uploads/' . implode('/', array_map('rawurlencode', $parts));
}

function upload_storage_file_exists(string $stored): bool
{
    $path = upload_storage_absolute_path($stored);

    return $path !== null && is_file($path);
}

function upload_storage_store_uploaded_file(
    PDO $pdo,
    int $userId,
    array $file,
    array $pendingFiles = []
): array
{
    if(($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
        throw new RuntimeException('خطا در آپلود فایل');
    }

    $maxSize = 20 * 1024 * 1024;

    if(($file['size'] ?? 0) > $maxSize){
        throw new RuntimeException('حداکثر حجم فایل ۲۰ مگابایت است');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';

    $allowed = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/3gpp',
    ];

    if(!in_array($mime, $allowed, true)){
        throw new RuntimeException('فرمت فایل مجاز نیست');
    }

    $dateFolder = upload_storage_jalali_date_folder();
    $dateDir = upload_storage_ensure_date_dir($dateFolder);
    $userCode = upload_storage_get_user_code($pdo, $userId);
    $extension = upload_storage_extension_from_upload(
        basename((string)$file['name']),
        $mime
    );

    $sequence = upload_storage_next_sequence(
        $dateDir,
        $userCode,
        $dateFolder,
        $pendingFiles
    );

    $filename = upload_storage_build_filename(
        $userCode,
        $dateFolder,
        $sequence,
        $extension
    );

    $relative = upload_storage_build_relative_path(
        $dateFolder,
        $filename
    );

    $target = $dateDir . '/' . $filename;

    if(!move_uploaded_file($file['tmp_name'], $target)){
        throw new RuntimeException('ذخیره فایل انجام نشد');
    }

    return [
        'stored' => $relative,
        'saved_as' => $filename,
        'display' => pathinfo($filename, PATHINFO_FILENAME),
        'original' => basename((string)$file['name']),
        'size' => (int)$file['size'],
        'url' => upload_storage_public_url($relative),
    ];
}

function upload_storage_delete_file(string $stored): bool
{
    $path = upload_storage_absolute_path($stored);

    if($path === null || !is_file($path)){
        return false;
    }

    return @unlink($path);
}
