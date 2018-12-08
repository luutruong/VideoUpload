<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Service\Video;

use XF\Util\File;
use XF\FileWrapper;
use XF\Entity\Attachment;
use XF\Service\AbstractService;
use Truonglv\VideoUpload\Callback;
use XF\Attachment\AbstractHandler;
use XF\Service\Attachment\Preparer;
use Truonglv\VideoUpload\Utils\Writer;
use Truonglv\VideoUpload\Repository\Video;

class Uploader extends AbstractService
{
    private $attachmentHash;
    private $totalSize;
    private $fileName;
    private $totalChunks;

    public function setAttachmentHash($attachmentHash)
    {
        $this->attachmentHash = $attachmentHash;

        return $this;
    }

    public function setTotalSize($totalSize)
    {
        $this->totalSize = $totalSize;

        return $this;
    }

    public function setTotalChunks($totalChunks)
    {
        if ($totalChunks < 1) {
            throw new \LogicException('Total chunks must be great than 0');
        }

        $this->totalChunks = $totalChunks;

        return $this;
    }

    public function setFileName($fileName)
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function upload(AbstractHandler $handler)
    {
        $this->ensureAllOptionsWasSet();

        $videoPath = $this->getFinalVideoPath();
        $videoTemp = $this->mergeVideoParts();

        if (!$videoTemp) {
            return false;
        }

        $oldExtension = File::getFileExtension($this->fileName);

        File::copyFileToAbstractedPath($videoTemp, $videoPath);

        /** @var \Truonglv\VideoUpload\Service\Video\Editor $editor */
        $editor = $this->service('Truonglv\VideoUpload:Video\Editor');
        if (!$editor->apply($videoPath)) {
            return false;
        }

        if (!$editor->getWidth() || !$editor->getHeight()) {
            return false;
        }

        $fileName = $this->fileName;
        if ($oldExtension !== $editor->getExtension()) {
            $fileName = str_replace('.' . $oldExtension, '.' . $editor->getExtension(), $fileName);
        }

        /** @var Preparer $attachmentPreparer */
        $attachmentPreparer = $this->service('XF:Attachment\Preparer');

        $extra = [
            'width' => $editor->getWidth(),
            'height' => $editor->getHeight()
        ];

        $tempFile = File::copyAbstractedPathToTempFile($videoPath);
        File::deleteFromAbstractedPath($videoPath);

        $file = new FileWrapper($tempFile, $fileName);

        $handler->beforeNewAttachment($file, $extra);
        $data = $attachmentPreparer->insertDataFromFile($file, \XF::visitor()->user_id, $extra);

        // for workaround with this.
        // https://xenforo.com/community/threads/xf-service-attachment-preparer-insertdatafromfile-ignore-some-extra-data-keys.157521/
        $data->width = $extra['width'];
        $data->height = $extra['height'];

        $data->save();

        $attachment = $attachmentPreparer->insertTemporaryAttachment(
            $handler,
            $data,
            $this->attachmentHash,
            $file
        );

        $this->insertVideoRecord($attachment);

        return $attachment;
    }

    protected function insertVideoRecord(Attachment $attachment)
    {
        /** @var \Truonglv\VideoUpload\Entity\Video $video */
        $video = $this->em()->create('Truonglv\VideoUpload:Video');
        $video->thread_id = 0;
        $video->attachment_id = $attachment->attachment_id;
        $video->save();
    }

    protected function getFinalVideoPath()
    {
        return sprintf(
            'internal-data://tvu_video_upload/%s.%s',
            $this->attachmentHash,
            File::getFileExtension($this->fileName)
        );
    }

    protected function getVideoRepo()
    {
        /** @var Video $videoRepo */
        $videoRepo = $this->repository('Truonglv\VideoUpload:Video');

        return $videoRepo;
    }

    private function mergeVideoParts()
    {
        $currentChunk = 1;

        $videoTemp = File::getTempFile();
        $extension = File::getFileExtension($this->fileName);

        $writer = new Writer($videoTemp);

        while ($currentChunk <= $this->totalChunks) {
            $filePart = $this->getVideoRepo()->getChunkPath($this->attachmentHash, $extension, $currentChunk);
            if (!$filePart) {
                $this->getVideoRepo()->logError(sprintf('Missing video file part: %s', $filePart));

                return false;
            }

            $tempFile = File::copyAbstractedPathToTempFile($filePart);
            File::deleteFromAbstractedPath($filePart);

            $appended = $writer->appendFrom($tempFile);
            if (!$appended) {
                $this->getVideoRepo()
                    ->logError(sprintf(
                        'Failed to read video part data. $path=%s',
                        $filePart
                    ));

                return false;
            }

            $currentChunk++;
        }

        $writer->close();

        return $videoTemp;
    }

    private function ensureAllOptionsWasSet()
    {
        if (empty($this->attachmentHash)) {
            throw new \LogicException('Must be set attachmentHash');
        }

        if (empty($this->totalSize)) {
            throw new \LogicException('Must be set totalSize');
        }

        if (empty($this->fileName)) {
            throw new \LogicException('Must be set fileName');
        }

        if (empty($this->totalChunks)) {
            throw new \LogicException('Must be set totalChunks');
        }

        $expectedChunks = ceil($this->totalSize / Callback::getChunkSize());
        if ($expectedChunks != $this->totalChunks) {
            throw new \LogicException(sprintf(
                'Invalid number chunks provided. $totalChunks=%d $expectedChunks=%d',
                $this->totalChunks,
                $expectedChunks
            ));
        }
    }
}
