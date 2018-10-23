<?php

class Truonglv_VideoUpload_XenForo_ViewPublic_Attachment_View extends XFCP_Truonglv_VideoUpload_XenForo_ViewPublic_Attachment_View
{
    public function renderRaw()
    {
        $response = parent::renderRaw();

        $attachment = $this->_params['attachment'];
        if (Truonglv_VideoUpload_Option::isAllowedExtension($attachment['filename'])) {
            $attachmentFile = $this->_params['attachmentFile'];
            $contentType = null;

            if (function_exists('mime_content_type')) {
                $contentType = mime_content_type($attachmentFile);
            }

            if (empty($contentType)) {
                $contentType = 'video/' . XenForo_Helper_File::getFileExtension($attachment['filename']);
            }

            $this->_response->setHeader('Content-Type', $contentType, true);
            $this->setDownloadFileName($attachment['filename'], true);
        }

        return $response;
    }
}
