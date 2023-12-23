<?php

namespace App\Console\Commands;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ProcessOther extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:process-others';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

    protected function newList($table, array $data)
    {
        DB::connection("mysql")->table($table)->insert([
            'namevi' => $data['title'],
            'slugvi' => $data['slug'],
            'status' => 'hienthi',
            'type' => $data['type'],
        ]);
        $plist = DB::connection("mysql")->table($table)->where("slugvi", '=',
            $data['slug'])
            ->first();
        return $plist;
    }

    protected function newMan($table, array $data)
    {
        DB::connection("mysql")->table($table)->insert($data);

        return DB::connection("mysql")->table($table)->where("slugvi", '=',
            $data['slugvi'])->first();
    }
    public function handle()
    {
        $productType = [

        ];
        $newsType = [
            'xay-dung', 'thiet-ke-noi-that',
            'tin-tuc', 'dich-vu'
        ];
        $allTypes = [
            'product' => $productType,
            'news' => $newsType
        ];

        $links = Link::where('type', '<>', 'index')->get();
        if($links){
            //process man_list
            foreach ($allTypes as $source => $types){
                foreach ($types as $type){
                    $manList = $links->where("source", "=", "man_list")->where("type", "=", $type);
                    if($manList->count() > 0){
                        foreach ($manList as $list){
                            $plist = $this->newList("table_{$source}_list", [
                                'title' => $list->title,
                                'slug' => $list->slug,
                                'type' => $list->type
                            ]);
                            $this->info("Insert list: " . $plist->namevi);
                            $products = $links->where('source', '=', 'man_detail')->where('list','=', $plist->slugvi);

                            foreach ($products as $product){
                                $crawler = new Crawler($product->content);

                                $metaCollection = $this->metadata($crawler);

                                //lay noi dung
                                $noidung = $crawler->filter("div.detail__content");
                                $_product = $this->newMan("table_{$source}", [
                                    'id_list' => $plist->id,
                                    'namevi' => $product->title,
                                    'slugvi' => $product->slug,
                                    'contentvi' => $noidung->html(),
                                    'descvi' => $product->desc ?? $metaCollection['meta']['description'],
                                    'status' => 'noibat,hienthi',
                                    'type' => $type,
                                    'photo' => $metaCollection['post_image']
                                ]);

                                DB::connection("mysql")->table("table_seo")->insert([
                                    'com' => $source,
                                    'id_parent' => $_product->id,
                                    'act' => 'man',
                                    'type' => $type,
                                    'titlevi' => $_product->namevi,
                                    'descriptionvi' => $metaCollection['meta']['description'],
                                    'keywordsvi' => $metaCollection['meta']['keywords']
                                ]);
                            }
                        }
                    }else{
                        $_products = $links->where("source", "=", "man_detail")->where("type", "=", $type);
                        foreach ($_products as $product){
                            $crawler = new Crawler($product->content);

                            $metaCollection = $this->metadata($crawler);
                            //lay noi dung
                            $noidung = $crawler->filter("div.detail__content");
                            $_product = $this->newMan("table_{$source}", [
                                'namevi' => $product->title,
                                'slugvi' => $product->slug,
                                'contentvi' => $noidung->html(),
                                'descvi' => $product->desc ?? $metaCollection['meta']['description'],
                                'status' => 'noibat,hienthi',
                                'type' => $type,
                                'photo' => $metaCollection['post_image']
                            ]);

                            DB::connection("mysql")->table("table_seo")->insert([
                                'com' => $source,
                                'id_parent' => $_product->id,
                                'act' => 'man',
                                'type' => $type,
                                'titlevi' => $_product->namevi,
                                'descriptionvi' => $metaCollection['meta']['description'],
                                'keywordsvi' => $metaCollection['meta']['keywords']
                            ]);
                        }
                    }

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
