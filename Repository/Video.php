<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Repository;

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

    public function logError($message)
    {
        \XF::logError("[tl] Thread Video Upload: {$message}");
    }
}
