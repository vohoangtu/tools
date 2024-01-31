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

class DetectType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:detect-basic';

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
        foreach ($links as $link){
            $crawler = new Crawler($link->content);
            $metaCollection = $this->metadata($crawler);

            $hrefs = $crawler->filter("body div.breadCrumbs a");
            $breadcrums = null;
            foreach ($hrefs->links() as $cLink) {
                $breadcrums[] = Str::replace("https://diennuochuynhchuong.com/", "", $cLink->getNode()->getAttribute("href"));
            }
            if(is_null($breadcrums)) continue;
            $index = $breadcrums[0];
            $type =  $breadcrums[1];
            $last = last($breadcrums);
            $link->title = $metaCollection['title'];
            if($type == 'san-pham'){
                $link->source = 'product';
                $link->type = $type;
                for ($i = 2; $i < count($breadcrums); $i++){
                    if($i < count($breadcrums) && $i < 4){
                        $link->{$this->getMan($i)} = $breadcrums[$i];
                    }
                    if(isset($breadcrums[$i])){
                        $link->sub = $this->getMan($i);
                    }
                }

                if($link->sub == 'cat'){
                    preg_match("/\"@type\": \"Product\"/", $link->content, $match);
                    if(!empty($match)){
                        $link->sub = 'detail';
                        $link->cat = null;
                    }
                }

            }else{
                $link->type = $type;
                $link->source = 'news';
                for ($i = 1; $i < count($breadcrums); $i++){
                    if(isset($breadcrums[$i])){
                        $link->sub = 'detail';
                    }
                }
            }

            $link->save();
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
