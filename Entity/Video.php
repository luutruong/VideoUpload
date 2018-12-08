<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Entity;

use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null video_id
 * @property int thread_id
 * @property int attachment_id
 * @property string remote_url
 * @property int remote_upload_date
 * @property int upload_date
 *
 * RELATIONS
 * @property \XF\Entity\Thread Thread
 * @property \XF\Entity\Attachment Attachment
 */
class Video extends Entity
{
    public function getStreamUrl()
    {
        /** @var Attachment|null $attachment */
        $attachment = $this->Attachment;
        if (!$attachment) {
            return null;
        }

        if (!empty($this->remote_url) && $this->app()->options()->TVU_useExternalViewUrl) {
            $baseUrl = $this->app()->options()->TVU_baseUrl;
            $remoteUrl = $this->remote_url;

            if ($baseUrl === 'cdn.digitaloceanspaces.com') {
                $remoteUrl = str_replace('.digitaloceanspaces.com', '.cdn.digitaloceanspaces.com', $remoteUrl);
            }

            return $remoteUrl;
        }

        return $this
            ->app()
            ->router('public')
            ->buildLink('full:attachments', $attachment);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_truonglv_videoupload_video';
        $structure->primaryKey = 'video_id';
        $structure->shortName = 'Truonglv\VideoUpload:Video';

        $structure->columns = [
            'video_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'thread_id' => ['type' => self::UINT, 'default' => 0],
            'attachment_id' => ['type' => self::UINT, 'required' => true],
            'remote_url' => ['type' => self::STR, 'default' => ''],
            'remote_upload_date' => ['type' => self::UINT, 'default' => 0],
            'upload_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];

        $structure->relations = [
            'Thread' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:Thread',
                'conditions' => 'thread_id',
                'primary' => true
            ],
            'Attachment' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:Attachment',
                'conditions' => 'attachment_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
