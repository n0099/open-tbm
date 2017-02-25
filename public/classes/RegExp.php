<?php

class RegExp
{
    private $exp;

    public function ctrl($char) {
        $this->exp .= "\\c$char";
        return $this;
    }

    public function page() {
        $this->exp .= "\\f";
        return $this;
    }

    public function cr() {
        $this->exp .= "\\r";
        return $this;
    }

    public function lf() {
        $this->exp .= "\\n";
        return $this;
    }

    public function crlf() {
        $this->exp .= "\\r\\n";
        return $this;
    }

    public function emp() {
        $this->exp .= "\\s";
        return $this;
    }

    public function limit($start, $end = 0) {
        if ($start == 0 && $end == 0)
            $this->exp .= "*";
        elseif ($start == 0 && $end == 1)
            $this->exp .= "?";
        elseif ($start == 1 && $end == 0)
            $this->exp .= "+";
        elseif ($end == 0)
            $this->exp .= "{".$start.",}";
        else
            $this->exp .= "{".$start.",".$end."}";
        return $this;
    }

    public function chars() {
        $str = "[";
        if (func_num_args() > 0)
            foreach (func_get_args() as $i)
                $str .= $i;
        $str.= "]";
        $this->exp .= $str;
        return $this;
    }
}