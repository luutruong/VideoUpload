<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Service\Video;

use XF\Util\File;
use XF\Http\Upload;
use XF\Service\AbstractService;
use Truonglv\VideoUpload\Callback;
use Truonglv\VideoUpload\Repository\Video;

class Chunk extends AbstractService
{
    private $file;

    private $attachmentHash;
    private $chunkNumber;
    private $chunkSize;
    private $fileName;

    private $lastError;

    public function __construct(\XF\App $app, Upload $file)
    {
        parent::__construct($app);

        $this->file = $file;
    }

    public function setAttachmentHash($attachmentHash)
    {
        $this->attachmentHash = $attachmentHash;

        return $this;
    }

    public function setChunkNumber($chunkNumber)
    {
        if ($chunkNumber < 1) {
            throw new \LogicException('Chunk number must be great than 0');
        }

        $this->chunkNumber = $chunkNumber;

        return $this;
    }

    public function setChunkSize($chunkSize)
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    public function setFileName($fileName)
    {
        $this->fileName = $fileName;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    public function upload()
    {
        $this->ensureAllOptionsWasSet();

        $file = $this->file;
        $options = $this->app->options();

        $maxSize = Callback::getChunkSize();
        $allowedExtensions = $options->TVU_allowedVideoExtensions;

        $this->lastError = null;

        if (empty($allowedExtensions)) {
            return false;
        }

        $allowedExtensions = explode(',', $allowedExtensions);
        $allowedExtensions = array_map('trim', $allowedExtensions);

        $file->setAllowedExtensions($allowedExtensions);
        $file->setMaxFileSize($maxSize);

        $errors = null;
        if (!$file->isValid($errors)) {
            $this->lastError = reset($errors);

            return false;
        }

        $baseFile = $file->getTempFile();

        /** @var Video $videoRepo */
        $videoRepo = $this->repository('Truonglv\VideoUpload:Video');

        $internalDataPath = 'internal-data://';
        $chunkPath = $videoRepo->getChunkPath($this->attachmentHash, $file->getExtension(), $this->chunkNumber);

        $uploadedSize = $file->getFileSize();
        if ($uploadedSize !== $this->chunkSize) {
            $videoRepo->logError(sprintf(
                'Chunk size mismatched. $uploadSize=%d $requestSize=%d',
                $uploadedSize,
                $this->chunkSize
            ));

            return false;
        }

        if (!File::copyFileToAbstractedPath($baseFile, $chunkPath)) {
            $videoRepo->logError(sprintf(
                'Cannot copy file. $source=%s $dest=%s',
                $baseFile,
                $chunkPath
            ));

            return false;
        }

        $this->db()->insert('xf_truonglv_videoupload_video_part', [
            'path' => ltrim(substr($chunkPath, strlen($internalDataPath) + 1), '/'),
            'upload_date' => \XF::$time
        ]);

        return true;
    }

    private function ensureAllOptionsWasSet()
    {
        if (empty($this->attachmentHash)) {
            throw new \LogicException('Must be set attachmentHash');
        }

        if ($this->chunkNumber < 1) {
            throw new \LogicException('Chunk number must be great than 0');
        }

        if ($this->chunkSize < 1) {
            throw new \LogicException('Must be set chunkSize');
        }
    }
}
