<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_Deferred_BackBlazeImporter extends XenForo_Deferred_Abstract
{
    public function canCancel()
    {
        return true;
    }

    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'batch' => 100,
            'position' => 0
        ), $data);

        /** @var Truonglv_VideoUpload_Model_Video $videoModel */
        $videoModel = XenForo_Model::create('Truonglv_VideoUpload_Model_Video');
        $videoIds = $videoModel->getVideoIdsInRange($data['position'], $data['batch']);
        if (empty($videoIds)) {
            return true;
        }

        /** @var Truonglv_VideoUpload_Model_VideoUploader $videoUploader */
        $videoUploader = $videoModel->getModelFromCache('Truonglv_VideoUpload_Model_VideoUploader');

        $start = microtime(true);
        foreach ($videoIds as $videoId) {
            $data['position'] = $videoId;

            $videoUploader->uploadToStorage($videoId);
            if ($targetRunTime && (microtime(true) - $start) >= $targetRunTime) {
                break;
            }
        }

        // hard-code
        $status = 'Upload video ' . $data['position'];
        return $data;
    }
}
