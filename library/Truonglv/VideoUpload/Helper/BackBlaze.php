<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_Helper_BackBlaze
{
    private $_path;
    private $_fileName;

    private $_accountId;
    private $_masterKey;
    private $_bucketId;
    private $_baseFileUrl;

    public function __construct($path, $fileName)
    {
        $this->_path = $path;
        $this->_fileName = $fileName;

        $this->_accountId = Truonglv_VideoUpload_Option::get('backblazeAccountId');
        $this->_masterKey = Truonglv_VideoUpload_Option::get('backblazeMasterKey');
        $this->_bucketId = Truonglv_VideoUpload_Option::get('backblazeBucketId');
        $this->_baseFileUrl = Truonglv_VideoUpload_Option::get('backblazeBaseUrl');
    }

    public function getBaseFileUrl()
    {
        return $this->_baseFileUrl;
    }

    public function upload()
    {
        $this->_assertValidConfigSetup();

        if (empty($this->_path)) {
            throw new XenForo_Exception('No valid path for uploading');
        }

        list($apiUrl, $authToken) = $this->_getApiUrlAndToken();
        if (empty($apiUrl) || empty($authToken)) {
            return false;
        }

        list($uploadUrl, $fileId) = $this->_getUploadPartUrl($apiUrl, $authToken);
        if (!$uploadUrl) {
            return false;
        }

        $contentSha1 = $this->_doUpload($uploadUrl, $authToken);
        if (!empty($contentSha1)) {
            return $this->_completeUploadFile($apiUrl, $authToken, $fileId, $contentSha1);
        } else {
            $this->_cancelUploadLargeFile($apiUrl, $authToken, $fileId);

            return false;
        }
    }

    protected function _getApiUrlAndToken()
    {
        try {
            $client = XenForo_Helper_Http::getClient('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
            $client->setAuth($this->_accountId, $this->_masterKey);

            $response = $client->request('GET');
            $body = $response->getBody();

            $json = json_decode($body, true);
            if (!empty($json['apiUrl']) && !empty($json['authorizationToken'])) {
                $apiUrl = $json['apiUrl'];
                $authToken = $json['authorizationToken'];

                return array($apiUrl, $authToken);
            } else {
                $this->_logError(
                    new XenForo_Exception(sprintf(
                        'Unexpected response body $url=%s $body=%s',
                        'https://api.backblazeb2.com/b2api/v1/b2_authorize_account',
                        $body
                    ))
                );
            }
        } catch (\Exception $e) {
            $this->_logError($e);
        }

        return false;
    }

    protected function _getUploadPartUrl($apiUrl, &$authToken)
    {
        $fileId = $this->_getUploadFileId($apiUrl, $authToken);
        if (!$fileId) {
            return false;
        }

        $ch = curl_init("{$apiUrl}/b2api/v2/b2_get_upload_part_url");

        $data = array('fileId' => $fileId);
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
            $this->_logError(
                new XenForo_Exception('No `uploadUrl` from response. $body=' . $response)
            );

            return false;
        }

        $authToken = $json['authorizationToken'];
        return array($json['uploadUrl'], $fileId);
    }

    protected function _getUploadFileId($apiUrl, $authToken)
    {
        $ch = curl_init("{$apiUrl}/b2api/v2/b2_start_large_file");
        $data = array(
            'bucketId' => $this->_bucketId,
            'fileName' => $this->_fileName,
            'contentType' => Zend_Service_Amazon_S3::getMimeType($this->_fileName),
            'fileInfo' => json_encode(array(
                'large_file_sha1' => sha1_file($this->_path)
            ))
        );

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: {$authToken}"
        ));

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        if (!empty($json['fileId'])) {
            return $json['fileId'];
        }

        $this->_logError("_getUploadFieldId \$response={$response}");
        return false;
    }

    protected function _doUpload($uploadUrl, $authToken)
    {
        clearstatcache();
        $totalSize = filesize($this->_path);

        $fp = fopen($this->_path, 'rb');
        if (!$fp) {
            throw new XenForo_Exception('Cannot open the file. $file=' . $this->_path);
        }

        $minimumPartSize = $this->_getMaxUploadSizePerPart();
        $totalBytesSent = 0;
        $partNumber = 1;
        $bytesSentForPart = $minimumPartSize;
        $sha1Parts = array();

        while ($totalBytesSent < $totalSize) {
            if (($totalSize - $totalBytesSent) < $minimumPartSize) {
                $bytesSentForPart = $totalSize - $totalBytesSent;
            }

            fseek($fp, $totalBytesSent);
            $contents = fread($fp, $bytesSentForPart);

            $contentSha1 = sha1($contents);
            array_push($sha1Parts, $contentSha1);

            $ch = curl_init($uploadUrl);

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Authorization: ' . $authToken,
                'Content-Length: ' . $bytesSentForPart,
                'X-Bz-Part-Number: ' . $partNumber,
                'X-Bz-Content-Sha1: ' . $contentSha1
            ));

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            curl_setopt($ch, CURLOPT_INFILESIZE, $bytesSentForPart);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_READFUNCTION, array($this, '_readFileContents'));

            curl_exec($ch);
            $curlInfo = curl_getinfo($ch);

            curl_close($ch);
            if ($curlInfo['http_code'] != 200) {
                break;
            }

            $partNumber++;
            $totalBytesSent = $bytesSentForPart + $totalBytesSent;
        }

        fclose($fp);
        if ($totalBytesSent == $totalSize) {
            return $sha1Parts;
        }

        return false;
    }

    protected function _cancelUploadLargeFile($apiUrl, $authToken, $fileId)
    {
        $ch = curl_init("{$apiUrl}/b2api/v2/b2_cancel_large_file");
        $data = array(
            'fileId' => $fileId
        );

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Authorization: ' . $authToken
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }

    protected function _completeUploadFile($apiUrl, $authToken, $fileId, array $contentSha1)
    {
        $ch = curl_init("{$apiUrl}/b2api/v2/b2_finish_large_file");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'partSha1Array' => $contentSha1,
            'fileId' => $fileId
        )));

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Authorization: {$authToken}"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        return $curlInfo['http_code'] == 200;
    }

    protected function _getMaxUploadSizePerPart()
    {
        // 10 MB
        return 10 * 1024 * 1024;
    }

    private function _readFileContents($ch, $filePointer, $length)
    {
        return fread($filePointer, $length);
    }

    private function _logError($message)
    {
        if ($message instanceof \Exception) {
            XenForo_Error::logException($message, false, '[tl] Video Upload: ');
        } else {
            $this->_logError(new XenForo_Exception($message));
        }
    }

    private function _assertValidConfigSetup()
    {
        if (empty($this->_accountId)
            || empty($this->_masterKey)
            || empty($this->_bucketId)
            || empty($this->_baseFileUrl)
        ) {
            throw new XenForo_Exception('Invalid options setup for Backblaze provider');
        }
    }
}
