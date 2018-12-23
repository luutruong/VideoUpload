<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_Deferred_Uploader extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $maxVideosUpload = Truonglv_VideoUpload_Option::get('maxUploadPerProcess');
        if (empty($maxVideosUpload)) {
            return true;
        }

        /** @var Truonglv_VideoUpload_Model_Video $videoModel */
        $videoModel = XenForo_Model::create('Truonglv_VideoUpload_Model_Video');
        $videos = $videoModel->getAllVideo(array(
            'remote_upload_date' => 0,
            'thread_id_gt' => 0
        ), array(
            'order' => 'upload_date',
            'limit' => $maxVideosUpload
        ));

        if (empty($videos)) {
            return true;
        }

        /** @var Truonglv_VideoUpload_Model_VideoUploader $videoUploader */
        $videoUploader = XenForo_Model::create('Truonglv_VideoUpload_Model_VideoUploader');

        $start = microtime(true);
        foreach ($videos as $video) {
            $videoUploader->uploadToStorage($video['video_id']);
            if ($targetRunTime && (microtime(true) - $start) >= $targetRunTime) {
                break;
            }
        }

        return true;
    }
}
