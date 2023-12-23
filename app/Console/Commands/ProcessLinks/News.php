<?php

namespace App\Console\Commands\ProcessLinks;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class News extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:crawl-news';

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
        $links = Link::query()
            ->where("type", "<>", "do-choi-xe-hoi")
            ->where("type", "<>", "ho-tro")
            ->where("source", "=", "man_detail")
            ->get();

        if ($links) {
            foreach ($links as $link){
                $title = '';

                $crawler = new Crawler($link->content);
                $metaCollection = $this->metadata($crawler);
                $noidung = $crawler->filter("div.tieude_giua > div > a.text-1");


                foreach ($noidung->links() as $cLink) {
                    $breadcrums[] = $cLink->getNode();
                }
                $title = last($breadcrums)->nodeValue;


                $contentvi = $crawler->filter("div#main_content > div.box_container > div.content");

                try {
                    DB::connection("mysql")->table("table_news")->insert([
                        'namevi' => $title,
                        'contentvi' => empty($contentvi) ? '' : $contentvi->html(),
                        'slugvi' => $link->slug,
                        'status' => 'hienthi, noibat',
                        'type' => $link->type,
                        'photo' => $metaCollection['post_image']
                    ]);
                }catch (\Exception $ex){
                    $this->error($link->link);
                }


                $_product = DB::connection("mysql")->table("table_news")->where("slugvi", '=', $link->slug)->first();
                DB::connection("mysql")->table("table_seo")->insert([
                    'com' => 'news',
                    'id_parent' => $_product->id,
                    'act' => 'man',
                    'type' => $_product->type,
                    'titlevi' => $_product->namevi,
                    'descriptionvi' => $metaCollection['meta']['description'],
                    'keywordsvi' => $metaCollection['meta']['keywords']
                ]);
            }
        }
    }

    private function getPath($url){
        $path =  Str::replace(config("tools.crawler.site"), "", $url);
        return Str::replace(".html", "", $path);
    }

    private function getType($path){
        if(Str::contains($path, 'do-choi-xe-hoi')) return 'do-choi-xe-hoi';
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
