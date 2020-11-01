<?php

$file = rex_file::get(rex_path::addon('ynewsletter', 'README.md'));
$body = rex_markdown::factory()->parse($file);

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('ynewsletter_readme'));
$fragment->setVar('body', $body, false);

echo $fragment->parse('core/page/section.php');
