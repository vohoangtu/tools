<?php

namespace App\Console\Commands\Crawler;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class CrawlPage extends AbstractCrawler
{

    protected $photos = [];

    protected $signature = 'crawl:page';

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
        $links = [
            "http://dahoacuongbachthinh.com/dich-vu.html",
            "http://dahoacuongbachthinh.com/tin-tuc.html",
        ];

        foreach($links as $link){
            $pageContent = $this->get($link);
            preg_match_all('|http://([^\s]*)|', $pageContent, $matches);

            $matches = array_filter($matches[0], function ($value){
                return Str::contains($value, "dahoacuongbachthinh.com") && (Str::contains($value, "dich-vu/") || Str::contains($value, "tin-tuc/"));
            });
            foreach ($matches as $url){
                $url = Str::replace('">', '', $url);

                $content = Http::get($url);
                if($content->ok()){
                    $pageContent = $content->body();
                    $crawler = new Crawler($pageContent, null, config("tools.crawler.site"));
                    $pageImages = $this->filterPageImages($crawler);
                    foreach ($pageImages as $image){
                        $this->saveImage($image);
                    }

                    $_slug = last(explode("/", $url));
                    $_slug = explode("-", Str::replace(".html", "", $_slug));
                    array_pop($_slug);

                    Link::query()->insert([
                        'link' => $url,
                        'slug' => implode("-", $_slug),
                        'content' => $pageContent
                    ]);

                }else{
                    $this->info("fail at: " . $url);
                }
            }


            $links = Link::all();

            foreach ($links as $link){
                $title = '';

                $crawler = new Crawler($link->content);
                $metaCollection = $this->metadata($crawler);

                $title = $crawler->filter("div.header > h2");


                $contentvi = $crawler->filter("div.news-content > div > div.content");

                if(Str::contains($link->link, 'dich-vu')){
                    $link->type = 'dich-vu';
                }else{
                    $link->type = 'tin-tuc';
                }

                DB::connection("mysql")->table("table_news")->insert([
                    'namevi' => $title->text(),
                    'contentvi' => empty($contentvi) ? '' : $contentvi->html(),
                    'slugvi' => $link->slug,
                    'status' => 'hienthi,noibat',
                    'type' => $link->type,
                    'photo' => $metaCollection['post_image']
                ]);
                dump($link->slug);

                $_product = DB::connection("mysql")->table("table_news")->where("slugvi", '=', $link->slug)->first();

                DB::connection("mysql")->table("table_seo")->insert([
                    'com' => 'news',
                    'id_parent' => $_product->id,
                    'act' => 'man',
                    'type' => $_product->type,
                    'titlevi' => $_product->namevi,
                    'descriptionvi' => $metaCollection['meta']['description'],
                    'keywordsvi' => $metaCollection['meta']['keywords']
                ]);
            }
        }
    }

}
