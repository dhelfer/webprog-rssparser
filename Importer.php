<?php

namespace solcity\rssparser;

use \app\models\User;
use \app\models\Webcrawler;

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
        $importStates = array();
        
        $feeds = Webcrawler::find()->all();
        $rssUserId = User::find()->where("username = 'SOLCITY_RSS_CRAWLER'")->one()->userId;
        
        foreach ($feeds as $feed) {
            $rssFeed = RSS_Get_Custom($feed->link);
            foreach ($rssFeed as $rssItem) {
                $article = new \app\models\Article(array(
                    'title' => $rssItem['title'],
                    'article' => $rssItem['description'],
                    'originLink' => $rssItem['link'],
                    'userId' => $rssUserId,
                    'categoryId' => $feed->categoryId,
                    'subCategoryId' => $feed->subCategoryId,
                    'released' => 0
                ));
                
                if ($article->save(false)) {
                    $importStates[$feed->webcrawlerId][$article->articleId] = true;
                } else {
                    $importStates[$feed->webcrawlerId][$article->articleId] = false;
                }
            }
        }
        return $importStates;
    }
}
