<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Stats;

use XF\Stats\AbstractHandler;

class Video extends AbstractHandler
{
    public function getData($start, $end)
    {
        $db = $this->db();
        $videos = $db->fetchPairs(
            $this->getBasicDataQuery(
                'xf_truonglv_videoupload_video',
                'upload_date',
                'thread_id > ?'
            ),
            [$start, $end, 0]
        );

        return [
            'tvu_video' => $videos
        ];
    }

    public function getStatsTypes()
    {
        return [
            'tvu_video' => \XF::phrase('tvu_stats_videos_uploaded')
        ];
    }
}
