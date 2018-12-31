<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Repository;

use XF\Entity\Thread;
use XF\Entity\Attachment;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;

class Video extends Repository
{
    public function getChunkPath($attachmentHash, $extension, $chunkNumber)
    {
        return sprintf(
            'internal-data://tvu_video_upload/%s.%s.%d',
            $attachmentHash,
            $extension,
            $chunkNumber
        );
    }

    public function onEntityDeleted(Entity $entity)
    {
        $videoFinder = $this->finder('Truonglv\VideoUpload:Video');
        if ($entity instanceof Attachment) {
            $videoFinder->where('attachment_id', $entity->attachment_id);
        } elseif ($entity instanceof Thread) {
            $videoFinder->where('content_id', $entity->thread_id);
            $videoFinder->where('content_type', 'thread');
        } elseif ($entity instanceof User) {
            $videoFinder->where('content_id', $entity->user_id);
            $videoFinder->where('content_type', 'user');
        } else {
            $videoFinder->whereImpossible();
        }

        foreach ($videoFinder->fetch() as $video) {
            $video->delete();
        }
    }

    public function logError($message)
    {
        \XF::logError("[tl] Thread Video Upload: {$message}");
    }
}
