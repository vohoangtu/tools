<?php

namespace App\Console\Commands;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class DetectNews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:detect-news';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $links = Link::all();
        if($links){
            foreach ($links as $link){
                $crawler = new Crawler($link->content);
                preg_match("/\"@type\": \"NewsArticle\"/", $link->content, $match);
                if(!empty($match)){
                    $link->source = 'man_detail';
                }else{
                    continue;
                }
                preg_match("/\"headline\": \"(.*)\",/",$link->content, $matchTitle);
                $metaCollection = $this->metadata($crawler);
                //lay noi dung
                $noidung = $crawler->filter("div.news-content");

                $title_index = $crawler->filter("div.title_index > span")->text();
                $type = 'bang-gia';
                switch($title_index) {
                    case 'Tin tức':
                        $type = 'tin-tuc';
                        break;
                    case 'Dịch vụ':
                        $type = 'dich-vu';
                        break;
                }
                DB::connection("mysql")->table("table_news")->insert([
                    'namevi' => $matchTitle[1],
                    'slugvi' => $link->slug,
                    'contentvi' => $noidung->html(),
                    'descvi' => $product->desc ?? $metaCollection['meta']['description'],
                    'status' => 'noibat,hienthi',
                    'type' => $type,
                    'photo' => $metaCollection['post_image']
                ]);
                $_product = DB::connection("mysql")->table("table_news")->where("slugvi", '=', $link->slug)->first();
                DB::connection("mysql")->table("table_seo")->insert([
                    'com' => 'news',
                    'id_parent' => $_product->id,
                    'act' => 'man',
                    'type' => $type,
                    'titlevi' => $_product->namevi,
                    'descriptionvi' => $metaCollection['meta']['description'],
                    'keywordsvi' => $metaCollection['meta']['keywords']
                ]);
                $link->save();
            }
        }

    }

    protected function removeBaseHref($fullUri){
        $uri = Str::replace(config("tools.crawler.site"), '',$fullUri);
        return Str::replace('/', '', $uri);

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
