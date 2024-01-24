<?php

namespace App\Console\Commands\Old;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class StepOne extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:old-step-one';

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
            $link->sub = '';
            $link->title = '' ;
            $crawler = new Crawler($link->content);
            if(Str::startsWith($link->slug, [
                'san-pham/'
            ])){
                if(Str::contains($link->content, [
                    '<div class="" id="product-detail">'
                ])){
                    $link->sub = 'detail';
                }else{
                    if(Str::endsWith($link->slug,[
                        '.htm'
                    ])){
                        $link->sub = 'list';
                    }else{
                        $_slug = explode("/", $link->slug);
                        $link->sub = 'cat';
                        $link->list = $_slug[1];
                    }
                }
                if(in_array($link->sub, [
                    'list', 'cat'
                ])){
                    $tilte = $crawler->filter("div.title-global > h2 > span")->innerText();
                    $link->title = $tilte;
                }

                if(in_array($link->sub, [
                    'detail'
                ])){
                    $tilte = $crawler->filter("div#product-detail div#detail div.title > h1")->innerText();
                    $link->title = $tilte;
                }
                $link->save();
            }

        }
    }

    protected function removeBaseHref($fullUri){
        $uri = Str::replace(config("tools.crawler.site"), '',$fullUri);
        return Str::replace('/', '', $uri);
    }

}
