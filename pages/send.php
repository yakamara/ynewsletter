<?php

/** @var rex_addon $this */

$newsletter_id = rex_request('newsletter_id', 'int', 0);
$package_size = rex_request('package_size', 'int', 50);
$ynewsletter_send = rex_request('ynewsletter_send', 'int', 0);
$send_delay = rex_request('send_delay', 'int', 10);
$func = rex_request('func', 'string', '');

$csrf = rex_csrf_token::factory('ynewsletter_send');

// Versandsperre eines abgebrochenen Konsolen-/Cronjob-Laufs aufheben
if ('unlock' === $func) {
    if (!$csrf->isValid()) {
        echo rex_view::error(rex_i18n::msg('ynewsletter_msg_csrf'));
    } else {
        $newsletter = rex_ynewsletter::get($newsletter_id);
        if (!$newsletter) {
            echo rex_view::error(rex_i18n::translate('translate:ynewsletter_msg_newsletternotavailable'));
        } else {
            $newsletter->releaseSendLock();
            echo rex_view::success(rex_i18n::msg('ynewsletter_msg_unlocked', $newsletter->getId()));
        }
    }
}

if (1 == $ynewsletter_send) {
    if (0 === $newsletter_id) {
        echo rex_view::error(rex_i18n::translate('translate:ynewsletter_msg_newsletter_not_selected'));
    }
    if ($newsletter_id > 0) {
        $newsletter = rex_ynewsletter::get($newsletter_id);
        if (!$newsletter) {
            echo rex_view::error(rex_i18n::translate('translate:ynewsletter_msg_newsletternotavailable'));
        } elseif (1 == $newsletter->getValue('status')) {
            echo rex_view::warning(rex_i18n::translate('translate:ynewsletter_msg_newslettersent'));
        } elseif ($newsletter->isScheduled()) {
            // Terminierte Newsletter gehen ausschließlich über Konsole oder Cronjob
            echo rex_view::error(rex_i18n::msg('ynewsletter_msg_scheduled_no_browser', $newsletter->getId(), (string) $newsletter->getSendAt()));
        } elseif ($newsletter->isLocked()) {
            echo rex_view::error(rex_i18n::msg('ynewsletter_msg_locked', $newsletter->getId(), (string) $newsletter->getSendingStartedAt()));
        } else {
            $ready = $newsletter->sendPackage($package_size);

            if ($ready) {
                echo rex_view::success($this->i18n('ynewsletter_msg_emailssent', $newsletter->ynewsletter_user_count, $newsletter->getValue('subject') . ' [id=' . $newsletter->getId() . ']'));
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

// Manuell versendbar sind nur Newsletter ohne Termin und ohne laufende Sperre
$manual_newsletters = [];
$scheduled_newsletters = [];
foreach ($open_newsletters as $newsletter) {
    if ($newsletter->isScheduled() || $newsletter->isLocked()) {
        $scheduled_newsletters[] = $newsletter;
    } else {
        $manual_newsletters[] = $newsletter;
    }
}

if (0 == count($manual_newsletters)) {
    echo rex_view::warning($this->i18n('ynewsletter_msg_noopennewsletteravailable'));
} else {
    $formElements = [];

    $newsletterSelect = new rex_select();
    $newsletterSelect->setId('rex-ynewsletter-newsletter');
    $newsletterSelect->setName('newsletter_id');
    $newsletterSelect->setAttribute('class', 'form-control');
    $newsletterSelect->addOption(rex_i18n::msg('ynewsletter_choice_newsletter'), 0);
    foreach ($manual_newsletters as $newsletter) {
        $group = $newsletter->getGroup();

        $name = '[id=' . $newsletter->getId() . '] ' . rex_i18n::msg('ynewsletter_subject') . ': ' . $newsletter->getValue('subject') . ' | ' . rex_i18n::msg('ynewsletter_emails', $group->countUsers()) . ' | ' . rex_i18n::msg('ynewsletter_status') . ': ' . rex_i18n::translate('translate:ynewsletter_status_open');
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
    $n['label'] = '<label for="rex-ynewsletter-delay">' . rex_i18n::msg('ynewsletter_send_delay') . '</label>';
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

// Übersicht der terminierten und gerade laufenden Versände, inkl. Aufheben hängender Sperren
if (count($scheduled_newsletters) > 0) {
    $rows = '';
    foreach ($scheduled_newsletters as $newsletter) {
        $newsletter->getUserOffset(); // füllt die Zähler

        if ($newsletter->isLocked()) {
            $state = rex_i18n::msg('ynewsletter_sending_since', (string) $newsletter->getSendingStartedAt());
            $action = '<a class="btn btn-delete btn-xs" href="' . rex_url::currentBackendPage(['func' => 'unlock', 'newsletter_id' => $newsletter->getId()] + $csrf->getUrlParams()) . '">' . rex_i18n::msg('ynewsletter_unlock') . '</a>';
        } else {
            $state = rex_i18n::msg('ynewsletter_scheduled_for', (string) $newsletter->getSendAt());
            $action = '';
        }

        $rows .= '<tr>'
            . '<td class="rex-table-id">' . $newsletter->getId() . '</td>'
            . '<td>' . rex_escape((string) $newsletter->getValue('subject')) . '</td>'
            . '<td>' . rex_escape($state) . '</td>'
            . '<td>' . rex_i18n::msg('ynewsletter_progress', (int) $newsletter->ynewsletter_sent_count, (int) $newsletter->ynewsletter_user_count) . '</td>'
            . '<td class="rex-table-action">' . $action . '</td>'
            . '</tr>';
    }

    $table = '<table class="table table-striped table-hover">'
        . '<thead><tr>'
        . '<th class="rex-table-id">ID</th>'
        . '<th>' . rex_i18n::msg('ynewsletter_subject') . '</th>'
        . '<th>' . rex_i18n::msg('ynewsletter_status') . '</th>'
        . '<th>' . rex_i18n::msg('ynewsletter_send') . '</th>'
        . '<th class="rex-table-action"></th>'
        . '</tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '</table>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('ynewsletter_scheduled'), false);
    $fragment->setVar('body', '<p>' . rex_i18n::msg('ynewsletter_scheduled_info', rex_i18n::msg('ynewsletter_cronjob_send')) . '</p>' . $table, false);
    echo $fragment->parse('core/page/section.php');
}

?><script>
(function () {
    var packageSelect = document.getElementById("rex-ynewsletter-package");
    if (!packageSelect) {
        return;
    }
    packageSelect.addEventListener("change", function () {
        document.getElementById('rex-js-ynewsletter-send-delay').style.display = (0 == this.value) ? 'none' : 'block';
    });
    packageSelect.dispatchEvent(new Event('change'));
})();
</script>
