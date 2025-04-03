<?php
    if($argc < 1 || $argv[1] == "-h" || $argv[1] == "--help" || $argv[1] == "-?") {
        printf("Usage:\n");
        printf("    - export .ts file for translation: %s RtmpBroadcaster_no.ts\n", $argv[0]);
        printf("    - import translation into .ts file: %s translation.xml nn_CC\n", $argv[0]);
        printf("      where nn_CC is locale code form two parts:\n");
        printf("           -- nn is a language code\n");
        printf("           -- CC is a country code\n");
        die();
    }
    $src = $argv[1];
    if(!is_file($src)) {
        die("$src is not a file\n");
    }

    $xml = simplexml_load_file($src);
    if(!$xml) {
        die("Error reading source file $src\n");
    }

    $totMsg = 0;
    $rootName = $xml->getName();
    if(strtolower($rootName) == "ts") { // Export
        $full = [];
        $cnt = $xml->count();
        for($ix = 0; $ix < $cnt; $ix++) {
            $context = $xml->context[$ix];
            $context_name = $context->name->__toString();
            printf("== CONTEXT ==[ $context_name ]==");
            $msg_cnt = $context->message->count();
            $totMsg += $msg_cnt;
            for($im = 0; $im < $msg_cnt; $im++) {
                $msg = $context->message[$im];
                $msg_src = $msg->source->__toString();
                $msg_tr = $msg->translation->__toString();
                $msg_attr = $msg->translation->attributes()['type'];
                $msg_type = $msg_attr ? $msg_attr->__toString() : "";
                $key = str_replace("\n", '$n$', str_replace(" ", "_", $msg_src));
                if(isset($full[$key])) {
                    if(!isset($full[$key]['context'][$context_name])) {
                        $full[$key]['context'][] = $context_name;
                    }
                } else {
                    $full[$key] = [
                        'tr' => $msg_tr,
                        'src' => $msg_src,
                        'context' => [
                            $context_name
                        ]
                    ];
                }
            }
            printf(" has %d items\n", $msg_cnt);
        }

        ksort($full, SORT_NATURAL | SORT_FLAG_CASE);

        printf("\nExport %d translations:\n\n", count($full));

        // export
        $dst = str_replace(".ts", ".xml", $src);
        $out = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><resources></resources>");

        foreach($full as $key => $obj) {
            sort($obj['context']);
            $cntx = implode(",", $obj['context']);
            $tr = $obj['tr'] ? $obj['tr'] : "==NO TRANS==";
            $name = "{$cntx}##{$key}";
            echo ".";

            $str = $out->addChild("string", $obj['src']);
            $str->addAttribute("name", $name);
        }

        echo "\n\n";

        $pretty = str_replace("</string>", "</string>\n    ", $out->asXML());
        $pretty = str_replace("<resources>", "<resources>\n    ", $pretty);
        $pretty = str_replace("    </resources>", "</resources>", $pretty);
        file_put_contents($dst, $pretty);

        printf("Exported to %s\nFrom %d items in %d sections => %d translations\n", $dst, $totMsg, $cnt, count($full));
    } else if(strtolower($rootName) == "properties") { // import Java XML
        $code = $argc >= 3 ? $argv[2] : '';
        if(!$code) die("LANG_CODE is required to import $src\n");
        if(!preg_match("/^([a-z][a-z])_[A-Z][A-Z]$/", $code, $matches)) die("LANG_CODE [$code] should be in format nn_CC where nn - language code, CC letters long\n");
        $lang = $matches[1];
        $dst = "RtmpBroadcaster_{$lang}.ts";
        $tmp = "RtmpBroadcaster_en.ts";
        if(!is_file($tmp)) {
            die("No template file $tmp found!\n");
        }
        $out = simplexml_load_file($tmp);
        $attr = $out->attributes()["language"];
        if($attr) {
            $out->attributes()["language"] = $code;
        } else {
            $out->addAttribute("language", $code);
        }

        $sections = [];

        $cnt = $xml->count();
        for($ix = 0; $ix < $cnt; $ix++) {
            $str = $xml->entry[$ix];
            if($str) {
                $str_val = $str->__toString();
                $str_attr = $str->attributes()['key'];
                $str_name = $str_attr ? $str_attr->__toString() : "";
                if($str_name) {
                    $arr = explode("##", $str_name);
                    if(count($arr) == 2) {
                        $section_list = $arr[0];
                        $source_name = $arr[1];
                        $section_names = explode(",", $section_list);
                        foreach($section_names as $section_name) {
                            if(!isset($sections[$section_name])) {
                                $sections[$section_name] = [];
                                printf("Add section %s\n", $section_name);
                            }
                            $sections[$section_name][$source_name] = $str_val;
                        }
                    } else {
                        printf("Invalid string key [$str_name]!\n");
                    }
                } else {
                    printf("Invalid string [$str_val] without key!\n");
                }
            }
        }

        $cnt = $out->count();
        for($ix = 0; $ix < $cnt; $ix++) {
            $context = $out->context[$ix];
            $context_name = str_replace(" ", "_", $context->name->__toString());
            if(isset($sections[$context_name])) {
                printf("<< CONTEXT ==[ $context_name ]==\n\n");
                $section = $sections[$context_name];
                $msg_cnt = $context->message->count();
                for($im = 0; $im < $msg_cnt; $im++) {
                    $msg = $context->message[$im];
                    $msg_src = str_replace("\n", '$n$', str_replace(" ", "_", $msg->source->__toString()));
                    if(isset($section[$msg_src])) {
                        unset($out->context[$ix]->message[$im]->translation);
                        $out->context[$ix]->message[$im]->addChild("translation", $section[$msg_src]);
                        // echo("{$msg_src} => {$out->context[$ix]->message[$im]->translation}\n");
                    } else {
                        echo "!!! NOT FOUND ==> $msg_src\n";
                    }
                }
            } else {
                printf("** CONTEXT ==[ $context_name ]== ABSENT\n\n");
            }
        }

        $pretty = $out->asXML();
        $pretty = str_replace("        \n    <translation", "        <translation", $pretty);
        $pretty = str_replace("></message>", ">\n    </message>", $pretty);
        file_put_contents($dst, $pretty);

    } else if(strtolower($rootName) == "resources") { // import (android XML)
        $code = $argc >= 3 ? $argv[2] : '';
        if(!$code) die("LANG_CODE is required to import $src\n");
        if(!preg_match("/^([a-z][a-z])_[A-Z][A-Z]$/", $code, $matches)) die("LANG_CODE [$code] should be in format nn_CC where nn - language code, CC letters long\n");
        $lang = $matches[1];
        $dst = "RtmpBroadcaster_{$lang}.ts";
        $tmp = "RtmpBroadcaster_no.ts";
        if(!is_file($tmp)) {
            die("No template file $tmp found!\n");
        }
        $out = simplexml_load_file($tmp);
        $attr = $out->attributes()["language"];
        if($attr) {
            $out->attributes()["language"] = $code;
        } else {
            $out->addAttribute("language", $code);
        }

        $sections = [];

        $cnt = $xml->count();
        for($ix = 0; $ix < $cnt; $ix++) {
            $str = $xml->string[$ix];
            $str_val = $str->__toString();
            $str_attr = $str->attributes()['name'];
            $str_name = $str_attr ? $str_attr->__toString() : "";
            if($str_name) {
                $arr = explode("##", $str_name);
                if(count($arr) == 2) {
                    $section_name = $arr[0];
                    $source_name = $arr[1];
                    if(!isset($sections[$section_name])) {
                        $sections[$section_name] = [];
                    }
                    $sections[$section_name][$source_name] = $str_val;
                } else {
                    printf("Invalid string name [$str_name]!\n");
                }
            } else {
                printf("Invalid string [$str_val] without name!\n");
            }
        }

        $cnt = $out->count();
        for($ix = 0; $ix < $cnt; $ix++) {
            $context = $out->context[$ix];
            $context_name = str_replace(" ", "_", $context->name->__toString());
            if(isset($sections[$context_name])) {
                printf("<< CONTEXT ==[ $context_name ]==\n\n");
                $section = $sections[$context_name];
                $msg_cnt = $context->message->count();
                for($im = 0; $im < $msg_cnt; $im++) {
                    $msg = $context->message[$im];
                    $msg_src = str_replace("\n", '$n$', str_replace(" ", "_", $msg->source->__toString()));
                    if(isset($section[$msg_src])) {
                        unset($out->context[$ix]->message[$im]->translation);
                        $out->context[$ix]->message[$im]->addChild("translation", $section[$msg_src]);
                        printf("{$msg_src} => {$out->context[$ix]->message[$im]->translation}\n");
                    }
                }
            }
        }

        $pretty = $out->asXML();
        $pretty = str_replace("        \n    <translation", "        <translation", $pretty);
        $pretty = str_replace("></message>", ">\n    </message>", $pretty);
        file_put_contents($dst, $pretty);

    } else {
        die("Unknown source file $src format <$rootName>\n");
    }

    printf("Finished\n");

