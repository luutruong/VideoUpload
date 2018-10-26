<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_StatsHandler_Video extends XenForo_StatsHandler_Abstract
{
    public function getData($startDate, $endDate)
    {
        $db = $this->_getDb();
        $videos = $db->fetchPairs(
            $this->_getBasicDataQuery(
                'xf_truonglv_videoupload_video',
                'upload_date',
                'thread_id > ?'
            ),
            array($startDate, $endDate, 0)
        );

        return array(
            'tvu_video' => $videos
        );
    }

    public function getStatsTypes()
    {
        return array(
            'tvu_video' => new XenForo_Phrase('tvu_stats_video_uploaded')
        );
    }
}
