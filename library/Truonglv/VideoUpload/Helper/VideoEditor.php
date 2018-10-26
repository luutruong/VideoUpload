<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
class Truonglv_VideoUpload_Helper_VideoEditor
{
    private $_path;
    private $_ffmpeg;
    private $_autoConvert = false;
    private $_extension;

    private $_width = 0;
    private $_height = 0;

    private $_lastError = null;

    public function __construct($path)
    {
        $this->_path = $path;
        $this->_extension = XenForo_Helper_File::getFileExtension($path);

        $this->_ffmpeg = Truonglv_VideoUpload_Option::get('ffmpeg');
        $this->_autoConvert = !!Truonglv_VideoUpload_Option::get('autoConvertMp4');
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->_width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->_height;
    }

    /**
     * @return null|string
     */
    public function getLastError()
    {
        return $this->_lastError;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->_extension;
    }

    /**
     * @return bool|string
     */
    public function save()
    {
        if (!$this->_checkSecurity()) {
            return false;
        }

        if (empty($this->_ffmpeg)) {
            return $this->_path;
        }

        if (!$this->_doCropVideoDuration()) {
            return false;
        }

        return $this->_autoConvertMp4();
    }

    protected function _autoConvertMp4()
    {
        $path = $this->_path;

        if ($this->_extension === 'mp4' || !$this->_autoConvert) {
            return $path;
        }

        $output = str_replace('.' . $this->_extension, '.mp4', $path);

        $command = $this->_ffmpeg
            . ' -i ' . escapeshellarg($path)
            . ' -f mp4 '
            . ' -vcodec libx264 '
            . ' -preset fast '
            . ' -profile:v main '
            . ' -acodec aac '
            . ' -strict -2 '
            . escapeshellarg($output)
            . ' -hide_banner 2>&1';
        exec($command, $commandOutput);

        $newFileSize = filesize($output);
        if ($newFileSize < 1) {
            // failed to convert video.
            $this->_logError(sprintf(
                'Failed to convert video. $command=%s $output=%s',
                $command,
                var_export($commandOutput, true)
            ));

            return false;
        }

        $this->_extension = 'mp4';

        unlink($path);
        return $output;
    }

    protected function _doCropVideoDuration()
    {
        $ffmpeg = $this->_ffmpeg;
        $maxDuration = Truonglv_VideoUpload_Option::get('maxVideoDuration');

        if (empty($maxDuration)) {
            return true;
        }

        $commandVideoInfo = $ffmpeg . ' -i ' . escapeshellarg($this->_path) . ' 2>&1';
        exec($commandVideoInfo, $output);
        if (empty($output)) {
            return false;
        }

        $output = implode("\n", $output);

        preg_match('/Duration:\s+(\d+:)?(\d+:)?(\d+\.\d+)\,/', $output, $durationMatches);
        if (empty($durationMatches)) {
            $this->_logError('Regex duration failed. $content=' . $output);

            return false;
        }

        preg_match('/(\b[^0]\d+x[^0]\d+\b)/m', $output, $resolutionMatches);
        if (empty($resolutionMatches)) {
            $this->_logError('Regex resolution failed. $content=' . $output);

            return false;
        }

        list($width, $height) = explode('x', $resolutionMatches[1]);

        $this->_width = intval($width);
        $this->_height = intval($height);

        $duration = intval($durationMatches[1]) * 3600 + intval($durationMatches[2]) * 60 + intval($durationMatches[3]);
        if ($duration <= $maxDuration) {
            return true;
        }

        $outputFile = dirname($this->_path) . '/copy-' . basename($this->_path);

        $command = $ffmpeg . ' -i ' . escapeshellarg($this->_path)
            . ' -ss 00:00:00 '
            . ' -t 00:01:00 '
            . ' -c copy '
            . ' ' . escapeshellarg($outputFile)
            . ' 2>&1';
        exec($command);

        @unlink($this->_path);
        XenForo_Helper_File::safeRename($outputFile, $this->_path);

        return true;
    }

    protected function _checkSecurity()
    {
        $fp = @fopen($this->_path, 'rb');
        if ($fp) {
            $previous = '';
            while (!@feof($fp)) {
                $content = fread($fp, 256000);
                $test = $previous . $content;
                $exists = (
                    strpos($test, '<?php') !== false
                    || preg_match('/<script\s+language\s*=\s*(php|"php"|\'php\')\s*>/i', $test)
                );

                if ($exists) {
                    @fclose($fp);

                    $this->_logError('Video contains invalid content');

                    return false;
                }

                $previous = $content;
            }

            @fclose($fp);

            return true;
        }

        return false;
    }

    protected function _logError($message)
    {
        $this->_lastError = $message;
    }
}
