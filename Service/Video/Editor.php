<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\VideoUpload\Service;

use XF\Service\AbstractService;

class Editor extends AbstractService
{
    private $path;
    private $ffmpeg;
    private $extension;
    private $lastError;

    private $autoConvertMp4 = false;

    private $width = 0;
    private $height = 0;

    public function __construct(\XF\App $app, $path)
    {
        parent::__construct($app);

        $this->path = $path;
    }
}