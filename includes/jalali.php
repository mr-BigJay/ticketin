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

function fa_datetime($date){

    return jalali_date($date);

}

function toEnglishNumbers($string){

    $persian = [

        '۰','۱','۲','۳','۴',
        '۵','۶','۷','۸','۹'

    ];

    $arabic = [

        '٠','١','٢','٣','٤',
        '٥','٦','٧','٨','٩'

    ];

    $english = [

        '0','1','2','3','4',
        '5','6','7','8','9'

    ];

    return str_replace(
        $arabic,
        $english,
        str_replace($persian, $english, $string)
    );

}

function jalali_date_only($date){

    if(!$date){

        return '-';

    }

    $time = strtotime($date);

    $jDate = new jDateTime(true,true,'Asia/Tehran');

    return toPersianNumbers(
        $jDate->date("Y/m/d", $time)
    );

}

function jalali_to_gregorian_date($date){

    $date =
    trim(
        toEnglishNumbers($date)
    );

    $date =
    str_replace('-', '/', $date);

    if(!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date, $matches)){

        return null;

    }

    $year = (int)$matches[1];
    $month = (int)$matches[2];
    $day = (int)$matches[3];

    if(!jDateTime::checkdate($month, $day, $year, true)){

        return null;

    }

    [$gy, $gm, $gd] = jDateTime::toGregorian($year, $month, $day);

    return sprintf(
        '%04d-%02d-%02d',
        $gy,
        $gm,
        $gd
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
