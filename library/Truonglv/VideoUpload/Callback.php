<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_Callback
{
    public static $videos = array();

    public static function renderPostAttachments($_, array $params, XenForo_Template_Public $template)
    {
        $post = reset($params);
        if (empty($post)) {
            throw new XenForo_Exception('Invalid post data');
        }

        $attachments = isset($post['attachments']) ? $post['attachments'] : array();
        if (empty($attachments)) {
            return '';
        }

        $videoAsAttachments = array();

        foreach ($attachments as $attachmentId => $attachment) {
            if (isset(self::$videos[$attachmentId])) {
                unset($attachments[$attachmentId]);

                $videoAsAttachments[] = $attachment;
            }
        }

        $html = '';
        if (!empty($videoAsAttachments)) {
            foreach ($videoAsAttachments as $videoAsAttachment) {
                $html .= self::renderVideoHtml(null, array($videoAsAttachment), $template);
            }
        }

        if (!empty($attachments)) {
            $post['attachments'] = $attachments;

            $params = $template->getParams();
            $params['post'] = $post;

            $html .= $template->create('attached_files', $params);
        }

        return $html;
    }

    public static function renderVideoHtml($_, array $params, XenForo_Template_Public $template)
    {
        if (empty(self::$videos)) {
            return null;
        }

        $attachment = reset($params);
        if (!isset(self::$videos[$attachment['attachment_id']])) {
            return null;
        }

        $video = self::_getVideoModel()->prepareVideo(self::$videos[$attachment['attachment_id']], $attachment);

        $baseWidth = 640;
        $baseHeight = 360;

        if ($attachment['width'] === 0) {
            $width = $baseWidth;
            $height = $baseHeight;
        } elseif ($attachment['width'] <= $baseWidth || $attachment['height'] <= $baseHeight) {
            $width = $attachment['width'];
            $height = $attachment['height'];
        } else {
            $ratio = min($baseWidth/$attachment['width'], $baseHeight/$attachment['height']);

            $width = $ratio * $attachment['width'];
            $height = $ratio * $attachment['height'];
        }

        return $template->create('tvu_bb_code_attach_video', array(
            'video' => $video,
            'attachment' => $attachment,
            'width' => $width,
            'height' => $height
        ));
    }

    public static function hasAttachmentVideo($fileName)
    {
        $fileExt = XenForo_Helper_File::getFileExtension($fileName);
        return Truonglv_VideoUpload_Option::isAllowedExtension($fileExt);
    }

    /**
     * @return Truonglv_VideoUpload_Model_Video
     * @throws XenForo_Exception
     */
    protected static function _getVideoModel()
    {
        static $videoModel = null;
        if (!$videoModel) {
            $videoModel = XenForo_Model::create('Truonglv_VideoUpload_Model_Video');
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $videoModel;
    }
}
