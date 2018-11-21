<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Utils;

class Writer
{
    private $resource;

    public function __construct($path, $mode = 'w+')
    {
        $this->resource = fopen($path, $mode);
        $this->assertValidResource();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);

            $this->resource = null;
        }
    }

    public function getContents(callable $onRead = null)
    {
        $this->assertValidResource();

        $contents = '';
        fseek($this->resource, 0);

        while (!feof($this->resource)) {
            $content = fread($this->resource, 1024 * 1024);
            if ($onRead) {
                call_user_func($onRead, $content);
            } else {
                $contents .= $content;
            }
        }

        return $contents;
    }

    public function appendFrom($path)
    {
        $this->assertValidResource();

        $writer = new static($path, 'rb');

        $appendedBytes = 0;
        $writer->getContents(function ($content) use (&$appendedBytes) {
            fwrite($this->resource, $content);
            $appendedBytes += strlen($content);
        });

        unset($writer);

        return $appendedBytes > 0;
    }

    private function assertValidResource()
    {
        if (!is_resource($this->resource)) {
            throw new \LogicException('Resource has not initialized.');
        }
    }
}
