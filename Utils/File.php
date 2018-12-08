<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\VideoUpload\Utils;

class File
{
    public static function isValidVideo($path)
    {
        $allowVideoExts = \XF::options()->TVU_allowedVideoExtensions;
        $allowVideoExts = preg_split('/\s*,\s*/', $allowVideoExts, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($allowVideoExts)) {
            $allowVideoExts = [];
        }
        $allowVideoExts = array_map('trim', $allowVideoExts);

        $fileExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($fileExt, $allowVideoExts, true);
    }
}
