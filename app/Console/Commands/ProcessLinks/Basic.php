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

class Basic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:crawl-basic';

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
            $path = trim($this->getPath($link->link));
            $epl = [];

//            if(Str::endsWith($path, "/")){
//                $path = Str::substr($path, 0 , -1);
//            }

            if(Str::contains($path, '/' )){
                $epl = explode('/', $path);
                $path = last($epl);
                if(empty($path)){
                    $link->source = "man_cat";
                    $path = $epl[count($epl) - 2];
                }else{
                    if(Str::contains($link->link, ".html")){
                        $link->source = "man_detail";
                    }else{
                        if(count($epl) >= 2){
                            $link->source = "man_list";
                        }else{
                            $link->source = "man";
                        }
                    }
                }
                $link->type = $epl[0] ?? $path;
            }

            if(empty($link->source) && empty($link->type)){
                $link->type = $path;
                $link->source = "man";
            }
            $link->slug = $path;
            $link->save();
        }
    }

    private function getPath($url){
        $path =  Str::replace(config("tools.crawler.site"), "", $url);
        return Str::replace(".html", "", $path);
    }

    private function getType($path){
        if(Str::contains($path, 'do-choi-xe-hoi')) return 'do-choi-xe-hoi';
    }

}
