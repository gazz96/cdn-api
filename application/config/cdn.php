<?php 

defined('BASEPATH') OR exit('No direct script access allowed');


$config['cdn'] = [

    // base storage folder (relative to FCPATH)
    'base_path' => 'storage',

    // public / private folder name
    'public_folder'  => 'public',
    'private_folder' => 'private',

    // folder structure options
    'use_year'  => true,
    'use_month' => true,
    'use_day'   => true,

    // permission for auto-created folder
    'folder_permission' => 0755,

    'cdn_max_file_size' => 10 * 1024 * 1024,
    'cdn_allowed_types' => ['jpg','png','pdf','zip'],
    'allow_custom_folder' => true,
    'folder_pattern' => '/^[a-z0-9\/_-]+$/',
    'profiles' => [
        'soal_ujian' => [
            'public' => true,
            'base_folder' => 'lembar-soal',
            'use_year'  => true,
            'use_month' => true,
            'use_day'   => true,
            'max_size'  => 2 * 1024 * 1024, // 2MB
            'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf',],
        ],
        'profile_image' => [
            'public' => true,
            'base_folder' => 'avatars/users',
            'use_year'  => true,
            'use_month' => true,
            'use_day'   => true,
            'max_size'  => 2 * 1024 * 1024, // 2MB
            'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'],
            'auto_resize' => true,
            'resize' => ['w' => 300, 'h' => 300],
        ],
        'answer_sheet' => [
            'public' => false,
            'base_folder' => 'exam/answers',
            'use_year'  => true,
            'use_month' => true,
            'use_day'   => false,
            'max_size'  => 10 * 1024 * 1024, // 10MB
            'allowed_mime' => ['application/pdf', 'image/jpeg'],
            'auto_resize' => false,
        ],

        'public_asset' => [
            'public' => true,
            'base_folder' => 'assets',
            'use_year'  => false,
            'use_month' => false,
            'use_day'   => false,
            'max_size'  => 5 * 1024 * 1024,
            'allowed_mime' => ['image/*'],
        ],
    ]
];
