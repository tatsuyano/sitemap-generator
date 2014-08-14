<?php

require_once 'lib/SitemapGenerator.php';

$url = $argv[1]; // e.g. http://hoge.com

if(empty($url)){
    print "Please input a URL.\n";
    exit;
}

$generator = new SitemapGenerator\SitemapGenerator(array('url' => $url));
$generator->findRecursive()->makeSiteMap();
