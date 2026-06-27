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

    upload_settings_ensure_schema($pdo);
}

function upload_settings_format_catalog(): array
{
    return [
        'تصویر' => [
            'jpg' => 'JPG',
            'jpeg' => 'JPEG',
            'png' => 'PNG',
            'gif' => 'GIF',
            'webp' => 'WEBP',
            'heic' => 'HEIC',
            'heif' => 'HEIF',
            'bmp' => 'BMP',
        ],
        'ویدیو' => [
            'mp4' => 'MP4',
            'webm' => 'WEBM',
            'mov' => 'MOV',
            '3gp' => '3GP',
            'avi' => 'AVI',
            'mkv' => 'MKV',
        ],
        'سند' => [
            'pdf' => 'PDF',
            'doc' => 'DOC',
            'docx' => 'DOCX',
            'xls' => 'XLS',
            'xlsx' => 'XLSX',
            'ppt' => 'PPT',
            'pptx' => 'PPTX',
            'txt' => 'TXT',
            'csv' => 'CSV',
        ],
        'فشرده' => [
            'zip' => 'ZIP',
            'rar' => 'RAR',
            '7z' => '7Z',
        ],
    ];
}

function upload_settings_default_extensions(): array
{
    return [
        'jpg','jpeg','png','gif','webp','heic','heif',
        'mp4','webm','mov','3gp',
        'pdf','doc','docx','xls','xlsx','txt',
        'zip','rar',
    ];
}

