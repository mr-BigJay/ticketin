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

    return $jDate->date('Y/m/d', time());
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

    return toPersianNumbers(trim(toEnglishNumbers($date)));
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
    $normalized = trim(toEnglishNumbers($reminderDate));

    if($normalized === ''){
        return false;
    }

    return strcmp($normalized, jalali_today_for_db()) < 0;
}
