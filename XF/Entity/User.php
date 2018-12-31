<?php

namespace Truonglv\VideoUpload\XF\Entity;

class User extends XFCP_User
{
    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var \Truonglv\VideoUpload\Repository\Video $videoRepo */
        $videoRepo = $this->repository('Truonglv\VideoUpload:Video');
        $videoRepo->onEntityDeleted($this);
    }
}
