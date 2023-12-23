<?php

namespace App\Console\Commands\ProcessLinks;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use function PHPUnit\Framework\isInstanceOf;

class Product extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:process-links-products';

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
        $links = Link::where('type', '=', 'do-choi-xe-hoi')->get();
        if ($links) {
            $manDetails = $links->where("source", "=", "man_detail");
            if ($manDetails->count() > 0) {
                foreach ($manDetails as $detail) {
                    $this->debug($detail);
                    $breadcrums = [];
                    $cat = null;
                    $list = null;
                    $crawlerCat = new Crawler($detail->content);
                    $noidung = $crawlerCat->filter("div.tieude_giua > div > a.text-1");
                    foreach ($noidung->links() as $cLink) {
                        $breadcrums[] = $cLink->getNode();
                    }
                    if (isset($breadcrums[2]) && count($breadcrums) > 3) {
                        $detail->list = Str::replace('do-choi-xe-hoi/', '',$breadcrums[2]->getAttribute("href"));
                        $list = $links->where('source', '=', 'man_list')->where('slug', '=', $detail->list)->first();
                        $list->title = $breadcrums[2]->nodeValue;
                        $list->save();
                    }
                    if (isset($breadcrums[3]) && count($breadcrums) > 4) {
                        $detail->cat = Str::replace('do-choi-xe-hoi/', '', $breadcrums[3]->getAttribute("href"));
                        $detail->cat = Str::replace('/', '', $detail->cat);

                        $cat = $links->where('source', '=', 'man_cat')->where('slug', '=', $detail->cat)->first();
                        $cat->title = $breadcrums[3]->nodeValue;
                        $cat->save();
                    }
                    $detail->title = last($breadcrums)->nodeValue;
                    $detail->save();
                }
            }
        }

        /**
         * INsert List
         */
        $product_list = $this->insertProductList($links);
        $product_cat = $this->insertProductCats($links, $product_list);
        $this->insertProducts($links, $product_list, $product_cat);
    }
    private function debug($detail){
        return;
        $crawler = new Crawler($detail->content);
        $feature_cke = $crawler->filter("ul.product_info > div.mota_pro_detail")->html();
        dd($feature_cke);
        $sale_cke = $crawler->filter("ul.product_info > div.box_khuyenmai > p.km ~ div.mota_pro_detail")->html();
        $present_cke = $crawler->filter("ul.product_info > div.box_khuyenmai.box_khuyenmai > div.mota_pro_detail > li")
            ->html();
        $thong_r = $crawler->filter("div.thongtin_sp_box > div.thong_r > div.content_sp1")
            ->html();
    }
    private function insertProducts($links, array $lists, array $cats){
        $manDetail = $links->where("source", "=", "man_detail");
        foreach ($manDetail as $detail){
            $crawler = null;
            try {
                $feature_cke_content = '';
                $sale_cke_content = '';
                $present_cke_content = '';
                $thong_r_content = '';
                $contentvi = '';
                $crawler = new Crawler($detail->content);
                $metaCollection = $this->metadata($crawler);


                $contentPro = $crawler->filter("div.thongtin_sp_box > div.thong_l > div.content_sp");

                if(!is_null($contentPro->getNode(0))){
                    $contentvi = $contentPro->html();
                }

                $mainPhoto = $crawler->filter("a#Zoom-detail");
                $img = '';
                if(!is_null($mainPhoto)){
                    $mainPhoto = $mainPhoto->attr("href");
                    $img = last(explode("/", $mainPhoto));
                }


                $giacu = $crawler->filter("li.gia.giacu");
                $giakm = $crawler->filter("li.giakm");
                $giacu_value = 0;
                $giakm_value = 0;
                if($giacu->getNode(0) != null){
                    $giacu_value = Str::replace([
                        "Giá: ",
                        " vnđ",
                        '.'
                    ],[
                        '','',''
                    ],$giacu->innerText()
                    );
                }

                if($giakm->getNode(0) != null){
                    $giakm_value = Str::replace([
                        "Giá K.mãi: ",
                        " vnđ",
                        '.'
                    ],[
                        '','',''
                    ],$giakm->innerText()
                    );
                }

                $feature_cke = $crawler->filter("ul.product_info > p.tn ~ div.mota_pro_detail");

                if(!is_null($feature_cke->getNode(0))){
                    $feature_cke_content = $feature_cke->html();
                }

                $sale_cke = $crawler->filter("ul.product_info > div.box_khuyenmai > p.km ~ div.mota_pro_detail");

                if(!is_null($sale_cke->getNode(0))){
                    $sale_cke_content = $sale_cke->html();
                }

                $present_cke = $crawler->filter("ul.product_info > div.box_khuyenmai.box_khuyenmai > div.mota_pro_detail > li")
                    ;

                if(!is_null($present_cke->getNode(0))){
                    $present_cke_content = $present_cke->html();
                }


                $thong_r = $crawler->filter("div.thongtin_sp_box > div.thong_r > div.content_sp1");

                if(!is_null($thong_r->getNode(0))){
                    $thong_r_content = $thong_r->html();
                }
                foreach ([
                             'slugvi' => $detail->slug,
                             'namevi' => $detail->title,
                             'contentvi' => $contentvi,
                             'photo' => $img,
                             'regular_price' => $giacu_value,
                             'sale_price' => $giakm_value,
                             'feature_cke' => $feature_cke_content,
                             'sale_cke' => $sale_cke_content,
                             'present_cke' => $present_cke_content,
                             'info_cke' => $thong_r_content,
                             'dimension' => !empty($thong_r_content) ? '9-10 inch' : '',
                             'storage' => !empty($thong_r_content) ? '2GB/16GB' : '',
                             'processer' => !empty($thong_r_content) ? '8 nhân - 1.8GHz' : '',
                             'camera' => !empty($thong_r_content) ? 'Camera 360: Không' : '',
                             'id_list' => $lists[$detail->list] ?? null,
                             'id_cat' => $cats[$detail->cat] ?? null,
                             'type' => $detail->type
                         ] as $k => $v){
                    if(is_object($v)){
                        if(Crawler::class == get_class($v)){
                            dump($k);
                        }
                    }

                }
                DB::connection("mysql")->table("table_product")->insert([
                    'slugvi' => $detail->slug,
                    'namevi' => $detail->title,
                    'contentvi' => $contentvi,
                    'photo' => $img,
                    'regular_price' => $giacu_value,
                    'sale_price' => $giakm_value,
                    'feature_cke' => $feature_cke_content,
                    'sale_cke' => $sale_cke_content,
                    'present_cke' => $present_cke_content,
                    'info_cke' => $thong_r_content,
                    'dimension' => !empty($thong_r_content) ? '9-10 inch' : '',
                    'storage' => !empty($thong_r_content) ? '2GB/16GB' : '',
                    'processer' => !empty($thong_r_content) ? '8 nhân - 1.8GHz' : '',
                    'camera' => !empty($thong_r_content) ? 'Camera 360: Không' : '',
                    'id_list' => $lists[$detail->list] ?? null,
                    'id_cat' => $cats[$detail->cat] ?? null,
                    'type' => $detail->type
                ]);

                $_product = DB::connection("mysql")->table("table_product")->where("slugvi", '=', $detail->slug)->first();
                DB::connection("mysql")->table("table_seo")->insert([
                    'com' => 'product',
                    'id_parent' => $_product->id,
                    'act' => 'man',
                    'type' => $detail->type,
                    'titlevi' => $_product->namevi,
                    'descriptionvi' => $metaCollection['meta']['description'],
                    'keywordsvi' => $metaCollection['meta']['keywords']
                ]);
            }catch (\Exception $ex){


            }

        }
    }

    private function insertProductList($links){
        $manLists = $links->where("source", "=", "man_list");
        $result = [];
        foreach ($manLists as $list){
            DB::connection("mysql")->table("table_product_list")->insert([
                'namevi' => $list->title,
                'slugvi' => $list->slug,
                'status' => 'hienthi, noibat'
            ]);
            $_product_list = DB::connection("mysql")->table("table_product_list")->where("slugvi", '=', $list->slug)
                ->first();
            $result[$list->slug] = $_product_list->id;
        }

        return $result;
    }

    private function insertProductCats($links, $list){
        $manCats = $links->where("source", "=", "man_cat");
        $result = [];
        foreach ($manCats as $cat){
            DB::connection("mysql")->table("table_product_cat")->insert([
                'namevi' => $cat->title,
                'slugvi' => $cat->slug,
                'id_list' => $list[$cat->list] ?? '',
                'status' => 'hienthi, noibat'
            ]);
            $_product_cat = DB::connection("mysql")->table("table_product_cat")->where("slugvi", '=', $cat->slug)
                ->first();
            $result[$cat->slug] = $_product_cat->id;
        }

        return $result;
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
