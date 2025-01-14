<?php

namespace o;

class u_Json extends OStdModule {

    protected $suggestMethod = [
        'stringify' => 'encode()',
        'parse'     => 'decode()',
    ];

    function u_encode ($v, $flags=null) {

        $this->ARGS('*m', func_get_args());

        $flags = $this->flags($flags, [
            'format' => false,
        ]);

        $json = json_encode($v, JSON_UNESCAPED_UNICODE);

        if ($flags['format']) {
            $json = $this->u_format($json);
        }

        // TODO: this is duplicated in u_format()
      //  $json = str_replace("'{EMPTY_MAP}'", '{}', $json);

        return $json;
    }

    function u_decode ($v) {

        $this->ARGS('s', func_get_args());

        $dec = json_decode($v, false);
        if (is_null($dec)) {
            $this->error("Unable to decode JSON string: `" . v($v)->u_limit(20) . "`");
        }

        return $this->convertToBags($dec);
    }

    // Recursively convert to THT Lists and Maps
    function convertToBags ($obj) {

        if (is_object($obj)) {

            $map = [];
            foreach (get_object_vars($obj) as $key => $val) {
                $map[$key] = $this->convertToBags($val);
            }

            return OMap::create($map);
        }
        else if (is_array($obj)){

            foreach ($obj as $i => $val) {
                $obj[$i] = $this->convertToBags($obj[$i]);
            }

            return OList::create($obj);
        }
        else {
            return $obj;
        }
    }

    function deepSortKeys ($obj) {

        ksort($obj);

        foreach ($obj as $key => $value) {
            $uvObj = unv($obj[$key]);
            if (is_array($uvObj)) {
                $obj[$key] = $this->deepSortKeys($uvObj);
            }
        }

        return $obj;
    }

    function formatOneLineSummary($obj, $maxLen) {

        $json = $this->u_encode($obj);

        $json = OClass::tokensToBareStrings($json);

        $json = preg_replace('/,\n/', ', ', $json);
        $json = preg_replace('/{\s+/', '{ ', $json);
        $json = preg_replace('/\s+}/', ' }', $json);
        $json = preg_replace('/\s*\n+\s*/', '', $json);
        $json = preg_replace('/\s+/', ' ', $json);

        // TODO: would prefer not to need this, but windows paths are still escaped?
        $json = preg_replace('!\\\/!', '/', $json);
        $json = preg_replace('!\\\\\\\\!', '\\', $json);

        $json = str_replace('"⟪', '⟪', $json);
        $json = str_replace('⟫"', '⟫', $json);


        // truncate
        if (strlen($json) > $maxLen) {
            $json = substr($json, 0, $maxLen - 1) . '…';
            if ($json[0] == '[') {
                $json .= ' ]';
            }
            else if ($json[0] == '{') {
                $json .= ' }';
            }
            else if ($json[0] == "'") {
                $json .= "'";
            }
        }



        return $json;
    }

    // Make JSON output human-readable
    function u_format($rawJson, $flags=null) {

        $this->ARGS('*m', func_get_args());

        if (!is_string($rawJson)) {
            $rawJson = $this->u_encode($rawJson);
        }

        $flags = $this->flags($flags, [
            'strict' => false,
        ]);
        $isStrict = $flags['strict'];


        $tab = str_repeat(' ', 4);
        $out = '';
        $indentLevel = 0;
        $inString = false;

        $len = strlen($rawJson);
        for ($i = 0; $i < $len; $i++) {
            $c = $rawJson[$i];

            if ($c === "'" && !$isStrict) {
                $c = "\\'";
            }
            else if ($c === '"') {
                if (($i > 0 && $rawJson[$i-1] !== '\\') || $i == 0) {
                    $inString = !$inString;
                    if (!$isStrict) { $c = "'"; }
                }
            }

            if ($inString) {
                $out .= $c;
            }
            else if ($c === '{' || $c === '[') {
                $out .= $c . "\n" . str_repeat($tab, $indentLevel + 1);
                $indentLevel += 1;
            }
            else if ($c === '}' || $c === ']') {
                $indentLevel -= 1;
                // TODO: ugly workaround. Not sure why these can be mis-matched
                if ($indentLevel < 0) {
                    $indentLevel = 0;
                }
                $out .= "\n" . str_repeat($tab, $indentLevel) . $c;
            }
            else if ($c === ',') {
                $out .= ",\n" . str_repeat($tab, $indentLevel);
            }
            else if ($c === ':') {
                $out .= ": ";
            }
            else {
                $out .= $c;
            }
        }

        if (!$isStrict) { $out = preg_replace("/'(.*?)':/", '$1:', $out); }

        $out = preg_replace('/\{\s+\}/', '{}', $out);
        $out = preg_replace('/\[\s+\]/', '[]', $out);

        $out = preg_replace('!\\\\/!', '/', $out);
        $out = str_replace("'{EMPTY_MAP}'", '{}', $out);
        $out = str_replace("'[EMPTY_LIST]'", '[]', $out);

        return $out;
    }
}

