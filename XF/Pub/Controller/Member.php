<?php

namespace Truonglv\VideoUpload\XF\Pub\Controller;

use Truonglv\VideoUpload\Data\ProfilePostForm;
use XF\Entity\User;
use XF\Entity\UserProfile;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

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

            if ($user->user_id === \XF::visitor()->user_id) {
                $attachmentHash = md5(sprintf('%s%s%d',
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
                $formData->addAttachmentData($user, $attachmentData);
            }

            $profilePosts = $response->getParam('profilePosts');
            $attachmentRepo->addAttachmentsToContent($profilePosts, 'profile_post');
        }

        return $response;
    }

    protected function setupProfilePostCreate(UserProfile $userProfile)
    {
        /** @var \Truonglv\VideoUpload\XF\Service\ProfilePost\Creator $creator */
        $creator = parent::setupProfilePostCreate($userProfile);

        if ($userProfile->User->user_id === \XF::visitor()->user_id
            && $userProfile->User->hasPermission('general', 'tvu_uploadVideos')
        ) {
            $creator->setTVUAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        return $creator;
    }
}
