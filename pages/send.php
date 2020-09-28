<?php

echo rex_view::title($this->i18n('ynewsletter'));

$newsletter_id = rex_request('newsletter_id', 'int', 0);
$package_size = rex_request('package_size', 'int', 50);
$ynewsletter_send = rex_request('ynewsletter_send', 'int', 0);

if (1 == $ynewsletter_send) {
    if ($newsletter_id > 0) {
        $newsletter = rex_ynewsletter::get($newsletter_id);
        if (!$newsletter) {
            echo rex_view::error(rex_i18n::translate('translate:ynewsletter_msg_newsletternotavailable'));
        } elseif (1 == $newsletter->status) {
            echo rex_view::warning(rex_i18n::translate('translate:ynewsletter_msg_newslettersent'));
        } else {
            $ready = $newsletter->sendPackage($package_size);

            if ($ready) {
                echo rex_view::success($this->i18n('ynewsletter_msg_emailssent', $newsletter->ynewsletter_user_count, ($newsletter->subject . ' [id='.$newsletter->id.']')));
            } else {
                echo rex_view::warning($this->i18n('ynewsletter_msg_send', $newsletter->ynewsletter_user_count, $newsletter->ynewsletter_sent_count));

                echo '<script>
                    function win_reload(){ window.location.reload(); }
                    setTimeout("win_reload()", 200); // Millisekunden 1000 = 1 Sek * 80
                </script>';
            }
        }
    }
}

$open_newsletters = rex_ynewsletter::query()->where('status', 0)->orderBy('id', 'desc')->find();

if (0 == count($open_newsletters)) {
    echo rex_view::warning($this->i18n('ynewsletter_msg_noopennewsletteravailable'));
} else {
    $formElements = [];

    $newsletterSelect = new rex_select();
    $newsletterSelect->setId('rex-ynewsletter-newsletter');
    $newsletterSelect->setName('newsletter_id');
    $newsletterSelect->setAttribute('class', 'form-control');
    foreach ($open_newsletters as $newsletter) {
        if (1 == $newsletter->status) {
            $status_name = rex_i18n::translate('translate:ynewsletter_status_sent');
        } else {
            $status_name = rex_i18n::translate('translate:ynewsletter_status_open');
        }

        $group = $newsletter->getRelatedDataset('group');

        $name = '[id='.$newsletter->id.'] '.rex_i18n::msg('ynewsletter_subject').': '.$newsletter->subject . ' | '.rex_i18n::msg('ynewsletter_emails', $group->countUsers()).' | '.rex_i18n::msg('ynewsletter_status').': '.$status_name.'';
        $newsletterSelect->addOption($name, $newsletter->id);
        if ($newsletter_id == $newsletter->id) {
            $newsletterSelect->setSelected($newsletter->id);
        }
    }

    $n = [];
    $n['header'] = '<div id="rex-js-ynewsletter-newsletter-div">';
    $n['label'] = '<label for="rex-ynewsletter-newsletter">' . rex_i18n::msg('ynewsletter_select_newsletter') . '</label>';
    $n['field'] = $newsletterSelect->get();
    $n['footer'] = '</div>';
    $formElements[] = $n;

    $packageSelect = new rex_select();
    $packageSelect->setId('rex-ynewsletter-package');
    $packageSelect->setName('package_size');
    $packageSelect->setAttribute('class', 'form-control');
    $packageSelect->addOption(rex_i18n::translate('translate:ynewsletter_package_all'), '0');
    $packageSelect->addOption(rex_i18n::translate('translate:ynewsletter_package_10'), '10');
    $packageSelect->addOption(rex_i18n::translate('translate:ynewsletter_package_50'), '50');
    $packageSelect->addOption(rex_i18n::translate('translate:ynewsletter_package_100'), '100');
    $packageSelect->setSelected($package_size);

    $n = [];
    $n['header'] = '<div id="rex-js-ynewsletter-package-div">';
    $n['label'] = '<label for="rex-ynewsletter-package">' . rex_i18n::msg('ynewsletter_select_package') . '</label>';
    $n['field'] = $packageSelect->get();
    $n['footer'] = '</div>';
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content = '<fieldset><input type="hidden" name="ynewsletter_send" value="1" />';
    $content .= $fragment->parse('core/form/form.php');
    $content .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="export" value="' . rex_i18n::msg('ynewsletter_form_sent') . '">' . rex_i18n::msg('ynewsletter_form_sent') . '</button>';
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', rex_i18n::msg('ynewsletter_send'), false);
    $fragment->setVar('body', $content, false);
    $fragment->setVar('buttons', $buttons, false);
    $content = $fragment->parse('core/page/section.php');

    $content = '
<form action="index.php" data-pjax="false" method="get">
<input type="hidden" name="page" value="ynewsletter/send" />
    ' . $content . '
</form>';

    echo $content;
}
