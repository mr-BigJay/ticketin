<?php

function ticket_attachment_list($attachment){

    if(empty($attachment)){

        return [];

    }

    $decoded = json_decode($attachment, true);

    if(is_array($decoded)){

        return array_values(array_filter(
            $decoded,
            function($item){
                return is_string($item) && $item !== '';
            }
        ));

    }

    return [$attachment];

}
