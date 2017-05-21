<?php

namespace AppBundle\Helper;

class GridHelper
{
    public static 
        $logger,
        $connection;

    public function __construct($logger, $connection)
    {
        self::$logger = $logger;
        self::$connection = $connection;
    }

    public static function isBlank($i, $j, $grid)
    {
        $val = $grid[$i][$j];

        return !empty($val['blank']);
    }

    public static function getGrid($name)
    {
        $sql = '
        select * from grids G
        inner join cells C ON C.grid_id = G.id
        where G.name = "' . $name . '"
        ORDER BY row, col';
        $connection = self::$connection;
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $anchors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $height = 1 + $anchors[0]['height'];
        $width = 1 + $anchors[0]['width'];
        $default_cell = [
            'display' => null,
            'is_data' => true,
            'choices' => [],
        ];
        $cells = array_fill(0, $width * $height, $default_cell);
        foreach ($anchors as $anchor) {
            $row = $anchor['row'];
            $col = $anchor['col'];
            $cells[$row * $width + $col]['display'] = [(int)$anchor['label_v'], (int)$anchor['label_h']];
            $cells[$row * $width + $col]['is_data'] = false;
        }

        return [
            'height' => $height,
            'width' => $width,
            'cells' => array_values($cells),
        ];
    }

    public static function hstrip($i, $j, $grid)
    {
        if (self::isBlank($i, $j, $grid)) {
            return [];
        }

        $j_start = self::getStripStartH($i, $j, $grid);
        $j_stop = self::getStripStopH($i, $j, $grid);

        $h = [];
        for ($k = $j_start; $k <= $j_stop; $k++) {
            $h[$k] = $grid[$i][$k];
        }

        return $h;
    }

    public static function vstrip($i, $j, $grid)
    {
        if (self::isBlank($i, $j, $grid)) {
            return [];
        }

        $i_start = self::getStripStartV($i, $j, $grid);
        $i_stop = self::getStripStopV($i, $j, $grid);
        $v = [];
        for ($k = $i_start; $k <= $i_stop; $k++) {
            $v[$k] = $grid[$k][$j];
        }

        return $v;
    }

    public static function getStrip($i, $j, $grid, $dir, $sum)
    {
        if (self::isBlank($i, $j, $grid)) {
            return null;
        }

        $strip = $dir === 'h' ? self::hstrip($i, $j, $grid) : self::vstrip($i, $j, $grid);
        $start_i = $dir === 'v' ? key($strip) : $i;
        $start_j = $dir === 'h' ? key($strip) : $j;

        return [
            'dir' => $dir,
            'total' => $sum,
            'len' => count($strip),
            'start' => [$start_i, $start_j],
        ];
    }

    public static function getStripStartH($i, $j, $grid)
    {
        $j_start = $j;
        while (true) {
            $x = $j_start - 1;
            if ($x >= 0 && !self::isBlank($i, $x, $grid)) {
                $j_start--;
            } else {
                break;
            }
        }

        return $j_start;
    }

    public static function getStripStopH($i, $j, $grid)
    {
        $j_stop = $j;
        $width = count($grid);
        while (true) {
            $x = $j_stop + 1;
            if ($x < $width && !self::isBlank($i, $x, $grid)) {
            $j_stop++;
            } else {
                break;
            }
        }

        return $j_stop;
    }

    public static function getStripStartV($i, $j, $grid)
    {
        $i_start = $i;
        while (true) {
            $x = $i_start - 1;
            if ($x >= 0 && !self::isBlank($x, $j, $grid)) {
                $i_start--;
            } else {
                break;
            }
        }

        return $i_start;
    }

    public static function getStripStopV($i, $j, $grid)
    {
        $i_stop = $i;
        $height = count($grid[0]);
        while (true) {
            $x = $i_stop + 1;
            if ($x < $height && !self::isBlank($x, $j, $grid)) {
                $i_stop++;
            } else {
                break;
            }
        }

        return $i_stop;
    }

    // move to base process class
    public static function pickRandom($arr)
    {
        $keys = array_keys($arr);
        $index = rand(1, count($keys)) - 1;
        return $keys[$index];
    }

    public static function log($str, $level = 'notice')
    {
        if (!is_string($str))
        {
            $str = print_r($str, TRUE);
        }
        $accepted_levels = array(
            'emergency',
            'alert',
            'critical',
            'warning',
            'notice',
            'info',
            'debug'
        );

        if (!in_array($level, $accepted_levels))
        {
            $level = 'info';
        }
        self::$logger->$level($str);
    }

    public static function getConnection()
    {
        return self::$connection;
    }

    public function onKernelRequest($event)
    {
        return;
    }

    public function onConsoleCommand($event)
    {
        return;
    }
}