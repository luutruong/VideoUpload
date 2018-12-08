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
            $videoFinder->where('attachment_id', $attachment->attachment_id);

            /** @var Video $video */
            foreach ($videoFinder->fetch() as $video) {
                $video->thread_id = $container->Thread->thread_id;
                $video->save();
            }
        }
    }
}
