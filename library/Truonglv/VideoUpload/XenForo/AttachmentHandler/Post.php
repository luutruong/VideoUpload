<?php

class Truonglv_VideoUpload_XenForo_AttachmentHandler_Post extends XFCP_Truonglv_VideoUpload_XenForo_AttachmentHandler_Post
{
    protected function _canViewAttachment(array $attachment, array $viewingUser)
    {
        if (Truonglv_VideoUpload_Option::get('allowGuestViewVideos')
            && Truonglv_VideoUpload_Option::isAllowedExtension($attachment['filename'])
        ) {
            return true;
        }

        return parent::_canViewAttachment($attachment, $viewingUser);
    }
}
