<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\VideoUpload\Data;

use XF\Entity\User;

class ProfilePostForm
{
    private $attachmentData = [];

    public function addAttachmentData(User $user, array $attachmentData)
    {
        $this->attachmentData[$user->user_id] = $attachmentData;
    }

    public function getAttachmentData($userId)
    {
        return isset($this->attachmentData[$userId]) ? $this->attachmentData[$userId] : null;
    }
}
