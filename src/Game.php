<?php
/**
 * Created by PhpStorm.
 * User: ToothlessRebel
 * Date: 2017-01-06
 * Time: 01:42
 */

namespace ExposureSoftware\Bingo;


/**
 * Class Game
 *
 * @package ExposureSoftware\Bingo
 */
class Game
{
    /** @var array $options */
    protected $options = [];
    /** @var array $cards */
    protected $cards = [];
    /** @var array $winners */
    protected $winners = [];
    /** @var array $calls */
    protected $calls = [];
    protected $card_width;
    protected $card_height;

    /**
     * Game constructor.
     *
     * @param int   $players
     * @param array $options
     * @param int   $card_width
     * @param int   $card_height
     */
    public function __construct($players, array $options, $card_width, $card_height = null)
    {
        $card_height = $card_height ?: $card_width;
        $this->options = $options;
        $this->card_height = $card_height;
        $this->card_width = $card_width;

        do {
            $card = new Card($card_width, $card_height);
            shuffle($this->options);
            $card->populate(array_slice($this->options, 0, ($card_width * $card_height)));
            $this->cards[$card->getId()] = $card;
        } while (count($this->cards) < $players);
    }

    /**
     * Prints the cards for the game.
     * Optionally can output the cards to a file at the path given.
     *
     * @param string $to_file
     *
     * @return string
     */
    public function printCards($to_file = null)
    {
        $lines = [];
        /**
         * @var string $id
         * @var Card   $card
         */
        foreach ($this->cards as $id => $card) {
            $lines[] = "Card {$id}";
            $lines[] = $card->printGrid() . PHP_EOL;
        }
        $completed = implode(PHP_EOL, $lines);

        if ($to_file) {
            file_put_contents($to_file, $completed);
        }

        return $completed;
    }

    /**
     * Selects a random option to be called.
     *
     * @param null $choice
     *
     * @return string
     */
    public function call($choice = null)
    {
        $available = array_diff($this->options, $this->calls);

        if (is_null($choice)) {
            $choice = array_rand($available);
            $called = $available[$choice];
        } else {
            $called = $choice;
        }

        if (!$called) {
            die("Called {$called} at index {$choice} of " . print_r($available, true) . PHP_EOL);
        }

        $this->calls[] = $called;

        /** @var Card $card */
        foreach ($this->cards as $card) {
            $card->mark($called);
        }

        return $called;
    }

    /**
     * Exports the current cards to be used in another Game.
     * The result is a JSON encoded array containing JSON encoded cards.
     *
     * @return string
     */
    public function exportCards()
    {
        return json_encode(array_map(function ($card) {
            /** @var Card $card */
            return serialize($card);
        }, $this->cards));
    }

    /**
     * Imports cards from a previous game into the current.
     *
     * @param $json
     *
     * @return bool
     */
    public function importCards($json)
    {
        $imported = true;
        $cards = array_map(function ($card) {
            return unserialize($card);
        }, json_decode($json, true));

        /** @var Card $card */
        foreach ($cards as $card) {
            if ($card->getHeight() !== $this->card_height || $card->getWidth() !== $this->card_width) {
                $imported = false;
            }
        }

        if ($imported) {
            $this->cards = $cards;
        }

        return $imported;
    }

    /**
     * Determines if the game has been won.
     *
     * @return bool
     */
    public function hasWinner()
    {
        $winner = false;

        foreach ($this->cards as $id => $card) {
            if ($card->won()) {
                if (!in_array($id, $this->winners)) {
                    $this->winners[] = $id;
                }
                $winner = true;
            }
        }

        return $winner;
    }

    /**
     * Lists the winning cards.
     *
     * @return string
     */
    public function getWinners()
    {
        return implode(', ', $this->winners);
    }
}
