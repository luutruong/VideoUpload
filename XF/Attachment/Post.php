<?php

namespace Truonglv\VideoUpload\XF\Attachment;

use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use Truonglv\VideoUpload\Entity\Video;

class Post extends XFCP_Post
{
    public function onAssociation(Attachment $attachment, Entity $container = null)
    {
        parent::onAssociation($attachment, $container);

        if ($container instanceof \XF\Entity\Post) {
            $videoFinder = \XF::app()->finder('Truonglv\VideoUpload:Video');
            $videoFinder->where('attachment_id', $attachment->attachment_id)
                ->where('content_type', 'thread');

            /** @var Video $video */
            foreach ($videoFinder->fetch() as $video) {
                $video->content_id = $container->Thread->thread_id;
                $video->save();

                /** @var \Truonglv\VideoUpload\Repository\Video $videoRepo */
                $videoRepo = \XF::app()->repository('Truonglv\VideoUpload:Video');
                $videoRepo->postToProfile($video, $container);
            }
        }
    }
}
