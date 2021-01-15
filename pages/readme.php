<style>

    .panel-body h1,
    .panel-body h2,
    .panel-body h3,
    .panel-body h4 {
        border-bottom: 1px solid #eee;
        padding-bottom: 4px;
        margin-top: 22px;
        margin-bottom: 11px;

    }

    .panel-body h1{
        margin-top: 12px;
    }

    .panel-body table {
        display:block;
        width:100%;
        overflow: auto;
        margin-bottom: 16px;
    }

    .panel-body table tr td,
    .panel-body table tr th
    {
        padding: 6px 13px;
        border: 1px solid #ccc;
    }

    .panel-body table tr:nth-child(2n) {
        background-color: #f5f5f5;
    }

</style><?php

$file = rex_file::get(rex_path::addon('ynewsletter', 'README.md'));
$body = rex_markdown::factory()->parse($file);

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('ynewsletter_readme'));
$fragment->setVar('body', $body, false);

echo $fragment->parse('core/page/section.php');
