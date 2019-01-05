<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Repository;

use XF\Util\File;
use XF\Entity\Post;
use XF\Entity\User;
use XF\FileWrapper;
use XF\Entity\Thread;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\Service\Attachment\Preparer;

class Video extends Repository
{
    public function getChunkPath($attachmentHash, $extension, $chunkNumber)
    {
        return sprintf(
            'internal-data://tvu_video_upload/%s.%s.%d',
            $attachmentHash,
            $extension,
            $chunkNumber
        );
    }

    public function onEntityDeleted(Entity $entity)
    {
        $videoFinder = $this->finder('Truonglv\VideoUpload:Video');
        if ($entity instanceof Attachment) {
            $videoFinder->where('attachment_id', $entity->attachment_id);
        } elseif ($entity instanceof Thread) {
            $videoFinder->where('content_id', $entity->thread_id);
            $videoFinder->where('content_type', 'thread');
        } elseif ($entity instanceof User) {
            $videoFinder->where('content_id', $entity->user_id);
            $videoFinder->where('content_type', 'user');
        } else {
            $videoFinder->whereImpossible();
        }

        foreach ($videoFinder->fetch() as $video) {
            $video->delete();
        }
    }

    public function postToProfile(\Truonglv\VideoUpload\Entity\Video $video, Post $post)
    {
        $user = $post->User;

        /** @var \Truonglv\VideoUpload\XF\Service\ProfilePost\Creator $creator */
        $creator = \XF::asVisitor($user, function () use ($user) {
            return $this->app()->service('XF:ProfilePost\Creator', $user->Profile);
        });

        $tempFile = File::copyAbstractedPathToTempFile($video->Attachment->Data->getAbstractedDataPath());
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $handler = $attachmentRepo->getAttachmentHandler('profile_post');

        $file = new FileWrapper($tempFile, $video->Attachment->filename);
        $attachmentHash = md5(uniqid('', true));

        /** @var Preparer $preparer */
        $preparer = $this->app()->service('XF:Attachment\Preparer');
        $attachment = $preparer->insertAttachment($handler, $file, $user, $attachmentHash);
        $attachment->Data->fastUpdate([
            'width' => $video->Attachment->Data->width,
            'height' => $video->Attachment->Data->height
        ]);

        $creator->setTVUAttachmentHash($attachmentHash);
        $creator->setContent("\0");
        if (!$creator->validate($errors)) {
            return;
        }

        $creator->save();

        /** @var \Truonglv\VideoUpload\Entity\Video $videoEntity */
        $videoEntity = $this->em->create('Truonglv\VideoUpload:Video');

        $videoEntity->content_type = 'user';
        $videoEntity->content_id = $user->user_id;
        $videoEntity->attachment_id = $attachment->attachment_id;

        $videoEntity->save();
    }

    public function logError($message)
    {
        \XF::logError("[tl] Thread Video Upload: {$message}");
    }
}
