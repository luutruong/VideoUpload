<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_CronEntry_Uploader
{
    /**
     * @var Zend_Service_Amazon_S3
     */
    private static $_s3;

    public static function runHourly()
    {
        self::_cleanVideoParts();

        $maxVideosUpload = Truonglv_VideoUpload_Option::get('maxUploadPerProcess');
        if (empty($maxVideosUpload)) {
            return;
        }

        $apiKey = Truonglv_VideoUpload_Option::get('spacesApiKey');
        $apiSecret = Truonglv_VideoUpload_Option::get('spacesApiSecret');
        $bucket = Truonglv_VideoUpload_Option::get('spacesBucket');
        $region = Truonglv_VideoUpload_Option::get('spacesRegion');

        if (empty($apiKey) || empty($apiSecret) || empty($bucket) || empty($region)) {
            return;
        }

        $s3 = new Zend_Service_Amazon_S3($apiKey, $apiSecret, $region);
        $s3->setEndpoint("https://{$region}.digitaloceanspaces.com");

        self::$_s3 = $s3;

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
            return;
        }

        /** @var XenForo_Model_Attachment $attachmentModel */
        $attachmentModel = $videoModel->getModelFromCache('XenForo_Model_Attachment');
        $attachmentIds = XenForo_Application::arrayColumn($videos, 'attachment_id');

        $db = XenForo_Application::getDb();
        $attachments = $attachmentModel->fetchAllKeyed('
            SELECT attachment.*, attachment_data.*
            FROM xf_attachment AS attachment
				INNER JOIN xf_attachment_data AS attachment_data ON
					(attachment_data.data_id = attachment.data_id)
            WHERE attachment_id IN (' . $db->quote($attachmentIds) . ')
        ', 'attachment_id');

        foreach ($videos as $video) {
            if (empty($video['thread_id'])) {
                continue;
            }

            $dw = XenForo_DataWriter::create('Truonglv_VideoUpload_DataWriter_Video');
            $dw->setExistingData($video);

            if (empty($attachments[$video['attachment_id']])) {
                $dw->delete();

                continue;
            }

            $attachmentRef = $attachments[$video['attachment_id']];
            $filePath = $attachmentModel->getAttachmentDataFilePath($attachmentRef);

            $success = self::doUpload(
                $filePath,
                $bucket . '/' . $attachmentRef['attachment_id'] . '-' . $attachmentRef['filename']
            );

            if ($success) {
                $remoteUrl = sprintf(
                    'https://%s.%s.digitaloceanspaces.com/%s',
                    $bucket,
                    $region,
                    $attachmentRef['attachment_id'] . '-' . $attachmentRef['filename']
                );

                $dw->set('remote_url', $remoteUrl);
                $dw->set('remote_upload_date', XenForo_Application::$time);
                $dw->save();
            }
        }

        self::$_s3 = null;
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

    protected static function doUpload($filePath, $saveAs)
    {
        try {
            return self::$_s3->putFileStream($filePath, $saveAs, array(
                Zend_Service_Amazon_S3::S3_ACL_HEADER => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ
            ));
        } catch (\Exception $e) {
            $ex = new Exception(
                '[tl] Thread Video Upload: Failed upload video. $e='
                . $e->getMessage()
                . ' $file=' . $filePath
            );

            XenForo_Error::logException($ex, false);

            return false;
        }
    }
}
