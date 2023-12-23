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

class ProductCont extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $photos = [];

    protected $signature = 'app:crawl-products-cont';

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
            ->where("type", "=", "do-choi-xe-hoi")->get();

        if ($links) {
            $linkCats = $links->where("source", "=", "man_cat")
                ->whereNull(["title"]);
            foreach ($linkCats as $detail) {
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
                    if($list == null){
                        dd([
                            $list,
                            $detail->list,
                            $detail->link
                        ]);
                    }
                    $list->title = $breadcrums[2]->nodeValue;
                    $list->save();
                }

                $detail->title = last($breadcrums)->nodeValue;
                $detail->save();
            }
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
