# Emak - Loco files converter

## Import from Loco

Export File format: **XML** + **Java properties XML**
Save exported file like *messages_xx.xml*

Run command:
```bash
php convert_strings.php messages_xx.xml xx_CC
```

Where:
 - xx - language code (en, de, sw, ...)
 - CC - country code (GB, DE, SW, ...)

### Don't forget to compile .ts into .qm
```bash
home/imx8/InstallQt/qt513tools/bin/lrelease resources/translations/RtmpBroadcaster_xx.ts resources/translations/RtmpBroadcaster_xx.qm
```


## Export .ts to Loco

Run command:
```bash
php convert_strings.php ../doorbell-v2/resources/translations/RtmpBroadcaster_no.ts
```

Upload resulted file RtmpBroadcaster_no.xml to Loco