<?php

namespace App\Console\Commands;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class CrawlSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:crawl-sitemap';

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

        $sitemap = Http::get(config("tools.crawler.sitemap"));

        if($sitemap->ok()){
            $regex = "/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i";
            preg_match_all($regex, $sitemap->body(), $locs);
            foreach ($locs[0] as $loc){
                if(Str::contains($loc, [config("tools.crawler.site")])){
                    $content = Http::get($loc);
                    if($content->ok()){
                        $pageContent = $content->body();
                        $crawler = new Crawler($pageContent, null, config("tools.crawler.site"));
                        foreach ($crawler->filter("body img")->images() as $node){
                            $img = $node->getNode()->getAttribute("src");
                            if(empty($img)){
                                $img = $node->getNode()->getAttribute("data-src");
                            }
                            Log::info($img);
                            if(!empty($img)){
                                $this->saveImage($img);
                            }
                        }
                        Link::query()->insert([
                            'link' => $loc,
                            'slug' => Str::replace(config('tools.crawler.site'), '', $loc),
                            'content' => $pageContent
                        ]);

                    }else{
                        $this->info("fail at: " . $loc);
                    }
                }
            }
        }


    }

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

}
