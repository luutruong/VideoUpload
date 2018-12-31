<?php

namespace Truonglv\VideoUpload\XF\Entity;

use XF\Mvc\Entity\Structure;

class ProfilePost extends XFCP_ProfilePost
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        if (!isset($structure->relations['Attachments'])) {
            $structure->relations['Attachments'] = [
                'type' => self::TO_MANY,
                'entity' => 'XF:Attachment',
                'conditions' => [
                    ['content_type', '=', $structure->contentType],
                    ['content_id', '=', '$profile_post_id']
                ],
                'with' => 'Data',
                'order' => 'attach_date'
            ];
        }

        return $structure;
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentRepo->fastDeleteContentAttachments($this->structure()->contentType, $this->profile_post_id);
    }
}
