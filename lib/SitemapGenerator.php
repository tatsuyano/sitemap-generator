<?php
namespace SitemapGenerator;

require_once 'packages/parser/simple_html_dom.php';
require_once 'Href.php';

/**
 * 指定されたサイトを再帰的にクロールする。負荷対策のため、再帰する回数の上限を設けている。
 * また、クロール対象から外すblack_list機能と、クロール済ページとして登録するwhite_list機能がある
 *
 * @exsample
 * require_once 'lib/SitemapGenerator.php';
 *
 * $generator = new SitemapGenerator\SitemapGenerator(array(
 *     'url'        => 'http://hoge.com',
 *     'white_list' => './white_list.txt',
 *     'black_list' => './black_list.txt' ));
 *
 * $generator->findRecursive()->makeSiteMap('./sitemap.xml');
 *
 */
class SitemapGenerator {

    const NOT_CRAWLING_YET = 0;
    const ALREADY_CRAWLED  = 1;
    const REMOVED_HREF     = 9;
    const FIND_RECURSIVE_LIMIT = 20;
    const WHITE_LIST = 'white_list';
    const BLACK_LIST = 'black_list';

    private $domain   = null;
    private $href     = null;

    function __construct($p = null){

        $url = (isset($p['url'])) ? $p['url'] : '';

        if (empty($url) || !@file_get_contents($url)){
            print "URL that is specified does not exist.\n";
            exit;
        }

        if (preg_match('{^https?://([^\?]+)\??}',$url,$match) ){
            $this->domain   = trim($match[1],"/");
        }

        $this->href = new Href($this->domain);
        
        if (isset($p['white_list']))
            $this->_setList(self::WHITE_LIST,$p['white_list']);

        if (isset($p['black_list']))
            $this->_setList(self::BLACK_LIST,$p['black_list']);
        
        $this->findHref($url); // 一回目のfind
    }

    /**
     * 再帰的にAncerタグをクロールする
     *
     * @param  int  $limit 再帰的にクロールする回数の上限
     * @return Href $href  privateなHrefオブジェクト
     */
    function findRecursive($limit = self::FIND_RECURSIVE_LIMIT){

        //while($arr = $this->href->notCrawlingArrHref()){ // 無制限バージョン
        while($limit--){ // 制限バージョン
            $arr = $this->href->notCrawlingArrHref();

            foreach ($arr as $href => $crawl){
                $this->findHref($href);
            }
        }
        
        return $this->href;
    }

    /**
     * Ancerタグをクロールする。
     *
     * @param  uri  $string クロールする対象のURI
     * @return Href $href   privateなHrefオブジェクト
     */
    function findHref($uri = null){

        // 存在しないURIだった場合
        if (!@file_get_contents($uri)){
            $this->href->removeHref($uri);
            return $this->href;
        }

        $arr_href = array();
        $html = file_get_html($uri);
        
        foreach($html->find('a') as $element) {
            $href = $element->href;
            
            if (empty($href) || preg_match('{(^/$|#)}',$href)) continue;
            
            // hrefが[https?://xxx] の場合
            if (preg_match('{^https?://([^\?]+)\??}',$href,$match) ){
                $href_domain = $match[1];
                
                // 設定したドメインと同じ(サブ)ドメインの場合
                if (preg_match('{'. $this->domain .'}',$href_domain)){

                    // 画像,Js,cssではない場合
                    if (!preg_match('{\.(gif|jpg|jpeg|jpe|jfif|png|bmp|ico|js|css)$}i',$href_domain)){
                        $href = trim($href,"/");
                        $arr_href[$href] = self::NOT_CRAWLING_YET;
                    }
                }
                
            }// 相対パスの場合
            elseif(preg_match('{^/(.+)}',$href,$match) ){
                $url = sprintf("http://%s/%s",$this->domain,$match[1]);
                $url = trim($url,"/");
                $arr_href[$url] = self::NOT_CRAWLING_YET;
            }
        }
        
        $uri = trim($uri,"/");
        $this->href->crawledHref($uri);
        $this->href->setArrHref($arr_href);
        $html->clear();
        
        return $this->href;
    }

    private function _setList($list = null, $file_full_path = null){
        if (empty($list) || empty($file_full_path)) return;

        if(is_file($file_full_path)){
            $fp = fopen($file_full_path, "r");
            
            while (!feof($fp)) {
                $uri = fgets($fp);
                $uri = trim($uri,"\n");
                
                if (!empty($uri)){
                    if ($list == self::WHITE_LIST){
                        $this->href->crawledHref($uri);
                    }else{
                        $this->href->removeHref($uri);
                    }
                }
            }

            fclose($fp);
        }
    }

}
