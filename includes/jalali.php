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
