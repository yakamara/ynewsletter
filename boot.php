<?php

if (rex::isBackend()) {
    rex_extension::register('PACKAGES_INCLUDED', function ($params) {

        $plugin = rex_plugin::get('yform', 'manager');

        if ($plugin) {
            $pages = $plugin->getProperty('pages');
            $ycom_tables = ['rex_ynewsletter', 'rex_ynewsletter_log'];

            if (isset($pages) && is_array($pages)) {
                foreach ($pages as $page) {
                    if (in_array($page->getKey(), $ycom_tables)) {
                        $page->setBlock('ynewsletter');
                    }
                }

            }

        }

    });
}

rex_yform_manager_dataset::setModelClass('rex_ynewsletter', rex_ynewsletter::class);
rex_yform_manager_dataset::setModelClass('rex_ynewsletter_log', rex_ynewsletter_log::class);

