<?php

class Truonglv_VideoUpload_Model_Video extends XenForo_Model
{
    /**
     * @param XenForo_Upload $file
     * @param array $input
     * @return bool|int|XenForo_Phrase
     * @throws XenForo_Exception
     */
    public function uploadChunkFile(XenForo_Upload $file, array $input)
    {
        if (!$file->isValid()) {
            $errors = $file->getErrors();

            return reset($errors);
        }

        $fileExtension = XenForo_Helper_File::getFileExtension($input['resumableFilename']);
        if (!Truonglv_VideoUpload_Option::isAllowedExtension($fileExtension)) {
            return new XenForo_Phrase('uploaded_file_does_not_have_an_allowed_extension');
        }

        $hash = $input['hash'];
        $chunkNumber = $input['resumableChunkNumber'];
        $totalChunks = $input['resumableTotalChunks'];
        $totalSize = $input['resumableTotalSize'];

        $filePart = sprintf(
            '%s/tvu_video_upload/%s.%s',
            XenForo_Helper_File::getInternalDataPath(),
            $hash,
            $fileExtension
        );

        $tempDir = dirname($filePart);
        if (!is_dir($tempDir)) {
            XenForo_Helper_File::createDirectory($tempDir);
        }

        $session = XenForo_Application::getSession();

        if ($chunkNumber > 1) {
            if (!file_exists($filePart)) {
                return false;
            }

            $uploadedChunk = (int) $session->get($hash);

            if ($chunkNumber > 1
                && $chunkNumber < $totalChunks
                && $chunkNumber !== ($uploadedChunk + 1)
            ) {
                return false;
            }
        }

        $fp = fopen($filePart, ($chunkNumber === 1) ? 'w' : 'a');
        if (!flock($fp, LOCK_EX)) {
            $this->_logError('Cannot lock file: ' . $filePart);

            return false;
        }
        
        $xfTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
        XenForo_Helper_File::safeRename($file->getTempFile(), $xfTempFile);

        $contents = file_get_contents($xfTempFile);

        fwrite($fp, $contents);
        flock($fp, LOCK_UN);
        fclose($fp);

        @unlink($xfTempFile);

        if ($chunkNumber === $totalChunks) {
            $session->remove($hash);
            $session->save();

            clearstatcache();

            $ourFileSize = filesize($filePart);
            if ($ourFileSize !== $totalSize) {
                @unlink($filePart);

                return false;
            }

            $extra = array();
            if (!$this->_doCropVideoDuration($filePart, $extra)) {
                @unlink($filePart);

                return false;
            }

            if (!$this->_checkSecurity($filePart)) {
                return false;
            }

            return $this->_uploadVideo(new XenForo_Upload($file->getFileName(), $filePart), $hash, $extra);
        }

        $session->set($hash, $chunkNumber);
        $session->save();

        return true;
    }

    public static function onDataWriterDelete(XenForo_DataWriter $dw)
    {
        $deleteWhere = '';
        $db = XenForo_Application::getDb();

        if ($dw instanceof XenForo_DataWriter_Discussion_Thread) {
            $deleteWhere = 'thread_id = ' . $db->quote($dw->get('thread_id'));
        } elseif ($dw instanceof XenForo_DataWriter_Attachment) {
            $deleteWhere = 'attachment_id = ' . $db->quote($dw->get('attachment_id'));
        }

        if (!empty($deleteWhere)) {
            $db->delete('xf_truonglv_videoupload_video', $deleteWhere);
        }
    }

    public function prepareVideo(array $video, array $attachment)
    {
        if (!empty($video['remote_url']) && Truonglv_VideoUpload_Option::get('useExternalViewUrl')) {
            $baseUrl = Truonglv_VideoUpload_Option::get('baseUrl');
            $remoteUrl = $video['remote_url'];

            if ($baseUrl === 'cdn.digitaloceanspaces.com') {
                $remoteUrl = str_replace('.digitaloceanspaces.com', '.cdn.digitaloceanspaces.com', $remoteUrl);
            }

            $video['streamUrl'] = $remoteUrl;
        } else {
            $video['streamUrl'] = $attachment['viewUrl'];
        }

        return $video;
    }

