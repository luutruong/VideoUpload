<?php

namespace Truonglv\VideoUpload\XF\Service\ProfilePost;

class Creator extends XFCP_Creator
{
    private $tvuAttachmentHash;

    public function setTVUAttachmentHash($attachmentHash)
    {
        $this->tvuAttachmentHash = $attachmentHash;
    }

    protected function _save()
    {
        $entity = parent::_save();

        $this->tvuAssociateAttachments();

        return $entity;
    }

    protected function tvuAssociateAttachments()
    {
        if ($this->tvuAttachmentHash) {
            $profilePost = $this->profilePost;

            /** @var \XF\Service\Attachment\Preparer $inserter */
            $inserter = $this->service('XF:Attachment\Preparer');
            $associated = $inserter->associateAttachmentsWithContent(
                $this->tvuAttachmentHash,
                'profile_post',
                $profilePost->profile_post_id
            );
            if ($associated) {
                $profilePost->fastUpdate('attach_count', $profilePost->attach_count + $associated);
            }
        }
    }
}
