<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload;

use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\Attachment;
use XF\Template\Templater;
use Truonglv\VideoUpload\Data\Video;

class Callback
{
    public static function renderUploadVideoButton($_, array $params, Templater $templater)
    {
        if (!isset($params['attachmentData'])) {
            throw new \InvalidArgumentException('Must be include `attachmentData` in param argument');
        }

        $attachmentData = $params['attachmentData'];
        if (empty($attachmentData['type']) || $attachmentData['type'] !== 'post') {
            return null;
        }

        $contextData = $templater->filter($attachmentData['context'], [['json']], false);

        $router = \XF::app()->router('public');
        $options = \XF::app()->options();

        $options = [
            'icon' => 'video',
            'class' => 'button--link js-tvuVideoUpload',
            'data-xf-init' => 'tvu-video-upload',
            'data-attachment-hash' => $attachmentData['hash'],
            'data-context-data' => $contextData,
            'data-upload-url' => $router->buildLink('attachments/tvu-video-upload'),
            'data-chunk-size' => self::getChunkSize(),
            'data-simultaneous-uploads' => $options->TVU_simultaneousUploads,
            'data-accept' => $options->TVU_allowedVideoExtensions
        ];

        $templater->includeJs([
            'src' => 'Truonglv/VideoUpload/video_upload.js',
            'addon' => 'Truonglv/VideoUpload',
            'min' => true
        ]);

        return $templater->button(\XF::phrase('tvu_upload_video'), $options);
    }

    public static function renderPostAttachments($_, array $params, Templater $templater)
    {
        /** @var Thread $thread */
        $thread = $params['thread'];
        /** @var Post $post */
        $post = $params['post'];

        if (!$post->attach_count) {
            return null;
        }

        /** @var Video $videoData */
        $videoData = \XF::app()->data('Truonglv\VideoUpload:Video');
        $videos = $videoData->getVideos($thread->thread_id);
        if ($videos === null || !$videos->count()) {
            return $templater->renderMacro('message_macros', 'attachments', [
                'attachments' => $post->Attachments,
                'message' => $post,
                'canView' => $thread->canViewAttachments()
            ]);
        }

        $attachments = $post->Attachments;
        $videosGrouped = $videos->groupBy('attachment_id');
        $html = '';

        foreach ($attachments as $index => $attachment) {
            if (isset($videosGrouped[$attachment->attachment_id])) {
                unset($attachments[$index]);

                /** @var \Truonglv\VideoUpload\Entity\Video $videoRef */
                $videoRef = reset($videosGrouped[$attachment->attachment_id]);
                $html .= self::renderVideoHtml($videoRef, $attachment, $templater);
            }
        }

        if (!empty($attachments)) {
            $html .= $templater->renderMacro('message_macros', 'attachments', [
                'attachments' => $attachments,
                'message' => $post,
                'canView' => $thread->canViewAttachments()
            ]);
        }

        return $html;
    }

    public static function getChunkSize()
    {
        $options = \XF::app()->options();

        return min($options->TVU_chunkSize, $options->attachmentMaxFileSize) * 1024;
    }

    private static function renderVideoHtml(
        \Truonglv\VideoUpload\Entity\Video $video,
        Attachment $attachment,
        Templater $templater
    ) {
        $video->hydrateRelation('Attachment', $attachment);

        $baseWidth = 640;
        $baseHeight = 360;

        if ($attachment->Data->width === 0) {
            $width = $baseWidth;
            $height = $baseHeight;
        } elseif ($attachment->Data->width <= $baseWidth || $attachment->Data->height <= $baseHeight) {
            $width = $attachment->Data->width;
            $height = $attachment->Data->height;
        } else {
            $ratio = min($baseWidth/$attachment->Data->width, $baseHeight/$attachment->Data->height);
            $width = $ratio * $attachment->Data->width;
            $height = $ratio * $attachment->Data->height;
        }

        return $templater->renderTemplate('public:tvu_bb_code_attach_video', [
            'attachment' => $attachment,
            'video' => $video,
            'width' => $width,
            'height' => $height
        ]);
    }
}
