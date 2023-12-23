<?php

namespace App\Console\Commands;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ProcessProductDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:process-product';

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
        $links = Link::where('type', '<>', 'tin-tuc')->get();
        if($links){
            $productList = $links->where('source', '=', 'man_list');
            foreach ($productList as $list){
                DB::connection("mysql")->table("table_product_list")->insert([
                    'namevi' => $list->title,
                    'slugvi' => $list->slug,
                    'status' => 'hienthi'
                ]);
                $plist = DB::connection("mysql")->table("table_product_list")->where("slugvi", '=', $list->slug)->first();

                $products = $links->where('source', '=', 'man_detail')->where('list','=', $plist->slugvi);
                foreach ($products as $product){
                    $crawler = new Crawler($product->content);

                    $metaCollection = $this->metadata($crawler);
                    $_product = DB::connection("mysql")->table("table_product")->insert([
                        'id_list' => $plist->id,
                        'namevi' => $product->title,
                        'slugvi' => $product->slug,
                        'status' => 'hienthi',
                        'type' => 'san-pham',
                        'photo' => $metaCollection['post_image']
                    ]);
                    $_product = DB::connection("mysql")->table("table_product")->where("slugvi", '=', $product->slug)->first();
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
