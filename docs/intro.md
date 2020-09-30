## YNewsletter: Einführung

YNewsletter dient zum Versand von E-Mail-Newslettern. Es werden REDAXO Artikel genommen und als E-Mail verschickt. Die verschiedenen Nutzergruppen können definiert werden und mit dem Artikel verknüpft werden um so einen individuellen Versand zu starten.

Der Versand selbst löuft normalerweise manuell über "Versand". Jede versendete E-Mail wird in Logs eingetragen um so einen abgebrochenen Versand wieder starten zu können.

Will man einen Versand über einen Cronjob immer automatisch starten, so kann man folgenden PHP Code verwenden:


```
<?php

$nllog = '';
$open_newsletters = self::query()->where('status', 0)->orderBy('id', 'desc')->find();

if (0 == count($open_newsletters)) {
    echo 'keine Newsletter zu verschicken';
} else {
    foreach ($open_newsletters as $obj) {
        $newsletter = self::get($obj->id);
        $newsletter->sendPackage(500);
        echo $newsletter->ynewsletter_user_count.'verschickt an '.$newsletter->subject.' [id='.$newsletter->id.']'."\n";
    }
}

?>
```
