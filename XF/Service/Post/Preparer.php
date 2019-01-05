<?php

namespace Truonglv\VideoUpload\XF\Service\Post;

use XF\Util\File;
use XF\FileWrapper;
use XF\Entity\Attachment;

class Preparer extends XFCP_Preparer
{
    protected function associateAttachments($hash)
    {
        parent::associateAttachments($hash);

        if ($hash) {
            $attachments = $this->post->Attachments;
            $validAttachments = [];

            foreach ($attachments as $attachment) {
                if ($attachment->Data->thumbnail_width > 0 && $attachment->Data->thumbnail_height > 0) {
                    $validAttachments[] = $attachment;
                }
            }

            if (count($validAttachments) === 0) {
                return;
            }

            $post = $this->post;
            $user = $post->User;

            if (!$user->hasNodePermission($post->Thread->node_id, 'tvu_autoPostProfile')) {
                return;
            }

            /** @var \Truonglv\VideoUpload\XF\Service\ProfilePost\Creator $creator */
            $creator = \XF::asVisitor($user, function () use ($user) {
                return $this->app->service('XF:ProfilePost\Creator', $user->Profile);
            });

            $attachmentHash = md5(uniqid('', true));

            /** @var Attachment $attachment */
            foreach ($validAttachments as $attachment) {
                $tempFile = File::copyAbstractedPathToTempFile($attachment->Data->getAbstractedDataPath());
                /** @var \XF\Repository\Attachment $attachmentRepo */
                $attachmentRepo = $this->repository('XF:Attachment');
                $handler = $attachmentRepo->getAttachmentHandler('profile_post');

                $file = new FileWrapper($tempFile, $attachment->filename);

                /** @var \XF\Service\Attachment\Preparer $preparer */
                $preparer = $this->app->service('XF:Attachment\Preparer');
                $preparer->insertAttachment($handler, $file, $user, $attachmentHash);
            }

            $creator->setTVUAttachmentHash($attachmentHash);
            $creator->setContent($post->Thread->title);
            if (!$creator->validate($errors)) {
                return;
            }

            $creator->save();
        }
    }
}
