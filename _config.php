<?php

use SilverStripe\Core\Config\Config;
use SilverStripe\View\Parsers\BBCodeParser;

Config::inst()->update(BBCodeParser::class, 'allow_smilies', true);
define('FORUM_DIR', basename(__DIR__));
