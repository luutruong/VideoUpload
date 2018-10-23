<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_Option
{
    public static function get($optionKey)
    {
        $options = XenForo_Application::getOptions();

        return $options->get('TVU_' . $optionKey);
    }

    public static function isAllowedExtension($extension)
    {
        $allowed = self::get('allowedVideoExtensions');
        $allowed = explode(',', $allowed);
        $allowed = array_map('trim', $allowed);

        if (strpos($extension, '.') !== false) {
            $extension = XenForo_Helper_File::getFileExtension($extension);
        }

        return in_array($extension, $allowed, true);
    }

    public static function verifyFFMPEG(&$path, XenForo_DataWriter $dw)
    {
        if (!empty($path)) {
            if (!is_executable($path)) {
                $dw->error('Please enter valid `ffmpeg` path. The give path not exists or not executable.');

                return false;
            }
        }

        return true;
    }
}
