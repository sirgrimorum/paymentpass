<?php

namespace Sirgrimorum\PaymentPass\Traits;

/**
 * Trait RandomStringGenerator
 * @package Sirgrimorum\PaymentPass\Traits
 *
 * Solution taken from here:
 * http://stackoverflow.com/a/13733588/1056679
 */
trait RandomStringGenerator
{
    /** @var string */
    protected $alphabet;

    /** @var int */
    protected $alphabetLength;

    /**
     * @param string $alphabet
     */
    private function setAlphabet($alphabet = "")
    {
        if ($alphabet == "") {
            $alphabet =
                  implode(range('a', 'z'))
                . implode(range('A', 'Z'))
                . implode(range(0, 9));
        }
        $this->alphabet = $alphabet;
        $this->alphabetLength = strlen($alphabet);
    }

    /**
     * @param int $length Use grater than 10 for uniqueness
     * @param string $alphabet
     * @return string
     */
    public function generateRandomString($length, $alphabet = "")
    {
        $this->setAlphabet($alphabet);

        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $randomKey = $this->getRandomInteger(0, $this->alphabetLength);
            $token .= $this->alphabet[$randomKey];
        }

        return $token;
    }

    /**
     * @param int $min
     * @param int $max
     * @return int
     */
    protected function getRandomInteger($min, $max)
    {
        $range = ($max - $min);

        if ($range < 0) {
            // Not so random...
            return $min;
        }

        $log = log($range, 2);

        // Length in bytes.
        $bytes = (int) ($log / 8) + 1;

        // Length in bits.
        $bits = (int) $log + 1;

        // Set all lower bits to 1.
        $filter = (int) (1 << $bits) - 1;

        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));

            // Discard irrelevant bits.
            $rnd = $rnd & $filter;

        } while ($rnd >= $range);

        return ($min + $rnd);
    }
}
