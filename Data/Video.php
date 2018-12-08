<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Data;

use XF\Mvc\Entity\AbstractCollection;

class Video
{
    private $videos = null;

    public function addVideos(AbstractCollection $videos)
    {
        $this->videos = $videos;
    }

    /**
     * @return null|AbstractCollection
     */
    public function getVideos()
    {
        return $this->videos;
    }
}
