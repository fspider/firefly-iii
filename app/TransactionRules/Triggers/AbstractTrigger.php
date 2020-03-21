<?php
/**
 * AbstractTrigger.php
 * Copyright (c) 2019 james@firefly-iii.org
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

namespace FireflyIII\TransactionRules\Triggers;

use FireflyIII\Models\RuleTrigger;
use FireflyIII\Models\TransactionJournal;

/**
 * This class will be magical!
 *
 * Class AbstractTrigger
 * @method bool triggered($object)
 */
class AbstractTrigger
{
    /** @var bool Whether to stop processing after this one is checked. */
    public $stopProcessing;
    /** @var string Value to check for */
    protected $checkValue;
    /** @var TransactionJournal Journal to check */
    protected $journal;
    /** @var RuleTrigger Trigger object */
    protected $trigger;
    /** @var string Trigger value */
    protected $triggerValue;

    /**
     * Make a new trigger from the value given in the string.
     *
     * @codeCoverageIgnore
     *
     * @param string $triggerValue
     * @param bool   $stopProcessing
     *
     * @return static
     */
    public static function makeFromStrings(string $triggerValue, bool $stopProcessing)
    {
        $self                 = new static;
        $self->triggerValue   = $triggerValue;
        $self->stopProcessing = $stopProcessing;

        return $self;
    }

    /**
     * Make a new trigger from the rule trigger in the parameter
     *
     * @codeCoverageIgnore
     *
     * @param RuleTrigger $trigger
     *
     * @return AbstractTrigger
     */
    public static function makeFromTrigger(RuleTrigger $trigger): AbstractTrigger
    {
        $self                 = new static;
        $self->trigger        = $trigger;
        $self->triggerValue   = $trigger->trigger_value;
        $self->stopProcessing = $trigger->stop_processing;

        return $self;
    }

    /**
     * Make a new trigger from a trigger value.
     *
     * @codeCoverageIgnore
     *
     * @param string $triggerValue
     *
     * @return AbstractTrigger
     */
    public static function makeFromTriggerValue(string $triggerValue): AbstractTrigger
    {
        $self               = new static;
        $self->triggerValue = $triggerValue;

        return $self;
    }

    /**
     * Returns trigger
     *
     * @codeCoverageIgnore
     *
     * @return RuleTrigger
     */
    public function getTrigger(): RuleTrigger
    {
        return $this->trigger;
    }

    /**
     * Returns trigger value
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getTriggerValue(): string
    {
        return $this->triggerValue;
    }
}
