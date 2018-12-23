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
                $videoRemoteUrl = $this->_doUploadToDigitalOceanSpaces($video, $filePath);
                break;
            case 'backblaze':
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
        $accountId = Truonglv_VideoUpload_Option::get('backblazeAccountId');
        $masterKey = Truonglv_VideoUpload_Option::get('backblazeMasterKey');
        $bucketId = Truonglv_VideoUpload_Option::get('backblazeBucketId');
        $baseFileUrl = Truonglv_VideoUpload_Option::get('backblazeBaseUrl');

        if (empty($accountId) || empty($masterKey) || empty($bucketId) || empty($baseFileUrl)) {
            throw new XenForo_Exception('Invalid options setup for Backblaze provider');
        }

        $apiUrl = null;
        $authToken = null;

        $logPrefix = '[tl] Thread Video Upload: ';

        // step 1: get api URL & auth token
        try {
            $client = XenForo_Helper_Http::getClient('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
            $client->setAuth($accountId, $masterKey);

            $response = $client->request('GET');
            $body = $response->getBody();

            $json = json_decode($body, true);
            if (!empty($json['apiUrl']) && !empty($json['authorizationToken'])) {
                $apiUrl = $json['apiUrl'];
                $authToken = $json['authorizationToken'];
            } else {
                XenForo_Error::logException(
                    new XenForo_Exception(sprintf(
                        'Unexpected response body $url=%s $body=%s',
                        'https://api.backblazeb2.com/b2api/v1/b2_authorize_account',
                        $body
                    )),
                    false,
                    $logPrefix
                );
            }
        } catch (\Exception $e) {
            XenForo_Error::logException($e, false, $logPrefix);
        }

        if (empty($apiUrl) || empty($authToken)) {
            return false;
        }

        $uploadUrl = null;
        $ch = curl_init("{$apiUrl}/b2api/v2/b2_get_upload_url");

        $data = array("bucketId" => $bucketId);
        $postFields = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        $headers = array();
        $headers[] = "Authorization: {$authToken}";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);

        if (empty($json['uploadUrl'])) {
            XenForo_Error::logException(
                new XenForo_Exception('No `uploadUrl` from response. $body=' . $body)
            );

            return false;
        }
        $uploadUrl = $json['uploadUrl'];
        $authToken = $json['authorizationToken'];

        $fp = fopen($filePath, 'rb');
        if (!$fp) {
            throw new XenForo_Exception('Cannot read file');
        }

        $totalSizes = filesize($filePath);
        $ch = curl_init($uploadUrl);
        $fileSha1 = sha1_file($filePath);

        $headers = array(
            'Authorization: ' . $authToken,
            'X-Bz-File-Name: ' . $this->_getVideoPath($video),
            'X-Bz-Content-Sha1: ' . $fileSha1,
            'Content-Type: ' . Zend_Service_Amazon_S3::getMimeType($filePath)
        );

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, fread($fp, $totalSizes));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        $json = json_decode($response, true);

        if (!empty($json['contentSha1']) && $json['contentSha1'] === $fileSha1) {
            return sprintf(
                '%s/%s',
                rtrim($baseFileUrl, '/'),
                $json['fileName']
            );
        }

        XenForo_Error::logException(
            new XenForo_Exception('Failed verify response body. $body=' . $response),
            false,
            $logPrefix
        );

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
