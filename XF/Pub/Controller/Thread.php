<?php

namespace Truonglv\VideoUpload\XF\Pub\Controller;

use XF\Mvc\Reply\View;
use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);
        if ($response instanceof View) {
            $posts = $response->getParam('posts');

            /** @var \Truonglv\VideoUpload\ControllerPlugin\Video $videoPlugin */
            $videoPlugin = $this->plugin('Truonglv\VideoUpload:Video');
            $videoPlugin->collectVideos($posts);
        }

        return $response;
    }

    protected function getNewPostsReply(\XF\Entity\Thread $thread, $lastDate)
    {
        $response = parent::getNewPostsReply($thread, $lastDate);
        if ($response instanceof View) {
            $posts = $response->getParam('posts');

            /** @var \Truonglv\VideoUpload\ControllerPlugin\Video $videoPlugin */
            $videoPlugin = $this->plugin('Truonglv\VideoUpload:Video');
            $videoPlugin->collectVideos($posts);
        }

        return $response;
    }
}
