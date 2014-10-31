<?php

namespace solcity\rssparser;

use \app\models\User;
use \app\models\Webcrawler;
use \app\models\Article;
use \app\models\WebcrawlerImportLog;

require_once(__DIR__  . '/rsslib/rsslib.php');

class Importer extends \yii\base\Widget {
    public $options = [];
    
    public function init() {
        parent::init();
    }
    
    public function run() {
        if ($this->options['action'] == 'import') {
            return json_encode($this->import());
        }
    }
    
    private function import() {
        $feeds = Webcrawler::find()->all();
        $rssUserId = User::find()->where("username = 'SOLCITY_RSS_CRAWLER'")->one()->userId;
        
        foreach ($feeds as $feed) {
            $rssFeed = RSS_Get_Custom($feed->link);
            foreach ($rssFeed as $rssItem) {
                $article = new Article([
                    'title' => $rssItem['title'],
                    'article' => $rssItem['description'],
                    'originLink' => $rssItem['link'],
                    'userId' => $rssUserId,
                    'categoryId' => $feed->categoryId,
                    'subCategoryId' => $feed->subCategoryId,
                    'released' => 0
                ]);
                
                $log = new WebcrawlerImportLog(['webcrawlerId' => $feed->webcrawlerId]);
                if ($article->save(false)) {
                    $log->articleId = $article->articleId;
                } else {
                    foreach ($article->errors as $error) $log->message .= $error . "\n";
                }
                $log->save();
            }
        }
        return true;
    }
}
