<?php

namespace Truonglv\VideoUpload\XF\Pub\Controller;

use XF\Entity\User;
use XF\Mvc\Reply\View;
use XF\Mvc\ParameterBag;
use XF\Entity\UserProfile;
use Truonglv\VideoUpload\Data\ProfilePostForm;

class Member extends XFCP_Member
{
    public function actionView(ParameterBag $params)
    {
        $response = parent::actionView($params);
        if ($response instanceof View) {
            /** @var User $user */
            $user = $response->getParam('user');
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->repository('XF:Attachment');

            if ($this->tvuCanUploadAttachments($user)) {
                $attachmentHash = md5(sprintf(
                    '%s%s%d',
                    __METHOD__,
                    $this->app()->config('globalSalt'),
                    $user->user_id
                ));
                $attachmentData = $attachmentRepo->getEditorData(
                    'profile_post',
                    $user,
                    $attachmentHash
                );

                /** @var ProfilePostForm $formData */
                $formData = $this->data('Truonglv\VideoUpload:ProfilePostForm');
                $formData->addAttachmentData($user->user_id, $attachmentData);
            }

            $profilePosts = $response->getParam('profilePosts');
            $attachmentRepo->addAttachmentsToContent($profilePosts, 'profile_post');

            /** @var \Truonglv\VideoUpload\ControllerPlugin\Video $videoPlugin */
            $videoPlugin = $this->plugin('Truonglv\VideoUpload:Video');
            $videoPlugin->collectVideos($profilePosts);
        }

        return $response;
    }

    protected function setupProfilePostCreate(UserProfile $userProfile)
    {
        /** @var \Truonglv\VideoUpload\XF\Service\ProfilePost\Creator $creator */
        $creator = parent::setupProfilePostCreate($userProfile);

        if ($this->tvuCanUploadAttachments($userProfile->User)) {
            $creator->setTVUAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        return $creator;
    }

    protected function tvuCanUploadAttachments(User $user)
    {
        return ($user->user_id === \XF::visitor()->user_id
            && $user->hasPermission('general', 'tvu_uploadVideos')
        );
    }
}
