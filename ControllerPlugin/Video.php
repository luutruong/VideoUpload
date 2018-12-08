<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\ControllerPlugin;

use XF\Entity\Post;
use XF\Entity\Thread;
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

        $allowVideoExts = $this->options()->TVU_allowedVideoExtensions;
        $allowVideoExts = preg_split('/\s*,\s*/', $allowVideoExts, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($allowVideoExts)) {
            $allowVideoExts = [];
        }

        /** @var Post $post */
        foreach ($posts as $post) {
            $attachments = $post->Attachments;
            foreach ($attachments as $index => $attachment) {
                if ($attachment->Data->width > 0
                    && $attachment->Data->height > 0
                    && in_array($attachment->getExtension(), $allowVideoExts, true)
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
            $videoData->addVideos($thread->thread_id, $videos);
        }
    }
}
