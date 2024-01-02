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

class DetectDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:detect-detail';

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
            ->where("source", "=", "product")
            ->get();

        $allProductLists = $links->where("sub", "=", "list");
        $mapList = [];
        foreach ($allProductLists as $list){
            $mapList[$list->slug] = DB::connection("mysql")->table("table_product_list")->insertGetId([
                'namevi' => $list->title,
                'slugvi' => $list->slug,
                'status' => 'hienthi',
                'type' => 'san-pham',
                'date_created' => time(),
                'date_updated' => time(),
            ]);
        }

        $allProductCats = $links->where("sub", "=", "cat");
        $mapCat = [];
        foreach ($allProductCats as $cat){
            $mapCat[$cat->slug] = DB::connection("mysql")->table("table_product_cat")->insertGetId([
                'id_list' => $mapList[$cat->list],
                'namevi' => $cat->title,
                'slugvi' => $cat->slug,
                'status' => 'hienthi',
                'type' => 'san-pham',
            ]);
        }


        $allProducts = $links->where("sub", "=", "detail");
        foreach ($allProducts as $product){
            $crawler = new Crawler($product->content);
            $metaCollection = $this->metadata($crawler);
            $description = $crawler->filter("div.desc-pro-detail")->html();
            $cotnent = $crawler->filter("div#info-pro-detail")->html();

            $id_product = DB::connection("mysql")->table("table_product")->insertGetId([
                'id_list' => $mapList[$product->list],
                'id_cat' => $mapCat[$product->cat],
                'namevi' => $product->title,
                'descvi' => $description ?? '',
                'contentvi' => $cotnent ?? '',
                'slugvi' => $product->slug,
                'status' => 'hienthi',
                'type' => 'san-pham',
                'photo' => $metaCollection['post_image'],
                'date_created' => time(),
                'date_updated' => time(),
            ]);
            //Insert gallery products
            $images = $crawler->filter("div.left-pro-detail img")->images();
            $imgs = null;
            foreach ($images as $img){
                $_img = explode("/", $img->getNode()->getAttribute("src"));

                $imgs[] = last($_img);
            }
            for ($i=1;$i < count($imgs); $i++){
                DB::connection("mysql")->table("table_gallery")->insertGetId([
                    'id_parent' => $id_product,
                    'photo' => $imgs[$i],
                    'type' => 'san-pham',
                    'com' => 'product',
                    'kind' => 'man',
                    'val' => 'san-pham',
                    'status' => 'hienthi',
                    'date_created' => time(),
                    'date_updated' => time(),
                ]);
            }
            //SEO

            DB::connection("mysql")->table("table_seo")->insert([
                'com' => 'product',
                'id_parent' => $id_product,
                'act' => 'man',
                'type' => 'san-pham',
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
