<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Attachment;

use XF\Entity\User;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use XF\Attachment\AbstractHandler;
use Truonglv\VideoUpload\Entity\Video;

class ProfilePost extends AbstractHandler
{
    public function canView(Attachment $attachment, Entity $container, &$error = null)
    {
        if (!($container instanceof \XF\Entity\ProfilePost)) {
            return false;
        }

        return $container->canView($error);
    }

    public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
    {
        if (!($container instanceof \XF\Entity\ProfilePost)) {
            return;
        }

        $container->attach_count--;
        $container->save();
    }

    public function onAssociation(Attachment $attachment, Entity $container = null)
    {
        parent::onAssociation($attachment, $container);

        if ($container instanceof \XF\Entity\ProfilePost) {
            $videoFinder = \XF::app()->finder('Truonglv\VideoUpload:Video');
            $videoFinder->where('attachment_id', $attachment->attachment_id)
                ->where('content_type', 'user');

            /** @var Video $video */
            foreach ($videoFinder->fetch() as $video) {
                $video->content_id = $container->User->user_id;
                $video->save();
            }
        }
    }

    public function getContainerIdFromContext(array $context)
    {
        return isset($context['profile_post_id']) ? intval($context['profile_post_id']) : null;
    }

    public function canManageAttachments(array $context, &$error = null)
    {
        $user = null;
        $em = \XF::em();

        if (!empty($context['profile_post_id'])) {
            /** @var \XF\Entity\ProfilePost|null $profilePost */
            $profilePost = $em->find('XF:ProfilePost', $context['profile_post_id']);
            if (!$profilePost
                || !$profilePost->canEdit($error)
                || $profilePost->user_id !== $profilePost->profile_user_id
            ) {
                return false;
            }

            $user = $profilePost->User;
        } elseif (!empty($context['user_id'])) {
            $user = $em->find('XF:User', $context['user_id']);
        }

        if (!($user instanceof User)) {
            return false;
        }

        return $user->hasPermission('general', 'tvu_uploadVideos');
    }

    public function getConstraints(array $context)
    {
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = \XF::repository('XF:Attachment');
        $constraints = $attachmentRepo->getDefaultAttachmentConstraints();

        $videoExtensions = explode(',', \XF::options()->TVU_allowedVideoExtensions);
        $videoExtensions = array_map('trim', $videoExtensions);

        $constraints['extensions'] = array_merge(['png', 'jpeg', 'jpg', 'gif'], $videoExtensions);

        return $constraints;
    }

    public function getContainerLink(Entity $container, array $extraParams = [])
    {
        return $container->app()->router('public')
            ->buildLink('canonical:profile-posts', $container, $extraParams);
    }

    public function getContext(Entity $entity = null, array $extraContext = [])
    {
        if ($entity instanceof User) {
            $extraContext['user_id'] = $entity->user_id;
        } elseif ($entity instanceof \XF\Entity\ProfilePost) {
            $extraContext['profile_post_id'] = $entity->profile_post_id;
        }

        return $extraContext;
    }
}
