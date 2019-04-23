<?php

class MWXF_Hooks
{
    public static function ArticleSaveComplete(
        &$article, &$user, $text, $summary,
        $minoredit, $watchthis, $sectionanchor,
        &$flags, $revision, &$status, $baseRevId
    )
    {
        if (!class_exists('MediaWiki_Model_NewsFeed')) {
            return true;
        }

        if ($article instanceof WikiPage
            && $article->hasViewableContent()) {
            /** @var MediaWiki_Model_NewsFeed $newsFeedModel */
            $newsFeedModel = XenForo_Model::create('MediaWiki_Model_NewsFeed');
            $existingNewsFeedItem = $newsFeedModel->MediaWiki_getExistingNewsFeedItem(
                get_class($article),
                $article->getId()
            );

            if (empty($existingNewsFeedItem)) {
                // no existing item for this page

                $newsFeedModel->MediaWiki_publish(
                    get_class($article),
                    $article->getId(),
                    'update',
                    array(
                        'url' => strval($article->getTitle()->getFullURL()),
                        'title' => strval($article->getTitle()->getText()),
                        'text' => strval($article->getContent()->getTextForSummary()),
                    )
                );
            }
        }

        return true;
    }
}