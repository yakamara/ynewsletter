<?php

$newsletter_id = rex_request('newsletter_id', 'int', 0);
$package_size = rex_request('package_size', 'int', 50);
$ynewsletter_send = rex_request('ynewsletter_send', 'int', 0);
$send_delay = rex_request('send_delay', 'int', 10);

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
                    setTimeout("win_reload()", ' . ($send_delay * 1000) . '); // Sekunde * 1000 -> Millisekunden
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

        $name = '[id='.$newsletter->getId().'] '.rex_i18n::msg('ynewsletter_subject').': '.$newsletter->subject . ' | '.rex_i18n::msg('ynewsletter_emails', $group->countUsers()).' | '.rex_i18n::msg('ynewsletter_status').': '.$status_name.'';
        $newsletterSelect->addOption($name, $newsletter->getId());
        if ($newsletter_id == $newsletter->getId()) {
            $newsletterSelect->setSelected($newsletter->getId());
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

    $packageSelectDelay = new rex_select();
    $packageSelectDelay->setId('rex-ynewsletter-delay');
    $packageSelectDelay->setName('send_delay');
    $packageSelectDelay->setAttribute('class', 'form-control');
    $packageSelectDelay->addOption(rex_i18n::msg('ynewsletter_send_package_delay', 0), '0');
    $packageSelectDelay->addOption(rex_i18n::msg('ynewsletter_send_package_delay', '0.5'), '0.5');
    $packageSelectDelay->addOption(rex_i18n::msg('ynewsletter_send_package_delay', 1), '1');
    $packageSelectDelay->addOption(rex_i18n::msg('ynewsletter_send_package_delay', 10), '10');
    $packageSelectDelay->addOption(rex_i18n::msg('ynewsletter_send_package_delay', 60), '60');
    $packageSelectDelay->addOption(rex_i18n::msg('ynewsletter_send_package_delay', 300), '300');
    $packageSelectDelay->setSelected($send_delay);

    $n = [];
    $n['header'] = '<div id="rex-js-ynewsletter-send-delay">';
    $n['label'] = '<label for="rex-ynewsletter-package">' . rex_i18n::msg('ynewsletter_send_delay') . '</label>';
    $n['field'] = $packageSelectDelay->get();
    $n['note'] = rex_i18n::msg('ynewsletter_send_delay_notice');
    $n['footer'] = '</div>';
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content = '<fieldset><input type="hidden" name="ynewsletter_send" value="1" />';
    $content .= $fragment->parse('core/form/form.php');
    $content .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="export" value="' . rex_i18n::msg('ynewsletter_form_send') . '">' . rex_i18n::msg('ynewsletter_form_send') . '</button>';
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

?><script>
document.getElementById("rex-ynewsletter-package").addEventListener("change",function(){
    if (0 == this.value) {
        document.getElementById('rex-js-ynewsletter-send-delay').style.display = 'none';
    } else {
        document.getElementById('rex-js-ynewsletter-send-delay').style.display = 'block';
    }
});
document.getElementById("rex-ynewsletter-package").dispatchEvent(new Event('change'));
</script>
