<?php

namespace solcity\rssparser;

use \app\models\User;
use \app\models\Webcrawler;
use \app\models\Article;
use \app\models\WebcrawlerImportLog;
use \Yii;

class Importer extends \yii\base\Widget {
    public $options = [];
    
    public function init() {
        parent::init();
    }
    
    public function run() {
        $function = $this->options['action'];
        if (isset($this->options['json']) && $this->options['json'] === true) {
            return json_encode($this->$function());
        } else {
            return $this->$function();
        }
    }
    
    private function import() {
        if (isset($this->options['webcrawlerId'])) {
            $feeds = Webcrawler::find()->where(['webcrawlerId' => $this->options['webcrawlerId']])->all();
        } else {
            $feeds = Webcrawler::find()->all();
        }
        
        $rssUserId = User::find()->where("username = '" . Yii::$app->params['rssimport']['user'] . "'")->one()->userId;
        $runNumberRecord = Yii::$app->db->createCommand()->setSql('SELECT MAX(runNumber) as runNumber from sc_webcrawler_import_log')->queryOne();
        $runNumber = 1;
        if (!is_null($runNumberRecord['runNumber'])) {
            $runNumber = $runNumberRecord['runNumber'] + 1;
        }
        
        $result = ['feeds' => 0, 'articles' => 0];
        foreach ($feeds as $feed) {
            $rssFeed = simplexml_load_file($feed->link);
            foreach ($rssFeed->channel->item as $rssItem) {
                $article = new Article([
                    'title' => (string)$rssItem->title,
                    'article' => (string)$rssItem->description,
                    'originLink' => (string)$rssItem->link,
                    'userId' => $rssUserId,
                    'categoryId' => $feed->categoryId,
                    'subCategoryId' => $feed->subCategoryId,
                    'released' => 0
                ]);
                
                //assign here dynamic attributes from specialMapping
                if (!is_null($feed->specialMapping) && strpos($feed->specialMapping, ';') !== false) {
                    $specialMapping = explode('; ', $feed->specialMapping);
                    foreach ($specialMapping as $sp) {
                        if (strpos($sp, '=') !== false) {
                            $attributes = explode('=', $sp);
                            $articleAttribute = $attributes[0];
                            $feedAttribute = $attributes[1];
                            $article->$articleAttribute = $rssItem->$feedAttribute;
                        }
                    }
                }
                
                $log = new WebcrawlerImportLog(['webcrawlerId' => $feed->webcrawlerId, 'runNumber' => $runNumber]);
                $duplicateArticle = $article->findDuplicateByOriginlink();
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
                $result['articles']++;
            }
            $result['feeds']++;
        }
        return $result;
    }
    
    public function getItemStructure() {
        $rssFeed = simplexml_load_file($this->options['url']);
        $item = $rssFeed->channel->item[0];
        
        $attributes = array();
        foreach ((array)$item as $key => $value) {
            $attributes[] = $key;
        }
        
        return $attributes;
    }
}
