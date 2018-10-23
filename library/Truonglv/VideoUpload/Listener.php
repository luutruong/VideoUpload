<?php

class Truonglv_VideoUpload_Listener
{
    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += Truonglv_VideoUpload_FileSums::getHashes();
    }

    public static function load_class_XenForo_ControllerPublic_Misc($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Misc') {
            $extend[] = 'Truonglv_VideoUpload_XenForo_ControllerPublic_Misc';
        }
    }

    public static function load_class_XenForo_ControllerPublic_Thread($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Thread') {
            $extend[] = 'Truonglv_VideoUpload_XenForo_ControllerPublic_Thread';
        }
    }

    public static function load_class_XenForo_DataWriter_Discussion_Thread($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_Discussion_Thread') {
            $extend[] = 'Truonglv_VideoUpload_XenForo_DataWriter_Discussion_Thread';
        }
    }

    public static function load_class_XenForo_DataWriter_Attachment($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_Attachment') {
            $extend[] = 'Truonglv_VideoUpload_XenForo_DataWriter_Attachment';
        }
    }

    public static function load_class_4f477c58235ffb475271e2521731d700($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_DiscussionMessage_Post') {
            $extend[] = 'Truonglv_VideoUpload_XenForo_DataWriter_DiscussionMessage_Post';
        }
    }

    public static function template_create(&$templateName, array &$params, XenForo_Template_Abstract $template)
    {
        $template->preloadTemplate('tvu_bb_code_attach_video');
    }

    public static function load_class_XenForo_AttachmentHandler_Post($class, array &$extend)
    {
        if ($class === 'XenForo_AttachmentHandler_Post'
            && Truonglv_VideoUpload_Option::get('allowGuestViewVideos')
        ) {
            $extend[] = 'Truonglv_VideoUpload_XenForo_AttachmentHandler_Post';
        }
    }

    public static function load_class_XenForo_ViewPublic_Attachment_View($class, array &$extend)
    {
        if ($class === 'XenForo_ViewPublic_Attachment_View') {
            $extend[] = 'Truonglv_VideoUpload_XenForo_ViewPublic_Attachment_View';
        }
    }
}
