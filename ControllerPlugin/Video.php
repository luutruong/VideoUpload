<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\ControllerPlugin;

use XF\Entity\Post;
use XF\Entity\Thread;
use Truonglv\VideoUpload\Utils\File;
use XF\Mvc\Entity\AbstractCollection;
use XF\ControllerPlugin\AbstractPlugin;

class Video extends AbstractPlugin
{
    /**
     * @param Thread $thread
     * @param array|AbstractCollection $posts
     */
    public function collectVideos(Thread $thread, $posts)
    {
        $attachmentIds = [];

        /** @var Post $post */
        foreach ($posts as $post) {
            $attachments = $post->Attachments;
            foreach ($attachments as $index => $attachment) {
                if ($attachment->Data->width > 0
                    && $attachment->Data->height > 0
                    && File::isValidVideo($attachment->filename)
                ) {
                    $attachmentIds[] = $attachment->attachment_id;
                }
            }
        }

        if ($attachmentIds) {
            $videoFinder = $this->finder('Truonglv\VideoUpload:Video');
            $videoFinder->where('thread_id', $thread->thread_id);
            $videoFinder->where('attachment_id', $attachmentIds);

            $videos = $videoFinder->fetch();

            /** @var \Truonglv\VideoUpload\Data\Video $videoData */
            $videoData = $this->data('Truonglv\VideoUpload:Video');
            $videoData->addVideos($videos);
        }
    }
}
