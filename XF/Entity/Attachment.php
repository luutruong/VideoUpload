<?php

namespace Truonglv\VideoUpload\XF\Entity;

class Attachment extends XFCP_Attachment
{
    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var \Truonglv\VideoUpload\Repository\Video $videoRepo */
        $videoRepo = $this->repository('Truonglv\VideoUpload:Video');
        $videoRepo->onEntityDeleted($this);
    }
}
