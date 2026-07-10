<?php

require_once
__DIR__ .
'/jdatetime.class.php';

function jalali_date($date){

    if(!$date){

        return '-';

    }

    $time =
    strtotime($date);

    $jDate =
    new jDateTime(true,true,'Asia/Tehran');

    $formatted =
    $jDate->date(
        "Y/m/d H:i",
        $time
    );

    return toPersianNumbers(
        $formatted
    );

}

function toEnglishNumbers($string){

    $persian = [

        '۰','۱','۲','۳','۴',
        '۵','۶','۷','۸','۹'

    ];

    $english = [

        '0','1','2','3','4',
        '5','6','7','8','9'

    ];

    return str_replace(
        $persian,
        $english,
        (string)$string
    );

}

function toPersianNumbers($string){

    $english = [

        '0','1','2','3','4',
        '5','6','7','8','9'

    ];

    $persian = [

        '۰','۱','۲','۳','۴',
        '۵','۶','۷','۸','۹'

    ];

    return str_replace(
        $english,
        $persian,
        $string
    );

}

function jalali_today_for_db(): string
{
    $jDate = new jDateTime(false, true, 'Asia/Tehran');

    return normalize_jalali_date_for_db(
        $jDate->date('Y/m/d', time())
    );
}

function normalize_jalali_date_for_db(?string $date): string
{
    $value = trim(toEnglishNumbers((string)$date));

    if($value === ''){
        return '';
    }

    $value = str_replace('-', '/', $value);

    if(($spacePos = strpos($value, ' ')) !== false){
        $value = substr($value, 0, $spacePos);
    }

    if(($tPos = strpos($value, 'T')) !== false){
        $value = substr($value, 0, $tPos);
    }

    if(!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)){
        return '';
    }

    return sprintf(
        '%04d/%02d/%02d',
        (int)$matches[1],
        (int)$matches[2],
        (int)$matches[3]
    );
}

function jalali_matches_year_month(?string $date, int $year, int $month): bool
{
    $parts = jalali_extract_year_month($date);

    if(!$parts){
        return false;
    }

    return $parts['year'] === $year && $parts['month'] === $month;
}

function jalali_extract_year_month(?string $date): ?array
{
    $normalized = normalize_jalali_date_for_db($date);

    if($normalized === ''){
        return null;
    }

    $parts = explode('/', $normalized);

    return [
        'year' => (int)$parts[0],
        'month' => (int)$parts[1],
    ];
}

function jalali_month_filter_regexp(int $year, int $month): string
{
    $monthVariants = array_values(array_unique([
        (string)$month,
        sprintf('%02d', $month),
    ]));

    return '^' . $year . '/(' . implode('|', $monthVariants) . ')/';
}

function jalali_month_filter_patterns(int $year, int $month): array
{
    $patterns = [jalali_month_filter_regexp($year, $month)];

    $persianMonthVariants = array_values(array_unique([
        toPersianNumbers((string)$month),
        toPersianNumbers(sprintf('%02d', $month)),
    ]));

    $patterns[] = '^'
        . toPersianNumbers((string)$year)
        . '/('
        . implode('|', $persianMonthVariants)
        . ')/';

    return array_values(array_unique($patterns));
}

function fa_datetime($date){

    return jalali_date($date);

}

function fa_date($date){

    if(!$date){
        return '-';
    }

    $time = strtotime($date);
    $jDate = new jDateTime(true, true, 'Asia/Tehran');

    return toPersianNumbers(
        $jDate->date('Y/m/d', $time)
    );

}

function fa_time($date){

    if(!$date){
        return '-';
    }

    $time = strtotime($date);
    $jDate = new jDateTime(true, true, 'Asia/Tehran');

    return toPersianNumbers(
        $jDate->date('H:i', $time)
    );

}

function format_stored_jalali_date(?string $date): string
{
    if($date === null || trim($date) === ''){
        return '-';
    }

    $normalized = normalize_jalali_date_for_db($date);

    if($normalized === ''){
        return toPersianNumbers(trim(toEnglishNumbers($date)));
    }

    return toPersianNumbers($normalized);
}

function jalali_month_names(): array
{
    return [
        'فروردین',
        'اردیبهشت',
        'خرداد',
        'تیر',
        'مرداد',
        'شهریور',
        'مهر',
        'آبان',
        'آذر',
        'دی',
        'بهمن',
        'اسفند',
    ];
}

function jalali_current_year_month(): array
{
    $jDate = new jDateTime(false, true, 'Asia/Tehran');

    return [
        'year' => (int)$jDate->date('Y', time()),
        'month' => (int)$jDate->date('n', time()),
    ];
}

function jalali_parse_month_request(): array
{
    $current = jalali_current_year_month();
    $year = (int)($_GET['jy'] ?? $current['year']);
    $month = (int)($_GET['jm'] ?? $current['month']);

    if($year < 1300){
        $year = $current['year'];
    }

    if($month < 1){
        $month = 1;
    }

    if($month > 12){
        $month = 12;
    }

    return [
        'year' => $year,
        'month' => $month,
    ];
}

function jalali_shift_month(int $year, int $month, int $delta): array
{
    $month += $delta;

    while($month > 12){
        $month -= 12;
        $year++;
    }

    while($month < 1){
        $month += 12;
        $year--;
    }

    return [
        'year' => $year,
        'month' => $month,
    ];
}

function jalali_month_title(int $year, int $month): string
{
    $months = jalali_month_names();
    $label = $months[$month - 1] ?? '';

    return toPersianNumbers((string)$year) . ' ' . $label;
}

function jalali_month_prefix(int $year, int $month): string
{
    return sprintf('%d/%02d/', $year, $month);
}

function reminder_is_expired(string $reminderDate): bool
{
    $normalized = normalize_jalali_date_for_db($reminderDate);

    if($normalized === ''){
        return false;
    }

    return strcmp($normalized, jalali_today_for_db()) < 0;
}