    protected function _checkSecurity($path)
    {
        $fp = @fopen($path, 'rb');
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

                    return false;
                }

                $previous = $content;
            }

            @fclose($fp);

            return true;
        }

        return false;
    }

    protected function _uploadVideo(XenForo_Upload $upload, $hash, array $extra)
    {
        /** @var XenForo_Model_Attachment $attachmentModel */
        $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');

        $userId = XenForo_Visitor::getUserId();

        $dataId = $attachmentModel->insertUploadedAttachmentData($upload, $userId, $extra);
        $attachmentId = $attachmentModel->insertTemporaryAttachment($dataId, $hash);

        $dw = XenForo_DataWriter::create('Truonglv_VideoUpload_DataWriter_Video');
        $dw->bulkSet(array(
            'attachment_id' => $attachmentId,
            'thread_id' => 0,
            'remote_url' => '',
            'upload_date' => XenForo_Application::$time
        ));

        if ($dw->hasErrors()) {
            $errors = $dw->getErrors();

            return reset($errors);
        }

        $dw->save();

        return $attachmentId;
    }

    protected function _doCropVideoDuration($filePath, array &$extra)
    {
        $ffmpeg = Truonglv_VideoUpload_Option::get('ffmpeg');
        $maxDuration = Truonglv_VideoUpload_Option::get('maxVideoDuration');

        if (empty($ffmpeg) || empty($maxDuration)) {
            return true;
        }

        $commandVideoInfo = $ffmpeg . ' -i ' . escapeshellarg($filePath) . ' 2>&1';
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

        preg_match('/Stream\s+\#0:0.+\,\s+(\d+x\d+)/', $output, $resolutionMatches);
        if (empty($resolutionMatches)) {
            $this->_logError('Regex resolution failed. $content=' . $output);

            return false;
        }

        list($width, $height) = explode('x', $resolutionMatches[1]);

        $extra['width'] = intval($width);
        $extra['height'] = intval($height);

        $duration = intval($durationMatches[1]) * 3600 + intval($durationMatches[2]) * 60 + intval($durationMatches[3]);
        if ($duration <= $maxDuration) {
            return true;
        }

        $outputFile = dirname($filePath) . '/copy-' . basename($filePath);

        $command = $ffmpeg . ' -i ' . escapeshellarg($filePath)
            . ' -ss 00:00:00 '
            . ' -t 00:01:00'
            . ' -c copy '
            . ' ' . escapeshellarg($outputFile)
            . ' 2>&1';
        exec($command);

        @unlink($filePath);
        XenForo_Helper_File::safeRename($outputFile, $filePath);

        return true;
    }

    protected function _logError($message)
    {
        $e = new Exception('[tl] Thread Video Upload: ' . $message);
        XenForo_Error::logException($e, false);
    }

    /* Start auto-generated lines of code. Change made will be overwriten... */

    public function getList(array $conditions = array(), array $fetchOptions = array())
    {
        $allVideo = $this->getAllVideo($conditions, $fetchOptions);
        $list = array();

        foreach ($allVideo as $id => $video) {
            $list[$id] = $video['remote_url'];
        }

        return $list;
    }

    public function getVideoById($id, array $fetchOptions = array())
    {
        $allVideo = $this->getAllVideo(array('video_id' => $id), $fetchOptions);

        return reset($allVideo);
    }

    public function getVideoIdsInRange($start, $limit)
    {
        $db = $this->_getDb();

        return $db->fetchCol($db->limit('
            SELECT video_id
            FROM xf_truonglv_videoupload_video
            WHERE video_id > ?
            ORDER BY video_id
        ', $limit), $start);
    }

    public function getAllVideo(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareVideoConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareVideoOrderOptions($fetchOptions);
        $joinOptions = $this->prepareVideoFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $allVideo = $this->fetchAllKeyed($this->limitQueryResults("
            SELECT video.*
                $joinOptions[selectFields]
            FROM `xf_truonglv_videoupload_video` AS video
                $joinOptions[joinTables]
            WHERE $whereConditions
                $orderClause
            ", $limitOptions['limit'], $limitOptions['offset']), 'video_id');

        $this->_getAllVideoCustomized($allVideo, $fetchOptions);

        return $allVideo;
    }

    public function countAllVideo(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareVideoConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareVideoOrderOptions($fetchOptions);
        $joinOptions = $this->prepareVideoFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne("
            SELECT COUNT(*)
            FROM `xf_truonglv_videoupload_video` AS video
                $joinOptions[joinTables]
            WHERE $whereConditions
        ");
    }

    public function prepareVideoConditions(array $conditions = array(), array $fetchOptions = array())
    {
        $sqlConditions = array();
        $db = $this->_getDb();

        if (isset($conditions['video_id'])) {
            if (is_array($conditions['video_id'])) {
                if (!empty($conditions['video_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "video.video_id IN (" . $db->quote($conditions['video_id']) . ")";
                }
            } else {
                $sqlConditions[] = "video.video_id = " . $db->quote($conditions['video_id']);
            }
        }

        if (isset($conditions['thread_id'])) {
            if (is_array($conditions['thread_id'])) {
                if (!empty($conditions['thread_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "video.thread_id IN (" . $db->quote($conditions['thread_id']) . ")";
                }
            } else {
                $sqlConditions[] = "video.thread_id = " . $db->quote($conditions['thread_id']);
            }
        }

        if (isset($conditions['attachment_id'])) {
            if (is_array($conditions['attachment_id'])) {
                if (!empty($conditions['attachment_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "video.attachment_id IN (" . $db->quote($conditions['attachment_id']) . ")";
                }
            } else {
                $sqlConditions[] = "video.attachment_id = " . $db->quote($conditions['attachment_id']);
            }
        }

        if (isset($conditions['remote_upload_date'])) {
            if (is_array($conditions['remote_upload_date'])) {
                if (!empty($conditions['remote_upload_date'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "video.remote_upload_date IN (" . $db->quote($conditions['remote_upload_date']) . ")";
                }
            } else {
                $sqlConditions[] = "video.remote_upload_date = " . $db->quote($conditions['remote_upload_date']);
            }
        }

        if (isset($conditions['upload_date'])) {
            if (is_array($conditions['upload_date'])) {
                if (!empty($conditions['upload_date'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "video.upload_date IN (" . $db->quote($conditions['upload_date']) . ")";
                }
            } else {
                $sqlConditions[] = "video.upload_date = " . $db->quote($conditions['upload_date']);
            }
        }

        $this->_prepareVideoConditionsCustomized($sqlConditions, $conditions, $fetchOptions);

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareVideoFetchOptions(array $fetchOptions = array())
    {
        $selectFields = '';
        $joinTables = '';

        $this->_prepareVideoFetchOptionsCustomized($selectFields, $joinTables, $fetchOptions);

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareVideoOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
    {
        $choices = array();

        $this->_prepareVideoOrderOptionsCustomized($choices, $fetchOptions);

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    protected function _getAllVideoCustomized(array &$data, array $fetchOptions)
    {
        // customized code goes here
    }

    protected function _prepareVideoConditionsCustomized(array &$sqlConditions, array $conditions, array $fetchOptions)
    {
        $db = $this->_getDb();

        if (isset($conditions['thread_id_gt'])) {
            $sqlConditions[] = 'video.thread_id > ' . $db->quote($conditions['thread_id_gt']);
        }
    }

    protected function _prepareVideoFetchOptionsCustomized(&$selectFields, &$joinTables, array $fetchOptions)
    {
    }

    protected function _prepareVideoOrderOptionsCustomized(array &$choices, array &$fetchOptions)
    {
        // customized code goes here
    }
}
