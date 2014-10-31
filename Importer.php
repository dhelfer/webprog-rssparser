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
        $runNumber = WebcrawlerImportLog::findOne(['runNumber' => '(SELECT MAX(runNumber) from sc_webcrawler_import_log)'])->runNumber;
        
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
                
                $log = new WebcrawlerImportLog(['webcrawlerId' => $feed->webcrawlerId, 'runNumber' => $runNumber]);
                $duplicateArticle = $article->duplicateByOriginlink;
                if (!is_null($duplicateArticle)) {
                    $log->articleId = $duplicateArticle->articleId;
                    $log->message = 'already imported';
                } elseif ($article->save(false)) {
                    $log->articleId = $article->articleId;
                } else {
                    $log->message = '';
                    foreach ($article->errors as $error){
                        $log->message .= $error . "<br>";
                    }
                    $log->message = substr($log->message, -4);
                }
                $log->save();
            }
        }
        return true;
    }
}
