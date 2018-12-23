<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_CronEntry_Uploader
{
    public static function runHourly()
    {
        self::_cleanVideoParts();

        XenForo_Application::defer(
            'Truonglv_VideoUpload_Deferred_Uploader',
            array(),
            'tvu_uploadVideo'
        );
    }

    protected static function _cleanVideoParts()
    {
        $db = XenForo_Application::getDb();
        $cutOff = XenForo_Application::$time - 86400;

        $internalPath = XenForo_Helper_File::getInternalDataPath();

        $paths = $db->fetchCol('
            SELECT path
            FROM xf_truonglv_videoupload_video_part
            WHERE upload_date < ?
        ', array($cutOff));

        if (empty($paths)) {
            return;
        }

        foreach ($paths as $path) {
            @unlink($internalPath . DIRECTORY_SEPARATOR . $path);
        }

        $db->delete(
            'xf_truonglv_videoupload_video_part',
            'upload_date < ' . $db->quote($cutOff)
        );
    }
}
