<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.NewsStubs
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * Automatically generates branded news stubs.
 */
class plgSystemNewsStubs extends JPlugin
{
    protected $autoloadLanguage = true;

    /**
	 * The save event.
	 *
	 * @param   string   $context  The context
	 * @param   JTable   $item     The article data
	 * @param   boolean  $isNew    Is new item
	 * @param   array    $data     The validated data
	 *
	 */
	public function onContentAfterSave($context, $item, $isNew, $data = array())
    {
        $app = JFactory::getApplication();
        $input = $app->input;
        if (!$app->isAdmin()) {
            return; // Only run in admin
        }
        
        if (!($isNew && $context == 'com_content.article')) {
            #return; // Only run for new articles
        }

        
        #echo '<pre>'; var_dump($context); echo '</pre>';
        #echo '<pre>'; var_dump($item); echo '</pre>';
        #echo '<pre>'; var_dump($isNew); echo '</pre>';
        #echo '<pre>'; var_dump($data); echo '</pre>'; exit;
        echo '<pre>'; var_dump($input); echo '</pre>'; exit;
    }
}