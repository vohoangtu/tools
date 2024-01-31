<?php
//http://siquanaotreemquangchau.com/sitemap.xml
$domain = 'phuchongdinhmiennam.com';
$site = 'http://'.$domain.'/';
return [
    'crawler'=> [
        'domain' => $domain,
        'site' => $site,
        'sitemap' => $site . 'sitemap.xml'
    ]
];
