<?php
namespace SitemapGenerator;

/**
 * クロールされたHref(uri)を保持するクラス
 */
class Href {

    const DEFAULT_SITEMAP_FULL_PATH = './sitemap.xml';

    private $domain   = null;
    private $arr_href = array();

    function __construct($domain = null){
        $this->domain = $domain;
    }
    
    /**
     * クロールする予定のhrefのリストをセットする
     *
     * @param  arr  $arr クロールする予定のhrefのリスト
     * @return void
     */
    function setArrHref($arr = null){
        foreach ($arr as $href => $status){
            if (!isset($this->arr_href[$href]))
                $this->arr_href[$href] = $status;
        }
    }

    /**
     * まだクロールされていないhrefのリストを返す
     *
     * @return arr $arr まだクロールされていないhrefのリスト
     */
    function notCrawlingArrHref(){
        $arr = array();

        foreach ($this->arr_href as $href => $status){
            if ($status == SitemapGenerator::NOT_CRAWLING_YET)
                $arr[$href] = SitemapGenerator::NOT_CRAWLING_YET;
        }

        return $arr;
    }

    /**
     * 指定されたhrefのstatusを REMOVED_HREF にする
     *
     * @return void
     */
    function removeHref($href = null){
        $this->arr_href[$href] = SitemapGenerator::REMOVED_HREF;
    }

    /**
     * 指定されたhrefのstatusを ALREADY_CRAWLED にする
     *
     * @return void
     */
    function crawledHref($href = null){
        $this->arr_href[$href] = SitemapGenerator::ALREADY_CRAWLED;
    }

    /**
     * sitemapを生成する
     *
     * @param  string $file_full_path 生成するsitemapのフルパス
     * @return void
     */
    function makeSiteMap($file_full_path = self::DEFAULT_SITEMAP_FULL_PATH){
        $today = date("Y-m-d");
        
        $content  = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $content .= "<urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'>\n";
        
        foreach ($this->arr_href as $href => $status){
            if($status == SitemapGenerator::ALREADY_CRAWLED){
                $content .= "<url>\n";
                $content .= sprintf("<loc>%s</loc>\n",rawurlencode($href));
                $content .= sprintf("<lastmod>%s</lastmod>\n",$today);
                $content .= sprintf("<priority>%.1f</priority>\n",
                                    $this->_priorityDirectory($href));
                $content .= "</url>\n";
            }
        }
        $content .= "</urlset>\n";

        $fp = fopen($file_full_path, "w");
        fwrite($fp,$content);
        fclose($fp);
    }

    /**
     * [debug] 現在のhrefリストを標準出力する
     *
     * @return void
     */
    function debug(){
        printf("COUNT->[%d]\n",count($this->arr_href));
        
        foreach ($this->arr_href as $href => $status){
            printf("href => [%s], status => [%d]\n",$href,$status);
        }
    }

    /**
     * 階層が深いuriのpriorityが低くなるように設定する
     * 
     * @exsample
     *   http://adjp.me <- 1.0
     *   http://adjp.me/user/login <- 0.8
     *
     * @param  string $uri hrefのuri
     * @return void
     */
    private function _priorityDirectory($uri = null){

        $priority = 1.0;

        if(preg_match('{^https?://'. $this->domain .'(.+)}',$uri,$match)){
            $uri = $match[1];
            $uri = trim($uri,'/');

            list($url,$params) = explode('?',$uri);
            $cnt = (!empty($url)) ? count(explode('/',$url)) : 0;
            
            if ($cnt == 1) $cnt = 2; // Adjust for MaxMarket.
            if (!empty($params)) $cnt+= 2; // If there is a params, +2.
            
            $priority = 1.0 - $cnt * 0.1;
        }

        if ($priority < 0.5) $priority = 0.5; // 0.5 is low priority.

        return $priority;
    }

}
