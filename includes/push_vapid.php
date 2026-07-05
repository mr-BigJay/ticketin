<?php

function push_vapid_subject(): string
{
    return 'mailto:admin@ticketin.ir';
}

function push_vapid_private_pem_path(): string
{
    return __DIR__ . '/push_vapid_private.pem';
}
