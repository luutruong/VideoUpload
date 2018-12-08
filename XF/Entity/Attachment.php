<?php

namespace Truonglv\VideoUpload\XF\Entity;

use Truonglv\VideoUpload\Utils\File;

class Attachment extends XFCP_Attachment
{
    public function hasTVUVideo()
    {
        return File::isValidVideo($this->filename);
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var \Truonglv\VideoUpload\Repository\Video $videoRepo */
        $videoRepo = $this->repository('Truonglv\VideoUpload:Video');
        $videoRepo->onEntityDeleted($this);
    }
}
