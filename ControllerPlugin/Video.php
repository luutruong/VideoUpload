<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\ControllerPlugin;

use Truonglv\VideoUpload\Utils\File;
use XF\Mvc\Entity\AbstractCollection;
use XF\ControllerPlugin\AbstractPlugin;

class Video extends AbstractPlugin
{
    /**
     * @param array|AbstractCollection $entities
     */
    public function collectVideos($entities)
    {
        $attachmentIds = [];

        foreach ($entities as $entity) {
            $attachments = $entity->Attachments;
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
            $videoFinder->where('attachment_id', $attachmentIds);

            $videos = $videoFinder->fetch();

            /** @var \Truonglv\VideoUpload\Data\Video $videoData */
            $videoData = $this->data('Truonglv\VideoUpload:Video');
            $videoData->addVideos($videos);
        }
    }
}
