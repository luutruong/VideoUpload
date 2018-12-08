<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Data;

use XF\Mvc\Entity\AbstractCollection;

class Video
{
    private $videos = [];

    public function addVideos($threadId, AbstractCollection $videos)
    {
        $this->videos[$threadId] = $videos;
    }

    /**
     * @param int $threadId
     * @return null|AbstractCollection
     */
    public function getVideos($threadId)
    {
        return isset($this->videos[$threadId]) ? $this->videos[$threadId] : null;
    }
}
