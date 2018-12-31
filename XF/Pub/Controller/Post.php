<?php

namespace Truonglv\VideoUpload\XF\Pub\Controller;

use XF\Mvc\Reply\View;
use XF\Mvc\ParameterBag;

class Post extends XFCP_Post
{
    public function actionEdit(ParameterBag $params)
    {
        $response = parent::actionEdit($params);
        if ($response instanceof View) {
            $thread = $response->getParam('thread');
            $post = $response->getParam('post');

            /** @var \Truonglv\VideoUpload\ControllerPlugin\Video $videoPlugin */
            $videoPlugin = $this->plugin('Truonglv\VideoUpload:Video');
            $videoPlugin->collectVideos([$post->post_id => $post]);
        }

        return $response;
    }
}
