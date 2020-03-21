<?php
/**
 * AbnAmroDescription.php
 * Copyright (c) 2019 Robert Horlings
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace FireflyIII\Import\Specifics;

/**
 * Class AbnAmroDescription.
 *
 * Parses the description from txt files for ABN AMRO bank accounts.
 *
 * Based on the logic as described in the following Gist:
 * https://gist.github.com/vDorst/68d555a6a90f62fec004
 */
class AbnAmroDescription implements SpecificInterface
{
    /** @var array The current row. */
    public $row;

    /**
     * Description of this specific fix.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public static function getDescription(): string
    {
        return 'import.specific_abn_descr';
    }

    /**
     * Name of specific fix.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public static function getName(): string
    {
        return 'import.specific_abn_name';
    }

    /**
     * Run the fix.
     *
     * @param array $row
     *
     * @return array
     *
     */
    public function run(array $row): array
    {
        $this->row = array_values($row);

        if (!isset($row[7])) {
            return $row;
        }

        // Try to parse the description in known formats.
        $parsed = $this->parseSepaDescription() || $this->parseTRTPDescription() || $this->parseGEABEADescription() || $this->parseABNAMRODescription();

        // If the description could not be parsed, specify an unknown opposing
        // account, as an opposing account is required
        if (!$parsed) {
            $this->row[8] = (string)trans('firefly.unknown'); // opposing-account-name
        }

        return $this->row;
    }

    /**
     * Parses the current description with costs from ABN AMRO itself.
     *
     * @return bool true if the description is GEA/BEA-format, false otherwise
     */
    protected function parseABNAMRODescription(): bool
    {
        // See if the current description is formatted in ABN AMRO format
        if (preg_match('/ABN AMRO.{24} (.*)/', $this->row[7], $matches)) {
            $this->row[8] = 'ABN AMRO'; // this one is new (opposing account name)
            $this->row[7] = $matches[1]; // this is the description

            return true;
        }

        return false;
    }

    /**
     * Parses the current description in GEA/BEA format.
     *
     * @return bool true if the description is GEA/BEAformat, false otherwise
     */
    protected function parseGEABEADescription(): bool
    {
        // See if the current description is formatted in GEA/BEA format
        if (preg_match('/([BG]EA) +(NR:[a-zA-Z:0-9]+) +([0-9.\/]+) +([^,]*)/', $this->row[7], $matches)) {
            // description and opposing account will be the same.
            $this->row[8] = $matches[4]; // 'opposing-account-name'
            $this->row[7] = $matches[4]; // 'description'

            if ('GEA' === $matches[1]) {
                $this->row[7] = 'GEA ' . $matches[4]; // 'description'
            }

            return true;
        }

        return false;
    }

    /**
     * Parses the current description in SEPA format.
     *
     * @return bool true if the description is SEPA format, false otherwise
     *
     */
    protected function parseSepaDescription(): bool
    {
        // See if the current description is formatted as a SEPA plain description
        if (preg_match('/^SEPA(.{28})/', $this->row[7], $matches)) {
            $type           = $matches[1];
            $reference      = '';
            $name           = '';
            $newDescription = '';

            // SEPA plain descriptions contain several key-value pairs, split by a colon
            preg_match_all('/([A-Za-z]+(?=:\s)):\s([A-Za-z 0-9._#-]+(?=\s|$))/', $this->row[7], $matches, PREG_SET_ORDER);

            if (is_array($matches)) {
                foreach ($matches as $match) {
                    $key   = $match[1];
                    $value = trim($match[2]);
                    switch (strtoupper($key)) {
                        case 'OMSCHRIJVING':
                            $newDescription = $value;
                            break;
                        case 'NAAM':
                            $this->row[8] = $value;
                            $name         = $value;
                            break;
                        case 'KENMERK':
                            $reference = $value;
                            break;
                        case 'IBAN':
                            $this->row[9] = $value;
                            break;
                        default: // @codeCoverageIgnore
                            // Ignore the rest
                    }
                }
            }

            // Set a new description for the current transaction. If none was given
            // set the description to type, name and reference
            $this->row[7] = $newDescription;
            if ('' === $newDescription) {
                $this->row[7] = sprintf('%s - %s (%s)', $type, $name, $reference);
            }

            return true;
        }

        return false;
    }

    /**
     * Parses the current description in TRTP format.
     *
     * @return bool true if the description is TRTP format, false otherwise
     *
     */
    protected function parseTRTPDescription(): bool
    {
        // See if the current description is formatted in TRTP format
        if (preg_match_all('!\/([A-Z]{3,4})\/([^/]*)!', $this->row[7], $matches, PREG_SET_ORDER)) {
            $type           = '';
            $name           = '';
            $reference      = '';
            $newDescription = '';

            // Search for properties specified in the TRTP format. If no description
            // is provided, use the type, name and reference as new description
            if (is_array($matches)) {
                foreach ($matches as $match) {
                    $key   = $match[1];
                    $value = trim($match[2]);

                    switch (strtoupper($key)) {
                        case 'NAME':
                            $this->row[8] = $value;
                            break;
                        case 'REMI':
                            $newDescription = $value;
                            break;
                        case 'IBAN':
                            $this->row[9] = $value;
                            break;
                        case 'EREF':
                            $reference = $value;
                            break;
                        case 'TRTP':
                            $type = $value;
                            break;
                        default: // @codeCoverageIgnore
                            // Ignore the rest
                    }
                }

                // Set a new description for the current transaction. If none was given
                // set the description to type, name and reference
                $this->row[7] = $newDescription;
                if ('' === $newDescription) {
                    $this->row[7] = sprintf('%s - %s (%s)', $type, $name, $reference);
                }
            }

            return true;
        }

        return false;
    }
}
