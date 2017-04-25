<?php

$content = rex_file::get(rex_path::addon('ynewsletter','install/tablesets/ynewsletter_tables.json'));
rex_yform_manager_table_api::importTablesets($content);