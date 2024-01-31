<?php

namespace App\Console\Commands\Crawler;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

abstract class AbstractCrawler extends Command
{
    protected $photos = [];
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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

    protected function filterPageImages(Crawler $crawler): array
    {
        $result = [];
        foreach ($crawler->filter("body img")->images() as $node){
            $img = $node->getNode()->getAttribute("src");
            if(empty($img)){
                $img = $node->getNode()->getAttribute("data-src");
            }
            $result[] = $img;
        }
        return $result;
    }
    /**
     * Execute the console command.
     */
    abstract public function handle();


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

    protected function getPath($url){
        $path =  Str::replace(config("tools.crawler.site"), "", $url);
        return Str::replace(".html", "", $path);
    }

    protected function getType($path){
        if(Str::contains($path, 'do-choi-xe-hoi')) return 'do-choi-xe-hoi';
    }

    protected function removeBaseHref($fullUri){
        $uri = Str::replace(config("tools.crawler.site"), '',$fullUri);
        return Str::replace('/', '', $uri);

    }

}
