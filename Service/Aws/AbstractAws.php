<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Service\Aws;

use XF\Util\File;
use XF\Entity\Attachment;
use XF\Service\AbstractService;
use Truonglv\VideoUpload\Entity\Video;

abstract class AbstractAws extends AbstractService
{
    abstract protected function getBaseUrl();
    abstract protected function getS3Options();
    abstract protected function getBucket();

    private $s3Client;

    final public function bulkUploads()
    {
        $itemsPerProcess = $this->app->options()->TVU_maxUploadPerProcess;
        if (empty($itemsPerProcess)) {
            return;
        }

        if (!$this->isUsable()) {
            return;
        }

        $videoFinder = $this->finder('Truonglv\VideoUpload:Video');
        $videoFinder->with('Attachment');
        $videoFinder->where('thread_id', '>', 0);
        $videoFinder->where('remote_upload_date', 0);

        $videoFinder->order('upload_date');
        $videoFinder->limit($itemsPerProcess);

        $videos = $videoFinder->fetch();
        /** @var Video $video */
        foreach ($videos as $video) {
            /** @var Attachment|null $attachment */
            $attachment = $video->Attachment;
            if (!$attachment) {
                $video->delete();

                continue;
            }

            $savePath = $this->getRemoteVideoPath($attachment);
            $success = $this->doUploadFile($attachment->Data->getAbstractedDataPath(), $savePath);
            if ($success) {
                $video->remote_url = $this->getBaseUrl() . '/' . ltrim($savePath, '/');
                $video->remote_upload_date = \XF::$time;
                $video->save();
            }
        }
    }

    protected function doUploadFile($fileSource, $savePath)
    {
        $tempFile = File::copyAbstractedPathToTempFile($fileSource);
        if (!$tempFile) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot copy file (%s) to temp file',
                $fileSource
            ));
        }

        $fp = @fopen($tempFile, 'rb');
        if (!$fp) {
            throw new \InvalidArgumentException('Cannot read file for upload');
        }

        $s3 = $this->getS3Client();
        $objectUrl = null;

        try {
            $result = $s3->putObject([
                'Bucket' => $this->getBucket(),
                'Key' => $savePath,
                'Body' => $fp,
                'ACL' => 'public-read'
            ]);

            $objectUrl = $result->get('ObjectURL');
        } catch (\Aws\S3\Exception\S3Exception $e) {
            \XF::logException($e, false, '[tl] Video Upload: ');
        }

        fclose($fp);

        return !empty($objectUrl);
    }

    protected function isUsable()
    {
        return true;
    }

    protected function getRemoteVideoPath(Attachment $attachment)
    {
        $template = $this->app->options()->TVU_uploadPathTemplate;
        $prefix = '';

        if ($template['type'] == 1) {
            $prefix = 'videos/{year}/{month}/{day}';
        } elseif ($template['type'] == 2) {
            $prefix = rtrim($template['custom'], '/');
        }

        $now = \XF::$time;
        $prefix = strtr($prefix, [
            '{year}' => date('Y', $now),
            '{month}' => date('m', $now),
            '{day}' => date('d', $now)
        ]);

        return sprintf(
            '%s/%d-%s.%s',
            $prefix,
            $attachment->attachment_id,
            md5(uniqid('', true)),
            $attachment->getExtension()
        );
    }

    /**
     * @return \Aws\S3\S3Client|null
     */
    private function getS3Client()
    {
        if ($this->s3Client === null) {
            require_once \XF::getAddOnDirectory() . '/Truonglv/VideoUpload/vendor/autoload.php';

            $s3Options = array_replace([
                'version' => 'latest'
            ], $this->getS3Options());
            $this->s3Client = new \Aws\S3\S3Client($s3Options);
        }

        return $this->s3Client;
    }
}
