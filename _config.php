<?php

use SilverStripe\Core\Config\Config;
use SilverStripe\View\Parsers\BBCodeParser;

Config::inst()->update(BBCodeParser::class, 'allow_smilies', true);