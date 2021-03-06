<?php # $Id$

/**
 *  @version $Revision$
 *  @author Translator Name <yourmail@example.com>
 *  EN-Revision: Revision of lang_en.inc.php
 *  Revised by Vladimir Ajgl <vlada@ajgl.cz> 2007/11/25 
 */

@define('PLUGIN_EVENT_IMAGESELECTORPLUS_NAME',                      'Extended options for media manager');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_DESC',                      'Allows extended options for inserting images from the media manager [Serendipity >= 0.9]');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_TARGET',                    'Target for this link');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_TARGET_JS',                 'Popup window (via JavaScript, adaptive size)');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_TARGET_ENTRY',              'Isolated Entry');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_TARGET_BLANK',              'Popup window (via target=_blank)');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_QUICKBLOG',                 'QuickBlog');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_QUICKBLOG_DESC',            'If you enter at least a title in the following fields, the image will be posted as a new blog entry immediately. The design can be edited via the quickblog.tpl file.');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_MAXWIDTH',                  'Maximum width of thumbnail (discards height)');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_MAXHEIGHT',                 'Maximum height of thumbnail (discards width)');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_AUTORESIZE',                'Dynamically resize images based on width and height attributes');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_AUTORESIZE_DESC',           'Automatically send resized versions of your images to the client based on the width and/or height attributes specified in the IMG tag. This can make your life easier and decrease download times but decreases server-side performance. (Note: Aspect ratios are maintained).');

@define('PLUGIN_EVENT_IMAGESELECTORPLUS_UNZIP_FILES',               'ZIP archives unzipping');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_UNZIP_FILES_BLABLAH',       'Unzip uploaded ZIP archives? - Preset value for form on the images upload page.');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_UNZIP_FILES_DESC',          'Unzip uploaded ZIP archives?');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_UNZIP_OK',                  'ZIP archive succesfully unzipped');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_UNZIP_FAILED',              'ZIP archive failed to unzip');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_UNZIP_IMAGE_FROM_ARCHIVE',  'Image from zip archive');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_UNZIP_ADD_TO_DB',           'added to database');

@define('PLUGIN_EVENT_IMAGESELECTORPLUS_JHEAD',                     'Use jhead to obtain EXIF data');
@define('PLUGIN_EVENT_IMAGESELECTORPLUS_JHEAD_DESC',                'Override the default behaviour and use external calls to jhead to obtain EXIF data.  Choose this option only if jhead is installed and can be executed.'); 
