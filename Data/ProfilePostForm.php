<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\VideoUpload\Data;

class ProfilePostForm
{
    private $attachmentData = [];

    public function addAttachmentData($key, array $attachmentData)
    {
        $this->attachmentData[$key] = $attachmentData;
    }

    public function getAttachmentData($key)
    {
        return isset($this->attachmentData[$key]) ? $this->attachmentData[$key] : null;
    }
}
