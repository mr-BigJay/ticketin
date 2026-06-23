<?php

function app_root_dir(): string
{
    return dirname(__DIR__);
}

function app_uploads_dir(): string
{
    return app_root_dir() . '/uploads/';
}

function app_uploads_tickets_dir(): string
{
    return app_uploads_dir() . 'tickets/';
}

function app_ensure_upload_dirs(): void
{
    foreach([app_uploads_dir(), app_uploads_tickets_dir()] as $dir){
        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }
    }
}
