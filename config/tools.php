<?php
//http://siquanaotreemquangchau.com/sitemap.xml
$domain = 'dahoacuongbachthinh.com';
$site = 'http://'.$domain.'/';
return [
    'crawler'=> [
        'domain' => $domain,
        'site' => $site,
        'sitemap' => $site . 'sitemap.xml'
    ]
];
