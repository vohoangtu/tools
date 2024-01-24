<?php

namespace App\Console\Commands\Breadscrums;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    protected $signature = 'app:detect-news-v2';

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
            ->where("source", "=", "news")
            ->get();
        $allProducts = $links->where("sub", "=", "detail");
        foreach ($allProducts as $product){
            $crawler = new Crawler($product->content);
            $metaCollection = $this->metadata($crawler);
            $cotnent = $crawler->filter("div.content-main");
            if($cotnent->count() > 0){
                $cotnent = $cotnent->html();
            }else{
                $cotnent = '';
            }
            $id_product = DB::connection("mysql")->table("table_news")->insertGetId([
                'namevi' => $product->title,
                'descvi' => $metaCollection["meta"]['description'] ?? '',
                'contentvi' => $cotnent ?? '',
                'slugvi' => $product->slug,
                'status' => 'hienthi',
                'type' => $product->type,
                'photo' => $metaCollection['post_image'],
                'date_created' => time(),
                'date_updated' => time(),
            ]);


            DB::connection("mysql")->table("table_seo")->insert([
                'com' => 'news',
                'id_parent' => $id_product,
                'act' => 'man',
                'type' => $product->type,
                'titlevi' => $product->title,
                'descriptionvi' => $metaCollection['meta']['description'],
                'keywordsvi' => $metaCollection['meta']['keywords'],

            ]);
        }


    }

    protected function getMan($index)
    {
        return [
            1 => 'index',
            2 => 'list',
            3 => 'cat',
            4 => 'detail'
        ][$index];
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
                if($attribute->name == 'description'){
                    $collection['description'] = $attribute->value;
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
