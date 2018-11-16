<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Service\Video;

use XF\Util\File;
use XF\Util\Random;
use XF\Service\AbstractService;
use Truonglv\VideoUpload\Repository\Video;

class Editor extends AbstractService
{
    private $path;
    private $ffmpeg;

    private $extension;
    private $newExtension;

    private $autoConvertMp4 = false;
    private $maxDuration;

    private $width = 0;
    private $height = 0;

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);

        $options = $app->options();

        $this->ffmpeg = $options->TVU_ffmpeg;
        $this->autoConvertMp4 = !!$options->TVU_autoConvertMp4;
        $this->maxDuration = $options->TVU_maxVideoDuration;
    }

    public function apply(&$path)
    {
        if (empty($this->ffmpeg)) {
            return true;
        }

        $this->path = $path;
        $this->extension = File::getFileExtension($path);

        $baseTemp = File::copyAbstractedPathToTempFile($this->path);
        if (!$baseTemp) {
            throw new \InvalidArgumentException('Cannot copy file to temp file.');
        }

        $originalPath = $this->path;
        $this->path = $baseTemp;

        if (!$this->doCropDuration()) {
            return false;
        }

        if (!$this->autoConvertMp4()) {
            return false;
        }

        if ($this->newExtension) {
            File::deleteFromAbstractedPath($originalPath);

            $originalPath = str_replace('.' . $this->extension, '.' . $this->newExtension, $originalPath);
        }

        File::copyFileToAbstractedPath($this->path, $originalPath);

        $path = $originalPath;

        return true;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->newExtension ?: $this->extension;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    protected function getVideoRepo()
    {
        /** @var Video $videoRepo */
        $videoRepo = $this->repository('Truonglv\VideoUpload:Video');

        return $videoRepo;
    }

    private function doCropDuration()
    {
        if (empty($this->maxDuration)) {
            return true;
        }

        $ffmpeg = $this->ffmpeg;

        $commandVideoInfo = $ffmpeg . ' -i ' . escapeshellarg($this->path) . ' 2>&1';
        exec($commandVideoInfo, $output);
        if (empty($output)) {
            return false;
        }

        $output = implode("\n", $output);
        $videoRepo = $this->getVideoRepo();

        preg_match('/Duration:\s+(\d+:)?(\d+:)?(\d+\.\d+)\,/', $output, $durationMatches);
        if (empty($durationMatches)) {
            $videoRepo->logError('Regex duration failed. $content=' . $output);

            return false;
        }

        preg_match('/(\b[^0]\d+x[^0]\d+\b)/m', $output, $resolutionMatches);
        if (empty($resolutionMatches)) {
            $videoRepo->logError('Regex resolution failed. $content=' . $output);

            return false;
        }

        list($width, $height) = explode('x', $resolutionMatches[1]);

        $this->width = intval($width);
        $this->height = intval($height);

        $duration = intval($durationMatches[1]) * 3600 + intval($durationMatches[2]) * 60 + intval($durationMatches[3]);
        if ($duration <= $this->maxDuration) {
            return true;
        }

        $maxDuration = $this->maxDuration;

        $hours = floor($this->maxDuration / 3600);
        $maxDuration -= $hours * 3600;

        $minutes = floor($maxDuration / 60);
        $seconds = $maxDuration - $minutes * 60;

        if ($seconds > 60) {
            $minutes += 1;
            $seconds = $seconds - 60;
        }

        if ($minutes > 60) {
            $hours += 1;
            $minutes = $minutes - 60;
        }

        $cutToDuration = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        $outputFile = \XF::app()->config('internalDataPath')
            . '/tvu_video_upload/'
            . Random::getRandomString(8) . '_' . intval(microtime(true)) . '.' . $this->extension;

        $command = $ffmpeg . ' -i ' . escapeshellarg($this->path)
            . ' -ss 00:00:00 '
            . ' -t ' . escapeshellarg($cutToDuration)
            . ' -c copy '
            . ' ' . escapeshellarg($outputFile)
            . ' 2>&1';
        exec($command);

        copy($outputFile, $this->path);

        unlink($outputFile);

        return true;
    }

    private function autoConvertMp4()
    {
        if ($this->extension === 'mp4' || !$this->autoConvertMp4) {
            return true;
        }

        $output = File::getTempFile();
        unlink($output);

        $command = $this->ffmpeg
            . ' -i ' . escapeshellarg($this->path)
            . ' -f mp4 '
            . ' -vcodec libx264 '
            . ' -preset fast '
            . ' -profile:v main '
            . ' -acodec aac '
            . ' -pix_fmt yuv420p '
            . ' -strict -2 '
            . escapeshellarg($output)
            . ' -hide_banner 2>&1';
        exec($command, $commandOutput);

        $newFileSize = filesize($output);
        if ($newFileSize < 1) {
            // failed to convert video.
            $this->getVideoRepo()->logError(sprintf(
                'Failed to convert video. $command=%s $output=%s',
                $command,
                var_export($commandOutput, true)
            ));

            return false;
        }

        $this->newExtension = 'mp4';

        copy($output, $this->path);

        return true;
    }
}
