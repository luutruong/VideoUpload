<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload;

use XF\Entity\Post;
use XF\Entity\Attachment;
use XF\Template\Templater;
use Truonglv\VideoUpload\Data\Video;
use Truonglv\VideoUpload\Data\ProfilePostForm;

class Callback
{
    public static $allowContentTypes = ['post', 'profile_post'];

    public static function renderUploadVideoButton($_, array $params, Templater $templater)
    {
        static $depth = 0;
        if ($depth >= 1) {
            return null;
        }
        $depth++;

        if (!isset($params['attachmentData'])) {
            return null;
        }

        $attachmentData = $params['attachmentData'];
        if (empty($attachmentData['type'])
            || !in_array($attachmentData['type'], self::$allowContentTypes, true)
            || !\XF::visitor()->hasPermission('general', 'tvu_uploadVideos')
        ) {
            return null;
        }

        return $templater->renderTemplate('public:tvu_upload_video_button', [
            'attachmentData' => $attachmentData,
            'contentType' => $attachmentData['type'],
            'chunkSize' => self::getChunkSize()
        ]);
    }

    public static function renderProfilePostUploadButton($_, array $params, Templater $templater)
    {
        if (empty($params['key'])) {
            return null;
        }
        /** @var ProfilePostForm $formData */
        $formData = \XF::app()->data('Truonglv\VideoUpload:ProfilePostForm');
        $attachmentData = $formData->getAttachmentData($params['key']);

        if (empty($attachmentData)) {
            return null;
        }

        return self::renderUploadVideoButton(null, ['attachmentData' => $attachmentData], $templater);
    }

    public static function renderPostAttachments($_, array $params, Templater $templater)
    {
        $params = array_replace([
            'canViewAttachments' => false,
            'entity' => null
        ], $params);
        /** @var Post $entity */
        $entity = $params['entity'];

        if (!$entity->attach_count) {
            return null;
        }

        /** @var Video $videoData */
        $videoData = \XF::app()->data('Truonglv\VideoUpload:Video');
        $videos = $videoData->getVideos();
        if ($videos === null || !$videos->count()) {
            return $templater->renderMacro('public:message_macros', 'attachments', [
                'attachments' => $entity->Attachments,
                'message' => $entity,
                'canView' => $params['canViewAttachments']
            ]);
        }

        $attachments = $entity->Attachments;
        $videosGrouped = $videos->groupBy('attachment_id');
        $html = '';

        foreach ($attachments as $index => $attachment) {
            if (isset($videosGrouped[$attachment->attachment_id])
                && !$entity->isAttachmentEmbedded($attachment->attachment_id)
            ) {
                unset($attachments[$index]);

                /** @var \Truonglv\VideoUpload\Entity\Video $videoRef */
                $videoRef = reset($videosGrouped[$attachment->attachment_id]);
                $html .= self::renderVideoHtml($videoRef, $attachment, $templater);
            }
        }

        if (!empty($attachments)) {
            $html .= $templater->renderMacro('public:message_macros', 'attachments', [
                'attachments' => $attachments,
                'message' => $entity,
                'canView' => $params['canViewAttachments']
            ]);
        }

        return $html;
    }

    public static function renderBbCodeTagAttach($_, array $params, Templater $templater)
    {
        /** @var Attachment $attachment */
        $attachment = $params['attachment'];

        /** @var Video $videoData */
        $videoData = \XF::app()->data('Truonglv\VideoUpload:Video');
        $videos = $videoData->getVideos();

        if (!$videos) {
            $viewLink = \XF::app()->router('public')
                ->buildLink('full:attachments', $attachment);
            $viewPhrase = \XF::phrase('view_attachment_x', [
                'name' => $attachment->attachment_id
            ]);

            return '<a href="' . $templater->escape($viewLink) . '" target="_blank">' . $viewPhrase . '</a>';
        }

        $videos = $videos->groupBy('attachment_id');
        $video = reset($videos[$attachment->attachment_id]);

        return self::renderVideoHtml($video, $attachment, $templater);
    }

    public static function renderSwarmifyJs($_, array $params, Templater $templater)
    {
        static $rendered = null;
        if (!$rendered) {
            $rendered = true;
            $options = \XF::options();

            $templater->inlineJs("
                var swarmoptions = {
                    swarmcdnkey: \"{$options->TVU_swarmifyKey}\",
                    autoreplace: {
                        youtube: false,
                        videotag: true
                    },
                    theme: {
                        primaryColor: \"#04ae3c\"
                    }
                };
            ");
            $templater->includeJs([
                'src' => 'https://assets.swarmcdn.com/cross/swarmdetect.js'
            ]);
        }
    }

    public static function getChunkSize()
    {
        $options = \XF::app()->options();
        $serverMaxFileSize = \XF::app()->container('uploadMaxFilesize') / 1024;
        
        return min($options->TVU_chunkSize, $serverMaxFileSize) * 1024;
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
