<?php

class Truonglv_VideoUpload_Installer
{
    /* Start auto-generated lines of code. Change made will be overwriten... */

    protected static $_tables = array(
        'video' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_truonglv_videoupload_video` (
                `video_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`thread_id` INT(10) UNSIGNED NOT NULL
                ,`attachment_id` INT(10) UNSIGNED NOT NULL
                ,`remote_url` TEXT
                ,`remote_upload_date` INT(10) UNSIGNED DEFAULT \'0\'
                ,`upload_date` INT(10) UNSIGNED DEFAULT \'0\'
                , PRIMARY KEY (`video_id`)
                ,INDEX `attachment_id` (`attachment_id`)
                ,INDEX `thread_id` (`thread_id`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_truonglv_videoupload_video`',
        ),
        'video_part' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_truonglv_videoupload_video_part` (
                `path` VARCHAR(255) NOT NULL
                ,`upload_date` INT(10) UNSIGNED NOT NULL
                
                ,INDEX `upload_date` (`upload_date`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_truonglv_videoupload_video_part`',
        ),
    );
    protected static $_patches = array();

    public static function install($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_tables as $table) {
            $db->query($table['createQuery']);
        }

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (empty($existed)) {
                $db->query($patch['addQuery']);
            } elseif (!empty($patch['modifyQuery'])) {
                $db->query($patch['modifyQuery']);
            }
        }

        self::installCustomized($existingAddOn, $addOnData);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (!empty($existed)) {
                $db->query($patch['dropQuery']);
            }
        }

        foreach (self::$_tables as $table) {
            $db->query($table['dropQuery']);
        }

        self::uninstallCustomized();
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    public static function installCustomized($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::getDb();

        $db->query("
            INSERT IGNORE INTO xf_content_type
                (content_type, addon_id, fields)
            VALUES
                ('tvu_video', 'Truonglv_VideoUpload', '')
        ");

        $db->query("
            INSERT IGNORE INTO xf_content_type_field
                (content_type, field_name, field_value)
            VALUES
                ('tvu_video', 'stats_handler_class', 'Truonglv_VideoUpload_StatsHandler_Video')
        ");

        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();
    }
    
    public static function uninstallCustomized()
    {
        $db = XenForo_Application::getDb();

        $db->delete('xf_content_type', 'addon_id = ' . $db->quote('Truonglv_VideoUpload'));

        $contentTypesQuoted = $db->quote(array(
            'tvu_video'
        ));

        $db->delete('xf_content_type_field', 'content_type IN (' . $contentTypesQuoted . ')');

        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();
    }
}
