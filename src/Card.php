<?php
/**
 * Created by PhpStorm.
 * User: ToothlessRebel
 * Date: 2017-01-06
 * Time: 00:13
 */

namespace ExposureSoftware\Bingo;


use Serializable;

/**
 * Class Card
 * TODO: DRY up the array_reduce() function calls.
 *
 * @package ExposureSoftware\Bingo
 */
class Card implements Serializable
{
    /** @var array $cells */
    protected $cells = [];
    /** @var int $width */
    protected $width;
    /** @var int $height */
    protected $height;
    /** @var array $values */
    protected $values = [];
    /** @var int $maximum_value_length */
    protected $maximum_value_length;
    /** @var string $id */
    protected $id;

    /**
     * Card constructor.
     *
     * @param int      $width
     * @param int|null $height
     */
    public function __construct($width, $height = null)
    {
        $this->height = $height ?: $width;
        $this->width = $width;

        $sentinel = 0;
        do {
            $this->cells[] = $this->createRow();
            $sentinel++;
        } while ($this->height > $sentinel);
    }

    /**
     * Returns a string representation of the Card.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Serializes the Card into a JSON string.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode([
            'width'  => $this->width,
            'height' => $this->height,
            'cells'  => $this->cells,
            'values' => $this->values,
        ]);
    }

    /**
     * Performs the serialization of the Card.
     *
     * @return string
     */
    public function serialize()
    {
        return $this->toJson();
    }

    /**
     * Recreates the Card from a serialized state.
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $data = json_decode($serialized);

        $this->cells = $data->cells;
        $this->values = $data->values;
        $this->height = $data->height;
        $this->width = $data->width;
    }

    /**
     * Returns the Card's width.
     *
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Returns the Card's height.
     *
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Populates the card with values.
     *
     * @param array $values
     * @param bool  $shuffle
     *
     * @throws \Exception
     */
    public function populate(array $values, $shuffle = true)
    {
        if (count($values) !== $this->totalCells()) {
            throw new \Exception('Wrong number of values.');
        }
        $this->values = $values;
        if ($shuffle) {
            shuffle($values);
        }

        $value_index = 0;
        foreach ($this->cells as $index => $row) {
            foreach ($row as $row_index => $cell) {
                $row[$row_index] = $values[$value_index++];
            }
            $this->cells[$index] = $row;
        }
    }

    /**
     * Retrieves the unique ID of the card.
     *
     * @return string
     */
    public function getId()
    {
        if (is_null($this->id)) {
            $this->id = substr(md5(serialize($this->cells)), 0, 30);
        }

        return $this->id;
    }

    /**
     * Marks the cells matching the called value.
     *
     * @param $value
     */
    public function mark($value)
    {
        foreach ($this->cells as $row_number => $row) {
            $this->cells[$row_number] = array_map(function ($cell) use ($value) {
                return ($cell === $value) ? 'MARKED' : $cell;
            }, $row);
        }
    }

    /**
     * Determines if the card meets the win condition.
     *
     * @return bool
     */
    public function won()
    {
        $won = $this->scoreRows();
        $won = $this->scoreColumns() ?: $won;

        // If it's not square the diagonal cannot be determined.
        if ($this->height === $this->width) {
            $won = $this->scoreDiagonals() ?: $won;
        }

        return $won;
    }

    /**
     * Prints the card with grid lines.
     *
     * @return string
     */
    public function printGrid()
    {
        $lines = [];
        $separator_padding = 3 + $this->maximumValueLength();
        $separator_prefix = "%'-{$separator_padding}s";
        $value_padding = 2 + $this->maximumValueLength();
        $value_format = "%' -{$value_padding}s";
        $separator = '+';

        $sentinel = 0;
        do {
            $separator .= sprintf($separator_prefix, '+');
            $sentinel++;
        } while ($sentinel < $this->width);

        $lines[] = $separator;

        foreach ($this->cells as $row) {
            $line = '|';
            foreach ($row as $cell) {
                $line .= sprintf($value_format, " {$cell}") . '|';
            }
            $lines[] = $line;
            $lines[] = $separator;
        }
        $lines[] = 'Width: ' . strlen($separator) . PHP_EOL;

        return implode(PHP_EOL, $lines);
    }

    /**
     * Prints the card as lines of comma separated values.
     *
     * @return string
     */
    public function printRaw()
    {
        $lines = [];

        foreach ($this->cells as $row) {
            $lines[] = implode(', ', $row);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Determines if a row meets the win condition.
     *
     * @return bool
     */
    protected function scoreRows()
    {
        $won = false;

        foreach ($this->cells as $id => $row) {
            $won = array_reduce($row, function ($carry, $item) {
                return ($item === 'MARKED' && ($carry || is_null($carry))) ? true : false;
            });
        }

        return $won;
    }

    /**
     * Determines if a column meets the win condition.
     *
     * @return bool
     */
    protected function scoreColumns()
    {
        $columns = [];

        for ($i = 0; $i < $this->height; $i++) {
            $columns[$i] = array_reduce(
                array_map(
                    function ($row) use ($i) {
                        return $row[$i];
                    },
                    $this->cells
                ),
                function ($carry, $item) {
                    return ($item === 'MARKED' && ($carry || is_null($carry))) ? true : false;
                }
            );
        }

        return array_reduce($columns, function ($carry, $item) {
            return ($item || $carry);
        }, false);
    }

    /**
     * Determines if a diagonal meets the win conditions.
     *
     * @return bool
     */
    protected function scoreDiagonals()
    {
        // Create the top-left to bottom-right diagonal.
        $tl_to_br = [];
        for ($i = 0; $i < $this->width; $i++) {
            $tl_to_br[] = $this->cells[$i][$i];
        }

        // Create the bottom-left to top-right diagonal.
        $bl_to_tr = [];
        $highest_index = $this->height - 1;
        for ($i = $highest_index; $i >= 0; $i--) {
            $bl_to_tr[] = $this->cells[$i][$highest_index - $i];
        }

        return ($this->checkSetForWin($tl_to_br) || $this->checkSetForWin($bl_to_tr));
    }

    protected function checkSetForWin(array $set)
    {
        return array_reduce($set, function ($carry, $item) {
            return ($item === 'MARKED' && ($carry || is_null($carry))) ? true : false;
        });
    }

    /**
     * Retrieves the maximum length of all values.
     *
     * @return int
     */
    protected function maximumValueLength()
    {
        if (!$this->maximum_value_length) {
            foreach ($this->values as $value) {
                $value_length = strlen($value);
                $this->maximum_value_length = ($this->maximum_value_length > $value_length)
                    ? $this->maximum_value_length
                    : $value_length;
            }
        }

        return $this->maximum_value_length;
    }

    /**
     * Returns the longest value in the given column.
     *
     * @param $column
     *
     * @return int
     */
    protected function maximumColumnLength($column)
    {
        $length = 0;
        foreach ($this->cells as $row) {
            $length = (strlen($row($column)) > $length) ? strlen($row[$column]) : $length;
        }

        return $length;
    }

    /**
     * Creates an empty row with the correct number of indexes.
     *
     * @return array
     */
    protected function createRow()
    {
        $row = [];

        $sentinel = 0;
        do {
            $row[$sentinel] = '';
            $sentinel++;
        } while ($this->width > $sentinel);

        return $row;
    }

    /**
     * Calculates total number of cells.
     *
     * @return int
     */
    protected function totalCells()
    {
        return $this->width * $this->height;
    }
}
