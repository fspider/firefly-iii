<?php
/**
 * Accountant2Users.php
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

namespace FireflyIII\Models;

use FireflyIII\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Role.
 *
 * @property int    $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $display_name
 * @property string|null $description
 * @property-read \Illuminate\Database\Eloquent\Collection|\FireflyIII\User[] $users
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users query()
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FireflyIII\Models\Accountant2Users whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AccountantUser extends Model
{
    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts
        = [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

    /** @var array Fields that can be filled */
    protected $fillable = ['accountant_id', 'user_id', 'status'];
    protected $table = 'accountant_users';
    /**
     * @codeCoverageIgnore
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    /**
     * @codeCoverageIgnore
     * @return BelongsTo
     */
    public function accountant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_id', 'id');
    }
}
