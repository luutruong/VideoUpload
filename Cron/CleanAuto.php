<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Cron;

use XF\Util\File;
use League\Flysystem\FileNotFoundException;

class CleanAuto
{
    public static function runHourly()
    {
        $db = \XF::db();
        $cutOff = \XF::$time - 3600;

        $records = $db->fetchAll('
            SELECT *
            FROM `xf_truonglv_videoupload_video_part`
            WHERE `upload_date` < ?
            ORDER BY `upload_date`
        ', [$cutOff]);

        foreach ($records as $record) {
            $fileAbstract = sprintf('internal-data://%s', $record['path']);

            try {
                File::deleteFromAbstractedPath($fileAbstract);
            } catch (FileNotFoundException $e) {
            }
        }
        $db->delete('xf_truonglv_videoupload_video_part', 'upload_date < ?', [$cutOff]);

        \XF::app()
            ->jobManager()
            ->enqueueUnique('tvu_uploader', 'Truonglv\VideoUpload:Uploader');
    }
}
