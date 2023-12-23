<?php
$domain = 'thietbidienthaiduong.com';
$site = 'https://'.$domain.'/';
return [
    'crawler'=> [
        'domain' => $domain,
        'site' => $site,
        'sitemap' => $site . 'sitemap.xml'
    ]
];
