<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_Model_VideoUploader extends XenForo_Model
{
    public function uploadToStorage($videoId)
    {
        /** @var Truonglv_VideoUpload_Model_Video $videoModel */
        $videoModel = $this->getModelFromCache('Truonglv_VideoUpload_Model_Video');
        /** @var XenForo_Model_Attachment $attachmentModel */
        $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');

        $video = $videoModel->getVideoById($videoId, array(
            'join' => Truonglv_VideoUpload_Model_Video::FETCH_ATTACHMENT
        ));

        if (empty($video['filename'])) {
            $this->_getDb()->delete(
                'xf_truonglv_videoupload_video',
                'video_id = ' . $this->_getDb()->quote($videoId)
            );

            return;
        }

        $filePath = $attachmentModel->getAttachmentDataFilePath($video);
        $storageProvider = Truonglv_VideoUpload_Option::get('storageProvider');

        switch ($storageProvider) {
            case 'digitalocean':
                if (!empty($video['remote_url'])
                    && (
                        strpos($video['remote_url'], 'digitaloceanspaces.com') !== false
                        || strpos($video['remote_url'], 'digitalocean.com') !== false
                    )
                ) {
                    return;
                }

                $videoRemoteUrl = $this->_doUploadToDigitalOceanSpaces($video, $filePath);
                break;
            case 'backblaze':
                if (!empty($video['remote_url'])
                    && (
                        strpos($video['remote_url'], 'backblaze.com') !== false
                        || strpos($video['remote_url'], 'backblazeb2.com') !== false
                    )
                ) {
                    return;
                }

                $videoRemoteUrl = $this->_doUploadToBackBlaze($video, $filePath);
                break;
            default:
                $videoRemoteUrl = $this->_doUploadToCustomProvider($storageProvider, $video, $filePath);
        }

        if ($videoRemoteUrl) {
            $dw = XenForo_DataWriter::create('Truonglv_VideoUpload_DataWriter_Video');

            $dw->setExistingData($video['video_id']);
            $dw->set('remote_url', $videoRemoteUrl);
            $dw->set('remote_upload_date', XenForo_Application::$time);

            $dw->save();
        }
    }

    protected function _doUploadToCustomProvider($provider, array $video, $filePath)
    {
        throw new XenForo_Exception('Custom provider must be implemented!');
    }

    protected function _doUploadToBackBlaze(array $video, $filePath)
    {
        $fileName = $this->_getVideoPath($video);
        $uploader = new Truonglv_VideUpload_Helper_BackBlaze($filePath, $fileName);

        $success = $uploader->upload();
        if ($success) {
            return sprintf(
                '%s/%s',
                rtrim($uploader->getBaseFileUrl(), '/'),
                $fileName
            );
        }

        return false;
    }

    protected function _doUploadToDigitalOceanSpaces(array $video, $filePath)
    {
        $apiKey = Truonglv_VideoUpload_Option::get('spacesApiKey');
        $apiSecret = Truonglv_VideoUpload_Option::get('spacesApiSecret');
        $bucket = Truonglv_VideoUpload_Option::get('spacesBucket');
        $region = Truonglv_VideoUpload_Option::get('spacesRegion');

        if (empty($apiKey) || empty($apiSecret) || empty($bucket) || empty($region)) {
            throw new XenForo_Exception('Invalid setupfor DigitalOcean Spaces');
        }

        $s3 = new Zend_Service_Amazon_S3($apiKey, $apiSecret, $region);
        $s3->setEndpoint("https://{$region}.digitaloceanspaces.com");

        $videoPath = self::_getVideoPath($video);

        try {
            $success = $s3->putFileStream($filePath, $videoPath, array(
                Zend_Service_Amazon_S3::S3_ACL_HEADER => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ
            ));
        } catch (\Exception $e) {
            $ex = new Exception(
                '[tl] Thread Video Upload: Failed upload video. $e='
                . $e->getMessage()
                . ' $file=' . $filePath
            );

            XenForo_Error::logException($ex, false);

            $success = false;
        }

        if ($success) {
            return sprintf(
                'https://%s.%s.digitaloceanspaces.com/%s',
                $bucket,
                $region,
                $videoPath
            );
        }

        return false;
    }

    protected function _getVideoPath(array $attachment)
    {
        $templateOp = Truonglv_VideoUpload_Option::get('uploadPathTemplate');
        $prefix = '';

        if ($templateOp['type'] == 1) {
            $prefix = 'videos/{year}/{month}/{day}';
        } elseif ($templateOp['type'] == 2) {
            $prefix = $templateOp['custom'];
        }

        $now = XenForo_Application::$time;

        $prefix = strtr($prefix, array(
            '{year}' => date('Y', $now),
            '{month}' => date('m', $now),
            '{day}' => date('d', $now)
        ));

        $prefix = rtrim($prefix, '/') . '/' . $attachment['attachment_id'] . '-' . $attachment['filename'];
        return ltrim($prefix, '/');
    }
}
