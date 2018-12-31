<?php

namespace Truonglv\VideoUpload\XF\Pub\Controller;

use XF\Mvc\Reply\View;
use XF\Mvc\ParameterBag;
use Truonglv\VideoUpload\Data\ProfilePostForm;

class ProfilePost extends XFCP_ProfilePost
{
    public function actionEdit(ParameterBag $params)
    {
        $response = parent::actionEdit($params);
        if ($response instanceof View) {
            /** @var \XF\Entity\ProfilePost $profilePost */
            $profilePost = $response->getParam('profilePost');
            if ($this->tvuCanUploadAttachments($profilePost)) {
                /** @var \XF\Repository\Attachment $attachmentRepo */
                $attachmentRepo = $this->repository('XF:Attachment');
                $attachmentData = $attachmentRepo->getEditorData('profile_post', $profilePost);

                /** @var ProfilePostForm $formData */
                $formData = $this->data('Truonglv\VideoUpload:ProfilePostForm');
                $formData->addAttachmentData($profilePost->profile_post_id, $attachmentData);
            }
        }

        return $response;
    }

    protected function setupEdit(\XF\Mvc\Entity\Entity $profilePost)
    {
        /** @var \Truonglv\VideoUpload\XF\Service\ProfilePost\Editor $editor */
        $editor = parent::setupEdit($profilePost);
        if ($profilePost instanceof \XF\Entity\ProfilePost
            && $this->tvuCanUploadAttachments($profilePost)
        ) {
            $editor->setTVUAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        return $editor;
    }

    protected function tvuCanUploadAttachments(\XF\Entity\ProfilePost $profilePost)
    {
        return ($profilePost->User->user_id === $profilePost->ProfileUser->user_id
            && $profilePost->User->hasPermission('general', 'tvu_uploadVideos'));
    }
}
