<?php

namespace App\Console\Commands\V2;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class CrawlLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'v:crawl-links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    protected function getPageImages(Crawler $crawler){
        foreach ($crawler->filter("body img")->images() as $node){
            $img = $node->getNode()->getAttribute("src");
            if(empty($img)){
                $img = $node->getNode()->getAttribute("data-src");
            }
            Log::info($img);
            if(!empty($img)){
                $this->saveImage($img);
            }
        }
    }
    function get($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
        $data = curl_exec ($ch);
        curl_close ($ch);
        return $data;
    }
    /**
     * Execute the console command.
     */
    public function handle1()
    {
        $links = [
            "https://thietbidienthaiduong.com/san-pham.html",
            "https://thietbidienthaiduong.com/san-pham.html&p=2",
            "https://thietbidienthaiduong.com/san-pham.html&p=3",
            "https://thietbidienthaiduong.com/san-pham.html&p=4",
            "https://thietbidienthaiduong.com/san-pham.html&p=5",
            "https://thietbidienthaiduong.com/san-pham.html&p=6",
            "https://thietbidienthaiduong.com/san-pham.html&p=7",
            "https://thietbidienthaiduong.com/san-pham.html&p=8",
            "https://thietbidienthaiduong.com/san-pham.html&p=9",
            "https://thietbidienthaiduong.com/san-pham.html&p=10",
            "https://thietbidienthaiduong.com/thuong-hieu.html",
            "https://thietbidienthaiduong.com/dich-vu.html",
            "https://thietbidienthaiduong.com/tin-tuc.html",
            "https://thietbidienthaiduong.com/tin-tuc.html&p=2"
        ];
        for ($i = 11; $i < 46; $i++){
            $links[] = "https://thietbidienthaiduong.com/san-pham.html&p=".$i;
        }

        foreach ($links as $loc){
            $content =$this->get(trim($loc));
            if($content){
                $pageContent = Str::replace("</head>", "\"></script></head>", $content);
                $crawler = new Crawler($pageContent);

                $this->getPageImages($crawler);

                Link::query()->insert([
                    'link' => $loc,
                    'slug' => Str::replace(config('tools.crawler.site'), '', $loc),
                    'content' => $pageContent,
                    'type' => 'link'
                ]);

            }else{
                $this->info("fail at: " . $loc);
            }
        }
    }

    public function handle2()
    {
        $links = Link::query()->get();
        $crawledLinks = [];
        foreach ($links as $loc){
            $content = $loc->content;
            if($content){
                $pageContent = $content;
                $crawler = new Crawler($pageContent);
                foreach ($crawler->filter("body a")->links() as $node){
                    $href = $node->getNode()->getAttribute("href");
                    if( !Str::startsWith($href, [
                        'http', 'https'
                    ])){
                        $href = config("tools.crawler.site") . $href;
                    }
                    $hrefContent = Http::get($href);
                    if($hrefContent->ok() && !isset($crawledLinks[$href])){
                        $crawledLinks[$href] = true;
                        $urlContent = Str::replace("</head>", "\"></script></head>", $hrefContent->body());
//                        $crawler = new Crawler($urlContent);
//                        $this->getPageImages($crawler);
                        Link::query()->insert([
                            'link' => $href,
                            'slug' => Str::replace(config('tools.crawler.site'), '', $href),
                            'content' => $urlContent,
                            'type' => 'raw'
                        ]);
                    }
                }
            }else{
                $this->info("fail at: " . $loc);
            }
        }
    }
    protected function handle3()
    {
        $links = Link::query()->get();
        $crawledLinks = [];
        foreach ($links as $loc) {
            $content = $loc->content;
            if ($content) {
                foreach (['san-pham', 'tin-tuc'] as $type){
                    if (Str::contains($loc->slug, [$type])) {
                        $loc->source = $type;
                        if(Str::endsWith($loc->link, ".html")){
                            $loc->type = 'detail';
                        }
                        else if(Str::endsWith($loc->link, "/")){
                            $loc->type = 'cat';
                        }else if (Str::contains($loc->slug, [$type.'/'])) {
                            $loc->type = 'list';
                        }
                    }
                }
                //list: dcjq-parent active
                //cat :
                $loc->save();


            } else {
                $this->info("fail at: " . $loc);
            }
        }
    }
    protected function handleProduct()
    {
        $links = Link::query()->where('type', '=', 'detail')->where('source', '=', 'san-pham')->get();
        foreach($links as $link){
            $crawler = new Crawler($link->content);
            $this->getPageImages($crawler);
            $metaCollection = $this->metadata($crawler);

            $title = $crawler->filter("ul.product_info > .ten");
            preg_match("/^san-pham\/(.*).html/", $link->slug, $match);
            $_product = DB::connection("mysql")->table("table_product")->insert([
                'namevi' => $title->text(),
                'slugvi' => $match[1],
                'status' => 'hienthi,noibat',
                'type' => 'san-pham',
                'photo' => $metaCollection['post_image']
            ]);
            $_product = DB::connection("mysql")->table("table_product")->where("slugvi", '=', $match[1])->first();
            DB::connection("mysql")->table("table_seo")->insert([
                'com' => 'product',
                'id_parent' => $_product->id,
                'act' => 'man',
                'type' => 'san-pham',
                'titlevi' => $_product->namevi,
                'descriptionvi' => $metaCollection['meta']['description'],
                'keywordsvi' => $metaCollection['meta']['keywords']
            ]);
        }
    }
    public function handle()
    {
//        $this->handle1();
//        $this->handle2();
//        $this->handle3();
        $this->handleProduct();
//        $this->handleNews();
    }
    protected function saveUrlToLocal($savePath, $httpPath){
        if(in_array($savePath, $this->photos)){
            return false;
        }
        $this->photos[] = $savePath;
        try {
            File::ensureDirectoryExists(dirname(base_path($savePath)), 0777, 1);
            if(!File::exists(base_path($savePath))){
                File::put(base_path($savePath), file_get_contents(
                    $httpPath
                ));
            }
        }catch (\Exception $exception){
            return false;
        }
    }

    public function saveImage($path){

        $path = substr($path, strpos($path, "upload"), strlen($path));
        if($this->isLocalHttpPath($path)){
            $this->saveUrlToLocal($path, $path);
        }else{
            $this->saveUrlToLocal($path, config('tools.crawler.site').$path);
        }

    }

    protected function isLocalHttpPath($path){
        return Str::startsWith($path, [
                'http://',
                'https://'
            ]) && Str::contains($path, config("tools.crawler.domain"));
    }

    protected function metadata(Crawler $crawler){
        $metaCollection = [];

        foreach($crawler->filter("meta") as $meta){
            $collection = [];

            foreach ($meta->attributes as $attribute){
                if($attribute->name == 'name'){
                    $collection['name'] = $attribute->value;
                }
                if($attribute->name == 'property' && ($attribute->value == 'og:image')){
                    $collection['post_image'] = $attribute->value;

                }
                if($attribute->name == 'content'){
                    $collection['content'] = $attribute->value ?? '';
                }
                if($attribute->name == 'schema'){
                    $collection['schema'] = $attribute->value;
                }
            }
            if(isset($collection['name'])){
                $metaCollection['meta'][$collection['name']] = $collection['content'];
            }

            if(isset($collection['post_image'])){
                $metaCollection['post_image'] = last(explode("/", $collection['content']));
            }

        }
        $metaCollection['title'] = $crawler->filter("title")->innerText() ?? '';

        return $metaCollection;
    }

}
