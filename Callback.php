<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload;

use XF\Template\Templater;

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

    public static function getChunkSize()
    {
        $options = \XF::app()->options();

        return min($options->TVU_chunkSize, $options->attachmentMaxFileSize) * 1024;
    }
}
