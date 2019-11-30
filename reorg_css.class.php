<?php


class ReorgCss
{
    public function __construct($filename)
    {
        if (strpos($filename, '*') !== false) {
            $this->files = glob($filename);
        } else {
            $this->files = explode(',', $filename);
        }
    }


    public function execute()
    {
        $res = '';
        foreach ($this->files as $filename) {
            $str = $this->getSource( $filename );

            $styles = $this->parse($str);

            $out = $this->render($styles);

            $dest = str_replace('.css', '.scss', $filename);
            file_put_contents($dest, $out);
            $res .= "$dest,";
        }

        $res = str_replace(',', '<br>&rarr; ', rtrim($res, ','));
        exit("Output written to <br>&rarr; $res");
    }


    private function render($styles, $depth = 0) {
        $out = '';
        $indent = str_repeat('    ', $depth);
        foreach ($styles as $key0 => $rec) {
            $key = trim($key0);
            if ($key === '_') {
                $val = $this->formatRules($rec, "$indent");
                $out .= $val;

            } else {
                $out .= "\n$indent{$key} {";
                $out .= $this->render($rec, $depth+1);
                $out .= "\n$indent}";
            }
        }
        return $out;
    }




    private function cleanup($styles) {
        foreach ($styles as $key => $rec) {
            if (isset($rec['_']) && !preg_match('|^/@.*?@/$|', $rec['_'])) {
            }
        }
        return $styles;
    }





    private function parse($str) {
        $str = str_replace("\n", ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);

        $styles = [];
        if (preg_match_all('/([\w\-#.:&\(\)\s]*?) \{ ([^\}]*) \} /x', $str, $m)) {

            foreach ($m[1] as $i => $key) {
                $key = trim($key);
                $style =  trim($m[2][$i]);
                if (!$key || !$style) {
                    continue;
                }
                $index = "['".str_replace(' ', "']['", $key)."']['_']";
                $expr = "\$styles$index = '$style';";
                eval($expr);
            }
        }
        return $styles;
    } // parse




    private function formatRules($str, $indent) {
        $str = preg_replace('/;\s*/', ";\n$indent", $str);
        if (preg_match('|/@ (.+?) @/ \s* (.*)|msx', $str, $m)) {
            $str = "/*{$m[1]}*/\n";
            $str .= "$indent{$m[2]}";
        }
        $str = rtrim($str);
        return $str;
    }



    private function getSource( $filename ) {
        $fname = basename($filename);
        $file = file( $filename );

        $str = '';
        foreach ($file as $i => $line) {
            $ii = ($i + 2);
            $line = str_replace('{', "{/@$fname:$ii@/", $line);
            $str .= $line;
        }

        $str = zapFileEND($str);
        $str = removeCStyleComments($str);
        $str = removeEmptyLines($str);
        return $str;
    }
} // ReorgCss