function upload_settings_extension_mimes(): array
{
    return [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif'],
        'heif' => ['image/heif', 'image/heic'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime'],
        '3gp' => ['video/3gpp'],
        'avi' => ['video/x-msvideo', 'video/avi'],
        'mkv' => ['video/x-matroska'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/vnd.rar', 'application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
    ];
}

function upload_settings_catalog_extensions(): array
{
    $extensions = [];

    foreach(upload_settings_format_catalog() as $items){
        foreach($items as $ext => $label){
            $extensions[] = $ext;
        }
    }

    return array_values(array_unique($extensions));
}

function upload_settings_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS upload_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            max_size_mb INT UNSIGNED NOT NULL DEFAULT 20,
            allowed_extensions TEXT NOT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $count = (int)$pdo
        ->query("SELECT COUNT(*) FROM upload_settings")
        ->fetchColumn();

    if($count < 1){

        $stmt = $pdo->prepare("
            INSERT INTO upload_settings
            (id, max_size_mb, allowed_extensions)
            VALUES
            (1, ?, ?)
        ");

        $stmt->execute([
            20,
            json_encode(
                upload_settings_default_extensions(),
                JSON_UNESCAPED_UNICODE
            ),
        ]);

    }
}

function upload_settings_normalize_extensions(array $extensions): array
{
    $catalog = upload_settings_catalog_extensions();
    $normalized = [];

    foreach($extensions as $extension){

        $extension = strtolower(trim((string)$extension));

        if(
            $extension !== '' &&
            in_array($extension, $catalog, true) &&
            !in_array($extension, $normalized, true)
        ){
            $normalized[] = $extension;
        }

    }

    sort($normalized);

    return $normalized;
}

function upload_settings_get(PDO $pdo): array
{
    upload_settings_ensure_schema($pdo);

    $stmt = $pdo->query("
        SELECT max_size_mb, allowed_extensions
        FROM upload_settings
        WHERE id=1
        LIMIT 1
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $extensions = upload_settings_default_extensions();

    if(!empty($row['allowed_extensions'])){

        $decoded = json_decode(
            (string)$row['allowed_extensions'],
            true
        );

        if(is_array($decoded)){
            $extensions = upload_settings_normalize_extensions($decoded);
        }

    }

    if(!$extensions){
        $extensions = upload_settings_default_extensions();
    }

    $maxSizeMb = (int)($row['max_size_mb'] ?? 20);

    if($maxSizeMb < 0){
        $maxSizeMb = 0;
    }

    if($maxSizeMb > 100){
        $maxSizeMb = 100;
    }

    $uploadsEnabled = $maxSizeMb > 0;

    $allowedMimes = [];

    foreach($extensions as $extension){

        if(isset(upload_settings_extension_mimes()[$extension])){
            $allowedMimes = array_merge(
                $allowedMimes,
                upload_settings_extension_mimes()[$extension]
            );
        }

    }

    $allowedMimes = array_values(array_unique($allowedMimes));

    return [
        'max_size_mb' => $maxSizeMb,
        'max_size_bytes' => $uploadsEnabled
            ? ($maxSizeMb * 1024 * 1024)
            : 0,
        'uploads_enabled' => $uploadsEnabled,
        'allowed_extensions' => $extensions,
        'allowed_mimes' => $allowedMimes,
        'accept_attribute' => implode(
            ',',
            array_map(
                static function($extension){
                    return '.' . $extension;
                },
                $extensions
            )
        ),
    ];
}

function upload_settings_save(
    PDO $pdo,
    int $maxSizeMb,
    array $extensions
): ?string
{
    upload_settings_ensure_schema($pdo);

    if($maxSizeMb < 0 || $maxSizeMb > 100){
        return 'حداکثر حجم باید بین ۰ تا ۱۰۰ مگابایت باشد';
    }

    $extensions = upload_settings_normalize_extensions($extensions);

    if($maxSizeMb > 0 && !$extensions){
        return 'حداقل یک فرمت فایل باید انتخاب شود';
    }

    if($maxSizeMb === 0){
        $extensions = $extensions ?: upload_settings_default_extensions();
    }

    $stmt = $pdo->prepare("
        INSERT INTO upload_settings
        (id, max_size_mb, allowed_extensions)
        VALUES
        (1, ?, ?)
        ON DUPLICATE KEY UPDATE
        max_size_mb=VALUES(max_size_mb),
        allowed_extensions=VALUES(allowed_extensions)
    ");

    $stmt->execute([
        $maxSizeMb,
        json_encode($extensions, JSON_UNESCAPED_UNICODE),
    ]);

    return null;
}

function upload_settings_is_allowed_upload(
    PDO $pdo,
    string $originalName,
    string $mime,
    int $size
): ?string
{
    $settings = upload_settings_get($pdo);

    if(!$settings['uploads_enabled']){
        return 'امکان آپلود فایل غیرفعال است';
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $extension = $extension === 'jpeg' ? 'jpg' : $extension;

    if(
        $extension === '' ||
        !in_array($extension, $settings['allowed_extensions'], true)
    ){
        return 'فرمت فایل مجاز نیست';
    }

    if($size > $settings['max_size_bytes']){
        return 'حداکثر حجم فایل ' . $settings['max_size_mb'] . ' مگابایت است';
    }

    $allowedMimes = upload_settings_extension_mimes()[$extension] ?? [];

    if(
        $mime &&
        $allowedMimes &&
        !in_array($mime, $allowedMimes, true)
    ){
        return 'نوع فایل با پسوند آن مطابقت ندارد';
    }

    return null;
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
    string $mime,
    array $allowedExtensions
): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $ext = $ext === 'jpeg' ? 'jpg' : $ext;

    if(in_array($ext, $allowedExtensions, true)){
        return $ext;
    }

    foreach(upload_settings_extension_mimes() as $extension => $mimes){

        if(
            in_array($extension, $allowedExtensions, true) &&
            in_array($mime, $mimes, true)
        ){
            return $extension;
        }

    }

    return $ext ?: 'bin';
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

    $settings = upload_settings_get($pdo);
    $originalName = basename((string)$file['name']);
    $fileSize = (int)($file['size'] ?? 0);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';

    $validationError = upload_settings_is_allowed_upload(
        $pdo,
        $originalName,
        $mime,
        $fileSize
    );

    if($validationError){
        throw new RuntimeException($validationError);
    }

    $dateFolder = upload_storage_jalali_date_folder();
    $dateDir = upload_storage_ensure_date_dir($dateFolder);
    $userCode = upload_storage_get_user_code($pdo, $userId);
    $extension = upload_storage_extension_from_upload(
        $originalName,
        $mime,
        $settings['allowed_extensions']
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
        'original' => $originalName,
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
